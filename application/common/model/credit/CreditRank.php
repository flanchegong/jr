<?php

namespace application\common\model\credit;

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
use application\common\model\Base;

class CreditRank extends Base
{

     /**
     * @desc 函数：获取用户等级权益
     * @author liujian
     * @date 2017-4-7
     * @access public
     * @return void
     */
    public function getRank($lv)
    {
        $field           = 'name,rank,pic,exchangeRate,interestManagementFee as interest_manage_fee,freeTimes,memberFeeOne,memberFeeTwo';
        $where['point1'] = array('elt', $lv);
        $where['point2'] = array('egt', $lv);
        $rs              = $this->where($where)->field($field)->find()->toArray();
        if ($rs != false && $rs != null)
        {
            return $rs;
        }
        else
        {
            return false;
        }
    }

}
