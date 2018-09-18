<?php

/**
 * @Copyright (C), 2016, Liuj.
 * @Name $name
 * @Author Liuj
 * @Version stable 1.0
 * @Date: $date
 * @Description
 * 1. Example
 * @Function List
 * 1.
 * @History
 * Liuj $date     stable 1.0 第一次建
 */

namespace application\common\service\borrow;

use application\common\logic\InviteAward\InviteAward;
use application\common\model\activity\TreasureChest;
use application\common\model\borrow\AutoFinancingLog;
use application\common\model\borrow\AutoFinancingUser;
use application\common\model\borrow\ItemAutoInvest;
use application\common\Myredis;
use think\Db;
use application\common\model\account\IuserAmount;
use application\common\model\user\Iuser;
use application\common\logic\borrow\BorrowLogic;
use application\common\model\system\Variable;
use application\common\logic\account\AccountLogic;
use application\common\model\account\AccountModel;
use application\common\model\borrow\IborrowRepayment;
use application\common\model\borrow\Iborrow;
use application\common\model\system\Linkage;
use application\common\model\borrow\IborrowTender;

use application\common\model\activity\InvestCollection;
use application\common\logic\system\Task;
use application\common\model\borrow\RepayCredits;
use application\common\model\borrow\AutoInvestRedpacket;
use application\common\model\borrow\AutoInvestAccount;
use application\common\model\borrow\AutoInvestAccountDetail;
use application\common\model\system\MessageModel;
use application\common\model\borrow\IborrowCollection;
use application\common\logic\borrow\FundTypeLogLogic;
use application\common\model\borrow\FundTypeLog;
use think\Debug;

class BorrowServ
{


    /**
     * @desc   Iborrow
     * @var    string
     * @access protected
     */
    protected $_borrowApi;

    /**
     * @desc   account
     * @var    string
     * @access protected
     */
    protected $_account;

    /**
     * @desc   user
     * @var    string
     * @access protected
     */
    protected $_user;

    /**
     * @desc   iuserAmount
     * @var    string
     * @access protected
     */
    protected $_iuserAmount;

    /**
     * @desc   variable
     * @var    string
     * @access protected
     */
    protected $_variable;

    /**
     * @desc   variable
     * @var    string
     * @access protected
     */
    protected $_iborrowModel;

    /**
     * @desc   variable
     * @var    string
     * @access protected
     */
    protected $_accountModel;

    function __construct()
    {
        $this->_borrowApi = new BorrowLogic();
        $this->_account = new AccountLogic();
        $this->_user = new Iuser();
        $this->_iuserAmount = new IuserAmount();
        $this->_variable = new Variable();
        $this->_iborrowModel = new Iborrow();
        $this->_accountModel = new AccountModel();

    }

    /**
     * @desc 服务：入发标队列
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @param array $params 标信息数组
     * @return mixed
     */
    public function addBorrowToqueque($params = [])
    {
        if (empty($params) || !is_array($params))
        {
            return false;
        }
        //发标验证
        $data = $this->checkAddBorrow($params);
        if (is_string($data))
        {
            return $data;
        }
        //入队
        return $this->_borrowApi->addBorrowQueque($data);
    }

    /**
     * @desc 服务：发标
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @param array $params 标信息数组
     * @return bool
     */
    public function addBorrow($params = [])
    {
        try
        {
            Db::startTrans();
            //发标验证
            $check = $this->_addBorrowParamsValid($params);
            if (true !== $check)
            {
                triggleError($check);
            }
            $ret = $this->_borrowApi->addBorrow($params);
            if (!$ret)
            {
                triggleError('添加标信息失败');
            }
            if (!in_array($params['pborrow_type'], [
                6,
                10
            ]))
            {
                //额度冻结
                $config = config('system.amount_type');
                $freeze = [
                    'user_id'    => $params['user_id'],
                    'code_id'    => $config[$params['pborrow_type']],
                    'back_total' => $params['pAccount'],
                    'type'       => '122',
                    'rale_num'   => $params['pborrow_num'],
                    'remark'     => '发标冻结',
                ];
                $res = $this->_borrowApi->amountFreeze($freeze);
                if (!$res)
                {
                    triggleError($res);
                }
            }

            Myredis::getRedisConn()->setToHash("auto_account", $params['pborrow_num'], $params['pAccount'], false);
            //  Myredis::getRedisConn()->set("auto_tender_" . $params['pborrow_num'], 1);
            //秒标冻结保证资金
            if ($params['pborrow_type'] == 5)
            {
                $this->_borrowApi->freezeMoney($params);
            }
            Db::commit();
            return $ret;

        } catch (\Exception $exception)
        {
            Db::rollback();
            return msg($exception);
        }

    }

    /**
     * @desc 函数：自动投资入口
     * @author liuj
     * @update 2017-7-24
     * @access private
     * @param array $params
     * @return bool
     */
    public function autoTender()
    {
        set_time_limit(0);
        //获取未发布的标
        $where['status'] = 0;
        $where['borrow_type'] = [
            'in',
            '6,7,10,11'
        ];
        $params = $this->_iborrowModel->getOneByWhere([
            'where' => $where,
            'order' => 'id asc'
        ]);
        unset($where);
        if (empty($params))
        {
            return 'no borrow data';
        }

        //查询：根据标的项目期限查询相对应的自动投资规则
        $investAccountDetailModel = new AutoInvestAccountDetail();
        $where['auto_invest_item_term_month'] = $params['time_limit'];
        $where['user_id'] = [
            '<>',
            $params['user_id']
        ];
        $where['auto_invest_amount_surplus'] = [
            '>',
            0
        ];
        $sqlArr = [
            'order' => 'id asc',
            'where' => $where,
        ];
        $investAccountList = $investAccountDetailModel->getList($sqlArr);
        unset($sqlArr);
        unset($where);
        if (!empty($investAccountList))
        {
            //初始化剩余融资金额
            Myredis::getRedisConn()
                   ->setToHash("auto_account", $params['borrow_num'], intval($params['account'] - $params['account_yes']));
            $userRedpacketModel = new AutoInvestRedpacket();
            foreach ($investAccountList as $v)
            {
                $params['is_enable_invest_award'] = $v['is_enable_invest_award'];
                $params['is_red_packet'] = $v['is_red_packet'];
                $params['create_time'] = $v['create_time'];
                //使用红包自动投资
                if ($v['is_red_packet'] == 1)
                {
                    //获取用户红包数据 按配比金额升序
                    $sqlArr = [
                        'where' => [
                            'auto_invest_user_account_detail_id' => $v['id'],
                            'is_red_packet_use'                  => 0
                        ],
                        'order' => 'red_packe_invest_amount_min asc'
                    ];
                    $userRedpacket = $userRedpacketModel->getList($sqlArr);
                    //组装数据
                    foreach ($userRedpacket as $key => $value)
                    {
                        $userRedpacket[$key]['user_id'] = $v['user_id'];
                        $userRedpacket[$key]['user_name'] = $v['user_name'];
                        $userRedpacket[$key]['account_detail_id'] = $v['id'];
                    }
                    //取剩余融资金额
                    $surplusAccount = Myredis::getRedisConn()->getFromHash('auto_account', $params['borrow_num']);
                    //自动投资规则判断分支1
                    $ret = $this->_recursiveCheckOne($params, $userRedpacket, intval($surplusAccount), $v);
                    unset($userRedpacket);
                    //投资成功 跳出循环结束自动投资
                    if ($ret)
                    {
                        break;
                    }
                    //红包空或该用户不满足投资条件或该用户抛出异常，取下一个用户
                    else
                    {

                        continue;
                    }
                }
                else
                {
                    //取剩余融资金额
                    $surplusAccount = Myredis::getRedisConn()->getFromHash('auto_account', $params['borrow_num']);
                    //自动投资规则判断分支1
                    $ret = $this->_noRedRecursiveCheckOne($params, $v, intval($surplusAccount));
                    //投资成功 跳出循环结束自动投资
                    if ($ret)
                    {
                        break;
                    }
                    //取下一个用户
                    else
                    {

                        continue;
                    }
                }
                unset($v);

            }
            //投资成功短信信息
            $tenderMsg = Myredis::getRedisConn()->getHash("auto_tender_message_{$params['borrow_num']}");
            $tenderEndMsg = Myredis::getRedisConn()->getHash("auto_tender_end_message_{$params['borrow_num']}");
            if (!empty($tenderMsg))
            {
                $messageModel = new MessageModel();
                $messageModel->addAll(array_values($tenderMsg));
                Myredis::getRedisConn()->delete("auto_tender_message_{$params['borrow_num']}");
            }
            if (!empty($tenderEndMsg))
            {
                $messageModel = new MessageModel();
                $messageModel->addAll(array_values($tenderEndMsg));
                Myredis::getRedisConn()->delete("auto_tender_end_message_{$params['borrow_num']}");
            }
            //清除剩余融资金额
            Myredis::getRedisConn()->deleteFromHash('auto_account', $params['borrow_num']);
        }

        $borrowInfo = $this->_iborrowModel->getOneByWhere(['where' => ['borrow_num' => $params['borrow_num']]], 'status');
        $res = 0;
        if ($borrowInfo['status'] == 0)
        {
            //更改标状态为募集中，发布到大厅
            $up = [
                "status" => 1,
            ];
            $res = $this->_iborrowModel->editByWhere($up, [
                'borrow_num' => $params['borrow_num'],
                'status'     => 0
            ]);
        }

        return $params['borrow_num'] . ',publish success,当前标状态:.' . $borrowInfo['status'] . ',发布大厅结果:' . $res . PHP_EOL;
    }

