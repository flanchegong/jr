<?php

namespace application\common\logic\activity;

use application\common\logic\account\Account;
use think\Db;
use application\common\logic\system\Task;
use application\common\logic\credit\Credit;
use application\common\Myredis;
use application\common\model\borrow\Withdraw;
use application\common\model\system\QueenSms;
use application\common\model\user\Iuser;
use application\common\model\borrow\IborrowTender;
use application\common\model\borrow\Iborrow;
use application\common\logic\credit\CreditLogic;


/**
 * Description of Activity.
 *
 * @author Administrator
 */
class Active
{

    /**
     * @desc 函数：投资返利发放奖励
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return void
     */
    public function rewardMoery($data)
    {
        $reward = Myredis::getRedisConn(4)->getFromHash('invest_reward_two', 682);
        if (!$reward)
        {
            $model  = new InvestActivity();
            $reward = $model->getActivityInfo();
        }
        //判断活动是否开启
        if (time() >= $reward[0]['a_start_time'] && time() <= $reward[0]['a_end_time'] && $reward[0]['a_status'] == 1)
        {
            $ret = $this->sentReturnMoney($data['borrow_num'], '', $reward);
            return $ret;
        }
        return false;
    }

    /**
     * @desc 函数：满标发送奖励
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return void
     */
    public function sendAward($params = [], $userId = '')
    {
        if (!isset($params['borrow_num']))
        {
            return false;
        }
        $field               = 'user_id,username,borrow_num,invest_money,use_money,chance_money';
        $where['borrow_num'] = $params['borrow_num'];
        if ($userId != '')
        {
            $where['user_id'] = $userId;
        }
        //取得单个标用户投资有效金额的总和
        $awardInvestLogModel = new AwardInvestLog();
        $sqlAttr             = [
            'field' => $field,
            'where' => $where
        ];
        $awardIvestLog       = $awardInvestLogModel->getList($sqlAttr);
        unset($where);
        //取得正在进行有抽奖的活动
        $awardActiviInfo     = $this->_getOneActivity();
        if (empty($awardActiviInfo) || ($awardActiviInfo['activities_start'] > time() && time() < $awardActiviInfo['activities_end'] ))
        {
            return false;
        }
        $wardInvestChanceModel = new AwardInvestChance();
        $queenSmsModel         = new QueenSms();
        $iuserModel            = new Iuser();
        foreach ($awardIvestLog as $key => $value)
        {

            if ($value['chance_money'] < 5000)
            {
                continue;
            }
            $datas = array(
                'user_id'    => $value['user_id'],
                'username'   => $value['username'],
                'borrow_num' => $value['borrow_num'],
                'pid'        => $awardActiviInfo['pid'],
                'use_money'  => $value['chance_money'],
                'add_time'   => time(),
                'status'     => 1,
            );
            $ret   = $wardInvestChanceModel->add($datas);
            if (!$ret)
            {
                Myredis::getRedisConn(4)->appendToList('sendAward', $datas);
                continue;
            }
            $userInfo       = $iuserModel->getOne($value['user_id'], 'user_id,username,phone');
            $sms['content'] = "您已获得一次抽奖机会，快去抽奖吧，抽奖链接jr.yatang.cn/Award/index";
            $sms['phone']   = $userInfo['phone'];
            if ($sms['phone'])
            {
                $queenSmsModel->add($sms);
            }
        }
        return true;
    }

    /**
     * @desc 函数：发放积分
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return void
     */
    public function sendCredit($params = [], $userId = '')
    {
        //投资者送分
        $where['borrow_num'] = $params['borrow_num'];
        if ($userId != '')
        {
            $where['i.user_id'] = $userId;
        }
        $where['i.vip_status'] = 1;
        $fields                = "i.user_id,sum(it.account) as account";
        $ibrrowTenderModel     = new IborrowTender();
        $tenderList            = $ibrrowTenderModel->getCreditTenderList($where, $fields);
        $integral              = array();
        foreach ($tenderList as $k => $v)
        {
            $integral[$v['user_id']] = $v['account'];
            Myredis::getRedisConn(6)->deleteFromHash('vip_rules', $params['user_id']);
        }
        $vipjfRule   = config('system.RULES'); //vip等级对应的基数积分
        $multipleArr = config('system.MULTIPLES'); //倍数规则
        //所投的融资项目期限4个月及以上的倍数为2.0
        $multiple    = $params['time_limit'] >= 4 ? $multipleArr[4] : $multipleArr[$params['time_limit']];
        foreach ($integral as $key => $value)
        {
            $credit              = new CreditLogic();
            $creditjft           = $credit->creditTZ($key, $value, $vipjfRule, $multiple);
            $data['user_id']     = $key;
            $data['credit_type'] = 'invest_success';
            $data['credit']      = $creditjft;
            $data['num']         = $params['borrow_num'];
            $data['title']       = $params['name'];
            $r                   = $credit->creditChange($data);
            if (!$r)
            {
                $redisData = [
                    'borrow_num' => $params['borrow_num'],
                    'user_id'    => $key,
                    'name'       => $params['name'],
                    'time_limit' => $params['time_limit']
                ];
                Myredis::getRedisConn(4)->appendToList('sendCredit', $redisData);
                continue;
            }
        }
        return true;
    }

