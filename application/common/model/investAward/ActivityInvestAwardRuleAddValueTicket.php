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

class ActivityInvestAwardRuleAddValueTicket extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'activity_invest_award_rule_add_value_ticket';
    
    /**
     * 获取奖励增值券规则
     * @param int $item_id 活动id
     * @param int $rule_id 规则id
     *  @param int $item_period_number 标期限
     * **/
    public function getActivityInvestAwardRuleAddValueTicket($item_id, $rule_id, $item_period_number)
    {
        if ($item_period_number > 12)
        {
            $item_period_number = 12;
        }
        $info = parent::getOneByWhere(['where'=>['invest_award_item_id' => $item_id, 'invest_award_rule_id' => $rule_id, 'item_period_number' => $item_period_number]]);
        if ($info['add_value_ticket_rule'] > 0)
        {
            return $info;
        }
        return FALSE;
    }

}
