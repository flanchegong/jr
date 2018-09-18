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
use think\Db;

class ActivityInvestAwardItem extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'activity_invest_award_item';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

    /**
     * 获取正在进行的活动
     * * */
    public function getActivityInvestAwardItemInfo($type=0)
    {
        if($type){
            //判断投资奖励-资产奖励开关是否开启【1：开启，0：关闭】
            if(!Db::name('variable')->where(array('key'=>'SYS_INVEST_REWARD'))->value('value')){
                return FALSE;
            }
        }
        $time                         = date("Y-m-d H:i:s");
        $where['activity_time_start'] = array('ELT', $time);
        $where['activity_time_end']   = array('EGT', $time);
        $where['activity_status']     = 1;
        return parent::getOneByWhere(['where' => $where]);

    }

}
