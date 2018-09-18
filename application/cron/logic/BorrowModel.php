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

namespace application\cron\logic;

use application\common\logic\account\AccountLogic;
use think\Db;
use application\common\logic\borrow\BorrowLogic;
use application\common\Myredis;
use application\common\logic\activity\Coupon;
use application\common\logic\activity\Active;
use application\common\logic\system\Task;
use application\common\logic\credit\CreditLogic;
use application\common\model\borrow\Iborrow;
use application\common\model\borrow\IborrowTender;
use application\common\model\borrow\IborrowCollection;
use application\common\model\borrow\IborrowRepayment;
use application\common\model\user\Iuser;
use application\common\model\account\AccountModel;
use application\common\model\borrow\AutoFinancingRule;
use application\common\model\borrow\AutoFrequency;
use application\common\model\borrow\AutoAmount;
use application\common\model\borrow\AutoRate;
use application\common\model\borrow\AutoRuleDetail;
use application\common\model\borrow\AutoFinancingUser;
use application\common\model\borrow\AutoFinancingLog;
use application\common\service\borrow\BorrowServ;

class BorrowModel
{

    /**
     * @desc   account
     * @var    string
     * @access protected
     */
    protected $_account;

    /**
     * @desc   Iborrow
     * @var    string
     * @access protected
     */
    protected $_borrowApi;

    /**
     * @desc   Coupon
     * @var    string
     * @access protected
     */
    protected $_Coupon;

    /**
     * @desc   Task
     * @var    string
     * @access protected
     */
    protected $_Task;

    /**
     * @desc   Credit
     * @var    string
     * @access protected
     */
    protected $_Credit;

    /**
     * @desc   activity
     * @var    string
     * @access protected
     */
    protected $_activity;

    /**
     * @desc   iborrowModel
     * @var    string
     * @access protected
     */
    protected $_iborrowModel;

    function __construct()
    {
        $this->_account = new AccountLogic();
        $this->_borrowApi = new BorrowLogic();
        $this->_Coupon = new Coupon();
        $this->_Task = new Task();
        $this->_CreditApi = new CreditLogic();
        $this->_activity = new Active();
        $this->_iborrowModel = new Iborrow();
    }





    /**
     * @desc 函数：自动还款垫付
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param int $time 时间戳
     * @param bool $isAdvance true 系统垫付 false自动还款
     * @return string
     */
    public function autoPaymentAndAdvance($time = 0, $isAdvance = false)
    {
        $time = $time != 0 ? $time : time();
        $stime = strtotime(date('Y-m-d', $time));
        $etime = $stime + 86400;
        //获取还款数据
        $repaymentModel = new IborrowRepayment();
        $where['rep.status'] = 0;
        $where['rep.repayment_time'] = [
            'between',
            [
                $stime,
                $etime
            ]
        ];
        $list = $repaymentModel->getRepaymentList($where);
        if (empty($list))
        {
            return '无数据';
        }
        $accountModel = new AccountModel();
        $accountLogic = new AccountLogic();
        $creditLogic = new CreditLogic();
        $msg = '';
        $i = 0;
        $j = 0;
        foreach ($list as $value)
        {
            if (cache('LOCKAutoReplay' . $value['repayment_num']))
            {
                continue;
            }
            cache('LOCKAutoReplay' . $value['repayment_num'], 1);
            $checkMoney = $accountModel->hasMoney($value['user_id'], $value['repayment_account']);
            if ($checkMoney)
            {
                $value['prenum'] = $value['repayment_num'];
                if (!Myredis::getRedisConn()->existsInHash('repay_frozen_' . $value['user_id'], $value['repayment_num']))
                {
                    $lock = Myredis::getRedisConn(2)->lock('withdrawal_payment_business_'.$value['user_id'],600);
                    if (!$lock)
                    {
                        continue;
                    }
                    $i++;
                    $data['uid'] = $value['user_id'];
                    $data['to_uid'] = 1;
                    $data['num'] = $value['repayment_num'];
                    $data['remark'] = "还款冻结[Óa href='/Invest/ViewBorrow/num/{$value['borrow_num']}' target=_blankÔ" . $value['name'] . "Ó/aÔ]";
                    $data['use_change'] = -$value['repayment_account'];
                    $data['nouse_change'] = $value['repayment_account'];
                    $data['type'] = 666;
                    $data['btype'] = $value['borrow_type'];
                    $data['borrow_num'] = $value['borrow_num'];
                    $accountLogic->upChange($data);
                    Myredis::getRedisConn()->incrementInHash('repay_frozen_' . $value['user_id'], $value['repayment_num'], $value['repayment_account'] * 10000);
                    $value['pmode'] = 3;
                    $value['time'] = time();
                    Myredis::getRedisConn()->setToHash('repay_queue', $value['repayment_num'], $value['user_id']);
                    $ret = Myredis::getRedisConn()->appendToList("repay_queue_list", $value);
                    $msg .= '还款' . $value['borrow_num'] . '-' . $value['repayment_num'] . ':' . $ret . ',';
                }
                elseif ($isAdvance === true)
                {
                    $j++;
                    //资金不足冻结剩余款
                    $borrowServ = new BorrowServ();
                    $value['pmode'] = 2;
                    $ret = $borrowServ->repaymentHandle($value);
                    $msg .= '垫付' . $value['borrow_num'] . '-' . $value['repayment_num'] . ':' . $ret . ',';
                    //积分--系统垫付积分扣除 默认扣10分
                    $credit['user_id'] = $value['user_id'];
                    $credit['credit_type'] = 'paymentover_web';
                    $credit['num'] = $value['borrow_num'];
                    $credit['credit'] = 0;
                    $credit['title'] = $value['name'];
                    $creditLogic->creditChange($credit);
                    //垫付完成 等待500毫秒
                    usleep(500000);
                }
                else
                {
                    $msg.='还款' . $value['borrow_num'] . '-' . $value['repayment_num'] . ':已存在还款冻结,';
                    continue;
                }
            }
            else
            {
                $msg .= '还款' . $value['borrow_num'] . '-' . $value['repayment_num'] . ':余额不足,';
                continue;
            }
            cache('LOCKAutoReplay' . $value['repayment_num'], null);
        }
        return "垫付:" . $j . "还款:" . $i . '--' . $msg;
    }


