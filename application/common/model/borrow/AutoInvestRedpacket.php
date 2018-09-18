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

class AutoInvestRedpacket extends  Base
{
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'item_auto_invest_user_account_detail_red_packet';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'red_packet_id';

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

    /**
     * @desc 函数：获取投资用户金额
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param array $where
     * @param string $order
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getInvestUserByMoney($where=[], $order='b.id asc')
    {
        $ret= Db::name($this->_table)
                 ->alias('a')
                 ->where($where)
                 ->join('itd_item_auto_invest_user_account_detail b','a.auto_invest_user_account_detail_id=b.id')
                 ->order($order)
                 ->find();
        return $ret;
    }

    /**
     * @desc 函数：获取不用红包详情
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param $id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getDoneDetail($id)
    {
        $filed = 'sum(red_packe_amount) as red_packe_amount,
                          sum(red_packe_invest_amount_min) as invest_amount,
                          sum(invest_income) as invest_income';
        $where['a.auto_invest_user_account_detail_id'] = $id;
        $ret= Db::name($this->_table)
                ->alias('a')
                ->field($filed)
                ->where($where)
                ->join('itd_item_auto_invest b','a.red_packet_id=b.red_packet_id')
                ->find();
        return $ret;
    }

}
