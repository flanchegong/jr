<?php

/**
 * @Copyright (C), 2017, pandelin
 * @Name Invite.php
 * @Author pandelin
 * @Version stable 1.0
 * @Date 2017-6-27
 * @Description 投资奖励
 * @Class List
 *    1.
 * @Function List
 *    1.
 * @History
 * <author>  <time>              <version>    <desc>
 *  pandelin   2017-6-27          stable 1.0     第一次建立该文件
 */
namespace application\common\logic\InviteAward;

use think\Db;
use think\Log;
use application\common\model\user\Iuser;
use application\common\logic\system\Task;
use application\common\model\activity\UserAppre;
use application\common\model\activity\AwardRule;
use application\common\model\activity\AwardParam;
use application\common\model\account\AccountModel;
use application\common\logic\account\AccountLogic;
use application\common\logic\system\AppPushMessage;
use application\common\model\activity\Appreciation;
use application\common\model\activity\TreasureChest;
use application\common\model\activity\UserAppreRule;
use application\common\model\activity\UserAppreList;
use application\common\model\activity\AwardParamItem;
use application\common\model\investAward\ActivityInvestAwardPay;
use application\common\model\investAward\ActivityInvestAwardRule;
use application\common\model\investAward\ActivityInvestAwardItem;
use application\common\model\investAward\ActivityInvestAwardRuleCash;
use application\common\model\investAward\ActivityInvestAwardBlacklist;
use application\common\model\investAward\ActivityInvestAwardWhitelist;
use application\common\model\investAward\ActivityInvestAwardInvestItem;
use application\common\model\investAward\ActivityInvestAwardPayRedPacket;
use application\common\model\investAward\ActivityInvestAwardPromotionSet;
use application\common\model\investAward\ActivityInvestAwardRuleRedPacket;
use application\common\model\investAward\ActivityInvestAwardRuleAddValueTicket;
use application\common\model\investAward\ActivityInvestAwardRuleExchangeTicket;

class InviteAward
{

    /**
     * 供应链:对应itd_iborrow.borrow_type ：['1' => '供应链','9' => '供应链金融']
     * $var array
     * @access private
     * * */
    private $supply = [1, 9]; //供应链
    /**
     * 资产:对应itd_iborrow.borrow_type ：['6' => '资产1号', '7' => '资产2号', '10' => '资产4号', '11' => '资产3号']
     * $var array
     * @access private
     * * */
    private $asset  = [6, 7, 10, 11];

    /**
     * 投资奖励，投标添加日志
     * @param array $data 投标数组
     * @param int $redPacketMoney 红包金额
     * @param int $tenderMoney 投标实际金额
     * * */
    public function insertInviteAwardLog($InviteAwardActivityInfo,$data, $redPacketMoney=0, $tenderMoney)
    {
        if (!$InviteAwardActivityInfo)
        {
            return TRUE;
        }
        //取得标类型
        $itemType = self::getInvesItemTypeBig($data);

        if (!$itemType)
        {
            return TRUE;
        }
        if($itemType==2 || $itemType==1 ){
            //判断投资奖励-资产奖励开关是否开启【1：开启，0：关闭】
            if(!Db::name('variable')->where(array('key'=>'SYS_INVEST_REWARD'))->value('value')){
                return TRUE;
            }
        }
        //判断是否开启黑白名单:黑白名单选择【0：不启用黑白名单（默认）；1：黑名单；2：白名单】
        if ($InviteAwardActivityInfo['black_white_list_choose'] > 0)
        {
            //黑名单
            $ActivityInvestAwardBlacklist    = new ActivityInvestAwardBlacklist();
            //白名单
            $ActivityInvestAwardWhitelist    = new ActivityInvestAwardWhitelist();
            //渠道黑白名单
            $ActivityInvestAwardPromotionSet = new ActivityInvestAwardPromotionSet();

            if ($InviteAwardActivityInfo['black_white_list_choose'] == 1)
            {
                if ($ActivityInvestAwardBlacklist->checkUserBlackList($data['tuserid'], $InviteAwardActivityInfo['id']))
                {
                    return TRUE;
                }
                if ($ActivityInvestAwardPromotionSet->checkPromotionSetList($data['tuserid'], $InviteAwardActivityInfo['id']))
                {
                    return TRUE;
                }
            }
            elseif ($InviteAwardActivityInfo['black_white_list_choose'] == 2)
            {
                if (!$ActivityInvestAwardWhitelist->checkUserWhitelist($data['tuserid'], $InviteAwardActivityInfo['id']))
                {
                    return TRUE;
                }
                if (!$ActivityInvestAwardPromotionSet->checkPromotionSetList($data['tuserid'], $InviteAwardActivityInfo['id']))
                {
                    return TRUE;
                }
            }
            else
            {
                return TRUE;
            }
        }

     
        $dataLog                       = [
            'user_id'                          => $data['tuserid'],
            'invest_award_item_id'             => $InviteAwardActivityInfo['id'],
            'invest_item_id'                   => $data['id'],
            'inves_item_type_big'              => $itemType,
            'invest_amount'                    => $tenderMoney,
            'can_use_red_packet_invest_amount' => $redPacketMoney,
            'create_time'                      => date("Y-m-d H:i:s")
        ];
        $ActivityInvestAwardInvestItem = new ActivityInvestAwardInvestItem();
        return $ActivityInvestAwardInvestItem->insertActivityInvestAwardInvestItem($dataLog);
    }