    /**
     * @desc 函数：委托融资自动还款
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return string
     */
    public function specialAutoPayment()
    {

        $time = time();
        $stime = strtotime(date('Y-m-d', $time));
        $etime = ($stime + 86400)-1;
        //获取还款数据
        $repaymentModel = new IborrowRepayment();
        $where['rep.status'] = 0;
        $where['rep.repayment_time'] = [
            'between',
            [
                $stime,
                $etime
            ]
        ];

        Myredis::getRedisConn(1)->increment('specialAutoPayment_limit'.date('Y-m-d'),1);
        $page = Myredis::getRedisConn(1)->get('specialAutoPayment_limit'.date('Y-m-d'));
        if ($page==1)
        {
            $unrepayCount = $repaymentModel->getSpecialRepaymenCount($where);
            cache('specialAutoPayment'.date('Y-m-d'),$unrepayCount,3700);
        }
        $unrepayCount = cache('specialAutoPayment'.date('Y-m-d'));

//        dump($unrepayCount);
        $currentpage = $page-1;
        //每次处理行数
        $pageNum = ceil($unrepayCount / 8);
//        dump($pageNum);
        //开始行数
        $offset = 0;
        if ($unrepayCount <= $pageNum) {
            $pageNum = $unrepayCount;
        } else {
            $offset = $currentpage * $pageNum;
        }
        //超过17:40全部还清
        $limit = '';
        if ($page<8)
        {
            $limit = "{$offset},{$pageNum}";
        }
        else
        {
            Myredis::getRedisConn(1)->delete('specialAutoPayment_limit'.date('Y-m-d'));
        }
//       dump($limit);
        $list = $repaymentModel->getSpecialRepaymentList($where,$limit);
        if(!$list){
            $list = $repaymentModel->getSpecialRepaymentList($where,"0,{$pageNum}");
        }
//        echo $repaymentModel->getLastSql();
        if (empty($list))
        {
            return '无数据';
        }
        $accountModel = new AccountModel();
        $accountLogic = new AccountLogic();
        $msg = '';
        $i = 0;
        foreach ($list as $value)
        {
            if (cache('LOCKAutoReplay' . $value['repayment_num']))
            {
                continue;
            }
            $checkMoney = $accountModel->hasMoney($value['user_id'], $value['repayment_account']);
            if ($checkMoney)
            {
                $value['prenum'] = $value['repayment_num'];
                if (!Myredis::getRedisConn()->existsInHash('repay_frozen_' . $value['user_id'], $value['repayment_num']))
                {
                    $i++;
                    $lock = Myredis::getRedisConn(1)->get('LOCK_use_money_business'.$value['user_id']);
                    if ($lock)
                    {
                        $msg .= '还款' . $value['borrow_num'] . '-' . $value['repayment_num'] . ':资金互斥,';
                        continue;
                    }
                    cache('LOCKAutoReplay' . $value['repayment_num'], 1);
                    Myredis::getRedisConn(1)->setAndExpire('LOCK_use_money_business'.$value['user_id'],1,180);
                    $data['uid'] = $value['user_id'];
                    $data['to_uid'] = 1;
                    $data['num'] = $value['repayment_num'];
                    $data['remark'] = "还款冻结[Óa href='/Invest/ViewBorrow/num/{$value['borrow_num']}' target=_blankÔ" . $value['name'] . "Ó/aÔ]";
                    $data['use_change'] = -$value['repayment_account'];
                    $data['nouse_change'] = $value['repayment_account'];
                    $data['type'] = 666;
                    $data['btype'] = $value['borrow_type'];
                    $data['borrow_num'] = $value['borrow_num'];
                    $accountLogic->upChange($data);
                    Myredis::getRedisConn()->incrementInHash('repay_frozen_' . $value['user_id'], $value['repayment_num'], $value['repayment_account'] * 10000);
                    $value['pmode'] = 3;
                    $value['time'] = time();
                    Myredis::getRedisConn()->setToHash('repay_queue', $value['repayment_num'], $value['user_id']);
                    $ret = Myredis::getRedisConn()->appendToList("repay_queue_list", $value);
                    Myredis::getRedisConn(1)->delete('LOCK_use_money_business'.$value['user_id']);
                    $msg .= '还款' . $value['borrow_num'] . '-' . $value['repayment_num'] . ':' . $ret . ',';
                }
                else
                {
                    $msg.='还款' . $value['borrow_num'] . '-' . $value['repayment_num'] . ':已存在还款冻结,';
                    continue;
                }
            }
            else
            {
                $msg .= '还款' . $value['borrow_num'] . '-' . $value['repayment_num'] . ':余额不足,';
                continue;
            }
            $msg .="<br/>";
            cache('LOCKAutoReplay' . $value['repayment_num'], NULL);
        }
        return "还款:" . $i . '--' . $msg;
    }