    /**
     * @desc 函数：自动结标
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return bool
     */
    public function autoEndBorrow()
    {
        $autoFinancingModel = new AutoFinancingLog();
        $borrowList = $autoFinancingModel->getBorrowList();
        if (empty($borrowList))
        {
            return '没有需要结标的数据';
        }
        $i = 0;
        foreach ($borrowList as $v)
        {
            //调用结标服务
            $param['bnum'] = $v['borrow_num'];
            $param['user_id'] = $v['user_id'];
            $borrowServModel = new BorrowServ();
            $ret = $borrowServModel->endBorrow($param);
            if (true !== $ret)
            {
                continue;
            }
            $i++;
        }
        return '本次结标个数:' . $i;
    }


    /**
     * @desc 函数：自动投资规则判断分支1
     * @author liuj
     * @update 2017-7-24
     * @access private
     * @param array $params 标信息
     * @param array $userRedpacket 红包数组
     * @param int $account 融资剩余金额
     * @return bool
     */
    private function _recursiveCheckOne($params = [], $userRedpacket = [], $account = 0, $accountDetail = [])
    {
        //该用户红包已投完，返回false 取下一个用户
        if (empty($userRedpacket))
        {
            return false;
        }

        foreach ($userRedpacket as $kk => $vv)
        {
            $params['tender_money'] = $vv['red_packe_invest_amount_min'];
            $params['tuserid'] = $vv['user_id'];
            $params['tusername'] = $vv['user_name'];
            $params['red_packet_id'] = $vv['red_packet_id'];
            $params['red_packet_value'] = $vv['red_packe_amount'];
            $params['account_detail_id'] = $vv['account_detail_id'];
            //融资金额和红包配=用户第一个红包配比金额，直接满标
            if ($vv['red_packe_invest_amount_min'] == $account)
            {
                $ret = $this->_tenderSuccess($params);
                if (!$ret)
                {
                    return false;
                }
                $this->_fullBorrow($params);
                if ($accountDetail['auto_invest_amount_surplus'] - $params['tender_money'] == 0)
                {
                    $this->_investDone($vv['account_detail_id']);
                    $this->_doneSentMsg($params['tuserid'], $params['tusername'], $params['borrow_num'], $vv['account_detail_id']);
                }
                unset($params);
                unset($vv);
                return true;
            }
            //融资金额 > 用户第一个红包配比金额 移除第一个红包递归调用,项目金额=融资金额-红包配比金额
            elseif ($account > $vv['red_packe_invest_amount_min'])
            {
                $ret = $this->_tenderSuccess($params);
                if (!$ret)
                {
                    return false;
                }
                $surplusAccount = Myredis::getRedisConn()->getFromHash('auto_account', $params['borrow_num']);
                unset($userRedpacket[$kk]);
                if (!empty($userRedpacket))
                {
                    $accountDetail['auto_invest_amount_surplus'] = $accountDetail['auto_invest_amount_surplus'] - $vv['red_packe_invest_amount_min'];
                    unset($vv);
                    return $this->_recursiveCheckOne($params, $userRedpacket, $surplusAccount, $accountDetail);
                }
                else
                {
                    $this->_investDone($vv['account_detail_id']);
                    $this->_doneSentMsg($params['tuserid'], $params['tusername'], $params['borrow_num'], $vv['account_detail_id']);
                    unset($params);
                    unset($vv);
                    return false;
                }

            }
            //融资金额 < 用户第一个红包配比金额 调用分支2
            elseif ($account > 0)
            {
                $ret = $this->_recursiveCheckTwo($params, $account, $accountDetail);
                if (!$ret)
                {
                    return false;
                }
                unset($params);
                unset($vv);
                return true;
            }
            return true;
        }
    }

    /**
     * @desc 函数：不用红包规则判断
     * @author liuj
     * @update 2017-7-24
     * @access private
     * @param array $params 标信息
     * @param int $account 融资剩余金额
     * @return bool
     */
    private function _noRedRecursiveCheckOne($params = [], $accountDetail = [], $account = 0)
    {
        unset($params['red_packet_id']);
        //自动金额小=融资金额，投资成功，并满标，发送投资完成短信，取下一个标
        if ($accountDetail['auto_invest_amount_surplus'] == $account)
        {
            $params['tender_money'] = $account;
            $params['tuserid'] = $accountDetail['user_id'];
            $params['tusername'] = $accountDetail['user_name'];
            $params['account_detail_id'] = $accountDetail['id'];
            $ret = $this->_tenderSuccess($params);
            if (!$ret)
            {
                return false;
            }
            $this->_fullBorrow($params);
            $this->_investDone($accountDetail['id']);
            $this->_doneSentMsg($params['tuserid'], $params['tusername'], $params['borrow_num'], $accountDetail['id']);
            unset($accountDetail);
            unset($params);
            return true;
        }
        //自动金额小<融资金额，投资成功，并满标，发送投资完成短信，取下一个用户
        elseif ($accountDetail['auto_invest_amount_surplus'] < $account)
        {
            $params['tender_money'] = $accountDetail['auto_invest_amount_surplus'];
            $params['tuserid'] = $accountDetail['user_id'];
            $params['tusername'] = $accountDetail['user_name'];
            $params['account_detail_id'] = $accountDetail['id'];
            $ret = $this->_tenderSuccess($params);
            if (!$ret)
            {
                return false;
            }
            $this->_investDone($accountDetail['id']);
            $this->_doneSentMsg($params['tuserid'], $params['tusername'], $params['borrow_num'], $accountDetail['id']);
            unset($accountDetail);
            unset($params);
            return false;
        }
        //自动金额>融资金额，投资成功，并满标，取下一个标
        elseif($account > 0)
        {
            $params['tender_money'] = $account;
            $params['tuserid'] = $accountDetail['user_id'];
            $params['tusername'] = $accountDetail['user_name'];
            $params['account_detail_id'] = $accountDetail['id'];
            $ret = $this->_tenderSuccess($params);
            if (!$ret)
            {
                return false;
            }
            $this->_fullBorrow($params);
            unset($accountDetail);
            unset($params);
            return true;
        }
        return true;


    }

    /**
     * @desc 函数：自动投资规则判断分支2
     * @author liuj
     * @update 2017-7-24
     * @access private
     * @param array $params 标信息
     * @param  int $account 融资剩余金额
     * @return bool
     */
    private function _recursiveCheckTwo($params = [], $account = 0)
    {
        //查询：剩余自动投资用户中<=金额相等的红包
        $userRedpacketModel = new AutoInvestRedpacket();
        $where['a.red_packe_invest_amount_min'] = [
            '<=',
            $account
        ];
        $where['a.is_red_packet_use'] = 0;
        $where['b.user_id'] = [
            '<>',
            $params['user_id']
        ];
        $where['b.auto_invest_item_term_month'] = $params['time_limit'];
        $userInfo = $userRedpacketModel->getInvestUserByMoney($where, 'red_packe_invest_amount_min desc,b.id asc');
        unset($where);
        //存在余剩余金额相等的红包，直接满标
        if (!empty($userInfo))
        {
            if ($userInfo['red_packe_invest_amount_min'] == $account)
            {
                //存在 直接满标
                $params['tender_money'] = $userInfo['red_packe_invest_amount_min'];
                $params['tuserid'] = $userInfo['user_id'];
                $params['tusername'] = $userInfo['user_name'];
                $params['red_packet_id'] = $userInfo['red_packet_id'];
                $params['account_detail_id'] = $userInfo['id'];
                $ret = $this->_tenderSuccess($params);
                if (!$ret)
                {
                    return false;
                }
                $this->_fullBorrow($params);
                if ($userInfo['auto_invest_amount_surplus'] - $params['tender_money'] == 0)
                {
                    $this->_investDone($userInfo['id']);
                    $this->_doneSentMsg($params['tuserid'], $params['tusername'], $params['borrow_num'], $userInfo['id']);
                }
                unset($params);
                unset($userInfo);
            }
            elseif ($userInfo['red_packe_invest_amount_min'] < $account)
            {
                $params['tender_money'] = $userInfo['red_packe_invest_amount_min'];
                $params['tuserid'] = $userInfo['user_id'];
                $params['tusername'] = $userInfo['user_name'];
                $params['red_packet_id'] = $userInfo['red_packet_id'];
                $params['account_detail_id'] = $userInfo['id'];
                $ret = $this->_tenderSuccess($params);
                if (!$ret)
                {
                    return false;
                }
                if ($userInfo['auto_invest_amount_surplus'] - $params['tender_money'] == 0)
                {
                    $this->_investDone($userInfo['id']);
                    $this->_doneSentMsg($params['tuserid'], $params['tusername'], $params['borrow_num'], $userInfo['id']);
                }
                $surplusAccount = Myredis::getRedisConn()->getFromHash('auto_account', $params['borrow_num']);
                //  usleep(500000);
                unset($userInfo);
                return $this->_recursiveCheckTwo($params, $surplusAccount);
            }

        }
        //不存在，发布大厅
        else
        {
            //使用红包剩余尾巴，用不使用红包规则补满
            $investAccountDetailModel = new AutoInvestAccountDetail();
            $where['auto_invest_item_term_month'] = $params['time_limit'];
            $where['user_id'] = [
                '<>',
                $params['user_id']
            ];
            $where['auto_invest_amount_surplus'] = [
                '>',
                0
            ];
            $where['is_red_packet'] = 0;
            $sqlArr = [
                'order' => 'id asc',
                'where' => $where,
            ];
            $investAccountList = $investAccountDetailModel->getList($sqlArr);
            //不存在不实用红包规则，则发布大大厅
            if (empty($investAccountList))
            {
                //更改标状态为募集中，发布到大厅
                $up = [
                    "status" => 1,
                ];
                $ret = $this->_iborrowModel->editByWhere($up, ['borrow_num' => $params['borrow_num'],'status' => 0]);
                echo $params['borrow_num'] . '，没有不使用红包的用户,发布到大厅结果：' . $ret . PHP_EOL;
                unset($params);
                unset($userInfo);
                return true;
            }
            //存在 则调用不使用红包规则分支
            foreach ($investAccountList as $v)
            {
                $params['is_red_packet'] = $v['is_red_packet'];
                $params['is_enable_invest_award'] = $v['is_enable_invest_award'];
                $surplusAccount = Myredis::getRedisConn()->getFromHash('auto_account', $params['borrow_num']);
                $ret = $this->_noRedRecursiveCheckOne($params, $v, $surplusAccount);
                if ($ret)
                {
                    break;
                }
                else
                {
                    continue;
                }
            }
            return true;
        }

    }

