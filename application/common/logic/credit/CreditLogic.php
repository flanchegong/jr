<?php

namespace application\common\logic\credit;

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
use think\Model;
use application\common\model\user\Iuser;
use think\Db;

class CreditLogic extends Model
{


    /**
     * @desc 函数：根据积分类型加/减积分
     * @author liujian
     * @date 2017-4-7
     * @param array $params ['user_id'=>用户id,'num'=>关联编码，'credit'=>变化积分，'remark'=>备注说明，'credit_type'=>积分操作类型]
     * @access public
     * @return bool
     */
    public function creditChange($params = [])
    {
        if (!isset($params['user_id']) || !isset($params['credit_type']) || !isset($params['credit']) || !isset($params['num']))
        {
            return false;
        }
        $params['title'] = isset($params['title']) ? $params['title'] : '';
        $creditType      = new CreditType();
        $creditTypeInfo  = $creditType->getCreditTypeByNid($params['credit_type']);
        if (!$creditTypeInfo)
        {
            return false;
        }
        $remark    = !isset($params['remark']) ? $creditTypeInfo['name'] : $params['remark'];
        $credit    = abs($params['credit']) > 0 ? $params['credit'] : $creditTypeInfo['value'];
        $creditLog = new CreditLog();
        $log       = $creditLog->addLog($params['user_id'], $creditTypeInfo['id'], $creditTypeInfo['op'], $credit, $remark, 1);
        $creditBorrowModel = new CreditBorrow();
        if (in_array($creditTypeInfo['id'], array(7, 8, 24, 34)))
        {
            $add = [
                'log_id'      => $log,
                'num'         => $params['num'],
                'credit_type' => $creditTypeInfo['id'],
                'uid'         => $params['user_id'],
                'title'       => $params['title'] . "|" . $params['num']
            ];
            $creditBorrowModel->add($add);
            unset($add);
        }
        //债权转让、受让积分入库
        if ($params['credit_type'] == 'debt_out' || $params['credit_type'] == 'debt_in')
        {
            $add = [
                'log_id'      => $log,
                'num'         => $params['num'],
                'credit_type' => $creditTypeInfo['id'],
                'uid'         => $params['user_id'],
                'title'       => $params['title']
            ];
            $creditBorrowModel->add($add);
            unset($add);
        }

        $updateCedit = abs($credit);
        $creditModel = new Credit();
        if ($params['credit_type'] == "exchange")
        {
            $creditModel->editByWhere(['value' => $updateCedit], ['user_id' => $params['user_id']]);
            return true;
        }
        if ($creditTypeInfo['op'] == 1 || $creditTypeInfo["op"] == 2)
        {
            $changeCredit = 0;
            if ($creditTypeInfo["op"] == 1)
            {
                $data['value']    = array('exp', "value+'$updateCedit'");
                $data['lv_value'] = array('exp', "lv_value+$updateCedit");
                $changeCredit     = $updateCedit;
            }
            else
            {
                $data['value']    = array('exp', "value-$updateCedit");
                $data['lv_value'] = array('exp', " if(lv_value-$updateCedit>=2,lv_value-$updateCedit,1)");
                $changeCredit     = -$updateCedit;
            }
            $creditModel->editByWhere($data, ['user_id' => $params['user_id']]);
            //根据分数变化返回来确定是否需要修正等级
            $rulesConfig = config('system.RULES');
            $credit      = new Credit();
            $userCredit  = $credit->getOneByWhere(['where' => ['user_id' => $params['user_id']]]);
            if (empty($userCredit))
            {
                return false;
            }
            $level            = $userCredit["rank"] - 1;
            $afterChangeValue = $credit['lv_value'] + $changeCredit;
            if ($afterChangeValue > $rulesConfig[$level]["range"]["max"] || $afterChangeValue < $rulesConfig[$level]["range"]["min"])
            {
                usleep(mt_rand(100, 300));
                $this->saveLv($params['user_id'], $afterChangeValue);
                Myredis::getRedisConn()->deleteFromHash('vip_rules', $params['user_id']);
            }
            return true;
        }
        return true;
    }

    /**
     * @desc 函数：更新积分
     * @author liujian
     * @date 2017-4-7
     * @access public
     * @return void
     */
    public function saveLv($userId, $lvValue = 0)
    {
        $creditModel = new Credit();
        if ($lvValue == 0)
        {
            $creInfo = $creditModel->getOneByWhere(['where' => ['user_id' => $userId]]);
            if (empty($creInfo))
            {
                return false;
            }
            $lvValue = $creInfo['lv_value'];
        }
        $iuserModel           = new Iuser();
        $creditRank           = new CreditRank();
        $rankInfo             = $creditRank->getRank($lvValue);
        $vipStatus            = $iuserModel->getOne($userId, 'vip_status');
        $where['user_id']     = $userId;
        $data['rank']         = $rankInfo['rank'];
        $data['pic']          = $rankInfo['pic'];
        $data['exchangeRate'] = $rankInfo['exchangeRate'];
        if ($vipStatus['vip_status'])
        {
            $data['interest_manage_fee'] = $rankInfo['interest_manage_fee'] ? $rankInfo['interest_manage_fee'] : 0;
        }
        else
        {
            //利息管理费
            $data['interest_manage_fee'] = 0.18;
            if ($rankInfo['rank'] >= 7)
            {
                $data['interest_manage_fee'] = $rankInfo['rank'] == 7 ? 0.02 : '0';
                //特殊例子：某用户原来是非VIP，也没有充值变成VIP，一短时间内，积分等级猛飚到7级以上，则要把VIP状态改为1
                $info['vip_status']          = 1;
                $info['vip_time']            = strtotime('2038-01-01 00:00:00');
                $iuserModel->editByWhere($info, $where);
            }
        }

        $data['freeTimes']    = $rankInfo['freeTimes'];
        $data['memberFeeOne'] = $rankInfo['memberFeeOne'];
        $data['memberFeeTwo'] = $rankInfo['memberFeeTwo'];
        $rs                   = $creditModel->editByWhere($data, $where);
        return $rs;
    }