    /**
     * 取得标的大类型
     * @param array $data 投标数组
     * * */
    private function getInvesItemTypeBig($data)
    {
        if (isset($data['item_type']) && $data['item_type'] == 3)
        {
            if ($data['crowdfunding_type'] == 3)
            {
                return 4; //4：众筹(收益型)
            } 
            //3:众筹
            return 3;
        }
        else
        {
            if (in_array($data['borrow_type'], $this->supply))
            {
                //1:供应链
                return 1;
            }
            elseif (in_array($data['borrow_type'], $this->asset))
            {
                //2：资产
                return 2;
            }
            else
            {
                Log::write(sprintf("[投资奖励]时间:%s,错误信息:%s,", date('Y-m-d H:i:s'), $data['name'] . '：标不在奖励范围内'), 'info');
                return FALSE;
            }
        }
    }

    /**
     * 返回用户红包金额
     * @param int $userId 用户ID
     * @param int $tenderMoney 用户实际投资金额
     * * */
    public function getInvesAwardRedPacketMoney($userId, $tenderMoney)
    {
        $AccountModel = new AccountModel();
        $CashControl  = $AccountModel->getCashControl($userId);
        if (!$CashControl['status'])
        {
            return 0;
        }
        Log::write(sprintf("[投资奖励]时间:%s,错误信息:%s,", date('Y-m-d H:i:s'), '红包可使用金额：' . $CashControl['redpacket_money']), 'info');
        return sprintf("%u", $CashControl['redpacket_money'] < $tenderMoney ? $CashControl['redpacket_money'] : $tenderMoney);
    }

    /**
     * 发放投资奖励
     * @param array $data 发放数据
     * * */
    public function rewardMoery($data)
    {
        $activityInfo = isset($data['activityInfo']) ? $data['activityInfo'] : '';
        if (!$activityInfo)
        {
            Log::write(sprintf("[投资奖励]时间:%s,无活动信息：发放数据:%s,", date('Y-m-d H:i:s'), json_encode($data)), 'info');
            return TRUE;
        }

        unset($data['activityInfo']);
        //取得标类型
        $itemType = self::getInvesItemTypeBig($data);
        if (!$itemType)
        {
            return TRUE;
        }

        if ($itemType == 2)
        {
            if ($data['borrow_type'] == 6)
            {
                $ruleWhere['is_inves_item_type_big_asset1'] = 1;
            }
            elseif ($data['borrow_type'] == 7)
            {
                $ruleWhere['is_inves_item_type_big_asset2'] = 1;
            }
            elseif ($data['borrow_type'] == 11)
            {
                $ruleWhere['is_inves_item_type_big_asset3'] = 1;
            }
            elseif ($data['borrow_type'] == 10)
            {
                $ruleWhere['is_inves_item_type_big_asset4'] = 1;
            }
        }
        //取得标种类对应的规则
        $ruleWhere['invest_award_item_id'] = $activityInfo['id'];
        $ruleWhere['inves_item_type_big']  = $itemType;
        $ActivityInvestAwardRule           = new ActivityInvestAwardRule();
        $ruleList                          = $ActivityInvestAwardRule->getList(['where' => $ruleWhere]);

        if (!$ruleList)
        {
            Log::write(sprintf("[投资奖励]时间:%s,未匹配到奖励规则:%s,", date('Y-m-d H:i:s'), json_encode($data)), 'info');
            return TRUE;
        }
        //取得标对应用户的投资红包可以使用金额
        $fields                        = 'user_id,invest_item_id,invest_award_item_id,inves_item_type_big,FLOOR(SUM(can_use_red_packet_invest_amount)) as account,FLOOR(SUM(invest_amount)) as Allaccount';
        $where['invest_award_item_id'] = $activityInfo['id'];
        $where['invest_item_id']       = $data['id'];
        $sqlAttr                       = [
            'field' => $fields,
            'where' => $where,
            'group' => 'user_id'
        ];
        $ActivityInvestAwardInvestItem = new ActivityInvestAwardInvestItem();
        $userList                      = $ActivityInvestAwardInvestItem->getList($sqlAttr);

        foreach ($userList as $userValue)
        {
            $tmpRule = [];
            foreach ($ruleList as $ruleValue)
            {
                if ($ruleValue['invest_amount_min'] <= $userValue['account'] && $userValue['account'] < $ruleValue['invest_amount_max'])
                {
                    $tmpRule = $ruleValue;
                }
            }

            if ($tmpRule)
            {

                self::checkRule($userValue, $tmpRule, $data);
                unset($tmpRule);
            }
            else
            {
                Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,用户未匹配到奖励规则:%s", date('Y-m-d H:i:s'), $userValue['user_id'], json_encode($userValue)), 'info');
            }
            unset($userValue);
        }
        return TRUE;
    }

