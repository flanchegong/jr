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

use think\Db;
use application\common\model\Base;

class ActivityAddCash extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'activity_add_cash';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';
    

    /**
     * 活动增加资金，并且N天不能提现(精确到天，比如今天赠送的金额，限制一天，则明天可提现;排除掉已经反向操作过的，即type=1的)
     * @param type $userId
     * @author lingyq
     * @date 2017-9-14
     * @return type
     */
    public function getActivityControlMoney($userId){
        $nowTime = strtotime(date('Y-m-d H:i:s'));
        $where['user_id'] = array('eq',$userId);
        $where['mention_time'] = array('gt',$nowTime);
        $where['type'] = array('eq',0);
        $controlMoney = Db::name($this->_table)->field("sum(money) as money")->where($where)->find();
        return  !empty($controlMoney['money']) ? $controlMoney['money'] : 0;
    }
}