    /**
     * @desc 函数：添加抽奖机会
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return bool
     */
    public function addAwardInvestLog($data, $tenderMoney, &$rebate)
    {
        $info = $this->_getOneActivity();
        if (empty($info))
        {
            return true;
        }
        $InvestCompute = $this->investCompute($data['tuserid'], $tenderMoney);
        $money         = $InvestCompute['userMoney'];
        $rebate        = $InvestCompute['userMoney'];
        //排除天标秒标
        $datas         = array(
            'user_id'      => $data['tuserid'],
            'username'     => $data['tusername'],
            'borrow_num'   => $data['borrow_num'],
            'invest_money' => $data['pTaccount'],
            'tender_num'   => $data['tnum'],
            'use_money'    => $tenderMoney,
            'chance_money' => $money,
            'add_time'     => time(),
        );
        $model         = new AwardInvestLog();
        return $model->add($datas);
    }

    /**
     * @desc 函数：投资返现
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return void
     */
    public function investRewardActual($data, &$rebate = 0, $tenderMoney)
    {
        $reward = Myredis::getRedisConn(4)->getFromHash('invest_reward_two', 682);
        if (!$reward)
        {
            $model  = new InvestActivity();
            $reward = $model->getActivityInfo();
        }
        //判断活动是否开启
        if (time() >= $reward[0]['a_start_time'] && time() <= $reward[0]['a_end_time'] && $reward[0]['a_status'] == 1)
        {
            $data['tender_money']  = $tenderMoney;
            Myredis::getRedisConn(4)->setToHash('pTaccount', $data['tuserid'], $data);
            $rewardMoery           = $this->investCompute($data['tuserid'], $tenderMoney);
            $rebate                = $rewardMoery['userMoney'];
            $datas                 = array(
                'user_id'            => $data['tuserid'],
                'username'           => $data['tusername'],
                'borrow_num'         => $data['borrow_num'],
                'invest_money'       => $data['pTaccount'],
                'invest_award_money' => $rewardMoery['rewardMoery'],
                'award_money'        => $rewardMoery['userMoney'],
                'activity_id'        => $reward[0]['a_id'],
                'invest_id'          => $reward[0]['id'],
                'addtime'            => time(),
                'tender_num'         => $data['tnum'],
                'status'             => 2
            );
            $investCollectionModel = new InvestCollection();
            return $investCollectionModel->add($datas);
        }
        return true;
    }

    /**
     * @desc 函数：投资返利发放奖励
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return void
     */
    public function sentReturnMoney($borrowNum = '', $userId = '', $reward = [])
    {
        if ($borrowNum == '')
        {
            return false;
        }
        if (empty($reward))
        {
            $reward = Myredis::getRedisConn(4)->getFromHash('invest_reward_two', 682);
            if (!$reward)
            {

                $reward = $this->_getActivityInfo();
            }
        }

        $fields              = " user_id, username, FLOOR(SUM(award_money)) as account, CONCAT('',GROUP_CONCAT(id ORDER BY user_id SEPARATOR  ','),'') AS ids ";
        $where['borrow_num'] = $borrowNum;
        $where['status']     = 2;
        if ($userId != '')
        {
            $where['user_id'] = $userId;
        }
        $investCollectionModel = new InvestCollection();
        $sqlAttr               = [
            'field' => $fields,
            'where' => $where,
            'group' => 'user_id'
        ];
        $rsfull                = $investCollectionModel->getList($sqlAttr);
        $iborrowModel          = new Iborrow();
        $data                  = $iborrowModel->getOneByWhere(['where' => ['borrow_num' => $borrowNum]]);
        $rule                  = [];
        $mes                   = [];
        $investLogModel        = new InvestLog();
        foreach ($rsfull as $k => $v)
        {
            $tmp = array();
            foreach ($reward as $key => $value)
            {
                if ($value['least_money'] <= $v['account'] && $v['account'] < $value['max_money'])
                {
                    $tmp[] = $reward[$key];
                }
            }
            if (!$tmp)
            {
                continue;
            }
            if (count($tmp) > 1)
            {
                //意外后台配置多条匹配金额的规则，则取最大金额限制的规则
                foreach ($tmp as $k => $va)
                {
                    $arr[$k] = $va['max_money'];
                }
                $rule = $tmp[array_search(max($arr), $arr)];
            }
            else
            {
                //只有一条规则匹配
                $rule = $tmp[0];
            }
            if ($rule)
            {
                $borrowTypes = explode(',', $rule['borrow_type']);
                //匹配是否是奖励的标种
                if (in_array($data['borrow_type'], $borrowTypes))
                {
                    $rule['award_info'] = json_decode($rule['award_info'], true);
                    $investData         = array(
                        'user_id'               => $v['user_id'],
                        'borrow_num'            => $data['borrow_num'],
                        'award_money'           => $v['account'],
                        'activity_id'           => $rule['a_id'],
                        'invest_id'             => $rule['id'],
                        'invest_info'           => json_encode($rule),
                        'borrow_info'           => json_encode($data),
                        'addtime'               => time(),
                        'invest_collection_ids' => $v['ids']
                    );
                    $investId           = $investLogModel->add($investData);
                    if ($investId)
                    {
                        $ret = $this->rule($rule, $data, $v, $investId, $data['borrow_num']);
                    }
                    else
                    {
                        Myredis::getRedisConn(4)->appendToList('rewardMoery', $investData);
                    }
                    unset($investData);
                    $mes[$v['user_id']] = $ret;
                }
            }
            unset($rule);
            unset($v);
        }
        return $mes;
    }

