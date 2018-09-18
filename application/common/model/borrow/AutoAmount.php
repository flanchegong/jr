<?php
/**
 * @Copyright (C), 2016, jiquan
 * @Name AutoFinancingRule.php
 * @Author liuj
 * @Version stable 1.0
 * @Date 2017-7-24
 * @Description 模型基类
 * @Class List
 *    1.
 * @Function List
 *    1.
 * @History
 * <author>  <time>              <version>    <desc>
 *  liuj   2017-07-24          stable 1.0   第一次建立该文件
 */
namespace application\common\model\borrow;

use application\common\model\Base;
use think\Db;

class AutoAmount extends  Base
{
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'item_auto_financing_rule_detail_financing_item_amount';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = '';

    public function getAutoFinancingRule()
    {
        $where['financing_time_start'] = ['>=',date('Y-m-d',time())];
        $where['financing_time_end'] = ['<=',date('Y-m-d',time())];
        $where['rule_status'] =1;
        Db::name($this->_table)->where()->find();
    }

}