    /**
     * @desc 函数：投资人获得还款
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @param array $params 还款信息
     * @return mixed
     */
    public function investorGetRepayHandle($params = [])
    {
        try
        {
            Db::startTrans();
            $params['payby'] = $params['pmode'] == 2 ? 1 : 0;
            //更新代收表itd_iborrow_collection
            $r1 = $this->_getRepayupdateCollection($params);
            if (!$r1)
            {
                triggleError('更新代收表');
            }
            //获得还款
            $this->_getRepayMoney($params);
            //扣除利息管理费
            if ($params['borrow_type'] != 5)
            {
                $this->_deductInterestManageFee($params);
            }
            //释放保证金
            if ($params['pmode'] != 2 && $params['reorder'] + 1 == $params['time_limit'] && $params['borrow_type'] == 2)
            {
                $this->_backMargin($params);
            }

        } catch (\Exception $exception)
        {
            Db::rollback();
            return msg($exception);
        }
        Db::commit();
        //发送短信，清除缓存
        $this->_sendSmsDeleteCach($params);
        return true;
    }

    /**
     * @desc 函数：自动发标
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return mixed
     */
    public function autoAddBorrow()
    {
       //查詢：自动融资模型
        $where['financing_time_start'] = ['<=',date('Y-m-d',time())];
        $where['financing_time_end'] = ['>=',date('Y-m-d',time())];
        $where['rule_status'] =1;
        $financingRuleModel = new AutoFinancingRule();
        $ruleInfo= $financingRuleModel->getOneByWhere(['where'=>$where,'order'=>'id asc']);
        unset($where);
        if (empty($ruleInfo))
        {
            return '没有开启的委托融资模型';
        }
        //查询：自动融资模型规则
        $where['financing_time_start'] = ['<=',date('Y-m-d H:i:s',time())];
        $where['financing_time_end'] = ['>=',date('Y-m-d H:i:s',time())];
        $where['rule_status'] =1;
        $where['auto_financing_rule_id'] =$ruleInfo['id'];
        $ruleDetailModel = new AutoRuleDetail();
        $ruleDetailInfo= $ruleDetailModel->getOneByWhere(['where'=>$where,'order'=>'id asc']);
        if (empty($ruleDetailInfo))
        {
            return '没有开启的委托融资模型规则';
        }
        //年利率人工设置
        if ($ruleDetailInfo['financing_item_year_rate_mode']==0)
        {
            $rateModel = new AutoRate();
            $sqlArr = [
                'where'=>['auto_financing_rule_detail_id'=>$ruleDetailInfo['id']],
                'field'=>'financing_item_year_rate'
            ];
            $yearRateList = $rateModel->getList($sqlArr);
            $yearRate = $yearRateList[array_rand($yearRateList,1)]['financing_item_year_rate'];
        }
        //年利率采用自动选择时，采用的是当前系统最新发出的融资项目的年利率。
        else
        {
            $borrowInfo = $this->_iborrowModel->getOneByWhere(['where'=>['borrow_type'=>array('in','7,11')],'order'=>'id desc'],'apr');
            $yearRate = $borrowInfo['apr'];
        }
        //查询：融资金额配置
        $amountModel = new AutoAmount();
        $sqlArr = [
            'where'=>['auto_financing_rule_detail_id'=>$ruleDetailInfo['id']],
            'field'=>'auto_financing_rule_detail_id,financing_item_amount'
        ];
        $amountList = $amountModel->getList($sqlArr);
       // halt($amountList);
        if (empty($amountList))
        {
            return '未设置委托融资模型规则融资金额';
        }
        $amount = $amountList[array_rand($amountList,1)]['financing_item_amount'];

        //查询：融资频率配置
        $frequencyModel = new AutoFrequency();
        $sqlArr = [
            'where'=>['auto_financing_rule_detail_id'=>$ruleDetailInfo['id']],
            'field'=>'financing_item_frequency'
        ];
        $frequencyList = $frequencyModel->getList($sqlArr);
        unset($sqlArr);
        if (empty($frequencyList))
        {
            return '未设置委托融资模型规则融资频率';
        }
        $frequency = $frequencyList[array_rand($frequencyList,1)]['financing_item_frequency'];
        //查询：特殊融资人
        $userModel = new AutoFinancingUser();
        $sqlArr = [
            'where'=>['user_status'=>1]
        ];
        $userList = $userModel->getList($sqlArr);
        if (empty($userList))
        {
            $up = [
                'auto_financing_status'=>3
            ];
            $financingRuleModel->edit($up,$ruleInfo['id']);
            unset($up);
            return '未设置委托融资人';
        }
        $userInfo = $userList[array_rand($userList,1)];
        $financingLogModel = new AutoFinancingLog();
        //查询该模型已经融资的金额
        $alreadyFinacingAmount = $financingLogModel->getAlreadyFinancingAmount($ruleInfo['id']);
        if (($alreadyFinacingAmount+$amount) > $ruleInfo['financing_amount_max'])
        {
            $up = [
                'auto_financing_status'=>2
            ];
            $financingRuleModel->edit($up,$ruleInfo['id']);
            unset($up);
            return '委托模型id：'.$ruleInfo['id'].'额度不足了';
        }

        //查询：特殊融资人融资记录
        $financingLogInfo = $financingLogModel->getOneByWhere(['order'=>'id desc'],'financing_time');
        //判断：融资过
        if (!empty($financingLogInfo))
        {
            $time =  time()-strtotime($financingLogInfo['financing_time']);
//            if (floor($time/60) < $frequency)
//            {
//                return '未到发标时间';
//            }
            if ($time < $frequency)
            {
                return '未到发标时间';
            }
        }
        $borrowServ = new BorrowServ();
        $params = [
            'user_id'=>$userInfo['user_id'],
            'account'=>$amount,
            'apr'=>$yearRate,
            'ibtype'=>$userInfo['financing_type'],
            'repaystyle'=>0,
        ];
        $data = $borrowServ->checkAddBorrow($params,true);
        if (is_string($data))
        {
            return $data;
        }
        $data = $this->_borrowApi->addBorrowQueque($data,true);
        $ret = $borrowServ->addBorrow($data);
        if (is_numeric($ret))
        {
            $log = [
                'user_id' => $userInfo['user_id'],
                'user_name' => $userInfo['user_name'],
                'user_real_name' => $userInfo['user_real_name'],
                'financing_type' =>$userInfo['financing_type'],
                'item_id' => $ret,
                'item_name' => $data['pname'],
                'financing_time' => date('Y-m-d H:i:s'),
                'financing_amount' => $amount,
                'year_rate' => $yearRate,
                'auto_financing_rule_id'=>$ruleInfo['id'],
                'auto_financing_rule_detail_id'=>$ruleDetailInfo['id']
            ];
            $financingLogModel->add($log);
            $up = [
                'auto_financing_status'=>1
            ];
            $financingRuleModel->edit($up,$ruleInfo['id']);
            unset($up);
            return true;
        }
        return $ret['msg'];
    }

