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
namespace application\common\model\activity;

use application\common\model\Base;

class AwardRule extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'award_rule';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';
    public function getAward($id=0)
    {
        if (empty($id))
        {
            return false;
        }
        $field = 'a.value,a.multiple,a.splitNum,b.ruleType,b.drawType,b.ruleRemark,b.timeEffect,
                   b.ruleStartTime,bruleEndTime,b.borrowType,b.borrowTimeLimit,b.borrowStartMonth,
                   b.borrowEndMonth,b.userRuleType';
        $list = Db::name('award_param')
                  ->alias('a')
                  ->field($field)
                  ->join('itd_award_rule b', 'a.ruleId=b.id', 'left')
                  ->where(['a.ruleId'=>$id,'b.status'=>1])
                  ->select();
        return $list;
    }

}