    /**
     * @desc 函数：加入满标队列
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param $params
     * @return bool
     */
    private function _fullBorrow($params = [])
    {
        $surplusAccount = Myredis::getRedisConn()->getFromHash('auto_account', $params['borrow_num']);
        $borrow = $this->_iborrowModel->getOneByWhere(['where' => ['borrow_num' => $params['borrow_num']]]);

        if ($surplusAccount <= 0 || $borrow['account'] == $borrow['account_yes'])
        {
            //更新为满标
            $up = [
                "status" => 3,
            ];
            $ret = $this->_iborrowModel->editByWhere($up, [
                'borrow_num' => $params['borrow_num'],
                'status'     => 0
            ]);
            //加入满标队列
            $res = 0;
            if ($ret)
            {
                $params['account_yes'] = Myredis::getRedisConn()
                                                ->getFromHash('borrow_account_yes', $params['borrow_num']) / 100;
                $res = $this->_borrowApi->addFullBorrowQueue($params);
            }
            echo $params['borrow_num'] . ',更新满标状态结果:' . $ret . ',加入满标队列结果:' . $res . PHP_EOL;
            unset($params);
        }
        return true;
    }

    /**
     * @desc 函数：投标成功
     * @author liuj
     * @update 2017-7-24
     * @access private
     * @param $params
     * @return bool
     */
    private function _tenderSuccess($params = [])
    {
        Db::startTrans();
        try
        {
            // 增加代收记录
            $pcounts = $params['repaystyle'] == 4 ? $params['fatalism'] : $params['time_limit'];
            $resultRepay = interest($params['tender_money'], $params['apr'], $pcounts, $params['repaystyle'], 0, 1);
            $dataList = [];
            $tnum = made_num('tender', 1, $params['tuserid']);
            //更新iborrow account_yes tender_times
            $up = [
                'account_yes'  => [
                    'exp',
                   'account_yes + '.$params['tender_money']
                ],
                'tender_times' => [
                    'exp',
                    'tender_times + 1'
                ]
            ];
            $ret = $this->_iborrowModel->edit($up, $params['id']);
            if (!$ret)
            {
                triggleError('更新iborrow失败');
            }
            unset($up);


            //自动投资用户总帐减少
            $autoInvestAccountModel = new AutoInvestAccount();
            if ($params['is_red_packet'] == 1)
            {
                $up = [
                    'auto_invest_amount_surplus_use_red_packet' => [
                        'exp',
                        'auto_invest_amount_surplus_use_red_packet - '.$params['tender_money']
                    ]
                ];
            }
            else
            {
                $up = [
                    'auto_invest_amount_surplus_no_red_packet' => [
                        'exp',
                        'auto_invest_amount_surplus_no_red_packet - '.$params['tender_money']
                    ]
                ];
            }
            $res = $autoInvestAccountModel->edit($up, $params['tuserid']);
            if (!$res)
            {
                triggleError('更新账户总额失败');
            }
            unset($up);

            //自动投资用户账户详情剩余可投金额减少
            $autoInvestAccountDetailModel = new AutoInvestAccountDetail();
            $up = [
                'auto_invest_amount_surplus' => [
                    'exp',
                    'auto_invest_amount_surplus - '.$params['tender_money']
                ],
                'income_actual'              => [
                    'exp',
                    'income_actual + '.$resultRepay['total_interest']
                ],
            ];
            $res = $autoInvestAccountDetailModel->edit($up, $params['account_detail_id']);
            if (!$res)
            {
                triggleError('更新账户详情失败');
            }
            unset($up);
            if ($params['is_red_packet'] == 1)
            {
                //修改红包为已使用
                $userRedpacketModel = new AutoInvestRedpacket();
                $where['is_red_packet_use'] = 0;
                $where['red_packet_id'] = $params['red_packet_id'];
                //自动投资二期 开启
                $res = $userRedpacketModel->editByWhere([
                    'is_red_packet_use' => 1,
                    'invest_income'     => $resultRepay['total_interest']
                ], $where);

                if (!$res)
                {
                    triggleError('更新红包信息失败');
                }
                unset($where);
                //修改百宝箱红包信息
                $treasureChestModel = new TreasureChest();
                $data['out_brrow_num'] = $params['borrow_num'];
                $data['value_used'] = $params['red_packet_value'];
                $data['tnum'] = $tnum;
                $data['money'] = $params['tender_money'];
                $data['modify_time'] = time();
                $res = $treasureChestModel->edit($data, $params['red_packet_id']);
                if (!$res)
                {
                    triggleError('更新百宝箱红包信息失败');
                }
                unset($data);

            }

            foreach ($resultRepay['repayment_plan'] as $value)
            {
                $colData['addtime'] = time();
                $colData['tender_num'] = $tnum;
                $colData['borrow_num'] = $params['borrow_num'];
                $colData['user_id'] = $params['tuserid'];
                $colData['status'] = 2; //预存在的待收
                $colData['order'] = $value['times'];
                $colData['repay_time'] = $value['repayment_time'];
                $colData['repay_account'] = $value['repayment_account'];
                $colData['repay_yesaccount'] = 0;
                $colData['interest'] = $value['interest'];
                $colData['capital'] = $value['capital'];
                $colData['username'] = $params['tusername'];
                $dataList[] = $colData;
            }
            if (is_array($dataList))
            {
                $iborrowCollectionModel = new IborrowCollection();
                $res = $iborrowCollectionModel->addAll($dataList);
                unset($dataList);
                if (!$res)
                {
                    triggleError('添加代收失败');
                }

            }
            //增加投标记录
            $tender = [
                'tnum'           => $tnum,
                'tuserid'        => $params['tuserid'],
                'borrow_num'     => $params['borrow_num'],
                'pTaccount'      => $params['tender_money'],
                'type'           => 1,
                'client_ip'      => get_client_ip(),
                'tender_money'   => $params['tender_money'],
                'total_interest' => $resultRepay['total_interest'],
                'tusername'      => $params['tusername'],
                'cash_id'        => !isset($params['red_packet_id']) ? null : $params['red_packet_id'],
            ];
            $res = $this->_borrowApi->addTender($tender);
            unset($tender);
            if (!$res)
            {
                triggleError('添加投标记录失败');
            }

            //增加自动投标记录
            $autoTenderModel = new ItemAutoInvest();
            $add = [
                'auto_invest_user_account_detail_id' => $params['account_detail_id'],
                'user_id'                            => $params['tuserid'],
                'user_name'                          => $params['tusername'],
                'item_id'                            => $params['id'],
                'item_code'                          => $params['borrow_num'],
                'item_name'                          => $params['name'],
                'invest_amount'                      => $params['tender_money'],
                'item_rate'                          => $params['apr'],
                'item_type'                          => $params['borrow_type'],
                'red_packet_id'                      => !isset($params['red_packet_id']) ? null : $params['red_packet_id'],
                'item_term_month'                    => $params['time_limit'],
                'invest_income'                      => $resultRepay['total_interest'],
            ];
            $res = $autoTenderModel->add($add);
            unset($add);
            unset($resultRepay);
            if (!$res)
            {
                triggleError('添加自动投标记录失败');
            }

            /**投资奖励start**/
            $InviteAward = new InviteAward();
            $inviteAwardModel = new \application\common\model\investAward\ActivityInvestAwardItem();
            $activityInfo = $inviteAwardModel->getActivityInvestAwardItemInfo(1);
            $ret = $InviteAward->insertInviteAwardLog($activityInfo,$params, $params['tender_money'], $params['tender_money']);
            if (!$ret)
            {
                triggleError('计算投资返现失败');
            }
            /**投资奖励end**/

            //区分本次投资，所用金额的属性分布
            $this->tenderFundType($params, $tnum);

            $boviewurlpath = "/Invest/ViewBorrow/num/" . $params['borrow_num'];
            $exp = sprintf("[<a href='%s' target=_blank>%s</a>]", $boviewurlpath, $params['name']);
            //发送 短信
            $this->_sentMsg($params['tuserid'], $exp, $params['tender_money'], $params['borrow_num'], $tnum);
            Myredis::getRedisConn()
                   ->incrementInHash('auto_account', $params['borrow_num'], -intval($params['tender_money']));
            Myredis::getRedisConn()
                   ->incrementInHash("borrow_account_yes", $params['borrow_num'], $params['tender_money'] * 100);
            Db::commit();
            unset($params);
            return true;
        } catch (\Exception $exception)
        {
            Db::rollback();
            msg($exception);
            return false;
        }

    }

