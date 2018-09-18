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

class ActivityInvestAwardPay extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'activity_invest_award_pay';

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
    public function insertActivityInvestAwardPay($data)
    {
        try
        {
            return parent::add($data);
        }
        catch (\Exception $exc)
        {
            $info = $exc->getTrace(); //sql语句
            Log::write(sprintf("[投资奖励]时间:%s,错误编码:%s,错误信息:%s,activity_invest_award_pay:错误SQL:%s", date('Y-m-d H:i:s'), $exc->getCode(), $exc->getMessage(), $info[0]['args'][0]), 'sql');
            return FALSE;
        }
    }

}
