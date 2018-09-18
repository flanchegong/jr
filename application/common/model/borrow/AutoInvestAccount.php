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

class AutoInvestAccount extends  Base
{
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'item_auto_invest_user_account';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'user_id';


    /**
     * @desc   自增主键
     * @var    string
     * @access protected
     */
    protected $_autoIncrPrimaryKey = false;

    /**
     * @desc 自己调试日志的开关
     * @var string
     * @access protected
     */
    protected $_myLog = 0;

    /**
     * @desc sql输出日志的开关
     * @var string
     * @access protected
     */
    protected $_sqlLog = 0;


}