    /**
     * @param array $item 奖励规则信息
     * @param array $data 标的相关信息
     * @param array $info 奖励信息（包含用户id，投标总使用金额）
     * * */
    public function rule($item, $data, $info, $investLogId, $borrowNum)
    {
        $task                = new Task();
        $awardRuleModel      = new AwardRule();
        $awardParamModel     = new AwardParam();
        $awardParamItemModel = new AwardParamItem();
        $appreciationmModel  = new Appreciation();
        if ($item['rule_type'] == 1 && $item['award_type'] == 1)
        {

            //按投资金额比例/红包
            $checkId   = $item['award_info']["month_" . "{$data['time_limit']}"];
            $check_val = $item['award_info']["month_val_" . "{$data['time_limit']}"];
            if (!$check_val || !$checkId)
            {
                return false;
            }
            $rpRule = $awardRuleModel->getOne($checkId);
            if ($rpRule['userRuleType'] == 2001)
            {
                $sqlArr     = [
                    'where' => ['ruleId' => $rpRule['id']]
                ];
                $awardParam = $awardParamModel->getList($sqlArr);
                $multiple   = $awardParam[0]['multiple'];
            }
            elseif ($rpRule['userRuleType'] == 2003)
            {
                $multiple = $rpRule['multipleTotal'];
            }
            $money = $info['account'] * $check_val / 100;
            if (floor($money) < 1)
            {
                return false;
            }
            $ret                 = $this->redBackData($rpRule, $info['user_id'], $money, $multiple, $investLogId, $item, $borrowNum);
            //发送站内信                 
            $content["title"]    = "您已收到投资奖励";
            $content["body"]     = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                投资标名：" . $data['name'] . '<br>' . "
                                投资金额：￥" . $info['account'] . '<br>' . "
                                投资奖励：红包" . floor($money) . "元" . '<br>' . "
                                请于您的百宝箱查看。" . '<br>' . "
                                愿健康与快乐天天伴随您！";
            $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
            unset($content);
            //app消息推送
            $paramMsg['user_id'] = $info['user_id'];
            $paramMsg['type']    = 4;
            $paramMsg['mtype']   = 41;
            $paramMsg['name']    = '投资奖励到账';
            $paramMsg['content'] = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                 投资项目：" . $data['name'] . '<br>' . "
                                投资金额：￥" . $info['account'] . '<br>' . "
                                投资奖励：红包" . floor($money) . "元";
            add_message_app($paramMsg);
            unset($paramMsg);
            return $ret;
        }
        elseif ($item['rule_type'] == 1 && $item['award_type'] == 2)
        {

            //按投资金额比例/现金
            $checkId     = $item['award_info']["month_" . $data['time_limit']];
            $rewardMoery = $info['account'] * $checkId / 100;
            if (!$checkId)
            {
                return false;
            }
            if (intval($rewardMoery) < 1)
            {
                return false;
            }
            $ret = $this->addMoney($data, $info['user_id'], $rewardMoery, $investLogId);

            //发送站内信                 
            $content["title"]    = "您已收到投资奖励";
            $content["body"]     = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                投资标名：" . $data['name'] . '<br>' . "
                                投资金额：￥" . $info['account'] . '<br>' . "
                                投资奖励：￥" . intval($rewardMoery) . '<br>' . "
                                愿健康与快乐天天伴随您！";
            $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
            unset($content);
            //app消息推送
            $paramMsg['user_id'] = $info['user_id'];
            $paramMsg['type']    = 4;
            $paramMsg['mtype']   = 41;
            $paramMsg['name']    = '投资奖励到账';
            $paramMsg['content'] = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                 投资项目：" . $data['name'] . '<br>' . "
                                投资金额：￥" . $info['account'] . '<br>' . "
                                投资奖励：￥" . intval($rewardMoery) . "元";
            add_message_app($paramMsg);
            unset($paramMsg);
            return $ret;
        }
        elseif ($item['rule_type'] == 2 && $item['award_type'] == 1)
        {

            //随机抽奖/红包
            $checkId = $item['award_info']["month_" . "{$data['time_limit']}"];
            if (!$checkId)
            {
                return FALSE;
            }
            $rpRule = $awardRuleModel->getOne($checkId);

            //新规则
            if ($rpRule['userRuleType'] == 2001)
            {
                $sqlArr     = [
                    'where' => ['ruleId' => $rpRule['id']]
                ];
                $awardParam = $awardParamModel->getList($sqlArr);
                foreach ($awardParam as $key => $val)
                {
                    $arr[$key] = $val['rate'];
                }
                $res = self::getRand($arr);
                if ($awardParam[$res]['splitNum'])
                {

                    $sqlAttr              = [
                        'where' => ['award_value_id' => $awardParam[$res]['id']]
                    ];
                    $getRedAwardParamItem = $awardParamItemModel->getList($sqlAttr);
                    foreach ($getRedAwardParamItem as $k => $v)
                    {
                        $multiple = $v['multiple'];
                        $money    = $v['value'];
                        $ret      = $this->redBackData($rpRule, $info['user_id'], $money, $multiple, $investLogId, $item, $borrowNum);
                    }
                    //发送站内信                 
                    $content["title"]    = "您已收到投资奖励";
                    $content["body"]     = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                        投资标名：" . $data['name'] . '<br>' . "
                                        投资金额：￥" . $info['account'] . '<br>' . "
                                        投资奖励：红包" . floor($awardParam[$res]['value']) . "元（拆分）" . '<br>' . "
                                        请于您的百宝箱查看。" . '<br>' . "
                                        愿健康与快乐天天伴随您！";
                    $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
                    unset($content);
                    //app消息推送
                    $paramMsg['user_id'] = $info['user_id'];
                    $paramMsg['type']    = 4;
                    $paramMsg['mtype']   = 41;
                    $paramMsg['name']    = '投资奖励到账';
                    $paramMsg['content'] = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                            投资项目：" . $data['name'] . '<br>' . "
                                            投资金额：￥" . $info['account'] . '<br>' . "
                                            投资奖励：红包" . floor($awardParam[$res]['value']) . "元（拆分）";
                    add_message_app($paramMsg);
                    unset($paramMsg);
                }
                else
                {
                    $multiple = $awardParam[$res]['multiple'];
                    $money    = $awardParam[$res]['value'];
                    if (floor($money) < 1)
                    {
                        return FALSE;
                    }
                    $ret                 = $this->redBackData($rpRule, $info['user_id'], $money, $multiple, $investLogId, $item, $borrowNum);
                    //发送站内信                 
                    $content["title"]    = "您已收到投资奖励";
                    $content["body"]     = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                        投资标名：" . $data['name'] . '<br>' . "
                                        投资金额：￥" . $info['account'] . '<br>' . "
                                        投资奖励：红包" . floor($money) . "元" . '<br>' . "
                                        请于您的百宝箱查看。" . '<br>' . "
                                        愿健康与快乐天天伴随您！";
                    $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
                    unset($content);
                    //app消息推送
                    $paramMsg['user_id'] = $info['user_id'];
                    $paramMsg['type']    = 4;
                    $paramMsg['mtype']   = 41;
                    $paramMsg['name']    = '投资奖励到账';
                    $paramMsg['content'] = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                            投资项目：" . $data['name'] . '<br>' . "
                                            投资金额：￥" . $info['account'] . '<br>' . "
                                            投资奖励：红包" . floor($money) . "元";
                    add_message_app($paramMsg);
                    unset($paramMsg);
                }
                return $ret;
            }
            elseif ($rpRule['userRuleType'] == 2003)
            {
                $multiple = $data['rule_info']['multipleTotal'];
                $money    = $data['rule_info']['minValue'];
                if (floor($money) < 1)
                {
                    return FALSE;
                }
                $ret                 = $this->redBackData($rpRule, $info['user_id'], $money, $multiple, $investLogId, $item, $borrowNum);
                //发送站内信                 
                $content["title"]    = "您已收到投资奖励";
                $content["body"]     = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                    投资标名：" . $data['name'] . '<br>' . "
                                    投资金额：￥" . $info['account'] . '<br>' . "
                                    投资奖励：红包" . floor($money) . "元" . '<br>' . "
                                    请于您的百宝箱查看。" . '<br>' . "
                                    愿健康与快乐天天伴随您！";
                $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
                unset($content);
                //app消息推送
                $paramMsg['user_id'] = $info['user_id'];
                $paramMsg['type']    = 4;
                $paramMsg['mtype']   = 41;
                $paramMsg['name']    = '投资奖励到账';
                $paramMsg['content'] = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                            投资项目：" . $data['name'] . '<br>' . "
                                            投资金额：￥" . $info['account'] . '<br>' . "
                                            投资奖励：红包" . floor($money) . "元";
                add_message_app($paramMsg);
                unset($paramMsg);
                return $ret;
            }
            //新规则
        }
        elseif ($item['rule_type'] == 3 && $item['award_type'] == 1)
        {

            //固定值/红包
            $checkId   = $item['award_info']["month_" . "{$data['time_limit']}"];
            $check_val = $item['award_info']["month_val_" . "{$data['time_limit']}"];
            if (!$checkId)
            {
                return FALSE;
            }
            $rpRule = $awardRuleModel->getOne($checkId);
            if ($rpRule['userRuleType'] == 2001)
            {
                $sqlArr     = [
                    'where' => ['ruleId' => $rpRule['id']]
                ];
                $awardParam = $awardParamModel->getList($sqlArr);
                if ($awardParam[0]['splitNum'])
                {
                    if (floor($awardParam[0]['value']) < 1)
                    {
                        return FALSE;
                    }
                    $sqlAttr              = [
                        'where' => ['award_value_id' => $awardParam[0]['id']]
                    ];
                    $getRedAwardParamItem = $awardParamItemModel->getList($sqlAttr);
                    foreach ($getRedAwardParamItem as $k => $v)
                    {
                        $multiple = $v['multiple'];
                        $money    = $v['value'];
                        $ret      = $this->redBackData($rpRule, $info['user_id'], $money, $multiple, $investLogId, $item, $borrowNum);
                    }
                    //发送站内信                 
                    $content["title"]    = "您已收到投资奖励";
                    $content["body"]     = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                        投资标名：" . $data['name'] . '<br>' . "
                                        投资金额：￥" . $info['account'] . '<br>' . "
                                        
                                        请于您的百宝箱查看。" . '<br>' . "
                                        愿健康与快乐天天伴随您！";
                    $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
                    unset($content);
                    //app消息推送
                    $paramMsg['user_id'] = $info['user_id'];
                    $paramMsg['type']    = 4;
                    $paramMsg['mtype']   = 41;
                    $paramMsg['name']    = '投资奖励到账';
                    $paramMsg['content'] = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                            投资项目：" . $data['name'] . '<br>' . "
                                            投资金额：￥" . $info['account'] . '<br>';
                    add_message_app($paramMsg);
                    unset($paramMsg);
                }
                else
                {
                    $multiple = $awardParam[0]['multiple'];
                    $money    = $awardParam[0]['value'];
                    if (floor($money) < 1)
                    {
                        debug_info('notMeet_two', '奖励金额：' . $money, $info['user_id'], $item);
                        return FALSE;
                    }
                    $ret                 = $this->redBackData($rpRule, $info['user_id'], $money, $multiple, $investLogId, $item, $borrowNum);
                    //发送站内信                 
                    $content["title"]    = "您已收到投资奖励";
                    $content["body"]     = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                        投资标名：" . $data['name'] . '<br>' . "
                                        投资金额：￥" . $info['account'] . '<br>' . "
                                        
                                        请于您的百宝箱查看。" . '<br>' . "
                                        愿健康与快乐天天伴随您！";
                    $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
                    unset($content);
                    //app消息推送
                    $paramMsg['user_id'] = $info['user_id'];
                    $paramMsg['type']    = 4;
                    $paramMsg['mtype']   = 41;
                    $paramMsg['name']    = '投资奖励到账';
                    $paramMsg['content'] = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                            投资项目：" . $data['name'] . '<br>' . "
                                            投资金额：￥" . $info['account'] . '<br>';
                    add_message_app($paramMsg);
                    unset($paramMsg);
                }
                return $ret;
            }
            elseif ($rpRule['userRuleType'] == 2003)
            {
                $multiple = $data['rule_info']['multipleTotal'];
                $money    = $data['rule_info']['minValue'];
                if (floor($money) < 1)
                {
                    debug_info('notMeet_two', '奖励金额：' . $money, $info['user_id'], $item);
                    return FALSE;
                }
                $ret                 = $this->redBackData($rpRule, $info['user_id'], $money, $multiple, $investLogId, $item, $borrowNum);
                //发送站内信                 
                $content["title"]    = "您已收到投资奖励";
                $content["body"]     = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                    投资标名：" . $data['name'] . '<br>' . "
                                    投资金额：￥" . $info['account'] . '<br>' . "
                                    请于您的百宝箱查看。" . '<br>' . "
                                    愿健康与快乐天天伴随您！";
                $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
                unset($content);
                //app消息推送
                $paramMsg['user_id'] = $info['user_id'];
                $paramMsg['type']    = 4;
                $paramMsg['mtype']   = 41;
                $paramMsg['name']    = '投资奖励到账';
                $paramMsg['content'] = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                            投资项目：" . $data['name'] . '<br>' . "
                                            投资金额：￥" . $info['account'] . '<br>';
                add_message_app($paramMsg);
                unset($paramMsg);
                return $ret;
            }
        }
        elseif ($item['rule_type'] == 3 && $item['award_type'] == 2)
        {

            //固定值/现金
            $money   = $checkId = $item['award_info']["month_" . "{$data['time_limit']}"];
            if (!$money)
            {
                debug_info('notMeet_two', '投资奖励不满足奖励条件(当前月份没有奖励)' . $info['user_id'], $data['borrow_num'] . '---' . $info['account']);
                return FALSE;
            }
            if (floor($money) < 1)
            {
                debug_info('notMeet_two', '奖励金额：' . $money, $info['user_id'], $item);
                return FALSE;
            }
            $ret                 = $this->addMoney($data, $info['user_id'], $money, $investLogId);
            //发送站内信                 
            $content["title"]    = "您已收到投资奖励";
            $content["body"]     = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                投资标名：" . $data['name'] . '<br>' . "
                                投资金额：￥" . $info['account'] . '<br>' . "
                                投资奖励：￥" . intval($money) . '<br>' . "
                                愿健康与快乐天天伴随您！";
            $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
            unset($content);
            //app消息推送
            $paramMsg['user_id'] = $info['user_id'];
            $paramMsg['type']    = 4;
            $paramMsg['mtype']   = 41;
            $paramMsg['name']    = '投资奖励到账';
            $paramMsg['content'] = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                            投资项目：" . $data['name'] . '<br>' . "
                                            投资金额：￥" . $info['account'] . '<br>' . "
                                            投资奖励：￥" . intval($money) . "元";
            add_message_app($paramMsg);
            unset($paramMsg);
            return $ret;
        }
        elseif ($item['rule_type'] == 3 && $item['award_type'] == 3)
        {

            //固定值/增值券
            $checkId = $item['award_info']["month_" . "{$data['time_limit']}"];
            if (!$checkId)
            {
                debug_info('notMeet_two', '投资奖励不满足奖励条件(当前月份没有奖励)' . $info['user_id'], $data['borrow_num'] . '---' . $info['account']);
                return FALSE;
            }
            $ruleInfo = $appreciationmModel->getOne($checkId);
            if (floor($ruleInfo['money']) < 1)
            {
                debug_info('notMeet_two', '奖励金额：' . $ruleInfo['money'], $info['user_id'], $item);
                return FALSE;
            }
            $type                 = 1;
            $awardType            = 4;
            $appInfo['user_id']   = $info['user_id'];
            $appInfo['username']  = $info['username'];
            $appInfo['rule_info'] = $ruleInfo;
            $ret                  = $this->appreciationData($appInfo, $type, $awardType, $investLogId);
            unset($appInfo);
            //发送站内信                 
            $content["title"]     = "您已收到投资奖励";
            $content["body"]      = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                投资标名：" . $data['name'] . '<br>' . "
                                投资金额：￥" . $info['account'] . '<br>' . "
                                投资奖励：增值券" . floor($ruleInfo['money']) . "元" . '<br>' . "
                                请于您的百宝箱查看。" . '<br>' . "
                                愿健康与快乐天天伴随您！";
            $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
            unset($content);
            //app消息推送
            $paramMsg['user_id']  = $info['user_id'];
            $paramMsg['type']     = 4;
            $paramMsg['mtype']    = 41;
            $paramMsg['name']     = '投资奖励到账';
            $paramMsg['content']  = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                            投资项目：" . $data['name'] . '<br>' . "
                                            投资金额：￥" . $info['account'] . '<br>' . "
                                            投资奖励：增值券" . floor($ruleInfo['money']) . "元";
            add_message_app($paramMsg);
            unset($paramMsg);
            return $ret;
        }
        elseif ($item['rule_type'] == 3 && $item['award_type'] == 4)
        {

            //固定值/兑换券
            $checkId = $item['award_info']["month_" . "{$data['time_limit']}"];
            if (!$checkId)
            {
                debug_info('notMeet_two', '投资奖励不满足奖励条件(当前月份没有奖励)' . $info['user_id'], $data['borrow_num'] . '---' . $info['account']);
                return FALSE;
            }
            $ruleInfo = $appreciationmModel->getOne($checkId);
            if (floor($ruleInfo['money']) < 1)
            {
                debug_info('notMeet_two', '奖励金额：' . $ruleInfo['money'], $info['user_id'], $item);
                return FALSE;
            }
            $type                 = 2;
            $awardType            = 5;
            $appInfo['user_id']   = $info['user_id'];
            $appInfo['username']  = $info['username'];
            $appInfo['rule_info'] = $ruleInfo;
            $ret                  = $this->appreciationData($appInfo, $type, $awardType, $investLogId);
            unset($appInfo);
            //发送站内信                 
            $content["title"]     = "您已收到投资奖励";
            $content["body"]      = "尊敬的" . $info['username'] . "，您已收到来自系统的投资奖励。详细情况如下：" . '<br>' . "
                                投资标名：" . $data['name'] . '<br>' . "
                                投资金额：￥" . $info['account'] . '<br>' . "
                                投资奖励：兑换券" . floor($ruleInfo['money']) . "元" . '<br>' . "
                                请于您的百宝箱查看。" . '<br>' . "
                                愿健康与快乐天天伴随您！";
            $task->Sendmsg(1, $info['user_id'], $content["title"], $content["body"], '127.0.0.1');
            unset($content);
            //app消息推送
            $paramMsg['user_id']  = $info['user_id'];
            $paramMsg['type']     = 4;
            $paramMsg['mtype']    = 41;
            $paramMsg['name']     = '投资奖励到账';
            $paramMsg['content']  = "投资时间：" . date('Y/m/d H:i', time()) . '<br>' . "
                                            投资项目：" . $data['name'] . '<br>' . "
                                            投资金额：￥" . $info['account'] . '<br>' . "
                                            投资奖励：兑换券" . floor($ruleInfo['money']) . "元";
            add_message_app($paramMsg);
            unset($paramMsg);
            return $ret;
        }
    }