    /**
     * 区分投资金额中的资金属性成分
     * @param type $params 投标数据
     * @param type $tnum 投标编码
     */
    function tenderFundType($params, $tnum)
    {
        $timeStr = strtotime('2018-1-19 1:00:00');
        //红包投资
        if ($params['is_red_packet'] == 1)
        {
            if(strtotime($params['create_time']) <= $timeStr){//二期上线后，已经配置的尚未结束的红包自动投标规则对应的缓存key
                $hashKey = "auto_invest_money_" . $params['tuserid'];
            }else{//二期上线后，新配置的红包自动投标规则对应的缓存key
                $hashKey = "auto_invest_money_" . $params['tuserid']."_".$params['account_detail_id'];
            }
            
            $hashMoney = Myredis::getRedisConn()->getFromHash($hashKey, "invest_money");
            if ($hashMoney)
            {
                $fundTypeLogic = new FundTypeLogLogic();
                $fundData = $fundTypeLogic->capitalUsageOrderThree($params['tender_money'], $hashMoney['new_recharge_money'], $hashMoney['miao_back_money'], $hashMoney['withdrawal_money']);
                //插入日志表itd_fund_manage_fund_type_log
                $fundTypeModel = new FundTypeLog();
                $tenderInfo['user_id'] = $params['tuserid'];
                $tenderInfo['borrow_type'] = $params['borrow_type']; //标类型
                $tenderInfo['business_num'] = $tnum; //业务编码(目前为投标编码)
                $tenderInfo['business_type'] = 104; //网站业务类型
                $id = $fundTypeModel->insertFundManageFundTypeLog($params['tender_money'], $tenderInfo, $fundData);
                if ($id)
                {
                    //更改redis中个人剩余的自动投资属性金额
                    $leftHashData['new_recharge_money'] = $hashMoney['new_recharge_money'] - $fundData['useRechargeMoney'];
                    $leftHashData['miao_back_money'] = $hashMoney['miao_back_money'] - $fundData['useMiaoBackMoney'];
                    $leftHashData['withdrawal_money'] = $hashMoney['withdrawal_money'] - $fundData['useWithdrawalMoney'];
                    Myredis::getRedisConn()->setToHash($hashKey, "invest_money", $leftHashData);
                }
            }
        }
        else
        {
            if(strtotime($params['create_time']) <= $timeStr){//二期上线后，已经配置的尚未结束的非红包自动投标规则对应的缓存key
                $hashKey = "auto_no_repacket_invest_money_" . $params['tuserid'];
            }else{//二期上线后，新配置的非红包自动投标规则对应的缓存key
                $hashKey = "auto_no_repacket_invest_money_" . $params['tuserid'].'_'.$params['account_detail_id'];
            }
            
            $hashMoney = Myredis::getRedisConn()->getFromHash($hashKey, "invest_money");
            if ($hashMoney)
            {
                $fundTypeLogic = new FundTypeLogLogic();
                //开启了投资返利
                if ($params['is_enable_invest_award'] == 1)
                {
                    $fundData = $fundTypeLogic->capitalUsageOrderTwo($params['tender_money'], $hashMoney['new_recharge_money'], $hashMoney['miao_back_money'], $hashMoney['rong_money'], $hashMoney['withdrawal_money']);
                }
                else
                {
                    //不开启投资返利
                    //因为资产标，供应链屏蔽了投资返利代码，因此，自动投资也要统一
                    $fundData = $fundTypeLogic->capitalUsageOrderOne($params['tender_money'], $hashMoney['new_recharge_money'], $hashMoney['miao_back_money'], $hashMoney['rong_money'], $hashMoney['withdrawal_money']);
                 }

                //插入日志表itd_fund_manage_fund_type_log
                $fundTypeModel = new FundTypeLog();
                $tenderInfo['user_id'] = $params['tuserid'];
                $tenderInfo['borrow_type'] = $params['borrow_type']; //标类型
                $tenderInfo['business_num'] = $tnum; //业务编码(目前为投标编码)
                $tenderInfo['business_type'] = 104; //网站业务类型
                $id = $fundTypeModel->insertFundManageFundTypeLog($params['tender_money'], $tenderInfo, $fundData);
                if ($id)
                {
                    //更改redis中个人剩余的自动投资属性金额
                    $leftHashData['new_recharge_money'] = $hashMoney['new_recharge_money'] - $fundData['useRechargeMoney'];
                    $leftHashData['miao_back_money'] = $hashMoney['miao_back_money'] - $fundData['useMiaoBackMoney'];
                    $leftHashData['rong_money'] = $hashMoney['rong_money'] - $fundData['useRongMoney'];
                    $leftHashData['withdrawal_money'] = $hashMoney['withdrawal_money'] - $fundData['useWithdrawalMoney'];
                    Myredis::getRedisConn()->setToHash($hashKey, "invest_money", $leftHashData);
                }
            }

        }
    }

    /**
     * @desc 函数：发送信息
     * @author liuj
     * @update 2017-7-24
     * @access private
     * @param $uid
     * @param $bname
     * @param $Account
     * @param $borrowNum
     * @params $tnum
     * @return void
     */
    private function _sentMsg($uid, $bname, $Account, $borrowNum, $tnum)
    {
        $info["content"] = sprintf("您通过自动投资, 为标 %s 投入金额:￥%.2f，等待满标。", $bname, $Account);
        $info['name'] = sprintf('您通过自动投资设置,为%s投入资金.', $bname);
        $info['sent_user'] = 1;
        $info['type'] = 'web_info';
        $info['status'] = '1';
        $info['addtime'] = time();
        $info['receive_user'] = $uid;
        $info['receive_status'] = 1;
        $info['addip'] = get_client_ip();
        Myredis::getRedisConn()->setToHash("auto_tender_message_$borrowNum", $tnum, $info);
        cache("message_$uid", null);
    }

    /**
     * @desc 函数：发送信息
     * @author liuj
     * @update 2017-7-24
     * @access private
     * @param $uid
     * @param $username
     * @param $borrowNum
     * @return void
     */
    private function _doneSentMsg($uid, $username, $borrowNum, $accountdetailId)
    {
        $info["content"] = '尊敬的' . $username . '，您设置的自动投资已投资完成，请前往雅堂金融APP自动投资中查看详情，并可设置新的自动投资资金';
        $info['name'] = '亲，您设置的自动投资已投资完成';
        $info['sent_user'] = 1;
        $info['type'] = 'web_info';
        $info['status'] = '1';
        $info['addtime'] = time();
        $info['receive_user'] = $uid;
        $info['receive_status'] = 1;
        $info['addip'] = get_client_ip();
        Myredis::getRedisConn()->setToHash("auto_tender_end_message_$borrowNum", $uid . $accountdetailId, $info);
        cache("message_$uid", null);
    }


    /**
     * @desc 函数：更新完成时间
     * @author liuj
     * @update 2017-7-24
     * @access private
     * @param $accountdetailId
     * @return void
     */
    private function _investDone($accountdetailId)
    {
        if (empty($accountdetailId))
        {
            return false;
        }
        $accountdetailModel = new AutoInvestAccountDetail();
        return $accountdetailModel->edit([
            'finish_time'   => date('Y-m-d H:i:s'),
            'invest_status' => 1
        ], $accountdetailId);

    }


