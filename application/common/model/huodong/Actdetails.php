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

namespace application\common\model\huodong;

use application\common\model\Base;
use application\common\logic\Myredis;

class Actdetails extends Base
{
    protected $_database = 'huo_dong';
    
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'activities_details';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';
    
    public function getActivityList()
    {
        $rs   = Db::connect('huo_dong')
                ->name('activities_details')
                ->alias('d')
                ->field('d.rid,a.id,a.activities_name')
                ->join(['wy_activities_rebate a', ' d.a_id = a.id ', 'inner'])
                ->select();
        $data = [];
        foreach ($rs as $k => $v)
        {
            $data[$v['rid']] = $v;
        }
        unset($rs);
        return $data;
    }
}