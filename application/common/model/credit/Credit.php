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
namespace application\common\model\credit;

use think\Db;
use application\common\model\Base;

class Credit extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'credit';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'user_id';
    protected $_map        = array(
        'uid'                       => 'user_id',
        'credit_val'                => 'value',
    );

    /**
     * 获得用户积分详情
     * @param unknown $userId
     * @auther lingyq
     */
    public function getCreditInfo($userId)
    {
        $where['user_id'] = $userId;
        $info             = Db::name('credit')->where($where)->find();
        return $info ? $info : array();
    }

    /**
     * 添加新用户积分
     * @param type $credit
     * @param type $userId
     * @auther lingyq
     */
    public function addCredit($credit, $userId, $ip)
    {
        $data['value']    = $credit;
        $data['rank']     = 1;
        $data['lv_value'] = $credit;
        $data['user_id']  = $userId;
        $data['op_user']  = 1;
        $data['addtime']  = time();
        $data['addip']    = $ip;
        Db::name('credit')->insert($data);
    }

}