    /**
     * @desc 服务：结标
     * @author liujian
     * @date 2017-7-10
     * @access public
     * @return bool
     */
    public function endBorrow($params = [])
    {
        if (empty($params) || !is_array($params))
        {
            return false;
        }
        //结标验证
        $ret = $this->_checkEndBorrow($params);
        //入满标队列
        if (is_array($ret))
        {
            $res = $this->_iborrowModel->editByWhere(['status' => 3], [
                'borrow_num' => $params['bnum'],
                'user_id'    => $params['user_id']
            ]);
            if ($res)
            {
                $this->_borrowApi->addFullBorrowQueue($ret);
                return true;
            }
            else
            {
                \think\Log::write('结标更新状态为3失败' . Db::name('ibororw')->getLastSql(), "error");
                return false;
            }

        }
        else
        {
            \think\Log::write($ret, "error");
            return $ret;
        }

    }

    /**
     * @desc 服务：入还款队列
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @return bool
     */
    public function repaymentToQueque($params = [])
    {
        if (empty($params) || !is_array($params))
        {
            return false;
        }
        //验证
        $data = $this->_checkRepayment($params);
        //入队
        return $this->_borrowApi->addRepaymentQueque($data);
    }

    /**
     * @desc 服务：还款
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @param array $params 还款信息
     * @return bool
     */
    public function repaymentHandle($params = [])
    {
        //还款验证
        $params = $this->_repaymentParamsValid($params);
        if (is_string($params))
        {
            return $params;
        }
        try
        {
            Db::startTrans();
            //未还款
            if ($params['repayment_info']['status'] == 0)
            {
                //更新还款表信息 itd_iborrow_repayment
                $r1 = $this->_updateRepayment($params);
                if (!$r1)
                {
                    triggleError('更新还款表信息失败');
                }
                //更新标信息 itd_iborrow
                $r2 = $this->_updateRepaymentBorrow($params);
                if (!$r2)
                {
                    triggleError('更新borrow表信息失败');
                }
                //提前还款增加积分（排除天标和秒标）
                if ($params['pmode'] == 1 && $params['btype'] != 5 && $params['borrow_info']['fatalism'] == 0)
                {
                    $r3 = $this->_borrowApi->earlyRepayment($params);
                    if (!$r3)
                    {
                        triggleError('提前还款增加积分失败');
                    }
                }

                //返还额度
                if ($params['borrow_info']['borrow_type'] != 6)
                {
                    $r4 = $this->_borrowApi->repaymentAmountBack($params);
                    if (!$r4)
                    {
                        triggleError('返还额度失败');
                    }
                }
                //扣除融资人金额
                $params['message'] = $this->_borrowApi->repaymentUpdateMoney($params);
                //入投资人获得还款队列
                $repayQuque['borrow_num'] = $params['borrow_num'];
                $repayQuque['renum'] = $params['prenum'];
                $repayQuque['borrow_type'] = $params['btype'];
                $repayQuque['user_id'] = $params['user_id'];
                $repayQuque['message'] = $params['message'];
                $repayQuque['id'] = $params['borrow_info']['id'];
                $repayQuque['pmode'] = $params['pmode'];
                $repayQuque['reorder'] = $params['repayment_info']['order'];
                $repayQuque['forst_account'] = $params['borrow_info']['forst_account'];
                $repayQuque['time_limit'] = $params['time_limit'];
                $repayQuque['name'] = $params['name'];
                $repayQuque['recapital'] = $params['repayment_info']['capital'];
                Myredis::getRedisConn()->appendToList('list_repayToInvestor', $repayQuque);
            } //网站垫付
            elseif ($params['repayment_info']['status'] == 2)
            {
                $dfinfo = overdue_interest($params['repayment_info']['repayment_time'], $params['repayment_account']);
                $params['last_interest'] = $dfinfo['li'];
                //更新还款表信息 itd_iborrow_repayment
                $r1 = $this->_deductUpdateRepayment($params);
                if (!$r1)
                {
                    triggleError('垫付更新还款表信息失败');
                }
                //更新标信息 itd_iborrow
                $r2 = $this->_updateRepaymentBorrow($params);
                if (!$r2)
                {
                    triggleError('垫付更新borrow表信息失败');
                }
                //逾期垫付还款
                $this->_borrowApi->afterAdvancesRepayment($params);
                //网站加钱
                $this->_borrowApi->addMoneyToAdmin($params);
                //扣除罚金
                $this->_borrowApi->deductFine($params);

            }

        } catch (\Exception $exception)
        {
            Db::rollback();
            return msg($exception);
        }

        Db::commit();
        //清除缓存
        $this->_repaymentDeleteCache($params);
        return true;
    }

    /**
     * @desc 服务：撤标
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param int $userId 用户id
     * @param bool $type false 定时任务撤标 true 手动撤标
     * @param string $bnum 标编码
     * @return mixed bool string
     */
    public function withdrawBorrow($userId = 0, $bnum = '', $type = false)
    {
        //查询标信息
        $where['status'] = 1;
        if ($type)
        {
            if (!is_numeric($userId) || $userId == 0)
            {
                return false;
            }
            $where['user_id'] = $userId;
            $where['borrow_num'] = $bnum;
        }
        else
        {
            $where['end_time'] = [
                '<=',
                time()
            ];
        }
        $sqlArr = [
            'where' => $where
        ];
        $list = $this->_iborrowModel->getList($sqlArr);
        if (empty($list))
        {
            return false;
        }
        unset($where);
        $msg = '';
        foreach ($list as $value)
        {
            //手动撤标
            if ($type && $userId != 490)
            {
                $where['key'] = 'SYS_CHE_TIME';
                $info = $this->_variable->getOneByWhere(['where' => $where]);
                $ctime = $value['success_time'] + $info['value'] * 60;
                if (in_array($value['borrow_type'], [
                        6,
                        7,
                        10,
                        11
                    ]) && $value['account_yes'] > 0)
                {
                    return '已有用户投资，不能撤标';
                }
                elseif (time() < $ctime)
                {
                    return '发布融资成功后' . $info['value'] . '分钟内不可以撤销';
                }
                else
                {
                    $res = $this->_doWithdrawBorrow($value, $type);
                    return $res;
                }

            }
            //自动撤标
            else
            {
                //已有投资金额，加入满标队列
                if (in_array($value['borrow_type'], [
                        6,
                        7,
                        10,
                        11
                    ]) && $value['account_yes'] > 0)
                {
                    $ret = $this->_borrowApi->addFullBorrowQueue($value);
                    if (!$ret)
                    {
                        $msg .= $value['borrow_num'] . '加入满标队列失败</br>';
                    }
                }
                else
                {
                    $res = $this->_doWithdrawBorrow($value, $type);
                    if (true === $res)
                    {
                        continue;
                    }
                    else
                    {
                        $msg .= $value['borrow_num'] . '撤标失败:' . $res . '</br>';
                    }
                }

            }

        }
        return $msg != '' ? $msg : true;
    }