    public function checkRule($userInfo, $ruleInfo, $data)
    {
        $cashInfo       = $packetInfo     = $addInfo        = $exchangeInfo   = '';
        $ruleInfo['id'] = 51;
        if ($ruleInfo['is_award_cash'])
        {
            $ActivityInvestAwardRuleCash = new ActivityInvestAwardRuleCash();
            $cashInfo                    = $ActivityInvestAwardRuleCash->getActivityInvestAwardRuleCash($ruleInfo['invest_award_item_id'], $ruleInfo['id'], $data['time_limit']);
        }
        if ($ruleInfo['is_award_red_packet'])
        {
            $ActivityInvestAwardRuleRedPacket = new ActivityInvestAwardRuleRedPacket();
            $packetInfo                       = $ActivityInvestAwardRuleRedPacket->getActivityInvestAwardRuleRedPacket($ruleInfo['invest_award_item_id'], $ruleInfo['id'], $data['time_limit']);
        }
        if ($ruleInfo['is_award_add_value_ticket'])
        {
            $ActivityInvestAwardRuleAddValueTicket = new ActivityInvestAwardRuleAddValueTicket();
            $addInfo                               = $ActivityInvestAwardRuleAddValueTicket->getActivityInvestAwardRuleAddValueTicket($ruleInfo['invest_award_item_id'], $ruleInfo['id'], $data['time_limit']);
        }
        if ($ruleInfo['is_award_exchange_ticket'])
        {
            $ActivityInvestAwardRuleExchangeTicket = new ActivityInvestAwardRuleExchangeTicket();
            $exchangeInfo                          = $ActivityInvestAwardRuleExchangeTicket->getActivityInvestAwardRuleExchangeTicket($ruleInfo['invest_award_item_id'], $ruleInfo['id'], $data['time_limit']);
        }

        if (!$exchangeInfo && !$addInfo && !$packetInfo && !$cashInfo)
        {
            //规则下没有奖励
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,规则下无奖励内容:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode($ruleInfo)), 'info');
            return TRUE;
        }
        $Iuser     = new Iuser();
        $iuserInfo = $Iuser->getUseriInfo($userInfo['user_id']);
        if (!$iuserInfo)
        {
            //规则下没有奖励
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,用户查找失败:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode($userInfo)), 'info');
            return TRUE;
        }
