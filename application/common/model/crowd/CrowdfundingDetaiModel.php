<?php

namespace application\common\model\crowd;

use application\common\model\Base;
use think\Db;

/**
 * @uses 众筹基础表
 * @author jhl
 */
class CrowdfundingDetaiModel extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'crowdfunding_invest_detail';

    function getUserCrowdfundingCollection($userId){
        $endTime = '2017-9-13 00:00:00';
        $where['i.crowdfunding_type'] = array('in','1,2');
        $where['i.crowdfunding_status'] = array('eq',4);
        $where['d.redeem_status'] = array('neq',2);
        $where['d.user_id'] = array('eq',$userId);
        $where['i.crowdfunding_full_time'] = array('elt',$endTime);
        $crowdfundingInvest = Db::name($this->_table)
        ->alias('d')
        ->field('SUM(d.investment_amount_actual) as money')
        ->join('itd_crowdfunding_item i','i.id = d.crowdfunding_item_id')
        ->where($where)
        ->find();  
        $investMoney = $crowdfundingInvest['money'] > 0 ? $crowdfundingInvest['money'] : 0;

        //减去投过 “四川雅堂电子商务股份有限公司” 的待收
        //减去投过 “红木项目1号续投” 的待收
        $oWhere['crowdfunding_item_id'] = array('in','86,162');
        $oWhere['user_id'] = array('eq',$userId);
        $specialCollection = Db::name($this->_table)->field("SUM(investment_amount_actual) as money")->where($oWhere)->find();
        $specialMoney = $specialCollection['money'] > 0 ? $specialCollection['money'] : 0;
        
        $money = $investMoney + $specialMoney;
        return $money;
    }
}
