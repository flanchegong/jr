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
use think\Db;

class AwardActivitiesInfo extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'award_activities_info';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'pid';

    /**
     * @desc 函数：根据合作商id获取推广活动信息
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param int $partnerId 合作商id
     * @return mixed
     */
    public function getActivityRedPacket($partnerId=0)
    {
        if (empty($partnerId))
        {
            return false;
        }
        $list = Db::name('award_activities_info')
                  ->alias('a')
                  ->field('a.template_id,a.template_name,timeEffect,ruleStartTime,ruleEndTime,ruleValidDay,ruleRemark')
                  ->join('itd_award_rule b', 'a.template_id=b.id', 'left')
                  ->where(['a.partner_id'=>$partnerId])
                  ->select();
        if ($list)
        {
            foreach ($list as $k => $v)
            {
                $award = Db::name('award_param')->where(['ruleId'=>$v['template_id']])->select();
                foreach ($award as $key => $vv)
                {
                    $list[$k]['red_list'][$key]['value'] = $vv['value'];
                    $list[$k]['red_list'][$key]['multiple'] = $vv['multiple'];
                    $list[$k]['red_list'][$key]['splitNum'] = $vv['splitNum'];
                }
                $list[$k]['ruleStartTime'] = $v['timeEffect'] == 1 ? date('Y-m-d H:i:s',time()):date('Y-m-d H:i:s',$vv['ruleStartTime']);
                $list[$k]['ruleEndTime'] =  date('Y-m-d H:i:s',(strtotime($list[$k]['ruleStartTime']) + $v['ruleValidDay'] * 86400)-1);
            }
        }
        return $list;
    }

}
