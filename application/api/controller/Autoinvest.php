<?php
/**
 * @Copyright (C), 2017, liuj
 * @Name AutoInvest.php
 * @Author liuj
 * @Version stable 1.0
 * @Date 2017-11-30
 * @Description 自动投资类
 * @Class List
 *    1.
 * @Function List
 *    1.
 * @History
 * <author>  <time>              <version>    <desc>
 *  liuj   2017-11-30          stable 1.0   第一次建立该文件
 */

namespace application\api\controller;

use application\common\logic\account\AccountLogic;
use application\common\model\account\AccountModel;
use application\common\model\activity\TreasureChest;
use application\common\model\article\Article;
use application\common\model\borrow\AutoInvestAccount;
use application\common\model\borrow\AutoInvestAccountDetail;
use application\common\model\borrow\AutoInvestBuyVip;
use application\common\model\borrow\AutoInvestRedpacket;
use application\common\model\borrow\IborrowRepayment;
use application\common\model\borrow\ItemAutoInvest;
use application\common\model\borrow\MonthRate;
use application\common\model\credit\Credit;
use application\common\model\investAward\ActivityInvestAwardItem;
use application\common\model\user\Iuser;
use application\common\Myredis;
use application\common\RedisLock;
use think\Cache;
use think\Db;

class Autoinvest extends Base
{
    public function _initialize()
    {
        parent::_initialize();
    }

    public function _empty()
    {
        $this->failJson('无服务接口!');
    }