    /**
     * @desc 函数：发送短信，清除缓存
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params
     * @return void
     */
    private function _sendSmsDeleteCach($params = [])
    {
        $this->_Task->AddExeMone(108, $params['renum']);
        clear_user_cache($params['user_id'], Myredis::getRedisConn());
        clear_borrow_cache($params['id'], Myredis::getRedisConn());
        Myredis::getRedisConn()->delete('replay_borrow_list');
    }

    /**
     * @desc 函数：退还保证金
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params 标信息
     * @return bool
     */
    private function _backMargin($params = [])
    {
        //释放保证金
        if ($params['forst_account'] > 0)
        {
            $data['uid'] = $params['user_id'];
            $data['to_uid'] = 1;
            $data['num'] = $params['borrow_num'];
            $data['remark'] = "信用标[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]退还保证资金";
            $data['use_change'] = $params['forst_account'];
            $data['nouse_change'] = -$params['forst_account'];
            $data['type'] = 801;
            $this->_account->upChange($data);
            unset($data);
        }
        return true;
    }

    /**
     * @desc 函数：扣除利息管理费
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params
     * @return mixed
     */
    private function _deductInterestManageFee($params = [])
    {
        $where['borrow_num'] = $params['borrow_num'];
        $where['a.order'] = $params['reorder'];
        $where['sr_status'] = [
            'neq',
            2
        ];
        $where['a.status'] = 0;
        $iborrowCollectionModel = new IborrowCollection();
        $list = $iborrowCollectionModel->getManageFree($where);
        if (!empty($list))
        {
            foreach ($list as $key => $value)
            {
                $data['uid'] = $value['user_id'];
                $data['to_uid'] = 1;
                $data['num'] = $params['renum'];
                $data['remark'] = '扣除利息管理费';
                $data['use_change'] = $value['imf'];
                $data['total_change'] = $value['imf'];
                $data['type'] = 409;
                $this->_account->upChange($data);
                unset($data);
            }
        }
        return true;


    }

    /**
     * @desc 函数：获得还款
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params
     * @return mixed
     */
    private function _getRepayMoney($params = [])
    {
        $where['borrow_num'] = $params['borrow_num'];
        $where['order'] = $params['reorder'];
        $where['sr_status'] = [
            'neq',
            2,
        ];
        $where['status'] = 0;
        $iborrowCollection = new IborrowCollection();
        $sqlArr = [
            'field' => 'user_id,SUM(repay_account) as repay_account',
            'where' => $where,
            'group' => 'user_id'
        ];
        $repayList = $iborrowCollection->getList($sqlArr);
        if (!empty($repayList))
        {
            foreach ($repayList as $key => $value)
            {
                $data['uid'] = $value['user_id'];
                $data['to_uid'] = $params['user_id'];
                $data['num'] = $params['renum'];
                $data['remark'] = $params['message'];
                $data['use_change'] = $value['repay_account'];
                $data['collection_change'] = -$value['repay_account'];
                $data['type'] = 108;
                $this->_account->upChange($data);
                clear_user_cache($value['user_id'], Myredis::getRedisConn());
                unset($data);
            }
        }
        return true;

    }