    /**
     * @desc 函数：撤标
     * @author liuj
     * @update 2017-7-24
     * @access private
     * @param array $params 标数组
     * @param bool $type false 自动撤标 true 手动撤标
     * @return bool
     */
    private function _doWithdrawBorrow($params = [], $type = false)
    {
        //查询投标记录
        $tenderModel = new IborrowTender();
        $sqlArr = [
            'where' => [
                'borrow_num' => $params['borrow_num'],
                'status'     => 0
            ]
        ];
        $tenderList = $tenderModel->getList($sqlArr);
        try
        {
            Db::startTrans();
            //撤回额度 过滤资产4号
            if ($params['borrow_type'] != 10)
            {
                $amountTypeConfig = config('system.amount_type');
                $data['user_id'] = $params['user_id'];
                $data['code_id'] = $amountTypeConfig[$params['borrow_type']];
                $amount = $this->_borrowApi->getAmount($data);
                if ($amount['financing_type'] != 2)
                {
                    $back = [
                        'user_id'    => $params['user_id'],
                        'code_id'    => $data['code_id'],
                        'back_total' => $params['account'],
                        'type'       => '122',
                        'rale_num'   => $params['borrow_num'],
                        'remark'     => "项目[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]撤销返回额度",
                    ];
                    $r1 = $this->_borrowApi->amountUnfreeze($back);
                    if (!$r1)
                    {
                        triggleError('撤标撤回额度失败');
                    }
                }
            }

            if (!empty($tenderList))
            {
                //更新投标状态
                $tenderModel->editByWhere(['status' => 2], [
                    'borrow_num' => $params['borrow_num'],
                    'status'     => 0
                ]);
                //更新自动投标记录日志
                $autoTenderLogModel = new AutoTenderLog();
                $r2 = $autoTenderLogModel->editByWhere(['tenderStatus' => 0], ['borrow_num' => $params['borrow_num']]);
                if (!$r2)
                {
                    triggleError('撤标更新自动投标记录日志失败');
                }
                //更新投资奖励表记录
                $investCollectionModel = new InvestCollection();
                $r3 = $investCollectionModel->editByWhere(['status' => 3], ['borrow_num' => $params['borrow_num']]);
                if (!$r3)
                {
                    triggleError('撤标更新投资奖励表记录失败');
                }
            }

            //更新标状态
            $r4 = $this->_iborrowModel->editByWhere([
                'status'        => 5,
                'withdraw_time' => time()
            ], ['borrow_num' => $params['borrow_num']]);
            if (!$r4)
            {
                triggleError('撤标更新标状态失败');
            }
            //撤销退还保证资金
            if ($params['forst_account'] > 0)
            {
                $data['uid'] = $params['user_id'];
                $data['to_uid'] = 1;
                $data['num'] = $params['borrow_num'];
                $data['remark'] = "项目[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]撤销退还保证资金";
                $data['use_change'] = $params['forst_account'];
                $data['nouse_change'] = -$params['forst_account'];
                $data['type'] = 115;
                $data['btype'] = $params['borrow_type'];
                $this->_account->upChange($data);
                unset($data);
            }

            if (!empty($tenderList))
            {
                //退还投资人冻结资金
                foreach ($tenderList as $value)
                {
                    $data['uid'] = $value['user_id'];
                    $data['to_uid'] = $params['user_id'];
                    $data['num'] = $value['tender_num'];
                    $data['remark'] = "项目[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]撤销退还资金";
                    $data['use_change'] = $params['account'];
                    $data['nouse_change'] = -$params['account'];
                    $data['type'] = 106;
                    $data['btype'] = $params['borrow_type'];
                    $this->_account->upChange($data);
                    unset($data);
                }
            }

        } catch (\Exception $exception)
        {
            Db::rollback();
            return msg($exception);
        }
        Db::commit();
        //发送消息
        $task = new Task();
        $task->AddExeMone(106, $params['borrow_num']);
        $task->AddExeMone(115, $params['borrow_num']);
        if (!$type)
        {
            $task->AddExeMone(110, $params['borrow_num']);
        }
        Myredis::getRedisConn(0)->delete($params['borrow_num']);
        Myredis::getRedisConn(0)->delete('most_account_' . $params['borrow_num']);
        Myredis::getRedisConn(0)->deleteFromHash("invest_interest", $params['borrow_num']);
        Myredis::getRedisConn(0)->deleteFromHash("raise_interest", $params['borrow_num']);
        return true;
    }


    /**
     * @desc 发标校验
     * @author liuj
     * @update 2017-06-19
     * @access public
     * @param array $params 标信息
     * @return mixed
     */
    function checkAddBorrow($params = [], $isCli = false)
    {
        //判断是否已发标
        if (Myredis::getRedisConn()->existsInHash('add_borrow_status', $params['user_id']))
        {
            return '只能发布一个标!';
        }

        $where['user_id'] = $params['user_id'];
        $where['status'] = [
            'in',
            '0,1'
        ];
        $sqlAttr = [
            'where' => $where
        ];
        $borrow = $this->_iborrowModel->getOneByWhere($sqlAttr);
        if ($borrow)
        {
            return '只能同时发布一个标';
        }

        $userIno = $this->_user->getOne($params['user_id']);
        //获取用户交易密码
        if (!$isCli)
        {
            //解密交易密码
            $xxtea = new \Xxtea();
            $tradePassword = base64_decode(str_replace(' ', '+', $params['trade_password']));
            $tradePassword = md5($xxtea->decrypt($tradePassword, md5($params['user_id'])));
            if ($tradePassword != $userIno['paypassword'])
            {
                return '交易密码错误';
            }
        }


        //判断是否为黑名单用户
        $blackList = config('system.blacklist');
        if (in_array($params['user_id'], $blackList[0]))
        {
            return '您已被限制发布融资!';
        }
        if (array_key_exists($params['user_id'], $blackList[1]))
        {
            if ($blackList[1][$params['user_id']]['startTime'] <= time() && time() < $blackList[1][$params['user_id']]['endTime'])
            {
                return '由于恶意使用平台额度，您已被限制发布项目7天，以示警告，请您知悉!';
            }
        }
        //发标校验
        //融资人认证状态验证
        $stainfo = $this->_user->identification($params['user_id']);
        if ($stainfo !== true)
        {
            return $stainfo;
        }
        //发标额度验证
        $max = $this->_variable->getOneByWhere(['where' => ['key' => 'SYS_BIAO_MAX']], 'value');
        if ($params['account'] > $max['value'])
        {
            return '融资金额不能超过' . $max['value'] . '元';
        }

        //融资人额度验证
        $amountConfig = config('system.amount_type');
        $codeId = $amountConfig[$params['ibtype']];
        $amount = $this->_iuserAmount->getOneByWhere([
            'where' => [
                'user_id' => $params['user_id'],
                'codeid'  => $codeId
            ]
        ]);
        if (!$amount)
        {
            return '未找到相关额度';
        }
        elseif ($params['account'] > $amount['credit_use'])
        {
            if ($isCli)
            {
                $userModel = new AutoFinancingUser();
                $up = [
                    'user_status' => 0
                ];
                $userModel->edit($up, $params['user_id']);
            }
            return '额度不足';
        }

        //奖励金额
        $params['award_account'] = isset($params['award_account']) ? $params['award_account'] : 0;
        //奖励比例
        $params['award_rate'] = isset($params['award_rate']) ? $params['award_rate'] : 0;
        $params['award_rate'] = isset($params['award_rate']) ? $params['award_rate'] : 0;
        //定时标条件
        $checkDestine = in_array($params['ibtype'], [
                1,
                9
            ]) || ($params['ibtype'] == 5 && in_array($params['user_id'], [
                    490,
                    49843,
                    12136
                ]));
        //定时是否开启
        $params['destine_type'] = $checkDestine && isset($params['destine_type']) ? $params['destine_type'] : 0;
        //定时日期
        $params['destine_time_date'] = $checkDestine && isset($params['destine_time_date']) ? $params['destine_time_date'] : 0;
        //定时时间
        $params['destine_time'] = $checkDestine && isset($params['destine_time_time']) && $params['destine_type'] == 1 ? $params['destine_time_time'] : 0;
        //公司名称
        $params['companyname'] = isset($params['companyname']) ? $params['companyname'] : '';
        //标密码
        $params['borrowpwd'] = isset($params['borrowpwd']) ? $params['borrowpwd'] : null;
        //黄花梨投资基数
        $params['pearBase'] = isset($params['pearBase']) ? $params['pearBase'] : 0;
        //显示融资金额
        $params['account_show'] = isset($params['account_show']) ? $params['account_show'] : 0;
        //天标天数
        $params['fatalism'] = isset($params['fatalism']) && $params['repaystyle'] == 4 ? $params['fatalism'] : 0;
        //奖励类型
        $params['award_type'] = isset($params['award_type']) ? $params['award_type'] : null;
        //借款期数
        $params['time_limit'] = isset($params['time_limit']) ? $params['time_limit'] : 1;
        //奖励金额
        $params['award_account'] = isset($params['award_account']) ? $params['award_account'] : 0;
        //奖励利率
        $params['award_rate'] = isset($params['award_rate']) ? $params['award_rate'] : 0;
        $params['status'] = in_array($params['ibtype'], [
            6,
            7,
            10,
            11
        ]) ? 0 : 1;
        $params['status'] = 1;
        //还款方式
        $params['repaystyle'] = $params['ibtype'] == 5 ? 0 : $params['repaystyle'];
        //融资用途
        $params['use'] = in_array($params['ibtype'], [
            1,
            5,
            9
        ]) && isset($params['use']) ? $params['use'] : $params['ibtype'];
        //投标有效时间
        $params['valid_time'] = in_array($params['ibtype'], [
            1,
            5,
            9
        ]) && isset($params['valid_time']) ? $params['valid_time'] : 1;
        //最小投资金额类型
        $params['minimumtype'] = in_array($params['ibtype'], [
            1,
            5,
            9
        ]) && isset($params['minimumtype']) ? $params['minimumtype'] : 0;
        //最小投资金额
        $munlimited = isset($params['munlimited']) ? $params['munlimited'] : 50;
        $params['munlimited'] = in_array($params['ibtype'], [
            1,
            5,
            9
        ]) && isset($params['minimumtype']) && $params['minimumtype'] == 1 ? $munlimited : 50;
        //最大投资金额类型
        $params['maximumtype'] = isset($params['maximumtype']) ? $params['maximumtype'] : 0;
        //最大投资金额
        $params['unlimited'] = isset($params['unlimited']) && $params['maximumtype'] == 1 ? $params['unlimited'] : null;
        //是否公开个人信息
        $params['opens'] = in_array($params['ibtype'], [
            1,
            5,
            9
        ]) && isset($params['opens']) ? $params['opens'] : 0;
        //标编码
        $borrowConfig = config('system.borrow');
//        $borrowName = $borrowConfig[$params['ibtype']];
        $borrowName = '资产项目';

        $params['borrow_num'] = isset($params['borrow_num']) ? $params['borrow_num'] : made_num('borrow', 1, $params['user_id']);

        //密码标验证
        if (in_array($params['ibtype'], [
                5,
                6,
                7,
                11
            ]) && $params['borrowpwd'] != '')
        {
            return '秒标,资产1号,资产2号,资产3号不允许发布密码标';
        }
        //非秒标验证
        if ($params['ibtype'] != 5)
        {
            //最大年利率
            $maxApr = $this->_borrowApi->getMaxApr();
            //最小年利率
            $aprMin = $this->_borrowApi->getMinApr();
            if ($params['apr'] < $maxApr && ($params['award_rate'] > 0 || $params['award_type'] > 0 || $params['award_account'] > 0))
            {
                return '预期年化收益达到最大值才能设置奖励';
            }
            else
            {
                if (in_array($params['ibtype'], [
                        1,
                        9
                    ]) && isset($params['award_rate']) && $params['award_rate'] > 10)
                {
                    return '亲，奖励不能大于10%！';
                }
                elseif (in_array($params['ibtype'], [
                        6,
                        7,
                        11
                    ]) && isset($params['award_rate']) && $params['award_rate'] > 2)
                {
                    return '亲，奖励不能大于2%！';
                }
            }
            if ($userIno['type_id'] != 7)
            {
                if (($params['apr'] > $maxApr || $params['apr'] < $aprMin))
                {
                    return '该融资类型预期年化收益范围:' . $aprMin . ' ~ ' . $maxApr;
                }
                elseif ($params['apr'] < 1)
                {
                    return '您好，最低预期年化收益为1%！';
                }
            }
            else
            {
                if (strlen(trim($params('borrowpwd'))) != 6)
                {
                    return '您好，请设置标密码！';
                }
            }
            if (in_array($params['ibtype'], [
                    6,
                    7
                ]) && $params['time_limit'] > 6)
            {
                return '融资期限错误';
            }
            $params['forst_account'] = 0;

        }
        else
        {
            if ($params['award_account'] > 0 || $params['award_rate'] > 0)
            {
                return '秒标不能设置奖励';
            }
            $params['forst_account'] = $params['account'] * $params['apr'] / 1200;
            $accountModel = new AccountModel();
            $checkUserMoney = $accountModel->hasMoney($params['user_id'], $params['forst_account']);
            if (!$checkUserMoney)
            {
                return '可用额度不足';
            }
            if (!isset($params['name']))
            {
                return '请填写开心利是标题';
            }
        }
        //标题
        $params['name'] = $params['ibtype'] == 5 ? $params['name'] : $borrowName . '_' . substr($params['borrow_num'], -(strlen($params['borrow_num']) - 3));
        //奖励
        if (!is_null($params['award_type']))
        {
            switch ($params['award_type'])
            {
                case 0: //无奖励
                    $params['award_account'] = 0; //按固定金额报酬
                    $params['award_rate'] = 0; //按利率报酬
                    break;
                case 1: //按固定金额
                    //$params['award_account'] = $params['award_account']; //按固定金额报酬
                    $params['award_rate'] = 0; //按利率报酬
                    break;
                case 2: //按融资比例
                    $params['award_account'] = 0; //按固定金额报酬
                    $params['award_rate'] = truncate($params['award_rate'], 2); //按利率报酬
                    break;
            }
        }
        else
        {
            $params['award_type'] = 0;
            $params['award_account'] = 0; //按固定金额报酬
            $params['award_rate'] = 0; //按利率报酬
        }
        //显示金额验证
        if (isset($params['account_show']))
        {
            if ($params['account_show'] > $params['account'])
            {
                return '显示总额不能大于融资金额';
            }
            $params['account_show'] = in_array($params['user_id'], [
                490,
                2543
            ]) ? $params['account_show'] : 0;
        }
        //最大投资金额验证
        if ($params['unlimited'] > 0 && $params['maximumtype'] == 1)
        {
            if (in_array($params['ibtype'], [
                6,
                7,
                11
            ]))
            {
                return '资产融资不允许限制最大投资金额';
            }
        }
        //最小金额验证
        if ($userIno['type_id'] == 7 && !isset($params['munlimited']))
        {
            return '您好，请填写最小投资金额';
        }
        //最大金额验证
        if (!is_null($params['unlimited']))
        {
            if ($params['unlimited'] <= $params['munlimited'])
            {
                return '最大投资限额必须大于最小投资限额';
            }
            elseif ($userIno['type_id'] == 7 && $params['unlimited'] < $params['pearBase'])
            {
                return '亲，最大投资限额必须大于投资基数！';
            }
            elseif ($params['unlimited'] > $params['account'])
            {
                return '最大投资限额必须小于或等于融资金额';
            }
        }

        //内容验证
        $editInfo = $this->_borrowApi->canEditContent($params['user_id'], $params['ibtype']);
        $params['content'] = $editInfo['canEdit'] ? $params['content'] : htmlspecialchars_decode($editInfo['templateContent']);
        $params['content'] = html_filter($params['content']);
        if (strlen($params['content']) > 10000)
        {
            $params['content'] = csubstr($params['content'], 0, 10000);
        }
        //公司名称验证
        if (in_array($params['ibtype'], [
            1,
            9
        ]))
        {
            if (empty($params['companyname']))
            {
                return '请填写公司名称';
            }
        }

        //定时标
        if ($checkDestine)
        {
            //开启定时
            if ($params['destine_type'] == 1)
            {
                $temp = mktime($params['destine_time'], 0, 0, date("n"), date("j"), date("Y"));
                switch ($params['destine_time_date'])
                {
                    case 0://今天
                        if ($temp <= time() + 2 * 60 * 60)
                        {
                            return '定时数据错误';
                        }
                        $params['destine_time'] = $temp;
                        break;
                    case 1://明天
                        $params['destine_time'] = $temp + 24 * 60 * 60;
                        break;
                    case 2://后天
                        $params['destine_time'] = $temp + 48 * 60 * 60; // 48*60*60;
                        break;
                    case 3:
                        $params['destine_time'] = $temp + 72 * 60 * 60;
                        break;
                }
                if ($params['destine_time'] <= (time() - 36000))
                {
                    return '定时数据错误';
                }
                $params['pend_time'] = $params['destine_time'] + $params['valid_time'] * 24 * 3600;
            }
        }
        $params['pend_time'] = time() + $params['valid_time'] * 24 * 3600; //标结束时间
        $params['username'] = $userIno['username'];
        return $params;
    }