//        printp($exchangeInfo);printp($addInfo);printp($packetInfo);printp($cashInfo);
//        exit;
        //组装奖励信息数据
        $invest_log_data = [
            'invest_award_item_id'             => $ruleInfo['invest_award_item_id'],
            'invest_award_activity_name'       => $ruleInfo['invest_award_item_name'],
            'user_id'                          => $userInfo['user_id'],
            'user_name'                        => $iuserInfo['username'],
            'user_real_name'                   => $iuserInfo['realname'],
            'inves_item_type_big'              => $userInfo['inves_item_type_big'],
            'invest_item_id'                   => $data['id'],
            'invest_item_name'                 => $data['name'],
            'invest_amount'                    => $userInfo['Allaccount'],
            'can_use_red_packet_invest_amount' => $userInfo['account'],
            'pay_time'                         => date("Y-m-d H:i:s")
        ];

        $wherePay['invest_award_item_id'] = $ruleInfo['invest_award_item_id'];
        $wherePay['user_id']              = $userInfo['user_id'];
        $wherePay['inves_item_type_big']  = $userInfo['inves_item_type_big'];
        $wherePay['invest_item_id']       = $data['id'];
        $ActivityInvestAwardPay           = new ActivityInvestAwardPay();
        if ($ActivityInvestAwardPay->getOneByWhere(['where' => $wherePay]))
        {
            //满标重复-奖励已发放
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,满标重复-奖励已发放:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode($wherePay)), 'info');
            return TRUE;
        }
        $payId = $ActivityInvestAwardPay->insertActivityInvestAwardPay($invest_log_data);
        if (!$payId)
        {
            //满标重复-奖励已发放
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,activity_invest_award_pay:写入数据库失败:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode($invest_log_data)), 'info');
            return TRUE;
        }

        $ActivityInvestAwardItem = new ActivityInvestAwardInvestItem();
        $where                   = [
            'user_id'              => $userInfo['user_id'],
            'invest_item_id'       => $data['id'],
            'invest_award_item_id' => $ruleInfo['invest_award_item_id']
        ];
        $ActivityInvestAwardItem->editByWhere(['invest_award_pay_id' => $payId], $where);
        //变量初始化
        $msgSendAll              = $msgSendCash             = $msgSendPacket           = $msgSendAdd              = $msgSendExchange         = $msg                     = '';
        $cashRes                 = $packetRes               = $addInfoId               = $exchangeInfoId          = '';
        $save                    = [];

        if ($cashInfo)
        {
            $cashRes = self::sendMoney($cashInfo, $userInfo, $data, $payId);
            if ($cashRes)
            {
                $save['award_cash'] = $cashRes;
                $msg.='现金：' . $cashRes;
                //发送消息及站内信
                $msgSendCash        = '现金' . $cashRes . '元';
            }
        }
        if ($packetInfo)
        {
            //发放红包奖励
            $packetRes = self::sendRedPacket($packetInfo, $userInfo, $payId);
            if ($packetRes)
            {
                $save['award_red_packet'] = $packetRes;
                $msg.='红包：' . $packetRes;

                //发送消息及站内信
                $msgSendPacket = ' 红包' . $packetRes . '元';
            }
        }
        if ($addInfo)
        {
            //发放增值券奖励
            $AppreciationAddRule   = self::getAppreciationRule($addInfo['add_value_ticket_rule']);
            $type                  = 1;
            $award_type            = 4;
            $app_info['user_id']   = $userInfo['user_id'];
            $app_info['username']  = $iuserInfo['username'];
            $app_info['rule_info'] = $AppreciationAddRule;
            $addInfoId             = self::sendAppreciation($app_info, $type, $award_type);
            if ($addInfoId)
            {
                $addInfoMoney                      = floor($AppreciationAddRule['money']);
                $save['award_add_value_ticket_id'] = $addInfoId;
                $save['award_add_value_ticket']    = $addInfoMoney;
                $msg.='增值券：' . $addInfoMoney;

                //发送消息及站内信
                $msgSendAdd = ' 增值券' . $addInfoMoney . '元';
            }
        }
        if ($exchangeInfo)
        {
            //发放兑换券奖励
            $AppreciationExchangeRule = self::getAppreciationRule($exchangeInfo['exchange_ticket_rule']);
            $type                     = 2;
            $award_type               = 5;
            $app_info['user_id']      = $userInfo['user_id'];
            $app_info['username']     = $iuserInfo['username'];
            $app_info['rule_info']    = $AppreciationExchangeRule;
            $exchangeInfoId           = self::sendAppreciation($app_info, $type, $award_type);
            if ($exchangeInfoId)
            {
                $exchangeInfoMoney                 = floor($AppreciationExchangeRule['money']);
                $save['award_exchange_tickett_id'] = $exchangeInfoId;
                $save['award_exchange_ticket']     = $exchangeInfoMoney;
                $msg.='兑换券：' . $exchangeInfoMoney;

                //发送消息及站内信
                $msgSendExchange = ' 兑换券' . $exchangeInfoMoney . '元';
            }
        }

        $msgSendAll = $msgSendCash . $msgSendPacket . $msgSendAdd . $msgSendExchange;
        if ($msgSendAll)
        {
            self::sendAppAndMsg($userInfo['user_id'], $iuserInfo['username'], $data['name'], $userInfo['Allaccount'], $msgSendAll);
        }

        //保存奖励信息