    /**
     * @param array $info 奖励相关数据
     * @param int $type 类型【1：增值券；2：兑换券】
     * @param int $awardType 类型【4：增值券；5：兑换券】
     * * */
    public function appreciationData($info, $type, $awardType, $investLogId)
    {
        //卡券要入4个数据表，user_appreciation_list，user_appreciation，user_appreciation_rule，treasure_chest
        $userAppListModel    = new UserAppreList();
        $userAppRuleMode     = new UserAppreRule();
        $trearueChestModel   = new TreasureChest();
        $data['user_id']     = $info['user_id'];
        $data['username']    = $info['username'];
        $data['num']         = 1;
        $data['addtime']     = time();
        $data['editor_id']   = 1;
        $data['editor_name'] = 'admin';
        $data['type']        = $type;
        $listID              = $userAppListModel->add($data);
        $model               = $this->getModelRateById($info['rule_info']); //通过模板查找模板信息以及对应的增长率信息

        $modelInfo                = $model['model']; //增值券信息
        $rateArr                  = $model['rate']; //增值券增长率           
        $userData['user_id']      = $info['user_id'];
        $userData['username']     = $info['username'];
        $userData['aid']          = $modelInfo['id']; //增值券规则ID
        $userData['name']         = $modelInfo['name'];
        $userData['remark']       = '投资奖励';
        $userData['money']        = $modelInfo['money']; //奖品值
        $userData['status']       = 1;
        $userData['instructions'] = $modelInfo['instructions']; //奖品有效期结束时间
        $userData['start_time']   = $modelInfo['immediate_effect'] == 1 ? time() : strtotime($modelInfo['start_time']); //增值券开始时间
        $rate                     = end($rateArr); //获取最后一个
        $month                    = $rate['months'];
        $typeRate                 = $rate['type']; //天还是月
        if ($typeRate == '1')
        {
            $endTime = strtotime(" + " . $month . " month", strtotime(date("Y-m-d", $userData['start_time'])));
        }
        else
        {
            $endTime = strtotime(" + " . $month . " day", strtotime(date("Y-m-d", $userData['start_time'])));
        }
        $userData['end_time']  = $endTime; //增值券结束时间                        
        $userData['add_time']  = time();
        $userData['send_id']   = 1;
        $userData['send_name'] = 'admin';
        $userData['type']      = $type;
        $userData['list_id']   = $listID; //itd_user_appreciation_list表对应的ID
        //发送给用户的增值信息入库
        $userDataID            = $userAppListModel->add($userData);
        $monthStr              = '';
        //发送给用户的增值率入库
        foreach ($rateArr as $k => $v)
        {
            $ruleData['aid']             = $userDataID;
            $ruleData['user_id']         = $info['user_id'];
            $ruleData['months']          = $v['months'];
            $ruleData['appreciate_rate'] = $v['appreciate_rate'];
            $ruleData['addtime']         = time();
            $ruleData['type']            = $typeRate;
            $ruleId                      = $userAppRuleMode->add($ruleData);
            unset($ruleData);
            if ($typeRate == '1')
            {//月
                $monthStr .= "," . $v['months'];
            }
            else
            {//天
                $monthStr .= "," . $v['months'];
            }
            unset($v);
        }

        $uniquneCode                   = uniqueCodeCreate();
        //百宝箱入库
        $treasureData['user_id']       = $info['user_id'];
        $treasureData['award_type']    = $awardType;
        $treasureData['value']         = floor($info['rule_info']['money']);
        $treasureData['end_time']      = $endTime;
        $treasureData['add_time']      = time();
        $treasureData['startTime']     = $userData['start_time'];
        $treasureData['awardRuleId']   = $userDataID; //填写发送到user_appreciation的ID
        $treasureData['ruleType']      = 1002; //类型加息券
        $treasureData['drawType']      = 3001; //手动
        $treasureData['status']        = 0;
        $treasureData['awardSoleCode'] = $uniquneCode;
        $treasureData['remark']        = '投资奖励';
        $treasureData['extrasInfo']    = "增值率:" . substr($monthStr, 1); //增值率中月份拼接成的字符串
        $treasureId                    = $trearueChestModel->add($treasureData);
        $investLogModel                = new InvestLog();
        $investLogModel->edit(['send_id' => $treasureId], $investLogId);
        unset($treasureData);
        return $treasureId;
    }