    /**
     * @desc 结标校验
     * @author liuj
     * @update 2017-06-19
     * @access public
     * @param array $params 标信息
     * @return mixed
     */
    private function _checkEndBorrow($params = [])
    {
        $flag = false;
        if (Myredis::getRedisConn(1)->get($params['bnum']))
        {
            return '提前结束失败,投资处理中';
        }
        $tenderUser = config('settings.tender_usr');
        $bWhere['borrow_num'] = $params['bnum'];
        $bWhere['user_id'] = $params['user_id'];
        $field = 'id,account,account_yes,user_id,status,borrow_num,borrow_type,apr,lowest_account,most_account,name,repaystyle,time_limit,award_rate,award_account,fatalism,username';
        $borrowInfo = $this->_iborrowModel->getOneByWhere(['where' => $bWhere], $field);
        if (empty($borrowInfo))
        {
            return '提前结束失败,项目不存在';
        }
        elseif (Myredis::getRedisConn()->existsInHash("full_borrow_queue_hash", $borrowInfo['borrow_num']))
        {
            return '提前结束失败,项目已在满标队列中';
        }
        elseif ($borrowInfo['status'] != 1)
        {
            return '提前结束失败,项目不在募集中';
        }
        else
        {
            if (in_array($params['user_id'], $tenderUser))
            {
                if ($borrowInfo['account_yes'] <= $borrowInfo['account'])
                {
                    $flag = true;
                }
            }
            else
            {
                if (in_array($borrowInfo['borrow_type'], [
                    6,
                    7,
                    10,
                    11
                ]))
                {
                    if ($borrowInfo['account_yes'] <= $borrowInfo['account'] && $borrowInfo['account_yes'] >= 50)
                    {
                        $flag = true;
                    }
                }
                else
                {
                    $linkageModel = new Linkage();
                    $endBorrowPer = $linkageModel->getOneByWhere(['where' => ['name' => 'end_finborrow_per']], 'value');
                    if ($borrowInfo['account_yes'] <= $borrowInfo['account'] && $borrowInfo['account_yes'] >= $borrowInfo['account'] * $endBorrowPer['value'] / 100)
                    {
                        $flag = true;
                    }
                }
            }
        }
        if ($flag)
        {
            return $borrowInfo;
        }
        return '不满足结标条件';
    }