    /**
     * @desc 函数：更新代收表信息
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params
     * @return bool
     */
    private function _getRepayupdateCollection($params = [])
    {
        $iborrowCollection = new IborrowCollection();
        return $iborrowCollection->repayupdateCollection($params);
    }


    /**
     * @desc 函数：满标验证
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return mixed
     */
    private function _paramsValid($params = [])
    {
        if (!isset($params['borrow_num']))
        {
            return ['status'=>false,'msg'=>'未定义标编码'];
        }
        if ($params['status'] != 3)
        {
            return ['status'=>false,'msg'=>'标状态不正确'];
        }
        $borrow = $this->_iborrowModel->getOneByWhere(['where' => ['borrow_num' => $params['borrow_num']]], 'id,status,order');
        $iborrowRepaymentModel = new IborrowRepayment();
        $repayPlan = $iborrowRepaymentModel->getOneByWhere(['where' => ['borrow_num' => $params['borrow_num']]], 'user_id');
        if (empty($borrow))
        {
            return ['status'=>false,'msg'=>'未找到标信息'];
        }
        elseif ($borrow['status'] >= 7)
        {
            return ['status'=>false,'msg'=>'标已还款'];
        }
        elseif ($borrow['order'] == 0 && $repayPlan)
        {
            return ['status'=>false,'msg'=>'重复还款计划'];
        }
        return ['status'=>true,'data'=>$borrow['id']];
    }

    /**
     * @desc 函数：额度返还
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return bool
     */
    private function _amountBack($params = [])
    {
        $backTotal = $params['account'] - $params['account_yes'];
        //差额返还部分
        if (isset($params['Advance']) && $params['Advance'] == 'AddRepaymentWithData' && $backTotal > 0)
        {
            $config = config('system.amount_type');
            $back = [
                'user_id'    => $params['user_id'],
                'code_id'    => $config[$params['borrow_type']],
                'back_total' => $params['account'] - $params['account_yes'],
                'type'       => '122',
                'rale_num'   => $params['borrow_num'],
                'remark'     => '差额返还',
            ];
            return $this->_borrowApi->amountUnfreeze($back);
        }
        return true;
    }

    /**
     * @desc 函数：生成还款计划
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return mixed
     */
    private function _createRepayPlan($params = [])
    {
        $apr = isset($params['apr']) ? $params['apr'] : 0;
        $counts = $params['repaystyle'] == 4 ? $params['fatalism'] : $params['time_limit'];
        $resultRepay = interest($params['account_yes'], $apr, $counts, $params['repaystyle'], '', 2);
        $reptimes = count($resultRepay['repayment_plan']);
        $repayNum = made_num('repayment', $reptimes, $params['user_id']);
        if (!$repayNum)
        {
            return '还款编码计数更新失败';
        }
        $repaydata['borrow_num'] = $params['borrow_num'];
        $repaydata['addtime'] = time();
        $repayTotal = 0;
        if (!isset($resultRepay['repayment_plan']) && !is_array($resultRepay['repayment_plan']))
        {
            return '还款计划未找到';
        }
        foreach ($resultRepay['repayment_plan'] as $k => $v)
        {
            $repaydata['repayment_num'] = $reptimes == 1 ? $repayNum : $repayNum[$k];
            $repaydata['order'] = $v['times'];
            $repaydata['repayment_time'] = $v['repayment_time'];
            $repaydata['repayment_account'] = $v['repayment_account'];
            $repaydata['interest'] = $v['interest'];
            $repaydata['capital'] = $v['capital'];
            $repaydata['user_id'] = $params['user_id'];
            $repaydata['username'] = $params['username'];
            $repayTotal += $repaydata['repayment_account'];
            $repayNewData[] = $repaydata;
            $lastRepaydate = $repaydata['repayment_time'];
        }
        $ibrrowRepaymentModel = new IborrowRepayment();
        $re = $ibrrowRepaymentModel->addAll($repayNewData);
        if (!$re)
        {
            return '生成还款计划失败';
        }
        $params['repay_total'] = $repayTotal;
        $params['repayment_time'] = $lastRepaydate;
        $params['counts'] = $counts;
        $params['reptimes'] = $reptimes;
        $params['repay_info'] = $repaydata;
        return $params;
    }

    /**
     * @desc 函数：修改投资人信息
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return bool
     */
    private function _editInvestorInfo($params = [])
    {
        $iborrowTenderModel = new IborrowTender();
        $up = [
            'status'        => 1,
            'wait_account'  => [
                'exp',
                'repayment_account'
            ],
            'wait_interest' => [
                'exp',
                'interest'
            ],
        ];
        $r1 = $iborrowTenderModel->editByWhere($up, [
            'borrow_num' => $params['borrow_num'],
            'status'     => 0
        ]);
        $iborrowCollectionModel = new IborrowCollection();
        $r2 = $iborrowCollectionModel->fullUpdateCollection($params['borrow_num']);
        if (false === $r1 || false === $r2)
        {
            return false;
        }
        return true;
    }

