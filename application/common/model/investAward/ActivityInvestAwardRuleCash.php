<?php

/**
 * @Copyright (C), 2016, pandelin.
 * @Name $name
 * @Author pandelin
 * @Version stable 1.0
 * @Date: $date
 * @Description
 * 1. Example
 * @Function List
 * 1.
 * @History
 * pandelin $date     stable 1.0 第一次建
 */
namespace application\common\model\investAward;

use application\common\model\Base;

class ActivityInvestAwardRuleCash extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'activity_invest_award_rule_cash';

    /**
     * 获取奖励现金规则
     * @param int $item_id 活动id
     * @param int $rule_id 规则id
     *  @param int $item_period_number 标期限
     * * */
    public function getActivityInvestAwardRuleCash($item_id, $rule_id, $item_period_number)
    {
        if ($item_period_number > 12)
        {
            $item_period_number = 12;
        }
        $info = parent::getOneByWhere(['where'=>['invest_award_item_id' => $item_id, 'invest_award_rule_id' => $rule_id, 'item_period_number' => $item_period_number]]);
        if ($info['pay_rule_invest_ratio'] > 0 || $info['pay_rule_cash'] > 0)
        {
            return $info;
        }
        return FALSE;
    }

}