    /**
     * 投资人积分规则
     * @param int   $userId    投资人userID
     * @param float $account    投资人投资金额
     * @param array $rules      vip等级对应的基数积分
     * @param int   $multiple   倍数
     */
    Public function creditTZ($userId, $account, $rules, $multiple)
    {
        $creditModel = new Credit();
        $info        = $creditModel->getOneByWhere(['where' => ['user_id' => $userId]]);
        $rank        = $info['rank']; //会员vip等级
        //积分规则:  投资金额/100 * 基数积分 * 倍数
        if ($rank >= 1)
        {
            $cardinalpoints = $rules[$rank - 1]['exchangeRate']; //跟会员等级对应的基数积分
            $jfitbt         = round($account / 100 * $cardinalpoints * $multiple);
        }
        else
        {
            $jfitbt = 0; //非vip，积分基数为0，则获取到的积分也就为0
        }
        return $jfitbt;
    }

    /**
     * 获取VIP配置信息
     *
     * @param int $credit 会员积分
     * @return array
     */
    public function getUserCreditRules($uid)
    {
        $config      = config("system.RULES");
        $creditModel = new Credit();
        $credit      = $creditModel->getOneByWhere(['where' => ['user_id' => $uid]], 'rank,lv_value,`value`,lv_value,min_withdrawal_count,min_withdrawal_count_use,min_withdrawal_cash,min_withdrawal_count,min_withdrawal_count_use');

        if ($credit > 0)
        {
            $level             = $credit['rank'] - 1;
            $rules             = $config[$level];
            $rules['credit']   = $credit["lv_value"];
            $rules["icon"]     = array(
                "account/vip/0/{$credit['rank']}.png",
                "account/vip/1/{$credit['rank']}.png"
            );
            $rules["value"]    = $credit['value'];
            $rules['lv_value'] = $credit['lv_value'];
            if ($credit['min_withdrawal_count'] - $credit['min_withdrawal_count_use'] > 0)
            { // 尚有可使用次数
                // 可低额提现的金额
                $rules['minimum_cash'] = $credit['min_withdrawal_cash'] > 0 ? $credit['min_withdrawal_cash'] : 100;
                // 可低额提现的次数
                $rules['left_num']     = $credit['min_withdrawal_count'] - $credit['min_withdrawal_count_use'];
            }
            else
            {
                // 可低额提现的金额
                $rules['minimum_cash'] = 100;
                // 可低额提现的次数
                $rules['left_num']     = 0;
            }
            return $rules;
        }
        else
        {
            // 可低额提现的金额
            $config[0]['minimum_cash'] = 100;
            // 可低额提现的次数
            $config[0]['left_num']     = 0;
            $config[0]["credit"]       = 0;
            $config[0]["value"]        = 0;
            $config[0]["icon"]         = array('account/vip/0/1.png', 'account/vip/1/1.png');
            return $config[0]; //非VIP
        }
    }
    
    /**
     * 积分变更
     * @param type $userId       积分变更者
     * @param type $opUserID     积分变更操作人
     * @param type $Exp          备注
     * @param type $type
     * @param type $relaID
     * auther lingyq
     * @return boolean
     */
    public function Upcredit($userId, $opUserID, $Exp, $type, $relaID, $ip)
    {
        if (empty($opUserID)) {
            return false;
        }
        $re = $this->creditCycle($userId, $type);
        if ($re) {
            $Exp = strtolower($Exp) == 'phone' ? "手机认证" : $Exp;
            
            $creditObj = new Credit();
            $creditInfo = $creditObj->getCreditInfo($userId);       
            if (!empty($creditInfo)) {//若已有积分，则对积分进行变更
                $this->creditChange(array("userId"=>$userId, "creditType"=>$Exp, "relevance"=>$relaID));
            } else {//若无积分账号，则新建账号
                $creditTypeObj = new CreditType();
                //获取认证积分数值
                $result = $creditTypeObj->getCreditTypeByName($Exp);
                $creditObj->addCredit($result['value'], $userId, $ip);
                
                //更新用户积分等级
                $this->saveLv($userId); 
                
                $creditLogObj = new CreditLog();
                $creditLogObj->addLog($userId, $type, $result['op'], $result['value'], $Exp, $opUserID);
            }
            return true;
        }
        return false;
    }

    
    /**
     * 判断积分周期
     * @param type $userId
     * @param type $type
     * @auther lingyq
     * @return boolean
     */
    public function creditCycle($userId, $type)
    {
        $where['id'] = $type;
        $creditType = new CreditType();
        $rsCTypeData = $creditType->getCreditTypeOne('value,award_times,cycle', $where);
        
        $opportunity = false;
        switch ((integer) $rsCTypeData['cycle'])
        {
            case 1:
                $creditLogObj = new CreditLog();
                $coun = $creditLogObj->getUserLogCountByType($userId,$type);
                if ($coun == 0) $opportunity = true;
                break;
            case 4:
                $opportunity = true;
                break;
            default:
                break;
        }
        return $opportunity;
    }

}