    private function _editInvestorMoney($params = [])
    {
        $iborrowTenderModel = new IborrowTender();
        $sqlAttr = [
            'field' => 'user_id,SUM(repayment_account) AS repayment_account ,SUM(interest) AS interest,SUM(account) AS account',
            'where' => [
                'borrow_num' => $params['borrow_num'],
                'status'     => 0
            ],
            'group' => 'user_id'
        ];
        $rsfull = $iborrowTenderModel->getList($sqlAttr);
        if (!empty($rsfull))
        {
            foreach ($rsfull as $v)
            {
                $data['uid'] = $v['user_id'];
                $data['to_uid'] = $params['user_id'];
                $data['num'] = $params['borrow_num'];
                $data['remark'] = "投资成功[Óa href='/Invest/ViewBorrow/num/'" . $params['borrow_num'] . "target=_blankÔ" . $params['name'] . "Ó/aÔ]";
                $data['total_change'] = $v['interest'];
                $data['nouse_change'] = -$v['account'];
                $data['collection_change'] = $v['repayment_account'];
                $data['type'] = 109;
                $data['btype'] = $params['borrow_type'];
                $this->_account->upChange($data);
                unset($data);
            }
        }
        return true;
    }


    /**
     * @desc 函数：修改融资人信息
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return mixed
     */
    private function _editFinancierInfo($params = [])
    {
        $feeRate = $this->_borrowApi->getManageFeer($params['borrow_type'], $params['repaystyle']);
        $fee = $params['account_yes'] * $feeRate * $params['counts'];
        //奖励borrowaward 及管理费用
        $params['award_type'] = isset($params['award_type']) ? $params['award_type'] : 0;
        $award = $params['award_type'] == 2 ? $params['account_yes'] * ($params['award_rate'] / 100) : 0;
        if ($award > 0)
        {
            $iborrowTenderModel = new IborrowTender();
            $eachUnit = $award / $params['account_yes'];
            $up = [
                'awardat' => [
                    'exp',
                    'account * ' . $eachUnit
                ],
            ];
            $ret = $iborrowTenderModel->editByWhere($up, ['borrow_num' => $params['borrow_num']]);
            if (!$ret)
            {
                return '更新投标表奖励失败';
            }
        }
        $params['award'] = $award;
        $params['bfee'] = $fee;
        return $params;
    }

    /**
     * @desc 函数：修改融资人信息
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @param array $params 标信息
     * @return bool
     */
    private function _editFinancierMoney($params = [])
    {
        $data['uid'] = $params['user_id'];
        $data['to_uid'] = 1;
        $data['num'] = $params['borrow_num'];
        $data['remark'] = "融资成功[Óa href='/Invest/ViewBorrow/num/'" . $params['borrow_num'] . "target=_blankÔ" . $params['name'] . "Ó/aÔ]";
        $data['total_change'] = $params['account_yes'];
        $data['use_change'] = $params['account_yes'];
        $data['waitreplay_change'] = $params['repay_total'];
        $data['type'] = 105;
        $data['btype'] = $params['borrow_type'];
        $this->_account->upChange($data);
        unset($data);
        $feeRate = $this->_borrowApi->getManageFeer($params['borrow_type'], $params['repaystyle']);
        $fee = $params['account_yes'] * $feeRate * $params['counts'];
        //奖励borrowaward 及管理费用
        $params['award_type'] = isset($params['award_type']) ? $params['award_type'] : 0;
        $award = $params['award_type'] == 2 ? $params['account_yes'] * ($params['award_rate'] / 100) : 0;
        if ($award > 0)
        {
            $data['uid'] = $params['user_id'];
            $data['to_uid'] = 1;
            $data['num'] = $params['borrow_num'];
            $data['remark'] = "奖励支出[Óa href='/Invest/ViewBorrow/num/'" . $params['borrow_num'] . "target=_blankÔ" . $params['name'] . "Ó/aÔ]";
            $data['total_change'] = -$award;
            $data['use_change'] = -$award;
            $data['type'] = 601;
            $data['btype'] = $params['borrow_type'];
            $this->_account->upChange($data);
            unset($data);
            $iborrowTenderModel = new IborrowTender();
            $sqlArr = [
                'field' => 'user_id,SUM(account) AS account',
                'where' => ['borrow_num' => $params['borrow_num']],
                'group' => 'user_id'
            ];
            $rsaward = $iborrowTenderModel->getList($sqlArr);
            $eachUnit = $award / $params['account_yes'];
            if (!empty($rsaward))
            {
                foreach ($rsaward as $v)
                {
                    $data['uid'] = $v['user_id'];
                    $data['to_uid'] = $params['user_id'];
                    $data['num'] = $params['borrow_num'];
                    $data['remark'] = "投标成功获得[Óa href='/Invest/ViewBorrow/num/'" . $params['borrow_num'] . "target=_blankÔ" . $params['name'] . "Ó/aÔ]奖励";
                    $data['total_change'] = $v['account'] * $eachUnit;
                    $data['use_change'] = $v['account'] * $eachUnit;
                    $data['type'] = 701;
                    $data['btype'] = $params['borrow_type'];
                    $this->_account->upChange($data);
                    unset($data);
                }
            }
        }
        if ($fee > 0 && $params['borrow_type'] != 11)
        {
            //扣除融资人的管理费支出,工薪贷不收管理费
            $data['uid'] = $params['user_id'];
            $data['to_uid'] = 1;
            $data['num'] = $params['borrow_num'];
            $data['remark'] = "管理费支出[Óa href='/Invest/ViewBorrow/num/'" . $params['borrow_num'] . "target=_blankÔ" . $params['name'] . "Ó/aÔ]奖励";
            $data['total_change'] = -$fee;
            $data['use_change'] = -$fee;
            $data['type'] = 407;
            $data['btype'] = $params['borrow_type'];
            $this->_account->upChange($data);
            unset($data);
        }
        return true;
    }


