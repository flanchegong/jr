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

use application\common\model\Base;
use think\Log;

class TreasureChest extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'treasure_chest';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

    /**
     * 插入
     * @param array $data 插入数据
     * @return int 
     * * */
    public function insertTreasureChest($data)
    {
        try
        {
            return parent::add($data);
        }
        catch (\Exception $exc)
        {
            $info = $exc->getTrace(); //sql语句
            Log::write(sprintf("[投资奖励]红包发放错误：时间:%s,错误编码:%s,错误信息:%s,activity_invest_award_pay:错误SQL:%s,数据：%s", date('Y-m-d H:i:s'), $exc->getCode(), $exc->getMessage(), $info[0]['args'][0], json($data)), 'sql');
            return FALSE;
        }
    }

}
