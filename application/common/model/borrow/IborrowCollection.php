<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace application\common\model\borrow;

use application\common\model\Base;
use think\Db;

/**
 * 资产代收类
 * 20170626
 * @author gong
 */
class IborrowCollection extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'iborrow_collection';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';
    
    
    /**
     * @desc   字段映射
     * @var    string
     * @access protected
     */
    protected $_map = [
        'bnum'             => 'id',        //自增长IDsss
        'uid'              => 'user_id',   //待收人ID
        'uname'            => 'username',  //待收人名称
        'bnum'             => 'borrow_num',  //待收人名称
        'tnum'             => 'tender_num',//标的业务码
        'tstatus'          => 'status',    //投标的业务号
        'torder'           => 'order',     //期数
        'rtime'            => 'repay_time', //估计还款时间
        'ryestime'         => 'repay_yestime',//已经还款时间
        'raccount'         => 'repay_account',//需还金额
        'ryesaccount'      => 'repay_yesaccount',//实还金额
        'tinterest'        => 'interest',//利息
        'tcapital'         => 'capital',//本金
        'tmanagefee'       => 'interest_manage',//利息管理费
        'ldays'            => 'late_days',//逾期天数
        'linterest'        => 'late_interest',//逾期利息或罚金
        'taddtime'         => 'addtime',//增加时间
        'tpayby'           => 'payby',//他人付款【0：正常（默认）】
        'tsruserid'        => 'sr_userid',//受让者ID
        'tsrstatus'        => 'sr_status',//转让状态
        'tsrid'            => 'sr_id',//转让标的id
    ];

    /**
     * 代收收益
     * @param type $field
     * @param type $userId
     * @param type $status
     * @return type
     */
    public function waitForInterest($field, $userId, $status)
    {
        $where['status']  = $status;
        $where['user_id'] = $userId;
        $rs               = $this->where($where)->field("sum($field) as interest")->find();
        return $rs['interest'];
    }

    /**
     * @desc 函数：满标更新代收
     * @author liujian
     * @date 2017-5-9
     * @param string $borrowNum 标编码
     * @access public
     * @return bool
     */
    public function fullUpdateCollection($borrowNum)
    {
        return Db::execute("update itd_iborrow_collection bc  left join itd_iborrow_repayment br on br.borrow_num=bc.borrow_num and br.`order`=bc.`order` " . "set bc.repay_time=br.repayment_time,bc.`status`=0 where bc.borrow_num='{$borrowNum}' and bc.`status`=2");
    }

    /**
     * @desc 函数：获得还款更新代收
     * @author liujian
     * @date 2017-5-9
     * @param array $params 数组
     * @access public
     * @return bool
     */
    public function repayupdateCollection($params)
    {
        $sql = " UPDATE
                    itd_iborrow_collection a
                LEFT JOIN itd_iuser b ON b.user_id=a.user_id
                LEFT JOIN itd_credit c ON c.user_id=a.user_id
                SET
                    a.interest_manage=a.interest*IFNULL(if(vip_status,c.interest_manage_fee,0.18),0),
                    a.status=1,
                    repay_yestime=UNIX_TIMESTAMP(now()),
                    repay_yesaccount = repay_yesaccount+repay_account,
                    payby = {$params['payby']}
                WHERE
                    borrow_num = '{$params['borrow_num']}'
                AND a.`order` = {$params['reorder']}
                AND sr_status!=2
                AND a.status=0";
        return Db::execute($sql);
    }

    /**
     * @desc 函数：获取利息管理费
     * @author liujian
     * @date 2017-5-9
     * @param array $where 条件
     * @access public
     * @return array
     */
    public function getManageFree($where)
    {
        $list = Db::name('iborrow_collection')
                ->alias('a')
                ->field('a.user_id,-SUM(interest)*d.interest_management_fee_rate imf') 
                ->join('itd_account_member_info c', 'a.user_id=c.user_id', 'left')
                ->join('itd_account_member_level d','d.vip_level=c.vip_level','left')
                ->where($where)
                ->group('a.user_id')
                ->select();
        return $list;
    }

    /**
     * 获取单个用户的待收列表
     * @param type $userId              用户ID
     * @param type $borrowType          标类型
     * @param type $collectStartTime    待收区间开始时间
     * @param type $collectEndTime      待收区间结束时间
     * @param type $page                当前页码
     * @param type $pagesize            每页显示数据大小
     * @return type
     */
    public function getCollectionList($userId,$borrowType, $collectStartTime, $collectEndTime,$page,$pagesize)
    {    
        $field = "b.id,b.name as title,b.borrow_num,b.time_limit as total_period,c.repay_account as collect,c.repay_time as time,(c.order +1) as period";
        //获取待收列表
        $where['c.user_id'] = $userId;
        $where['c.status'] = 0;//待收
        if ($borrowType)
        {
            $where['b.borrow_type'] = $borrowType;
        }
        $where['c.repay_time'] = array(array('egt',$collectStartTime),array('elt',$collectEndTime));   
        $list = Db::name('iborrow')
                ->alias('b')
                ->join('itd_iborrow_collection c','c.borrow_num=b.borrow_num','inner')
                ->field($field)
                ->where($where)          
                ->paginate($pagesize, false, ['page' => $page])
                ->toArray();     
        return !empty($list) ? $list : array();
    }
    
    /**
     * 获取单个用户的已收数据
     * @param type $userId              用户ID
     * @param type $borrowType          标类型
     * @param type $collectStartTime    已收区间开始时间
     * @param type $collectEndTime      已收区间结束时间
     * @param type $page                当前页码
     * @param type $pagesize            每页显示数据大小
     * @return type
     */
    public function getReceivedList($userId,$borrowType, $receivedStartTime, $receivedEndTime,$page,$pagesize)
    {    
        $field = "b.id,b.name as title,b.borrow_num,b.time_limit as total_period,c.repay_account as collect,c.repay_time as time,case when b.time_limit = 1 then 1 else c.order +1 end  as period";
        //获取待收列表
        $where['c.user_id'] = $userId;
        $where['c.status'] = 1;//已收
        if ($borrowType)
        {
            $where['b.borrow_type'] = $borrowType;
        }
        $where['c.repay_time'] = array(array('egt',$receivedStartTime),array('elt',$receivedEndTime));   
        $list = Db::name('iborrow')
                ->alias('b')
                ->join('itd_iborrow_collection c','c.borrow_num=b.borrow_num','inner')
                ->field($field)
                ->where($where)     
                ->paginate($pagesize, false, ['page' => $page])
                ->toArray();     
        return !empty($list) ? $list : array();
    }    
    
    /**
     * 获取待收统计
     * @param type $field
     * @param type $where
     * @return type
     */
    public function getCollectionCount($userId,$borrowType, $collectStartTime, $collectEndTime){
        $field = "sum(c.interest) as interest, sum(c.capital) as capital";
        //获取待收列表
        $where['c.user_id'] = $userId;
        if ($borrowType)
        {
            $where['b.borrow_type'] = $borrowType;
        }
        $where['c.repay_time'] = array(array('egt',$collectStartTime),array('elt',$collectEndTime));   
        $list = Db::name('iborrow')
         ->alias('b')
         ->join('itd_iborrow_collection c','c.borrow_num=b.borrow_num','inner')
         ->field($field)
         ->where($where)       
         ->find();        
        
        $collectCount['income'] = isset($list['interest']) && isset($list['capital']) ? $list['interest'] + $list['capital'] : 0;
        $collectCount['interest'] = isset($list['interest']) ? $list['interest'] : 0;
        return $collectCount;
    }
    
    /**
     * 获取单个项目的待收列表
     * @param type $userId      用户ID
     * @param type $borrowNum   项目编码
     * @param type $page        页码
     * @param type $pagesize    每页展示数据条数
     * @author lingyq
     * @date  2017/7/24
     * @return type
     */
    public function getOneBorrowCollect($userId, $borrowNum, $page, $pagesize)
    {
        $field = "id,order,interest,repay_time,repay_yestime,repay_account,status";
        $where['user_id'] = $userId;
        $where['borrow_num'] = $borrowNum;
        $list = Db::name('iborrow_collection')
                ->field($field)
                ->where($where)
                ->paginate($pagesize, false, ['page' => $page])
                ->toArray();        
        return empty($list) ? array() : $this->parseFieldsMap($list);
    }
    
    
    /**
     * 获取某个项目的全部待收数据
     * @param type $userId        用户ID
     * @param type $borrowNum     项目编码
     * @author lingyq
     * @date  2017/7/24
     * @return type
     */
    public function getOneBorrowCount($userId, $borrowNum)
    {
        $field = "id,order,repay_time,repay_yestime,repay_account,status";
        $where['user_id'] = $userId;
        $where['borrow_num'] = $borrowNum;
        $list = Db::name('iborrow_collection')->field($field)->where($where)->select();
        return empty($list) ? array() : $list;
    }
}