    /**
     * @desc 函数：修改标信息
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return bool
     */
    private function _editBorrowInfo($params = [])
    {
        $params['award'] = isset($params['award']) ? $params['award'] : 0;
        $params['bfee'] = isset($params['bfee']) ? $params['bfee'] : 0;
        $up = [
            'award_yes'         => [
                "exp",
                "award_yes+{$params['award']}",
            ],
            'bfee'              => [
                "exp",
                "bfee+{$params['bfee']}",
            ],
            'repayment_user'    => 1,
            'repayment_time'    => time(),
            'repayment_remark'  => '自动复审',
            'repayment_account' => $params['repay_total'],
            'status'            => 7,
            'each_time'         => $params['repayment_time'],
        ];
        return $this->_iborrowModel->editByWhere($up, ['borrow_num' => $params['borrow_num']]);

    }

    /**
     * @desc 函数：秒标释放保证金
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @param array $params 标信息
     * @return mixed
     */
    private function _releaseDeposit($params = [])
    {
        if (Myredis::getRedisConn()->existsInHash("repay_queue", $params['repay_info']['repayment_num']))
        {
            return "repay_queue队列已存在" . $params['repay_info']['repayment_num'];
        }

        //释放保证金
        if ($params['forst_account'] > 0)
        {
            $data['uid'] = $params['user_id'];
            $data['to_uid'] = 1;
            $data['num'] = $params['borrow_num'];
            $data['remark'] = "还款前[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ" . $params['name'] . "Ó/aÔ]退还保证资金";
            $data['use_change'] = $params['forst_account'];
            $data['nouse_change'] = -$params['forst_account'];
            $data['type'] = 203;
            $data['btype'] = $params['borrow_type'];
            $this->_account->upChange($data);
            unset($data);
        }

        return true;
    }


    /**
     * @desc 函数：秒标还款冻结
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @param array $params 标信息
     * @return bool
     */
    private function _repaymentRreeze($params = [])
    {
        $data['uid'] = $params['user_id'];
        $data['to_uid'] = 1;
        $data['num'] = $params['repay_info']['repayment_num'];
        $data['remark'] = "还款冻结[Óa href='/Invest/ViewBorrow/num/{$params['borrow_num']}' target=_blankÔ" . $params['name'] . "Ó/aÔ]";
        $data['use_change'] = -$params['repay_info']['repayment_account'];
        $data['nouse_change'] = $params['repay_info']['repayment_account'];
        $data['type'] = 666;
        $data['btype'] = $params['borrow_type'];
        $data['borrow_num'] = $params['borrow_num'];
        $this->_account->upChange($data);
        unset($data);
        Myredis::getRedisConn()->setToHash('repay_queue', $params['repay_info']['repayment_num'], $params['user_id'], false);
        $repayQueque = [
            'prenum'            => $params['repay_info']['repayment_num'],
            'pmode'             => 1,
            'user_id'           => $params['user_id'],
            'time'              => time(),
            'repayment_account' => $params['repay_info']['repayment_account'],
            'borrow_num'        => $params['borrow_num'],
            'name'              => $params['name'],
            'order'             => $params['repay_info']['order'] + 1,
            'time_limit'        => $params['time_limit'],
            'btype'             => $params['borrow_type'],
        ];
        Myredis::getRedisConn()->appendToList("repay_queue_list", $repayQueque);
        Myredis::getRedisConn()->incrementInHash("repay_frozen_{$params['user_id']}", $params['repay_info']['repayment_num'], $params['repay_info']['repayment_account'] * 10000);
        return true;
    }


