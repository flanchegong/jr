<?php
/**
 * @Copyright (C), 2016, jiquan
 * @Name AutoInvestBuyVip.php
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

class AutoInvestBuyVip extends  Base
{
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'item_auto_invest_user_buy_vip';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

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

    /**
     * @desc 函数：获取账户信息
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getRenewalList()
    {
        $field = "a.user_id";
        $where['vip_time_end'] = date('Y-m-d');
        return  Db::name($this->_table)
                  ->alias('a')
                  ->field($field)
                  ->where($where)
                  ->join('itd_item_auto_invest_user_account b','b.user_id=a.user_id')
                  ->order('a.create_time desc')
                  ->find();
    }


}