//        $this->pay->where(array('id' => $payId))->save($save);
        $ActivityInvestAwardPay->editByWhere($save, ['id' => $payId]);

        echo '用户：' . $iuserInfo['username'] . ' 参与活动：' . $ruleInfo['invest_award_item_name'] . '，投资：' . $data['name'] . '，奖励发放成功：' . $msg;
        echo "<br>";
    }

    /**
     * 发放现金
     * @param array $ruleInfo 规则奖励详情
     * @param array $userInfo 投资奖励数据
     * @param array $borrowInfo 标 的信息
     * @param int $payId itd_activity_invest_award_pay表的id（发放记录表）
     * * */
    public function sendMoney($ruleInfo, $userInfo, $borrowInfo, $payId)
    {
        if ($ruleInfo['pay_rule_cash'] > 0)
        {
            $money = $ruleInfo['pay_rule_cash'];
        }
        elseif ($ruleInfo['pay_rule_invest_ratio'] > 0)
        {
            $money = $ruleInfo['pay_rule_invest_ratio'] * $userInfo['account'] / 100;
        }
        else
        {
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,现金发放错误-发放类型错误:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode([$payId, $borrowInfo, $ruleInfo, $userInfo])), 'info');
            return FALSE;
        }
        $money = floor($money);
        if ($money < 1)
        {
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,现金发放错误-发放金额小于1元:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode([$payId, $borrowInfo, $ruleInfo, $userInfo])), 'info');
            return FALSE;
        }

        if (isset($borrowInfo['item_type']) && $borrowInfo['item_type'] == 3)
        {
            $borrowInfo['borrow_type'] = 0;
            $urlmsg                    = "投资返现活动[Óa href='/Crowdfunding/detail/projectid/" . $borrowInfo['id'] . "' target=_blankÔ" . $borrowInfo['name'] . "Ó/aÔ]";
        }
        else
        {
            $urlmsg = "投资返现活动[Óa href='/Invest/ViewBorrow/num/" . $borrowInfo['borrow_num'] . "' target=_blankÔ" . $borrowInfo['name'] . "Ó/aÔ]";
        }
        $AccountLogic = new AccountLogic();
        $params       = [
            'uid'               => $userInfo['user_id'], //用户id
            'to_uid'            => 1, //交易对象用户id
            'type'              => 682, //业务编码itd_remind.numb
            'num'               => $payId, //类型id
            'total_change'      => $money, //总金额改变量
            'use_change'        => $money, //可用金额改变量
            'nouse_change'      => 0, //冻结金额改变量
            'collection_change' => 0, //待收金额改变量
            'waitreplay_change' => 0, //借款金额改变量
            'remark'            => $urlmsg, //备注
            'btype'             => 0, //标类型
            'treasureChest'     => 0, //红包投资金额
            'bnum'              => 0, //标编码
            'capitial'          => 0//投资本金（暂为秒回款时资金流水的投资本金）
        ];
        $res          = $AccountLogic->upChange($params);

        if (!$res)
        {
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,现金发放错误-现金发放失败，Upchange失败:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode(array($payId, $borrowInfo, $ruleInfo, $userInfo, $params))), 'info');
            return FALSE;
        }
        return $money;
    }

    /**
     * 发放红包
     * @param array $ruleInfo 规则奖励详情
     * @param array $userInfo 投资奖励数据
     * @param int $pay_id itd_activity_invest_award_pay表的id（发放记录表）
     * * */
    public function sendRedPacket($ruleInfo, $userInfo, $pay_id)
    {
        //根据红包规则id，取得红包规则
        $rpRule = self::getAwardRule($ruleInfo['red_packet_rule']);
        if ($rpRule['userRuleType'] != 2001)
        {
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,红包发放错误-不支持递增类型红包:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode([$pay_id, $ruleInfo, $userInfo])), 'info');
            return FALSE;
        }
        $list           = array();
        $awardRuleParam = self::getAwardRuleParam($rpRule['id']);
        //红包发放规则【1：投资比例；2：固定值；3：随机发放；4：满值发放】
        if ($ruleInfo['red_packet_pay_rule'] == 1)
        {
            $list[] = $awardRuleParam[0];
            $money  = $userInfo['account'] * $ruleInfo['pay_rule_invest_ratio'] / 100;
            if (floor($money) < 1)
            {
                Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,红包发放错误-红包金额小于1元:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode([$pay_id, $ruleInfo, $userInfo])), 'info');
                return FALSE;
            }
            $list[0]['value'] = floor($money);
            $moneyCount       = floor($money);
        }
        elseif ($ruleInfo['red_packet_pay_rule'] == 2)
        {
            if ($awardRuleParam[0]['splitNum'])
            {
                //拆分红包
                $list = self::getAwardRuleParamItem($awardRuleParam[0]['id']);
            }
            else
            {
                $list[] = $awardRuleParam[0];
            }
            $moneyCount = $awardRuleParam[0]['value'];
        }
        elseif ($ruleInfo['red_packet_pay_rule'] == 3)
        {
            foreach ($awardRuleParam as $key => $val)
            {
                $arr[$key] = $val['rate'];
            }
            $res = self::getRand($arr);
            if ($awardRuleParam[$res]['splitNum'])
            {
                $list = self::getAwardRuleParamItem($awardRuleParam[$res]['id']);
            }
            else
            {
                $list[] = $awardRuleParam[$res];
            }
            $moneyCount = $awardRuleParam[$res]['value'];
        }
        elseif ($ruleInfo['red_packet_pay_rule'] == 4)
        {
            if ($ruleInfo['pay_rule_full_value_pay_max'] > 0 && $userInfo['account'] <= $ruleInfo['pay_rule_full_value_pay_max'])
            {
                $account = $userInfo['account'];
            }
            elseif ($ruleInfo['pay_rule_full_value_pay_max'] > 0 && $userInfo['account'] > $ruleInfo['pay_rule_full_value_pay_max'])
            {
                $account = $ruleInfo['pay_rule_full_value_pay_max'];
            }
            else
            {
                $account = $userInfo['account'];
            }
            $count = floor($account / $ruleInfo['pay_rule_full_value_pay']);
            for ($i = 1; $i <= $count; $i++)
            {
                if ($awardRuleParam[0]['splitNum'])
                {
                    //拆分红包
                    $list = array_merge_recursive($list, self::getAwardRuleParamItem($awardRuleParam[0]['id']));
                }
                else
                {
                    $list[] = $awardRuleParam[0];
                }
            }
            $moneyCount = $awardRuleParam[0]['value'] * $count;
        }
        else
        {
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,红包发放错误-红包发放规则错误:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode([$pay_id, $ruleInfo, $userInfo])), 'info');
            return FALSE;
        }
        if (!$list)
        {
            Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,红包发放错误-红包发放数据为空:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode([$pay_id, $ruleInfo, $userInfo])), 'info');
            return FALSE;
        }
        Log::write(sprintf("[投资奖励]时间:%s,用户ID：%d,红包发放数据:%s", date('Y-m-d H:i:s'), $userInfo['user_id'], json_encode([$pay_id, $ruleInfo, $userInfo, $list, count($list)])), 'info');
        $TreasureChest                   = new TreasureChest();
        $ActivityInvestAwardPayRedPacket = new ActivityInvestAwardPayRedPacket();
        $start_time                      = $rpRule['timeEffect'] == 1 ? strtotime(date("Y-m-d H:i:s")) : $rpRule['ruleStartTime']; //奖品有效期开始时间
        $startTimeStr                    = strtotime(date("Y-m-d", $start_time)); //获取开始时间的零点
        $end_time                        = $startTimeStr + $rpRule['ruleValidDay'] * 86400 - 1; //奖品有效期结束时间(包含开始时间那一天)
        foreach ($list as $key => $value)
        {
            $data['user_id']          = $userInfo['user_id'];
            $data['award_id']         = $ruleInfo['invest_award_item_id'];
            $data['award_type']       = 0; //现金券
            $data['user_constraint']  = $value['value'] * $value['multiple']; //使用约束
            $data['value']            = floor($value['value']); //奖品值
            $data['startTime']        = $start_time; //奖品有效期开始时间
            $data['end_time']         = $end_time; //奖品有效期结束时间
            $data['add_time']         = time(); //奖品添加时间
            $data['borrowType']       = $rpRule['borrowType']; //投标类型（1.企业9.创业6.净值7.股权11.工薪），多种类型用逗号分隔
            $data['borrowTimeLimit']  = isset($rpRule['borrowTimeLimit']) ? $rpRule['borrowTimeLimit'] : 0; //投标期限1.不限2.天标,0.没填
            $data['borrowStartMonth'] = $rpRule['borrowStartMonth']; //投标期限起始月份
            $data['borrowEndMonth']   = $rpRule['borrowEndMonth']; //投标期限结束月份
            $data['awardRuleId']      = $rpRule['id']; //奖品规则id
            $data['ruleType']         = $rpRule['ruleType']; //规则类型:1001.红包1002.加息券1003.体验金
            $data['drawType']         = 3002; //抽奖类型:3001.自动3002.手动
            $data['status']           = 0; //奖品状态
            $data['remark']           = '投资奖励活动'; //备注
            $id                       = $TreasureChest->insertTreasureChest($data);
            if ($id)
            {
                $ActivityInvestAwardPayRedPacket->add(['activity_invest_award_pay_id' => $pay_id, 'red_packet_id' => $id]);
            }
        }
        return $moneyCount;
    }

    /**
     * @param array $info 奖励相关数据
     * @param int $type 类型【1：增值券；2：兑换券】
     * @param int $award_type 类型【4：增值券；5：兑换券】
     * * */
    public function sendAppreciation($info, $type, $award_type)
    {
        $TreasureChest                              = new TreasureChest();
        $UserAppreList                              = new UserAppreList();
        $UserAppreRule                              = new UserAppreRule();
        $UserAppre                                  = new UserAppre();
        Db::startTrans();
        //卡券要入4个数据表，user_appreciation_list，user_appreciation，user_appreciation_rule，treasure_chest
        $user_appreciation_list_data['user_id']     = $info['user_id'];
        $user_appreciation_list_data['username']    = $info['username'];
        $user_appreciation_list_data['num']         = 1;
        $user_appreciation_list_data['addtime']     = time();
        $user_appreciation_list_data['editor_id']   = 1;
        $user_appreciation_list_data['editor_name'] = 'admin';
        $user_appreciation_list_data['type']        = $type;
        $user_appreciation_listId                   = $UserAppreList->add($user_appreciation_list_data);
        $model                                      = self::getModelRateById($info['rule_info']); //通过模板查找模板信息以及对应的增长率信息

        $model_info                             = $model['model']; //增值券信息
        $rateArr                                = $model['rate']; //增值券增长率           
        $user_appreciation_data['user_id']      = $info['user_id'];
        $user_appreciation_data['username']     = $info['username'];
        $user_appreciation_data['aid']          = $model_info['id']; //增值券规则ID
        $user_appreciation_data['name']         = $model_info['name'];
        $user_appreciation_data['remark']       = '投资奖励';
        $user_appreciation_data['money']        = $model_info['money']; //奖品值
        $user_appreciation_data['status']       = 1;
        $user_appreciation_data['instructions'] = $model_info['instructions']; //奖品有效期结束时间
        $user_appreciation_data['start_time']   = $model_info['immediate_effect'] == 1 ? time() : strtotime($model_info['start_time']); //增值券开始时间
        $rate                                   = end($rateArr); //获取最后一个
        $month                                  = $rate['months'];
        $type_rate                              = $rate['type']; //天还是月
        if ($type_rate == '1')
        {
            $end_time = strtotime(" + " . $month . " month", strtotime(date("Y-m-d", $user_appreciation_data['start_time'])));
        }
        else
        {
            $end_time = strtotime(" + " . $month . " day", strtotime(date("Y-m-d", $user_appreciation_data['start_time'])));
        }
        $user_appreciation_data['end_time']  = $end_time; //增值券结束时间                        
        $user_appreciation_data['add_time']  = time();
        $user_appreciation_data['send_id']   = 1;
        $user_appreciation_data['send_name'] = 'admin';
        $user_appreciation_data['type']      = $type;
        $user_appreciation_data['list_id']   = $user_appreciation_listId; //itd_user_appreciation_list表对应的ID
        //发送给用户的增值信息入库
        $user_appreciationId                 = $UserAppre->add($user_appreciation_data);

        $monthStr = '';
        //发送给用户的增值率入库
        foreach ($rateArr as $v)
        {
            $user_appreciation_rule_data['aid']             = $user_appreciationId;
            $user_appreciation_rule_data['user_id']         = $info['user_id'];
            $user_appreciation_rule_data['months']          = $v['months'];
            $user_appreciation_rule_data['appreciate_rate'] = $v['appreciate_rate'];
            $user_appreciation_rule_data['addtime']         = time();
            $user_appreciation_rule_data['type']            = $type_rate;
            $UserAppreRule->add($user_appreciation_rule_data);
            if ($type_rate == '1')
            {//月
                $monthStr .= "," . $v['months'];
            }
            else
            {//天
                $monthStr .= "," . $v['months'];
            }
        }

        $uniquneCode                          = uniqueCodeCreate();
        //百宝箱入库
        $treasure_chest_data['user_id']       = $info['user_id'];
        $treasure_chest_data['award_type']    = $award_type;
        $treasure_chest_data['value']         = floor($info['rule_info']['money']);
        $treasure_chest_data['end_time']      = $end_time;
        $treasure_chest_data['add_time']      = time();
        $treasure_chest_data['startTime']     = $user_appreciation_data['start_time'];
        $treasure_chest_data['awardRuleId']   = $user_appreciationId; //填写发送到user_appreciation的ID
        $treasure_chest_data['ruleType']      = 1002; //类型加息券
        $treasure_chest_data['drawType']      = 3001; //手动
        $treasure_chest_data['status']        = 0;
        $treasure_chest_data['awardSoleCode'] = $uniquneCode;
        $treasure_chest_data['remark']        = '投资奖励';
        $treasure_chest_data['extrasInfo']    = "增值率:" . substr($monthStr, 1); //增值率中月份拼接成的字符串
        $treasure_chestId                     = $TreasureChest->insertTreasureChest($treasure_chest_data);

        if (!$treasure_chestId)
        {
            return FALSE;
        }

        Db::commit();
        Db::query('SET autocommit=1');
        return $treasure_chestId;
    }

    /**
     * 获取红包规则
     * @param int $id 规则id
     * * */
    public function getAwardRule($id)
    {
        $AwardRule = new AwardRule();
        return $AwardRule->getOneByWhere(['where' => ['id' => $id]]);
    }

    /**
     * 获取红包列表
     * @param int $ruleId 红包规则id
     * * */
    public function getAwardRuleParam($ruleId)
    {
        $AwardParam = new AwardParam();
        return $AwardParam->getList(['where' => ['ruleId' => $ruleId]]);
    }

    /**
     * 获取红包拆分红包
     * @param int $awardValueId 拆分红包规则id
     * * */
    public function getAwardRuleParamItem($awardValueId)
    {
        $AwardParamItem = new AwardParamItem();
        return $AwardParamItem->getList(['where' => ['award_value_id' => $awardValueId]]);
    }

    /**
     * 根据id获取兑换券或增值券信息
     * @param int $id Description
     * * */
    public function getAppreciationRule($id)
    {
        $Appreciation = new Appreciation();
        return $Appreciation->getOne($id);
    }

    /**
     * 通过模板查找模板信息以及对应的增长率信息
     */
    public function getModelRateById($info)
    {
        $data = array();
        if ($info)
        {
            $UserAppreRule      = new UserAppreRule();
            $immediate_effect   = $info['immediate_effect'];
            $startTime          = $immediate_effect == 1 ? "即时生效" : date("Y-m-d H:i", $info['start_time']);
            $info['start_time'] = $startTime;
            $rate               = $UserAppreRule->getList(['where' => ['aid' => $info['id']], 'order' => 'id asc', 'field' => 'months,appreciate_rate,type']);
            foreach ($rate as $k => $v)
            {
                $rate[$k]['appreciate_rate'] = str_replace('.00', '', $v['appreciate_rate']);
            }
            $data = array('model' => $info, 'rate' => $rate);
        }
        return $data;
    }

    /**
     * 返回中奖概率数组下标
     * @param array $proArr 概率数组，一维数组
     * * */
    public static function getRand($proArr)
    {
        $result = '';
        //概率数组的总概率精度 
        $proSum = array_sum($proArr);
        //概率数组循环 
        foreach ($proArr as $key => $proCur)
        {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur)
            {
                $result = $key;
                break;
            }
            else
            {
                $proSum -= $proCur;
            }
        }
        unset($proArr);
        return $result;
    }

    public function sendAppAndMsg($user_id, $username, $name, $Allaccount, $msg)
    {
        $temp    = mb_substr($msg, 0, 1, 'utf-8');
        $add_txt = '';
        if ($temp != '￥')
        {
            $add_txt = '请于您的百宝箱查看。 <br>';
        }
        //发送站内信                 
        $content["title"]    = "您已收到投资奖励";
        $content["body"]     = "尊敬的" . $username . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' .
                "投资标名：" . $name . '<br>' .
                "投资金额：￥" . $Allaccount . '<br>' .
                "投资奖励：" . $msg . '<br>' . $add_txt .
                "愿健康与快乐天天伴随您！";
        //app消息推送
        $paramMsg['user_id'] = $user_id;
        $paramMsg['type']    = 4;
        $paramMsg['mtype']   = 47;
        $paramMsg['name']    = '投资奖励到账';
        $content_app         = [
            ['key' => '投资时间：', 'value' => date('Y/m/d H:i', time())],
            ['key' => '投资项目：', 'value' => $name],
            ['key' => '投资金额：', 'value' => '￥' . $Allaccount],
            ['key' => '投资奖励：', 'value' => $msg],
        ];
        $paramMsg['content'] = json_encode($content_app);

        $task = new Task();
        $task->Sendmsg(1, $user_id, $content["title"], $content["body"]);

        $AppPushMessage = new AppPushMessage();
        $AppPushMessage->addMessageAppPush($paramMsg);
        return TRUE;
    }

}