    /**
     * @desc 函数：首页判断
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function checkVip()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],
        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);
        $this->okJson();

        //检查是否已购买vip服务
        $autoInvestAccountModel = new AutoInvestAccount();
//        $accountInfo = $autoInvestAccountModel->getOne($uid);
//        if (empty($accountInfo))
//        {
//            $buyVipModel = new AutoInvestBuyVip();
//            //自动投资服务信息
//            $info = $buyVipModel->getOneByWhere([
//                'where' => [
//                    'user_id' => $uid,
//                ],
//                'order' => 'id desc',
//                'field' => 'user_id,vip_time_end'
//            ]);
//            if (empty($info))
//            {
//                $this->failJson('您还未购买自动投资服务,暂不能设置!', 2);
//            }
//            elseif (time() > strtotime($info['vip_time_end']))
//            {
//                $this->failJson('您的自动投资服务已过期,暂不能设置!', 2);
//            }
//        }
//        elseif (time() > strtotime($accountInfo['auto_invest_vip_time_end']))
//        {
//            $this->failJson('您的自动投资服务已过期,暂不能设置!', 2);
//        }

        $this->okJson();
    }

    /**
     * @desc 函数：是否可以开通vip
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function checkOpenVip()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],
        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);

        //实名判断
        $userModel = new Iuser();
        $userInfo = $userModel->getOne($uid);
        if (!isset($userInfo['realname']) || $userInfo['real_status'] != 1)
        {
            $this->failJson('抱歉，自动投资需实名认证用户才能购买！立即去实名认证~', 3);
        }

        //金融vip判断
        if ($userInfo['vip_status'] != 1 || (time() > $userInfo['vip_time']))
        {
            $this->failJson('抱歉，自动投资需金融VIP用户才能购买！立即开通金融vip~', 4);
        }

        //超级会员判断
        $superInfo = Myredis::getRedisConn(3)->getFromHash('account_service', $uid);
        if (empty($superInfo))
        {
            $this->failJson('抱歉，自动投资需超级会员用户才能购买！立即开通超级会员~', 5);
        }
        elseif (!$superInfo['superMember'])
        {
            $this->failJson('抱歉，自动投资需超级会员用户才能购买！立即开通超级会员~', 5);
        }
        elseif (date('Y-m-d H:i:s', time()) > $superInfo['superTimeEnd'])
        {
            $this->failJson('抱歉，自动投资需超级会员用户才能购买！立即开通超级会员~', 5);
        }

        $accModel = new AccountModel();
        $money = $accModel->getAccountInfoByUserId($uid);
        $costMoney = Myredis::getRedisConn()->get('auto_invest_cost_money');
        $costMoney = !empty($costMoney) ? $costMoney : 50;
        if ($money['use_money'] < $costMoney)
        {
            $this->failJson('亲，可用余额不足，请先充值~', 6);
        }

        $data['use_money'] = isset($money['use_money']) ? $money['use_money'] : 0;
        $data['user_name'] = $userInfo['username'];
        $data['cost_money'] = !empty($costMoney) ? $costMoney : 50;
        $this->okJson($data);
    }

    /**
     * @desc 函数：开通vip
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function openVip()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],
            [
                'param_name' => 'trade_password',
                'label_name' => '交易密码',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'is_auto_renew',
                'label_name' => '是否自动续费',
                'param_type' => 'int',
                'is_require' => true,
                'rule_name'  => 'list',
                'rule_value' => [
                    0,
                    1,
                ]
            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);

        //资金锁判断
        $checkLock = Myredis::getRedisConn(1)->get('use_money_business' . $uid);
        if ($checkLock)
        {
            $this->failJson('资金处理中，请稍后重试');
        }

        //资金判断
        $accModel = new AccountModel();
        $money = $accModel->getAccountInfoByUserId($uid);
        $costMoney = Myredis::getRedisConn()->get('auto_invest_cost_money');
        $costMoney = !empty($costMoney) ? $costMoney : 50;
        if ($money['use_money'] < $costMoney)
        {
            $this->failJson('亲，可用余额不足，请先充值~', 6);
        }

        //自动投资服务判断
        $autoInvestAccountModel = new AutoInvestAccount();
        $accountInfo = $autoInvestAccountModel->getOne($uid);
        $date = date('Y-m-d H:i:s');
        if (empty($accountInfo))
        {
            $buyVipModel = new AutoInvestBuyVip();
            //自动投资服务信息
            $info = $buyVipModel->getOneByWhere([
                'where' => [
                    'user_id' => $uid,
                ],
                'order' => 'id desc',
                'field' => 'user_id,vip_time_end'
            ]);
            if (!empty($info) && $info['vip_time_end'] > $date)
            {
                $this->failJson('亲，您已开通此服务！');
            }

        }
        elseif ($accountInfo['auto_invest_vip_time_end'] > $date)
        {
            $this->failJson('亲，您已开通此服务！');
        }

        //金融vip信息
        $userModel = new Iuser();
        $userInfo = $userModel->getOne($uid);

        //超级会员vip信息
        $superInfo = Myredis::getRedisConn(3)->getFromHash('account_service', $uid);

        //交易密码判断
        $param['user_id'] = $uid;
        $this->_checkPayPwd($param);

        //开通vip业务处理
        Db::transaction();
        try
        {
            //资金锁
            Myredis::getRedisConn(1)->set('use_money_business' . $uid, 1, 300);
            //增加购买记录
            $autoInvestBuyVipModel = new AutoInvestBuyVip();
            $add = [
                'user_id'        => $uid,
                'vip_month'      => 1,
                'vip_time_start' => date('Y-m-d H:i:s'),
                'vip_time_end'   => date('Y-m-d 23:59:59', strtotime("+1 month")),
                'vip_buy_amount' => $costMoney,
                'create_time'    => date('Y-m-d H:i:s'),
            ];
            //金融vip有效期判断
            if (strtotime($add['vip_time_end']) > $userInfo['vip_time'])
            {
                triggleError('不在金融vip有限期内', -1);
            }
            //超级会员有效期判断
            if ($add['vip_time_end'] > $superInfo['superTimeEnd'])
            {
                triggleError('不在超级会员有限期内', -1);
            }

            $ret = $autoInvestBuyVipModel->add($add);
            if (!$ret)
            {
                triggleError('添加购买记录失败');
            }

            $up['auto_invest_vip_time_end'] = $add['vip_time_end'];
            $up['is_auto_invest_vip_auto_renew'] = $param['is_auto_renew'];
            $ret = $autoInvestAccountModel->edit($up, $uid);
            if (!$ret)
            {
                triggleError('更新账户vip过期时间失败');
            }

            //资金处理
            $accountLogicModel = new AccountLogic();
            $data['uid'] = $uid;
            $data['to_uid'] = 1;
            $data['num'] = $ret;
            $data['remark'] = "自动投资购买服务扣费";
            $data['total_change'] = -$costMoney;
            $data['use_change'] = -$costMoney;
            $data['type'] = 50001;
            $data['btype'] = 0;
            $data['addtime'] = time();
            $accountLogicModel->upChange($data);
            Myredis::getRedisConn(1)->delete('use_money_business' . $uid);
            Db::commit();

        } catch (\Exception $exception)
        {
            Db::rollback();
            Myredis::getRedisConn(1)->delete('use_money_business' . $uid);
            $msg = msg($exception);
            $returnMsg = $msg['code'] != 0 ? $msg['msg'] : '服务器出错,请联系客服处理！';
            $this->failJson($returnMsg);
        }
        $this->okJson();
    }

    /**
     * @desc 函数：自动续费状态切换
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function changeStatus()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],
            [
                'param_name' => 'trade_password',
                'label_name' => '交易密码',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'is_auto_renew',
                'label_name' => '是否自动续费',
                'param_type' => 'int',
                'is_require' => true,
                'rule_name'  => 'list',
                'rule_value' => [
                    0,
                    1,
                ]
            ],


        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);

        //检查是否已购买vip服务
        $autoInvestAccountModel = new AutoInvestAccount();
        $accountInfo = $autoInvestAccountModel->getOne($uid, "date_format(auto_invest_vip_time_end,'%Y-%m-%d %H') as date");
        $date = date('Y-m-d H');
        $dateTimeArray = getdate(time());
        $hours = $dateTimeArray['hours'];
        if (empty($accountInfo))
        {
            $buyVipModel = new AutoInvestBuyVip();
            //自动投资服务信息
            $info = $buyVipModel->getOneByWhere([
                'where' => [
                    'user_id' => $uid,
                ],
                'order' => 'id desc',
                'field' => "user_id,date_format(vip_time_end,'%Y-%m-%d %H') as date"
            ]);
            if (empty($info))
            {
                $this->failJson('您还未购买自动投资服务,暂不能设置!');
            }
            elseif ($info['date'] == $date && $hours >= 18)
            {
                $this->failJson('当前购买自动投资服务有效期结束时间，有效期最后一天的18点后不允许开启或关闭自动续费!');
            }
        }
        elseif ($accountInfo['date'] == $date && $hours >= 18)
        {
            $this->failJson('当前购买自动投资服务有效期结束时间，有效期最后一天的18点后不允许开启或关闭自动续费!');
        }
        //验证交易密码
        $param['user_id'] = $uid;
        $this->_checkPayPwd($param);

        $ret = $autoInvestAccountModel->editByWhere(['is_auto_invest_vip_auto_renew' => $param['is_auto_renew']], ['user_id' => $uid]);
        if (!$ret)
        {
            $this->failJson();
        }
        $this->okJson();
    }


    /**
     * @desc 函数：设置首页
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function getHomePageInfo()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],
            [
                'param_name' => 'current_page',
                'label_name' => '页码',
                'param_type' => 'int',
                'is_require' => false,
                'default'    => 1,
            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);


        //可用红包个数
        $countSql = "SELECT SUM(a.num) AS total FROM(SELECT 
                    COUNT(id) AS num
                FROM
                    itd_treasure_chest
                 WHERE
                    user_id={$uid}
                     AND award_type=0
                     AND status=0
                     AND remove=0
                     AND end_time>= UNIX_TIMESTAMP(NOW())
                     AND startTime <= UNIX_TIMESTAMP(NOW())
                     AND (FIND_IN_SET('6',borrowType) OR FIND_IN_SET('7',borrowType) OR FIND_IN_SET('10',borrowType) OR FIND_IN_SET('11',borrowType))
                 )a
                 ";
        $redpacketTotal = Db::query($countSql);

        //金融vip判断
        $iuserModel = new Iuser();
        $userInfo = $iuserModel->getOne($uid, 'vip_status,realname');

        //逾期判断
        $where['user_id'] = $uid;
        $where['status'] = 2;
        $sqlAttr = [
            'where' => $where,
            'field' => 'sum(repayment_account) as beoverdue'
        ];
        $repaymentModel = new IborrowRepayment();
        $repaymentInfo = $repaymentModel->getOneByWhere($sqlAttr);
        unset($where);
        $data['is_set_up'] = 0;
        $data['notset_up_msg'] = '自动投资功能暂停使用';
//        $data['is_set_up'] = 1;
//        if ($userInfo['vip_status'] != 1)
//        {
//            $data['is_set_up'] = 0;
//            $data['notset_up_msg'] = '您还不是金融vip,暂不能进行自动投资设置~';
//        }
//        elseif (!isset($userInfo['realname']))
//        {
//            $data['is_set_up'] = 0;
//            $data['notset_up_msg'] = '您还未实名认证,暂不能进行自动投资设置~';
//        }
//        if ($repaymentInfo['beoverdue'] > 0)
//        {
//            $data['is_set_up'] = 0;
//            $data['notset_up_msg'] = '您有逾期未还,暂不能进行自动投资设置~';
//        }

        //可用红包个数
        $data['red_packet_total_num'] = isset($redpacketTotal[0]['total']) ? $redpacketTotal[0]['total'] : 0;

        //可用金额
        $accountModel = new AccountModel();
        $useMoney = $accountModel->getAccountInfoByUserId($uid);
        $data['use_money'] = isset($useMoney['use_money']) ? $useMoney['use_money'] : 0;

        //红包可用金额
        $accountModel = new AccountLogic();
        $useRedpacketMoney = $accountModel->getCashControl($uid);
        $data['use_red_packet_money'] = isset($useRedpacketMoney['redpacket_money']) ? $useRedpacketMoney['redpacket_money'] : 0;

        //可设置规则数
        $setNum = Myredis::getRedisConn()->get('can_set_num');
        $setNum = empty($setNum) ? 20 : $setNum;
        $data['allow_set_num'] = $setNum;

        //已经设置规则数
        $autoInvestAccountDetailModel = new AutoInvestAccountDetail();
        $where['user_id'] = $uid;
        $where['auto_invest_amount_surplus'] = [
            '>',
            0
        ];
        $checkDetailNum = $autoInvestAccountDetailModel->getCount($where);
        $data['has_set_num'] = $checkDetailNum;

        //投资记录
        //历史记录展示条数
        $logNum = Myredis::getRedisConn()->get('auto_invest_log_num');
        $logNum = !empty($logNum) ? $logNum : 100;
        $data['invest_log'] = $this->_getInvestLog($uid, $param['current_page'], $logNum, 20, false);
        $this->okJson($data);
    }

    /**
     * @desc 函数：我的服务
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function myService()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);

        //金额vip信息
        //用户名
        $userModel = new Iuser();
        $userInfo = $userModel->getOne($uid, 'username,vip_status,vip_time');
        //头像
        $userIcon = $this->getUserIcon($uid);
        //vip等级
        $creditModel = new Credit();
        $creditInfo = $creditModel->getOne($uid, 'rank');
        $data['icon'] = $userIcon;
        $data['user_name'] = $userInfo['username'];
        $data['jr_vip'] = [
            'edate'    => date('Y-m-d', $userInfo['vip_time']),
            'vip_rank' => $creditInfo['rank']
        ];

        //超级会员信息
        $superInfo = Myredis::getRedisConn(3)->getFromHash('account_service', $uid);
        $superVip['is_renew'] = 1;
        if (empty($superInfo))
        {
            $superVip['is_vip'] = 0;
            $superVip['sdate'] = '';
            $superVip['edate'] = '';
            $superVip['is_renew'] = 0;
        }
        else
        {
            $superVip['is_vip'] = $superInfo['superMember'];
            $superVip['sdate'] = !is_null($superInfo['superTimeStart']) ? $superInfo['superTimeStart'] : '';
            $superVip['edate'] = !is_null($superInfo['superTimeEnd']) ? $superInfo['superTimeEnd'] : '';
            if ($superVip['edate'] == '')
            {
                $superVip['is_renew'] = 0;
            }
            else
            {
                $currentDate = date("Y-m-d");
                $d1 = strtotime($currentDate);
                $d2 = strtotime($superVip['edate']);
                $days = round(($d2 - $d1) / 3600 / 24);
                if ($days > 60)
                {
                    $superVip['is_renew'] = 0;
                }
            }
        }
        $data['super_vip'] = $superVip;

        //自动投资服务信息
        $autoInvestModel = new AutoInvestBuyVip();
        $sqlArr = [
            'where' => ['user_id' => $uid],
            'order' => 'id desc,create_time desc',
            'field' => 'date(vip_time_start) as sdate,date(vip_time_end) as edate'
        ];
        $autoInvestService = $autoInvestModel->getOneByWhere($sqlArr);
        $autoAccountModel = new AutoInvestAccount();
        $accountInfo = $autoAccountModel->getOne($uid);
        $autoVip['sdate'] = !empty($autoInvestService) ? $autoInvestService['sdate'] : '';
        $autoVip['edate'] = !empty($autoInvestService) ? $autoInvestService['edate'] : '';
        $autoVip['is_auto_renew'] = !empty($accountInfo) ? $accountInfo['is_auto_invest_vip_auto_renew'] : 0;
        $data['auto_invest_vip'] = $autoVip;


        //购买费用
        $costMoney = Myredis::getRedisConn()->get('auto_invest_cost_money');
        $data['cost_money'] = !empty($costMoney) ? $costMoney : 50;

        //可用金额
        $accountModel = new AccountModel();
        $useMoney = $accountModel->getAccountInfoByUserId($uid);
        $data['use_money'] = isset($useMoney['use_money']) ? $useMoney['use_money'] : 0;

        $this->okJson($data);
    }

    /**
     * @desc 函数：使用红包自动投资
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function useRedpacket()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);


        //查询可用红包
        $field = 'COUNT(id) AS num,`value`,user_constraint,FROM_UNIXTIME(end_time,\'%Y-%m-%d\') as end_time';
        $sql = "SELECT * FROM(SELECT 
                $field
            FROM
                itd_treasure_chest
             WHERE
                user_id={$uid}
                 AND award_type=0
                 AND status=0
                 AND remove=0
                 AND end_time>= UNIX_TIMESTAMP(NOW())
                 AND startTime <= UNIX_TIMESTAMP(NOW())
                  AND (FIND_IN_SET('6',borrowType) OR FIND_IN_SET('7',borrowType) OR FIND_IN_SET('10',borrowType) OR FIND_IN_SET('11',borrowType))
             GROUP BY `value`,user_constraint,end_time
             ORDER BY user_constraint/`value`,`value`,end_time)a
             ";
        $list = Db::query($sql);

        //红包可用金额
        $accountModel = new AccountLogic();
        $useRedpacketMoney = $accountModel->getCashControl($uid);
        $data['use_red_packet_money'] = isset($useRedpacketMoney['redpacket_money']) ? $useRedpacketMoney['redpacket_money'] : 0;

        //可设置红包个数
        $canSetRedNum = Myredis::getRedisConn()->get('can_set_red_num');
        $canSetRedNum = !empty($canSetRedNum) ? $canSetRedNum : 100;
        $data['allow_set_red_num'] = $canSetRedNum;

        //可设置规则数
        $setNum = Myredis::getRedisConn()->get('can_set_num');
        $setNum = empty($setNum) ? 20 : $setNum;
        $data['allow_set_num'] = $setNum;

        //已经设置规则数
        $autoInvestAccountDetailModel = new AutoInvestAccountDetail();
        $where['user_id'] = $uid;
        $where['auto_invest_amount_surplus'] = [
            '>',
            0
        ];
        $checkDetailNum = $autoInvestAccountDetailModel->getCount($where);
        $data['has_set_num'] = $checkDetailNum;

        //月份年利率
        $data['month_rate'] = config('settings.month_rate');

        $data['red_list'] = $list;
        $this->okJson($data);
    }

    /**
     * @desc 函数：不使用红包自动投资
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function noRedpacket()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);

        //可用金额
        $accountModel = new AccountModel();
        $useMoney = $accountModel->getAccountInfoByUserId($uid);
        if ($useMoney['use_money'] < 50)
        {
            $this->failJson('可用余额不足，请先充值', 6);
        }

        //可设置规则数
        $setNum = Myredis::getRedisConn()->get('can_set_num');
        $setNum = empty($setNum) ? 20 : $setNum;
        $data['allow_set_num'] = $setNum;

        //已经设置规则数
        $autoInvestAccountDetailModel = new AutoInvestAccountDetail();
        $where['user_id'] = $uid;
        $where['auto_invest_amount_surplus'] = [
            '>',
            0
        ];
        $checkDetailNum = $autoInvestAccountDetailModel->getCount($where);
        $data['has_set_num'] = $checkDetailNum;

        //可用余额
        $data['use_money'] = isset($useMoney['use_money']) ? $useMoney['use_money'] : 0;
        //月份年利率
        $data['month_rate'] = config('settings.month_rate');

        $this->okJson($data);
    }

    /**
     * @desc 函数：投资详情
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function getDetail()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],
            [
                'param_name' => 'id',
                'label_name' => 'id',
                'param_type' => 'int',
                'is_require' => true,

            ],
            [
                'param_name' => 'current_page',
                'label_name' => '页码',
                'param_type' => 'int',
                'is_require' => false,
                'default'    => 1,
            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);

        //查询账户详情
        $where['id'] = $param['id'];
        $where['user_id'] = $uid;
        $autoDetailModel = new AutoInvestAccountDetail();
        $info = $autoDetailModel->getOneByWhere([
            'where' => [
                'id'      => $param['id'],
                'user_id' => $uid,
            ],
            'field' => 'auto_invest_amount_surplus,auto_invest_amount,red_packet_total_amount,finish_time,create_time,income_actual,income_plan,is_red_packet,invest_status'
        ]);
        if (empty($info))
        {
            $this->failJson('项目不存在');
        }
        unset($where);
        $autoInvestModel = new ItemAutoInvest();
        //使用红包
        if ($info['is_red_packet'] == 1)
        {
            //列表数据
            $list = $autoDetailModel->getRedPacketDetail($param['id'], $uid, $param['current_page'], 20);
            $autoPacketModel = new AutoInvestRedpacket();
            $sqlArr = [
                'where' => [
                    'auto_invest_user_account_detail_id' => $param['id'],
                    'is_red_packet_use'                  => 0
                ],
                'field' => 'sum(red_packe_amount) as red_packe_amount_surplus'
            ];

            $redPacketInfo = $autoPacketModel->getOneByWhere($sqlArr);
            $info['red_packe_amount_surplus'] = !empty($redPacketInfo['red_packe_amount_surplus']) ? $redPacketInfo['red_packe_amount_surplus'] : 0;
        }
        //不用红包
        else
        {
            //列表数据
            $investWhere['user_id'] = $uid;
            $investWhere['auto_invest_user_account_detail_id'] = $param['id'];
            $list = $autoInvestModel->getList([
                'field'     => 'id,invest_amount,invest_income',
                'where'     => $investWhere,
                'page'      => $param['current_page'],
                'list_rows' => '20',
                'order'     => 'create_time desc'
            ], true);
        }
        $data['top'] = $info;
        $data['list'] = $list;
        $this->okJson($data);

    }

    /**
     * @desc 函数：获取历史记录
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function getLog()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],
            [
                'param_name' => 'current_page',
                'label_name' => '页码',
                'param_type' => 'int',
                'is_require' => false,
                'default'    => 1,
            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        $uid = $this->_checkLogin($param['authenticationString']);

        $canSetRedNum = Myredis::getRedisConn()->get('can_set_red_num');
        $canSetRedNum = !empty($canSetRedNum) ? $canSetRedNum : 100;
        $list = cache('autoinvest_get_log' . $param['authenticationString'] . $param['current_page'] . $canSetRedNum);
        if (empty($list))
        {
            $list = $this->_getInvestLog($uid, $param['current_page'], $canSetRedNum, 20, true);
            cache('autoinvest_get_log' . $param['authenticationString'] . $param['current_page'] . $canSetRedNum, $list);
        }

        $this->okJson($list);

    }

    /**
     * @desc 函数：获取自动投资说明
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function getExplain()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'aid',
                'label_name' => '文章id',
                'param_type' => 'int',
                'is_require' => true,
            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);
        $articleModel = new Article();
        $sqlArr = [
            'field' => 'info,title',
            'where' => [
                'status' => 1,
                'id'     => $param['aid']
            ]
        ];
        $article = cache('autoinvest_get_explain' . $param['aid']);
        if (empty($article))
        {
            $articleInfo = $articleModel->getOneByWhere($sqlArr);
            $article = htmlspecialchars_decode($articleInfo['info']);
            cache('autoinvest_get_explain' . $param['aid'], $article, 86400);
        }
        $data['info'] = $article;
        $this->okJson($data);
    }

    /**
     * @desc 函数：获取温馨提示
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function getReminder()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,
            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);
        $info = Myredis::getRedisConn()->get('auto_invest_reminder');
        $article['info'] = htmlspecialchars_decode($info);
        $this->okJson($article);
    }

    /**
     * @desc 函数：资金转入前判断（使用红包投资）
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function checkRedpacketData()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],
            [
                'param_name' => 'redpacket_list',
                'label_name' => '红包数据',
                'param_type' => 'string',
                'is_require' => true,
            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        if ($this->verifyConcurrency('moneyInto' . $param['authenticationString']))
        {
            $this->failJson('您的请求太频繁了~,请5秒后再试！');
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);
        $redpacketList = json_decode($param['redpacket_list'], true);
        if (!is_array($redpacketList) || empty($redpacketList))
        {
            $this->failJson('数据格式错误,请联系客服');
        }
        $canSetRedNum = Myredis::getRedisConn()->get('can_set_red_num');
        $canSetRedNum = !empty($canSetRedNum) ? $canSetRedNum : 100;
        //查询：可使用红包金额
        $accountModel = new AccountLogic();
        $useRedpacketMoney = $accountModel->getCashControl($uid);
        $useRedpacketMoney['money'] = isset($useRedpacketMoney['redpacket_money']) ? $useRedpacketMoney['redpacket_money'] : 0;
        if ($useRedpacketMoney['money'] <= 0)
        {
            $this->failJson('红包可用余额不足，请先充值', 6);
        }
        foreach ($redpacketList as $k => $value)
        {
            if ($k == 0)
            {
                $this->failJson('红包数据项目期限错误');
                break;
            }
            $redpacket = [];
            $redpacketNum = 0;
            $redpacketMoney = 0;
            $redpacketInvestMoney = 0;
            $redpacketCount = 0;
            $redpacketTotalAmount = 0;
            foreach ($value as $v)
            {
                if (!isset($v['value']) || !isset($v['redpacket_num']) || !isset($v['user_constraint']) || !isset($v['end_time']))
                {
                    $this->failJson('数据格式错误');
                }
                //红包面额
                $redpacketTotalAmount += $v['value'];
                //每月红包数
                $redpacketNum += $v['redpacket_num'];
                //每月红包额
                $redpacketMoney += $v['value'] * $v['redpacket_num'];
                //每月投资额
                $redpacketInvestMoney += ($v['user_constraint'] * $v['redpacket_num']);
                //每月红包数据
                $sql = "SELECT id,`value`,user_constraint as invest_amount
                                FROM
                                    itd_treasure_chest
                                 WHERE
                                    user_id={$uid}
                                     AND award_type=0
                                     AND status=0
                                     AND remove=0
                                     AND startTime <= UNIX_TIMESTAMP(NOW())
                                     AND (FIND_IN_SET('6',borrowType) OR FIND_IN_SET('7',borrowType) OR FIND_IN_SET('10',borrowType) OR FIND_IN_SET('11',borrowType))
                                     AND `value`={$v['value']}
                                     AND user_constraint={$v['user_constraint']}
                                    AND  date(FROM_UNIXTIME(end_time))='{$v['end_time']}'
                                 ORDER BY id ASC 
                                 LIMIT {$v['redpacket_num']}
                                ";
                $list = Db::query($sql);
                if ($list)
                {
                    foreach ($list as $vv)
                    {
                        array_push($redpacket, $vv);
                    }
                }
            }
            if (empty($redpacket))
            {
                $this->failJson('抱歉您选择的红包已使用或已过期，请重新选择',7);
            }
            $redpacketCount += count($redpacket);
            if ($redpacketNum != $redpacketCount)
            {
                $this->failJson('抱歉您选择的红包已使用或已过期，请重新选择',7);
            }
            if ($redpacketNum > $canSetRedNum)
            {
                $this->failJson('最多只能选择' . $canSetRedNum . '个红包哦',7);
            }
            //判断：设置金额是否大于可使用红包金额
            if ($redpacketInvestMoney > $useRedpacketMoney['money'])
            {
                $this->failJson('设置金额超出了可使用红包金额');
            }
        }
        $this->okJson();
    }


    /**
     * @desc 函数：资金设置
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @return void
     */
    public function moneyInto()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'authenticationString',
                'label_name' => '登录信息',
                'param_type' => 'string',
                'is_require' => true,

            ],
            [
                'param_name' => 'is_red_packet',
                'label_name' => '是否使用红包',
                'param_type' => 'int',
                'is_require' => true,
                'default'    => 1,
            ],
            [
                'param_name' => 'redpacket_list',
                'label_name' => '红包数据',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'trade_password',
                'label_name' => '交易密码',
                'param_type' => 'string',
                'is_require' => true,
            ],


        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        $this->failJson('自动投资功能暂停使用');
