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

class InvestActivity extends Base
{
    protected $_database = 'itdb_hd';
        
    
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'invest_activity';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'a_id';
    
    public function getActivityInfo()
    {
        $fields = "a.a_id,a.a_status,a.a_start_time,a.a_end_time,a.a_title,r.id,r.least_money,r.max_money,r.borrow_type,r.rule_type,r.award_type,r.lottery_type,r.award_info";
        $sql    = "SELECT  $fields FROM "
                . "wy_invest_activity a "
                . "LEFT JOIN wy_invest_rule r ON a.a_id=r.activity_id "
                . "WHERE a.a_title='投资奖励' AND a.a_status=1 ORDER BY a.a_id ASC";
        $tmp    = Db::connect('huo_dong')->query($sql);
        Myredis::getRedisConn(4)->setToHash('invest_reward_two', 682, $tmp);
        return $tmp;
    }
    
    
    

    
    

}