    /**
     * @desc 还款校验
     * @author liuj
     * @update 2017-06-19
     * @access private
     * @param array $params 还款信息
     * @return mixed
     */
    private function _checkRepayment($params = [])
    {
        //获取用户交易密码
        $userIno = $this->_user->getOne($params['user_id']);
        //解密交易密码
        $xxtea = new \Xxtea();
        $tradePassword = base64_decode(str_replace(' ', '+', $params['trade_password']));
        $tradePassword = md5($xxtea->decrypt($tradePassword, md5($params['user_id'])));

        if ($tradePassword != $userIno['paypassword'])
        {
            fail_json('交易密码错误');
        }
        $iborrowRepaymentModel = new IborrowRepayment();
        $repayInfo = $iborrowRepaymentModel->getRepaymentInfo($params['user_id'], $params['borrow_num'], $params['repay_num']);
        if (empty($repayInfo))
        {
            fail_json('当前用户还款编码与项目编码不匹配');
        }
        $repaymenMoney = $repayInfo['repayment_account'] + $repayInfo['late_interest'];
        $checkUserMoney = $this->_accountModel->hasMoney($params['user_id'], $repaymenMoney);
        if (!$checkUserMoney)
        {
            fail_json('还款失败!可用余额不足');
        }
        $day = $this->_variable->getOneByWhere(['where' => ['key' => 'SYS_REPAY_DELAY']], 'value');
        if (strtotime('today') - strtotime(date('Y-m-d', $repayInfo['addtime'])) < $day['value'] * 86400 && $params['user_id'] != 490)
        {
            fail_json('融资成功' . $day['value'] . '天后才能还款');
        }

        if (in_array($repayInfo['repaystyle'], [
            0,
            3,
            4
        ]))
        {
            $variable = [
                0 => 'SYS_MONTH_REPAY_TIME',
                4 => 'SYS_TIANBIAO_REPAY_TIME',
                3 => 'SYS_QI_REPAY_TIME'
            ];
            $day = $this->_variable->getOneByWhere(['where' => ['key' => $variable[$repayInfo['repaystyle']]]], 'value');
            //还款时间-当前时间 = 实际提前天数
            $timeDiff = strtotime(date('Y-m-d', $repayInfo['repayment_time'])) - strtotime('today'); //86400
            //预设提前天数
            $beforeDays = $day['value'] * 86400; //0
            if ($timeDiff > $beforeDays && $params['user_id'] != 490)
            {
                fail_json('只能提前' . $day['value'] . '天还款');
            }
        }

        if (date('Y-m-d', $repayInfo['repayment_time']) == date('Y-m-d', time()) && date("G") == 23 && date("i") >= 50)
        {
            fail_json('23:50--00:00之间不能手动还款');
        }
        $check = cache('AutoReplay' . $params['repay_num']);
        if ($check)
        {
            fail_json('已经处于自动还款中');
        }
        if (Myredis::getRedisConn()->existsInHash("repay_queue", $params['repay_num']))
        {
            fail_json('已经处于还款队列中');
        }
        cache('AutoReplay' . $params['repay_num']);
        $result = Myredis::getRedisConn()->setToHash('repay_queue', $params['repay_num'], $params['user_id']);
        if (!$result)
        {
            cache('AutoReplay' . $params['repay_num']);
            fail_json('写入还款队列失败,稍候再试');
        }
        return $repayInfo;
    }


    /**
     * @desc 函数：发标参数验证
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @param array $params 标信息数组
     * @return mixed
     */
    private function _addBorrowParamsValid($params = [])
    {
        if (empty($params))
        {
            return '参数错误';
        }
        $where['user_id'] = $params['user_id'];
        $where['status'] = [
            'in',
            '0,1'
        ];
        $sqlAttr = [
            'where' => $where
        ];
        $borrow = $this->_iborrowModel->getOneByWhere($sqlAttr);
        if ($borrow)
        {
            return '只能同时发布一个标';
        }
        $repayCreditsModel = new RepayCredits();
        $repay = $repayCreditsModel->getOneByWhere(['where' => ['user_id' => $params['user_id']]], 'user_id');
        if (empty($repay))
        {
            $array = [
                1,
                2,
                3,
                4,
            ];
            $addAll = [];
            foreach ($array as $key => $value)
            {
                $add['user_id'] = $params['user_id'];
                $add['addtime'] = time();
                $add['type'] = $value;
                $addAll[] = $add;
            }
            $repayCreditsModel->addAll($addAll);
        }

        return true;
    }


    /**
     * @desc 函数：融资人还款验证
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @return mixed
     */
    private function _repaymentParamsValid($params = [])
    {
        if (empty($params))
        {
            return '融资人还款验证参数错误';
        }
        $fields = 'user_id,repayment_account,borrow_num,order,capital,`status`,interest,repayment_time,repayment_yesaccount';
        $where['user_id'] = $params['user_id'];
        $where['repayment_num'] = $params['prenum'];
        if ($params['pmode'] == 1)
        {
            $where['status'] = [
                'in',
                '0,2'
            ];
        }
        else
        {
            $where['status'] = 0;
        }
        $iborroRepaymentModel = new IborrowRepayment();
        $repaymentInfo = $iborroRepaymentModel->getOneByWhere(['where' => $where], $fields);
        if (empty($repaymentInfo))
        {
            return '未找到还款记录';
        }
        unset($where);
        //未还款
        if ($repaymentInfo['status'] == 0)
        {
            $where['status'] = 0;
            $where['borrow_num'] = $params['borrow_num'];
            $sqlArr = [
                'field' => 'order',
                'where' => $where,
                'order' => [
                    'order',
                    'id' => 'asc'
                ]
            ];
            $order = $iborroRepaymentModel->getOneByWhere($sqlArr);
            unset($where);
            if ($order['order'] != $repaymentInfo['order'])
            {
                return "请先还第[' . {$order['order']} . ']的款项";
            }
        } //网站垫付
        elseif ($repaymentInfo['status'] == 2)
        {
            if ($params['pmode'] != 1)
            {
                return '已经垫付';
            }
        }
        $frozen = Myredis::getRedisConn()->getFromHash("repay_frozen_{$params['user_id']}", $params['prenum']);
        if ((int)$frozen < (int)($repaymentInfo['repayment_account'] * 10000))
        {
            return '账户资金不足,不能还款';
        }
        $where['borrow_num'] = $params['borrow_num'];
        $where['status'] = 7;
        $borrowInfo = $this->_iborrowModel->getOneByWhere(['where' => $where], 'id,borrow_type,user_id,forst_account,name,time_limit,account,repaystyle,fatalism');
        if (empty($borrowInfo))
        {
            return '标状态不是还款';
        }
        if ($borrowInfo['borrow_type'] == 5 && $params['pmode'] == 2)
        {
            return '秒标不垫付';
        }
        $params['repayment_info'] = $repaymentInfo;
        $params['borrow_info'] = $borrowInfo;
        return $params;
    }

    /**
     * @desc 函数：融资人还款更新还款表信息
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params 标信息
     * @return bool
     */
    private function _updateRepayment($params = [])
    {
        if (in_array($params['pmode'], [
            1,
            3,
            4
        ]))
        {
            $up['status'] = 1;
        }
        else
        {
            $up['status'] = 2;
            $up['webstatus'] = 1;
        }
        $up['repayment_yesaccount'] = [
            'exp',
            'repayment_yesaccount +' . $params['repayment_account'],
        ];
        $up['repayment_yestime'] = time();
        $iborrowRepament = new IborrowRepayment();
        return $iborrowRepament->editByWhere($up, ['repayment_num' => $params['prenum']]);

    }

    /**
     * @desc 函数：融资人还款更新标信息
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params 还款信息
     * @return bool
     */
    private function _updateRepaymentBorrow($params = [])
    {
        if (in_array($params['pmode'], [
            1,
            3,
            4,
        ]))
        {
            if (($params['repayment_info']['order']) == $params['time_limit'])
            {
                $up['status'] = 8;
            }
            $up['repayment_yesaccount'] = [
                'exp',
                'repayment_yesaccount +' . $params['repayment_account'],
            ];
            $up['repayment_yesinterest'] = [
                'exp',
                'repayment_yesinterest +' . $params['repayment_info']['interest'],
            ];

            return $this->_iborrowModel->editByWhere($up, ['borrow_num' => $params['borrow_num']]);
        }

        return true;
    }


    /**
     * @desc 函数：融资人还款清除缓存
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params 标信息
     * @return void
     */
    private function _repaymentDeleteCache($params = [])
    {
        Myredis::getRedisConn()->deleteFromHash("repay_frozen_{$params['user_id']}", $params['prenum']);
        clear_user_cache($params['user_id'], Myredis::getRedisConn());
        clear_borrow_cache($params['borrow_info']['id'], Myredis::getRedisConn());
        Myredis::getRedisConn()->delete('replay_borrow_list'); //竞标大厅最近待还清理
    }


    /**
     * @desc 函数：罚金更新还款表
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params 标信息
     * @return bool
     */
    private function _deductUpdateRepayment($params = [])
    {
        $up['status'] = 1;
        $up['late_interest'] = $params['late_interest'];
        $up['repayment_yestime'] = time();
        $iborrowRepament = new IborrowRepayment();
        return $iborrowRepament->editByWhere($up, ['repayment_num' => $params['prenum']]);
    }


}