//        if ($this->verifyConcurrency('moneyInto' . $param['authenticationString']))
//        {
//            $this->failJson('您的请求太频繁了~,请5秒后再试！');
//        }
        $redpacketList = json_decode($param['redpacket_list'], true);
        if (!is_array($redpacketList))
        {
            $this->failJson('红包数据错误');
        }
        foreach ($redpacketList as $k => $v)
        {
            if ($k == 0)
            {
                $this->failJson('红包数据项目期限错误');
                break;
            }
        }
        //登录验证
        $uid = $this->_checkLogin($param['authenticationString']);

        //金融vip判断
        $iuserModel = new Iuser();
        $userInfo = $iuserModel->getOne($uid, 'vip_status,realname,paypassword,username,phone');

        //逾期判断
        $where['user_id'] = $uid;
        $where['status'] = 2;
        $sqlAttr = [
            'where' => $where,
            'field' => 'sum(repayment_account) as beoverdue'
        ];
        $repaymentModel = new IborrowRepayment();
        $repaymentInfo = $repaymentModel->getOneByWhere($sqlAttr);
        unset($where);
        if ($userInfo['vip_status'] != 1)
        {
            $this->failJson('您还不是金融vip,暂不能进行自动投资设置~');
        }
        elseif (!isset($userInfo['realname']))
        {
            $this->failJson('您还未实名认证,暂不能进行自动投资设置~');
        }
        if ($repaymentInfo['beoverdue'] > 0)
        {
            $this->failJson('您有逾期未还,暂不能进行自动投资设置~');
        }

        //可用金额
        $accountModel = new AccountModel();
        $userMoney = $accountModel->getAccountInfoByUserId($uid);

        //查询：可使用红包金额
        $accountModel = new AccountLogic();
        $useRedpacketMoney = $accountModel->getCashControl($uid);
        $controlMoneyArr = $useRedpacketMoney;//用于写资金区分的缓存，另外复制的一个变量
        $useRedpacketMoney['money'] = isset($useRedpacketMoney['redpacket_money']) ? $useRedpacketMoney['redpacket_money'] : 0;
        if ($param['is_red_packet'] == 1)
        {
            if ($useRedpacketMoney['money'] <= 0)
            {
                $this->failJson('红包可用余额不足，请先充值', 6);
            }
        }
        else
        {
            if ($userMoney['use_money'] < 50)
            {
                $this->failJson('可用余额不足，请先充值', 6);
            }
        }


        //最大设置条数验证
        $autoInvestAccountDetailModel = new AutoInvestAccountDetail();
        $where['user_id'] = $uid;
        $where['auto_invest_amount_surplus'] = [
            '>',
            0
        ];
        $checkDetailNum = $autoInvestAccountDetailModel->getCount($where);
        $setNum = Myredis::getRedisConn()->get('can_set_num');
        $setNum = empty($setNum) ? 50 : $setNum;
        if ($checkDetailNum > $setNum)
        {
            $this->failJson('超出自动投资可设置条数,暂不能设置!');
        }
        elseif ((count($redpacketList) + $checkDetailNum) > $setNum)
        {
            $this->failJson('超出自动投资可设置条数,暂不能设置!');
        }
        unset($where);

        //交易密码验证
        $param['user_id'] = $uid;
        $this->_checkPayPwd($param);
       // $iuserModel = new Iuser();
      //  $userInfo = $iuserModel->getOne($uid, 'paypassword,username,realname,phone');

        //并发锁验证
        $redis = new RedisLock(1);
        $lock = $redis->lock('auto_invest_money_info' . $uid);
        if (!$lock)
        {
            $this->failJson('上一次自动投资设置尚未结束，本次被忽略');
        }
        $checkLock = $redis->lock('use_money_business' . $uid);
        if (!$checkLock)
        {
            $this->failJson('资金处理中，请稍后重试');
        }

        $autoInvestAccountModel = new AutoInvestAccount();
        $accountInfo = $autoInvestAccountModel->getOne($uid);
        if (empty($accountInfo))
        {
            $this->_addAccount(['user_id' => $uid]);
        }
