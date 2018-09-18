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
     * 根据条件获取活动列表
     * @param array $where 查询条
     * @param string $fields 查询字段
     * @param string $order [option] 排序
     * @return aarray Description
     * **/
    public function getAwardActivitiesInfoWhereSelect($where,$fields,$order='pid desc'){
        return Db::name($this->_table)->where($where)->field($fields)->order($order)->select();
    }

}