    /**
     * @desc 函数：投标参数验证
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @param array $params 投标信息
     * @return array
     */
    private function _tenderQuequeParamsValid($params = [])
    {
        $maxAccount = 0;
        if ($params['most_account'] > 0 && $params['pTaccount'] > $params['most_account'])
        {
            //查已投金额
            $iborrowTenderModel = new IborrowTender();
            $sqlArr = [
                'field'=>'SUM(account) as account',
                'where'=>['borrow_num'=>$params['borrow_num']]
            ];
            $accountInfo = $iborrowTenderModel->getOneByWhere($sqlArr);
            if (empty($accountInfo))
            {
                return [
                    'status' => 114,
                    'info'   => "限制失败",
                ];
            }
            $account = $accountInfo['account'];
            if ($account > $params['most_account'])
            {
                return [
                    'status' => 113,
                    'info'   => "超出限额",
                ];
            }
            //计算可投资金
            $maxAccount = $params['most_account'] - $account;
        }

        $field = '(account-account_yes)as lastaccount,account_yes,most_account,status,borrow_type';
        $sqlAttr = [
            'where' => ['borrow_num' => $params['borrow_num']],
            'field' => $field
        ];
        $borrowInfo = $this->_iborrowModel->getOneByWhere($sqlAttr, $field);
        if ($borrowInfo['status'] != 1)
        {
            return [
                'status' => 109,
                'info'   => "此标不在募集中",
            ];
        }
        $accountYes = Myredis::getRedisConn()->getFromHash('borrow_account_yes', $params['borrow_num']) / 100;
        $lastAccount = $params['account'] - $accountYes;
        $lastAccount = sprintf("%.2f", $lastAccount);
//        $tenderMoney = $params['pTaccount'];
        //计算最大可投差额
        if ($maxAccount > 0)
        {
            $lastAccount = $lastAccount >= $maxAccount ? $maxAccount : $lastAccount;
        }
        //计算实际投标金额
        $tenderMoney = $params['pTaccount'] >= $lastAccount ? $lastAccount : $params['pTaccount'];
        if ($tenderMoney <= 0)
        {
            return [
                'status' => 109,
                'info'   => "投标失败,此标可能已满",
            ];
        }
        $accountModel = new AccountModel();
        $userAccountInfo = $accountModel->hasMoney($params['tuserid'], $tenderMoney);
        if (!$userAccountInfo)
        {
            return [
                'status' => 104,
                'info'   => '可用余额不足',
            ];
        }
        if ($params['most_account'] > 0)
        {
            $key = "most_account_" . $params['borrow_num'];
            $allaccount = Myredis::getRedisConn()->getFromHash($key, $params['tuserid']);
            $value = Myredis::getRedisConn()->incrementInHash($key, $params['tuserid'], $tenderMoney * 100) / 100;
            if ($value > $params['most_account'])
            {
                Myredis::getRedisConn()->incrementInHash($key, $params['tuserid'], -$tenderMoney * 100);

                return [
                    'status' => 113,
                    'info'   => sprintf("超出限额:%.2f/已投%.2f", $params['most_account'], $allaccount / 100),
                ];
            }
        }

        return [
            'status'         => 1,
            'tender_money'   => $tenderMoney,
            'surplus_tender' => $lastAccount,
        ];
    }

    /**
     * @desc 函数：更新标信息
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @return bool
     */
    private function _updateBorrow($params = [])
    {
        //满标
        if ($params['tender_money'] >= $params['surplus_tender'])
        {
            $up['status'] = 3; //满标
        }
        $up['account_yes'] = [
            'exp',
            'account_yes +' . $params['tender_money'],
        ];
        $up['tender_times'] = [
            'exp',
            'tender_times +1',
        ];
        return $this->_iborrowModel->editByWhere($up, ['borrow_num' => $params['borrow_num']]);
    }


    /**
     * @desc 函数：添加代收记录
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @return mixed
     */
    private function _addRepay($params = [])
    {
        $apr = isset($params['apr']) ? $params['apr'] : 0;
        $counts = $params['repaystyle'] == 4 ? $params['fatalism'] : $params['time_limit'];
        $resultRepay = interest($params['tender_money'], $apr, $counts, $params['repaystyle'], '', 2);
        $dataList = [];
        foreach ($resultRepay['repayment_plan'] as $value)
        {
            $colData['addtime'] = time();
            $colData['tender_num'] = $params['tnum'];
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
            $returnCollection = $iborrowCollectionModel->addAll($dataList);
            if (!$returnCollection)
            {
                return false;
            }
        }
        $params['total_interest'] = $resultRepay['total_interest'];

        return $params;
    }

    /**
     * @desc 函数：满标发送信息
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @param array $params 标信息
     * @return bool
     */
    private function _sendSms($params)
    {
        $this->_Task->AddExeMone(109, $params['borrow_num']);
        $iuserModel = new Iuser();
        $user = $iuserModel->getOneByWhere(['where' => ['user_id' => $params['user_id']]], 'username,phone');
        if (!empty($user))
        {
            return $this->_Task->sendFullBorrowSuccess($params['user_id'], $params['name'], $user['username'], $params['account_yes'], $user['phone']);
        }
        return true;
    }

    /**
     * @desc 函数：满标清理缓存
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @param array $params
     * @return bool
     */
    private function _deleteCache($params)
    {
        Myredis::getRedisConn()->deleteFromHash("setInvest", $params['borrow_num']);
        Myredis::getRedisConn()->deleteFromHash("tender_times", $params['borrow_num']);
        Myredis::getRedisConn()->deleteFromHash("borrow_account_yes", $params['borrow_num']);
        clear_user_cache($params['user_id'], Myredis::getRedisConn());
        Myredis::getRedisConn()->delete('replay_borrow_list'); //竞标大厅最近待还清理
        cache('GetcanfuncPayroll' . $params['user_id'], null);
        clear_borrow_cache($params['id'], Myredis::getRedisConn());
        Myredis::getRedisConn()->delete('replay_borrow_list');
        Myredis::getRedisConn()->delete("most_account_" . $params['borrow_num']);
        Myredis::getRedisConn(1)->delete($params['borrow_num']);
        $tenderList = Db::name('iborrow_tender')->field("user_id")->where("borrow_num", $params['borrow_num'])
                        ->select();
        foreach ($tenderList as $v)
        {
            clear_user_cache($v['user_id'], Myredis::getRedisConn());
        }

        return true;
    }


}