    /**
     * 通过模板查找模板信息以及对应的增长率信息
     */
    private function getModelRateById($info)
    {
        $data = array();
        if ($info)
        {
            $immediateEffect    = $info['immediate_effect'];
            $startTime          = $immediateEffect == 1 ? "即时生效" : date("Y-m-d H:i", $info['start_time']);
            $info['start_time'] = $startTime;
            $autoWhere['aid']   = array("eq", $info['id']);
            $appreModel         = new Appreciation();
            $sqlArr             = [
                'field' => 'months,appreciate_rate,type',
                'where' => $autoWhere,
                'order' => 'id asc'
            ];
            $rate               = $appreModel->getList($sqlArr);
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
     * * */
    public function getRand($proArr)
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



    /**
     * @param array $rpRule 红包规则
     * @param int $userId 用户id
     * @param int $money 红包金额
     * @param int $multiple 红包使用限制
     * * */
    public function redBackData($rpRule, $userId, $money, $multiple, $investLogId, $item, $borrowNum)
    {

        $money                    = floor($money); //奖励金额取整
        $start_time               = $rpRule['timeEffect'] == 1 ? strtotime(date("Y-m-d H:i:s")) : $rpRule['ruleStartTime']; //奖品有效期开始时间
        $startTimeStr             = strtotime(date("Y-m-d", $start_time)); //获取开始时间的零点
        $endTime                  = $startTimeStr + $rpRule['ruleValidDay'] * 86400 - 1; //奖品有效期结束时间(包含开始时间那一天)
        $data['user_id']          = $userId;
        $data['award_type']       = 0; //现金券
        $data['user_constraint']  = $money * $multiple; //使用约束
        $data['value']            = $money; //奖品值
        $data['startTime']        = $start_time; //奖品有效期开始时间
        $data['end_time']         = $endTime; //奖品有效期结束时间
        $data['add_time']         = time(); //奖品添加时间
        $data['borrowType']       = $rpRule['borrowType']; //投标类型（1.企业9.创业6.净值7.股权11.工薪），多种类型用逗号分隔
        $data['borrowTimeLimit']  = isset($rpRule['borrowTimeLimit']) ? $rpRule['borrowTimeLimit'] : 0; //投标期限1.不限2.天标,0.没填
        $data['borrowStartMonth'] = $rpRule['borrowStartMonth']; //投标期限起始月份
        $data['borrowEndMonth']   = $rpRule['borrowEndMonth']; //投标期限结束月份
        $data['awardRuleId']      = $rpRule['id']; //奖品规则id
        $data['ruleType']         = $rpRule['ruleType']; //规则类型:1001.红包1002.加息券1003.体验金
        $data['drawType']         = 3002; //抽奖类型:3001.自动3002.手动
        $data['status']           = 0; //奖品状态
        $data['remark']           = '投资奖励'; //备注
        $data['in_borrow_num']    = $borrowNum;
        $treasureChestModel       = new TreasureChest();
        $investLogModel           = new InvestLog();
        if ($item['rule_type'] == 3 && $item['award_type'] == 1)
        {
            $investCollectionModel = new InvestCollection();
            $field                 = 'id,user_id,username,award_money';
            $sqlArr                = [
                'field' => $field,
                'where' => ['borrow_num' => $borrowNum, 'user_id' => $userId, 'status' => 2]
            ];
            $userList              = $investCollectionModel->getList($sqlArr);
            if ($userList)
            {
                foreach ($userList as $userValue)
                {
                    if ($userValue['award_money'] >= 10000)
                    {
                        $addData[] = $data;
                    }
                }
                if ($addData)
                {
                    $id = $treasureChestModel->addAll($addData);
                }
            }
            unset($addData);
        }
        else
        {
            $id = $treasureChestModel->add($data);
        }
        unset($data);
        $investLog = $investLogModel->getOne($investLogId);
        if ($investLog['send_id'])
        {
            $idsave = $id . ',' . $investLog['send_id'];
        }
        else
        {
            $idsave = $id;
        }
        $investLogModel->edit(['send_id' => $idsave], $investLogId);
        return $id;
    }

    /**
     * @desc 函数：获取一条进行中的活动
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return void
     */
    private function _getOneActivity()
    {
        $where['award_tpl_id']     = array('GT', 0);
        $where['status']           = 1;
        $where['choice_award_tpl'] = 1;
        $where['activities_start'] = array('LT', time());
        $where['activities_end']   = array('GT', time());
        $model                     = new AwardActivitiesInfo();
        return $model->getOneByWhere(['where' => $where]);
    }



}