//        if (empty($accountInfo))
//        {
//            $buyVipModel = new AutoInvestBuyVip();
//            //自动投资服务信息
//            $info = $buyVipModel->getOneByWhere([
//                'where' => [
//                    'user_id' => $uid,
//                ],
//                'order' => 'id desc',
//                'field' => 'user_id,vip_time_end'
//            ]);
//            if (empty($info))
//            {
//                $this->failJson('您还未购买服务,暂不能设置!', 2);
//            }
//            elseif (time() > strtotime($info['vip_time_end']))
//            {
//                $this->failJson('您的服务已过期,暂不能设置!', 2);
//            }
//        }
//        elseif (time() > strtotime($accountInfo['auto_invest_vip_time_end']))
//        {
//            $this->failJson('您的服务已过期,暂不能设置!', 2);
//        }


        $monthRate = config('settings.month_rate');

        $autoInvestRedpacketModel = new AutoInvestRedpacket();
        $treasureChestModel = new TreasureChest();
//        Myredis::getRedisConn(1)->setToHash('auto_invest_money_info', $uid, 1);
//        Myredis::getRedisConn(1)->set('use_money_business' . $uid, 1);
        $canSetRedNum = Myredis::getRedisConn()->get('can_set_red_num');
        $canSetRedNum = !empty($canSetRedNum) ? $canSetRedNum : 100;

        $investAwardModel = new ActivityInvestAwardItem();
        $activityInfo = $investAwardModel->getActivityInvestAwardItemInfo(1);
        Db::startTrans();
        try
        {
            //查询：自动投资账户信息
            //使用红包
            if ($param['is_red_packet'] == 1)
            {
                //红包数据处理
                foreach ($redpacketList as $key => $value)
                {
                    $redpacket = [];
                    $redpacketNum = 0;
                    $redpacketMoney = 0;
                    $redpacketInvestMoney = 0;
                    $redpacketCount = 0;
                    $redpacketTotalAmount = 0;
                    foreach ($value as $v)
                    {
                        if (!isset($v['value']) || !isset($v['redpacket_num']) || !isset($v['user_constraint']) || !isset($v['end_time']))
                        {
                            triggleError('数据格式错误');
                        }
                        //红包面额
                        $redpacketTotalAmount += $v['value'];
                        //每月红包数
                        $redpacketNum += $v['redpacket_num'];
                        //每月红包额
                        $redpacketMoney += $v['value'] * $v['redpacket_num'];
                        //每月投资额
                        $redpacketInvestMoney += ($v['user_constraint'] * $v['redpacket_num']);
                        //每月红包数据
                        $sql = "SELECT id,`value`,user_constraint as invest_amount
                                FROM
                                    itd_treasure_chest
                                 WHERE
                                    user_id={$uid}
                                     AND award_type=0
                                     AND status=0
                                     AND remove=0
                                     AND startTime <= UNIX_TIMESTAMP(NOW())
                                     AND (FIND_IN_SET('6',borrowType) OR FIND_IN_SET('7',borrowType) OR FIND_IN_SET('10',borrowType) OR FIND_IN_SET('11',borrowType))
                                     AND `value`={$v['value']}
                                     AND user_constraint={$v['user_constraint']}
                                    AND  date(FROM_UNIXTIME(end_time))='{$v['end_time']}'
                                 ORDER BY id ASC 
                                 LIMIT {$v['redpacket_num']}
                                ";
                        $list = Db::query($sql);
                        if ($list)
                        {
                            foreach ($list as $vv)
                            {
                                array_push($redpacket, $vv);
                            }
                        }
                    }

                    //账户总表

                    $params['user_id'] = $uid;
                    $params['invest_amount'] = $redpacketInvestMoney;
                    $params['invest_amount_surplus'] = $redpacketInvestMoney;
                    $retAccount = $this->_updateAccount($params, true);
                    if (!$retAccount)
                    {
                        triggleError('更新账户表失败');
                    }
                    unset($params);

                    //账户详情表
                    $params['user_id'] = $uid;
                    $params['invest_money'] = $redpacketInvestMoney;
                    $params['rate'] = $monthRate[$key];
                    $params['month'] = $key;
                    $params['user_name'] = $userInfo['username'];
                    $params['real_name'] = $userInfo['realname'];
                    $params['phone'] = $userInfo['phone'];
                    $params['is_red_packet'] = $param['is_red_packet'];
                    $params['red_packet_count'] = $redpacketNum;
                    $params['red_packet_total_amount'] = $redpacketMoney;
                    $id = $this->_addAccountDetail($params);
                    unset($params);

                    $redpacketCount += count($redpacket);
                    if ($redpacketNum != $redpacketCount)
                    {
                        triggleError('抱歉您选择的红包已使用或已过期，请重新选择',-1);
                    }
                    if ($redpacketNum > $canSetRedNum)
                    {
                        triggleError('最多只能选择' . $canSetRedNum . '个红包哦', -1);
                    }
                    unset($redpacketNum);
                    unset($redpacketCount);

                    if (!$id)
                    {
                        triggleError('添加账户详情失败!');
                    }

                    //红包数据更新
                    if ($redpacket)
                    {
                        foreach ($redpacket as $v)
                        {
                            //更新红包为已使用
                            $ret = $treasureChestModel->edit([
                                'status'      => 2,
                                'modify_time' => time()
                            ], $v['id']);
                            if (!$ret)
                            {
                                triggleError('更新百宝箱失败');
                            }
                            $costArray = interest($v['invest_amount'], $monthRate[$key], $key, 0, '', 1);
                            //新增账户红包明细
                            $add['auto_invest_user_account_detail_id'] = $id;
                            $add['red_packet_id'] = $v['id'];
                            $add['red_packe_amount'] = $v['value'];
                            $add['red_packe_invest_amount_min'] = $v['invest_amount'];
                            $add['is_red_packet_use'] = 0;
                            $add['invest_income'] = $costArray['total_interest'];
                            $res = $autoInvestRedpacketModel->add($add);
                            if (!$res)
                            {
                                triggleError('添加用户账户明细之红包失败');
                            }
                        }
                        unset($redpacket);
                    }
                    else
                    {
                        triggleError('抱歉您选择的红包已使用或已过期，请重新选择',-1);
                    }
                    //判断：设置金额是否大于可使用红包金额
                    if ($redpacketInvestMoney > $useRedpacketMoney['money'])
                    {
                        triggleError('设置金额超出了可使用红包金额', -1);
                    }

                    //资金记录
                    $params['user_id'] = $uid;
                    $params['num'] = $id;
                    $params['red_money'] = $redpacketMoney;
                    $params['invest_money'] = $redpacketInvestMoney;
                    $params['useRedpacketMoney'] = $useRedpacketMoney;
                    $this->_moneyHandle($params, true, $controlMoneyArr);
                    unset($params);

                    unset($redpacketInvestMoney);
                    unset($redpacketMoney);

                }
            }
            else
            {
                $noredpacketInvestMoney = 0;
                //红包数据处理
                foreach ($redpacketList as $key => $value)
                {
                    if (!isset($value['value']))
                    {
                        triggleError('数据格式错误');
                    }
                    //投资总额
                    $noredpacketInvestMoney += $value['value'];
                    if ($value['value'] < 50)
                    {
                        triggleError('最低投资金额为50元！', -1);
                    }
                }
                //判断：设置金额是否大于可使用红包金额
                if ($noredpacketInvestMoney > $userMoney['use_money'])
                {
                    triggleError('设置金额超出了可用金额', -1);
                }

                foreach ($redpacketList as $key => $value)
                {
                    //账户总表
                    $params['user_id'] = $uid;
                    $params['invest_amount'] = $value['value'];
                    $params['invest_amount_surplus'] = $value['value'];
                    $ret = $this->_updateAccount($params, false);
                    if (!$ret)
                    {
                        triggleError('更新账户表失败');
                    }
                    unset($params);
                    $rebateOpen = !empty($activityInfo) ? 1 : 0;
                    //账户详情表
                    $params['user_id'] = $uid;
                    $params['invest_money'] = $value['value'];
                    $params['rate'] = $monthRate[$key];
                    $params['month'] = $key;
                    $params['user_name'] = $userInfo['username'];
                    $params['real_name'] = $userInfo['realname'];
                    $params['phone'] = $userInfo['phone'];
                    $params['is_red_packet'] = $param['is_red_packet'];
                    $params['red_packet_count'] = 0;
                    $params['red_packet_total_amount'] = 0;
                    $params['is_enable_invest_award'] = $rebateOpen;
                    $id = $this->_addAccountDetail($params);
                    unset($params);
                    if (!$id)
                    {
                        triggleError('添加账户详情失败!');
                    }

                    //资金记录
                    $params['user_id'] = $uid;
                    $params['num'] = $id;
                    $params['invest_money'] = $value['value'];
                    $params['rebateOpen'] = $rebateOpen;
                    $this->_moneyHandle($params, false, $controlMoneyArr);
                    unset($params);
                    unset($value);
                }

            }

        } catch (\Exception $exception)
        {
            Db::rollback();
            $redis->unLock('auto_invest_money_info' . $uid);
            $redis->unLock('use_money_business' . $uid);
            $msg = msg($exception);
            $returnMsg = $msg['code'] != 0 ? $msg['msg'] : '服务器出错,请联系客服处理！';
            $this->failJson($returnMsg);
           // $this->failJson($msg['msg']);
        }
        Db::commit();
        $redis->unLock('auto_invest_money_info' . $uid);
        $redis->unLock('use_money_business' . $uid);
        $this->okJson();

    }

    /**
     * @desc 函数：验证交易密码
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @param array $params
     * @return bool
     */
    private function _checkPayPwd($params = [])
    {
        //交易密码判断
        $xxtea = new \Xxtea();
        $payPassword = $xxtea->decrypt(base64_decode($params['trade_password']), md5('itbt'));
        $iuserModel = new Iuser();
        $userInfo = $iuserModel->getOne($params['user_id'], 'paypassword,username,realname,phone');
        $key = 'auto_invest_buy_vip_' . $params['user_id'];
        $errNum = cache($key);
        if ($userInfo['paypassword'] != md5($payPassword))
        {
            if ($errNum >= 5)
            {
                $this->failJson("错误次数超过5次，请2小时后再设置");
            }
            else
            {
                cache($key, $errNum + 1, 60 * 60 * 2);
                $this->failJson('交易密码错误');
            }
        }
        else
        {
            if ($errNum >= 5)
            {
                $this->failJson("错误次数超过5次，请2小时后再设置");
            }
            else
            {
                cache($key, null);
                return true;
            }

        }
    }

    /**
     * @desc 函数：新增账户详情
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @param array $params
     * @return bool
     */
    private function _addAccountDetail($params = [])
    {
        //新增账户资金详情日志-每月
        $autoInvestAccountDetailModel = new AutoInvestAccountDetail();
        $costArray = interest($params['invest_money'], $params['rate'], $params['month'], 0, '', 1);
        $add['user_id'] = $params['user_id'];
        $add['user_name'] = $params['user_name'];
        $add['user_real_name'] = $params['real_name'];
        $add['mobile'] = $params['phone'];
        $add['auto_invest_amount'] = $params['invest_money'];
        $add['auto_invest_amount_surplus'] = $params['invest_money'];
        $add['is_red_packet'] = $params['is_red_packet'];
        $add['red_packet_count'] = $params['red_packet_count'];
        $add['auto_invest_item_term_month'] = $params['month'];
        $add['income_plan'] = $costArray['total_interest'];
        $add['income_actual'] = 0;
        $add['invest_status'] = 0;
        $add['red_packet_total_amount'] = $params['red_packet_total_amount'];
        $add['is_enable_invest_award'] = isset($params['is_enable_invest_award']) ? $params['is_enable_invest_award'] : 0;
        $day = Myredis::getRedisConn()->get('auto_account_monitor_day');
        $day = !empty($day) ? $day + 1 : 4;
        $add['finish_time'] = date('Y-m-d 23:59:59', strtotime("+{$day} days"));
        $id = $autoInvestAccountDetailModel->add($add);
        unset($add);
        return $id;
    }

    /**
     * @desc 函数：新增账户总表
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @param array $params
     * @param bool $isRedpacket
     * @return bool
     */
    private function _addAccount($params = [], $isRedpacket = true)
    {
        //新增账户资金详情日志-每月
        $autoInvestAccountModel = new AutoInvestAccount();
        $buyVipModel = new AutoInvestBuyVip();
        //自动投资服务信息
        $info = $buyVipModel->getOneByWhere([
            'where' => [
                'user_id' => $params['user_id'],
            ],
            'order' => 'id desc',
            'field' => 'user_id,vip_time_end'
        ]);
        if (!empty($info))
        {
            $add['auto_invest_vip_time_end'] = $info['vip_time_end'];
        }
        $add['user_id'] = $params['user_id'];
        $id = $autoInvestAccountModel->add($add);
        unset($add);
        return $id;
    }

    /**
     * @desc 函数：资金记录
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @param array $params
     * @param bool $isRedpacket
     * @return bool
     */
    private function _moneyHandle($params = [], $isRedpacket = true, &$controlMoneyArr)
    {
        $accountLogicModel = new AccountLogic();
        $time = time();
        $type = 204;
        $remark = '自动投资不用红包资金设置';
        if ($isRedpacket)
        {
            //红包充值
            $data['uid'] = $params['user_id'];
            $data['to_uid'] = 1;
            $data['num'] = $params['num'];
            $data['remark'] = "自动投资红包充值";
            $data['total_change'] = $params['red_money'];
            $data['use_change'] = $params['red_money'];
            $data['type'] = 552;
            $data['btype'] = 0;
            $data['addtime'] = $time;
            $accountLogicModel->upChange($data);
            unset($data);
            $type = 202;
            $remark = '自动投资使用红包资金设置';
        }
        //资金转入
        $data['uid'] = $params['user_id'];
        $data['to_uid'] = 1;
        $data['num'] = $params['num'];
        $data['remark'] = $remark;
        $data['use_change'] = -$params['invest_money'];
        $data['nouse_change'] = $params['invest_money'];
        $data['type'] = $type;
        $data['btype'] = 0;
        $data['addtime'] = $time;
        //把已减掉的可投红包金额中的属性区分，并且写入redis缓存
        if ($isRedpacket)
        {
            //红包投资
            $this->_setMoneyToRedis($params['user_id'], $params['invest_money'], $controlMoneyArr, $params['num']);
        }
        else
        {

            //计算本次设置金额中包含了多少的可用于投红包的金额以及资金属性区分
            $investMoneyTypeArr = $this->_autoInvestNoRedpacketFundType($params['invest_money'], $controlMoneyArr, $params['rebateOpen']);
            //非红包投资
            $this->_setMoneyToRedisNoRedpacket($params['user_id'], $investMoneyTypeArr['moneyArr'], $params['num']);
            $data['treasure_chest'] = $investMoneyTypeArr['agreementAmount'];//受协议控制的金额
        }
        $accountLogicModel->upChange($data);
        return true;
    }

    /**
     * @desc 函数：更新账户总表
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @param array $params
     * @param bool $isRedpacket
     * @return bool
     */
    private function _updateAccount($params = [], $isRedpacket = true)
    {
        //新增账户资金详情日志-每月
        $autoInvestAccountModel = new AutoInvestAccount();
        //查询：自动投资账户信息
        if ($isRedpacket)
        {
            $up = [
                'auto_invest_amount_use_red_packet'         => [
                    'exp',
                    'auto_invest_amount_use_red_packet + ' . $params['invest_amount']
                ],
                'auto_invest_amount_surplus_use_red_packet' => [
                    'exp',
                    'auto_invest_amount_surplus_use_red_packet + ' . $params['invest_amount_surplus']
                ]
            ];
//            $up['auto_invest_amount_use_red_packet'] = $params['invest_amount'];
//            $up['auto_invest_amount_surplus_use_red_packet'] = $params['invest_amount_surplus'];
        }
        else
        {
            $up = [
                'auto_invest_amount_no_red_packet'         => [
                    'exp',
                    'auto_invest_amount_no_red_packet + ' . $params['invest_amount']
                ],
                'auto_invest_amount_surplus_no_red_packet' => [
                    'exp',
                    'auto_invest_amount_surplus_no_red_packet + ' . $params['invest_amount_surplus']
                ]
            ];
//            $up['auto_invest_amount_no_red_packet'] = $params['invest_amount'];
//            $up['auto_invest_amount_surplus_no_red_packet'] = $params['invest_amount_surplus'];
        }
        $ret = $autoInvestAccountModel->edit($up, $params['user_id']);
        unset($up);
        return $ret;
    }

    /**
     * @desc 函数：检查登录
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @param string $loginStr
     * @return mixed|string
     */
    private function _checkLogin($loginStr = '')
    {
        $uid = $this->parseAppLoginInfo($loginStr);
        if (!$uid)
        {
            $this->failJson('亲,您还未登录!', -1);
        }
        return $uid;
    }

    /**
     * @desc 函数：获取投资记录
     * @author liuj
     * @update 2017-12-1
     * @access public
     * @param $uid
     * @return array
     */
    private function _getInvestLog($uid, $page, $total = 100, $nums = 20, $isHistory = false)
    {
        //投资记录
        $model = new AutoInvestAccountDetail();
        $where['user_id'] = $uid;
        if ($isHistory)
        {
            $where['invest_status'] = 1;

        }

        $count = $model->getCount($where);
        $total = $total >= $count ? $count : $total;
        $lastpage = ceil($total / $nums);
        $limit = get_limit($page, $nums);
        $data['total'] = $total;
        $data['current_page'] = $page;
        $data['per_page'] = $page - 1;
        $data['last_page'] = $lastpage;

        if ($page > $lastpage)
        {
            $data['list'] = [];
            return $data;
        }
        elseif ($page == $lastpage)
        {
            $offset = ($page - 1) * $nums;
            $nums = $total % $nums == 0 ? $nums : $total % $nums;
            $limit = $offset . ',' . $nums;
        }
        $sqlArr = [
            'where' => $where,
            'field' => 'id,create_time,auto_invest_item_term_month,finish_time,red_packet_total_amount,auto_invest_amount,income_actual,income_plan,invest_status',
            'order' => 'invest_status asc,create_time desc',
            'limit' => $limit,
        ];
        $list = $model->getList($sqlArr);
        $data['list'] = $list;

        return $data;

    }

    /**
     * 把设置的自动投资的金额区分属性后，写入缓存，有效期6天
     * 因为自动投资最长时间为5天，过了三天还没投完的情况下，后台便会一直发标进行融资，确保用户的金额投完
     * @param type $userId 用户ID
     * @param type $investMoney 设置用于投红包的金额
     * @param type &$controlMoneyArr 传址：多条自动规则设置时，需要自动改变最初获取到的协议金额的值
     * @param type $autoInvestSettingId 自动投资设置表itd_item_auto_invest_user_account_detail对应的ID
     * @author lingyq
     * @date  2017-10-16
     */
    private function _setMoneyToRedis($userId, $investMoney, &$controlMoneyArr, $autoInvestSettingId)
    {
        $newRechargeMoney = $controlMoneyArr['new_recharge_money']; //新充值
        $miaoBackMoney = $controlMoneyArr['miao_back_money']; //秒回
        $withdrawalMoney = $controlMoneyArr['withdrawal_money']; //可提现
        $needMoney = $investMoney;
        $moneyArr = array(
            'new_recharge_money' => 0,
            'miao_back_money'    => 0,
            'withdrawal_money'   => 0
        );
        if ($newRechargeMoney > 0)
        {
            $moneyArr['new_recharge_money'] = $newRechargeMoney > $investMoney ? $investMoney : $newRechargeMoney;
            $needMoney = $needMoney - $moneyArr['new_recharge_money'];
            //自动改变协议金额的值，用于兼容多条自动规则设置资金属性区分缓存值计算
            $controlMoneyArr['new_recharge_money'] = $newRechargeMoney - $moneyArr['new_recharge_money'];
        }
        if ($miaoBackMoney > 0 && $needMoney > 0)
        {
            $moneyArr['miao_back_money'] = $miaoBackMoney > $needMoney ? $needMoney : $miaoBackMoney;
            $needMoney = $needMoney - $moneyArr['miao_back_money'];
            //自动改变协议金额的值，用于兼容多条自动规则设置资金属性区分缓存值计算
            $controlMoneyArr['miao_back_money'] = $miaoBackMoney - $moneyArr['miao_back_money'];
        }
        if ($withdrawalMoney > 0 && $needMoney > 0)
        {
            $moneyArr['withdrawal_money'] = $withdrawalMoney > $needMoney ? $needMoney : $withdrawalMoney;
            //自动改变协议金额的值，用于兼容多条自动规则设置资金属性区分缓存值计算
            $controlMoneyArr['withdrawal_money'] = $withdrawalMoney - $moneyArr['withdrawal_money'];
        }
        $moneyArr['new_recharge_money'] = format_num($moneyArr['new_recharge_money'], 4);
        $moneyArr['miao_back_money'] = format_num($moneyArr['miao_back_money'], 4);
        $moneyArr['withdrawal_money'] = format_num($moneyArr['withdrawal_money'], 4);
        $key = "auto_invest_money_" . $userId . '_' . $autoInvestSettingId;
        Myredis::getRedisConn()->setToHash($key, 'invest_money', $moneyArr);
        Myredis::getRedisConn()->expire($key, 86400 * 6);
    }

    /**
     * 把非红包投资的自动投资的金额区分属性后，写入缓存，有效期6天
     * 因为自动投资最长时间为5天，过了三天还没投完的情况下，后台便会一直发标进行融资，确保用户的金额投完
     * @param type $userId 用户ID
     * @param type $moneyArr 非红包自动投资设置金额类型数组
     * @param type $autoInvestSettingId 自动投资设置表itd_item_auto_invest_user_account_detail对应的ID
     * @author lingyq
     * @date  2017-12-5
     */
    private function _setMoneyToRedisNoRedpacket($userId, $moneyArr, $autoInvestSettingId)
    {
        $key = "auto_no_repacket_invest_money_" . $userId . '_' . $autoInvestSettingId;
        Myredis::getRedisConn()->setToHash($key, 'invest_money', $moneyArr);
        Myredis::getRedisConn()->expire($key, 86400 * 6);
    }

    /**
     * 非红包自动投资资金属性区分
     * @param type $investMoney
     * @param type &$controlMoneyArr 传址：多条自动规则设置时，需要自动改变最初获取到的协议金额的值
     * @param type $rebateOpen
     * @return type
     */
    private function _autoInvestNoRedpacketFundType($investMoney, &$controlMoneyArr, $rebateOpen)
    {
        $newRechargeMoney = $controlMoneyArr['new_recharge_money']; //新充值
        $miaoBackMoney = $controlMoneyArr['miao_back_money']; //秒回
        $withdrawalMoney = $controlMoneyArr['withdrawal_money']; //可提现
        $rongMoney = $controlMoneyArr['rong_money']; //融资金额
        $needMoney = $investMoney;
        $moneyArr = array(
            'new_recharge_money' => 0,
            'miao_back_money'    => 0,
            'withdrawal_money'   => 0,
            'rong_money'         => 0,
        );
        if ($newRechargeMoney > 0)
        {
            $moneyArr['new_recharge_money'] = $newRechargeMoney > $investMoney ? $investMoney : $newRechargeMoney;
            $needMoney = $needMoney - $moneyArr['new_recharge_money'];
            //自动改变协议金额的值，用于兼容多条自动规则设置资金属性区分缓存值计算
            $controlMoneyArr['new_recharge_money'] = $newRechargeMoney - $moneyArr['new_recharge_money'];
        }
        if ($miaoBackMoney > 0 && $needMoney > 0)
        {
            $moneyArr['miao_back_money'] = $miaoBackMoney > $needMoney ? $needMoney : $miaoBackMoney;
            $needMoney = $needMoney - $moneyArr['miao_back_money'];
            //自动改变协议金额的值，用于兼容多条自动规则设置资金属性区分缓存值计算
            $controlMoneyArr['miao_back_money'] = $miaoBackMoney - $moneyArr['miao_back_money'];
        }
        if ($rebateOpen == 1)
        {//开启投资返利：先用可提现，再使用融资金额
            if ($withdrawalMoney > 0 && $needMoney > 0)
            {
                $moneyArr['withdrawal_money'] = $withdrawalMoney > $needMoney ? $needMoney : $withdrawalMoney;
                $needMoney = $needMoney - $moneyArr['withdrawal_money'];
                //自动改变协议金额的值，用于兼容多条自动规则设置资金属性区分缓存值计算
                $controlMoneyArr['withdrawal_money'] = $withdrawalMoney - $moneyArr['withdrawal_money'];
            }
            if ($rongMoney > 0 && $needMoney > 0)
            {
                $moneyArr['rong_money'] = $rongMoney > $needMoney ? $needMoney : $rongMoney;
                //自动改变协议金额的值，用于兼容多条自动规则设置资金属性区分缓存值计算
                $controlMoneyArr['rong_money'] = $rongMoney - $moneyArr['rong_money'];
            }
        }
        else
        {//不开启投资返利：先使用融资金额，再使用可提现
            if ($rongMoney > 0 && $needMoney > 0)
            {
                $moneyArr['rong_money'] = $rongMoney > $needMoney ? $needMoney : $rongMoney;
                $needMoney = $needMoney - $moneyArr['rong_money'];
                //自动改变协议金额的值，用于兼容多条自动规则设置资金属性区分缓存值计算
                $controlMoneyArr['rong_money'] = $rongMoney - $moneyArr['rong_money'];
            }
            if ($withdrawalMoney > 0 && $needMoney > 0)
            {
                $moneyArr['withdrawal_money'] = $withdrawalMoney > $needMoney ? $needMoney : $withdrawalMoney;
                //自动改变协议金额的值，用于兼容多条自动规则设置资金属性区分缓存值计算
                $controlMoneyArr['withdrawal_money'] = $withdrawalMoney - $moneyArr['withdrawal_money'];
            }
        }
        //设置中需要使用的新充值、秒回金额、可提现
        $agreementAmount = $moneyArr['new_recharge_money'] + $moneyArr['miao_back_money'] + $moneyArr['withdrawal_money'];
        $moneyArr['new_recharge_money'] = format_num($moneyArr['new_recharge_money'], 4);
        $moneyArr['miao_back_money'] = format_num($moneyArr['miao_back_money'], 4);
        $moneyArr['withdrawal_money'] = format_num($moneyArr['withdrawal_money'], 4);
        $moneyArr['rong_money'] = format_num($moneyArr['rong_money'], 4);
        return array(
            'agreementAmount' => $agreementAmount,
            'moneyArr'        => $moneyArr
        );
    }
}