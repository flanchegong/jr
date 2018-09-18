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
use application\common\Myredis;
use think\Db;

class AutoFinancingLog extends Base
{
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'item_auto_financing_log';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

    /**
     * @desc 函数：获取还在募集中的超过2小时10分未满标的标列表
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return array
     */
    public function getBorrowList()
    {
        $where['b.status'] = [
            '=',
            1
        ];
        $date = Myredis::getRedisConn()->get('end_borrow_date');
        $date = $date?$date:130;
        $list = Db::name('item_auto_financing_log')->alias('a')->field('a.financing_time,b.borrow_num,b.user_id')
                  ->join('itd_iborrow b', 'a.user_id=b.user_id and a.item_id=b.id')->where($where)
                  ->where('CEIL((UNIX_TIMESTAMP(NOW())-addtime)/60) >='.$date)->select();
        return $list;
    }

    /**
     * @desc 函数：获取已经融资的金额
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param  int $id
     * @return float|int
     */
    public function getAlreadyFinancingAmount($id = 0 )
    {
        if ($id==0)
        {
            return false;
        }
        $where['auto_financing_rule_id'] = array(
            '=',
            $id
        );
        return Db::name('item_auto_financing_log')->where($where)->sum('financing_amount');
    }

}
