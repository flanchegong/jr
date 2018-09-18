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

class AutoInvestAccountDetail extends  Base
{
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'item_auto_invest_user_account_detail';

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
     * @desc 函数： 获取红包详情
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param $id
     * @param $uid
     * @param string $page
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getRedPacketDetail($id, $uid, $page='',$listRows=20)
    {
        $field = 'red_packet_id,red_packe_amount,red_packe_invest_amount_min,invest_income,is_red_packet_use';
        $where['b.auto_invest_user_account_detail_id'] = $id;
        $where['a.user_id'] = $uid;
        $list=  Db::name($this->_table)
                ->alias('a')
                ->field($field)
                ->where($where)
                ->join('item_auto_invest_user_account_detail_red_packet b','b.auto_invest_user_account_detail_id=a.id')
                ->order('is_red_packet_use asc,red_packe_amount asc ')
                ->paginate($listRows, false, ['page' => $page]);
        return $list;


    }

}
