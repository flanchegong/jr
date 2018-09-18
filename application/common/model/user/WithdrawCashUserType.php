<?php

/**
 * 投资融资-提现用户类型
 * @Author lingyq
 * @Version stable 1.0
 * @Date 2017-12-27
 * @Description 用户模型类
 */

namespace application\common\model\user;

use think\Db;
use application\common\model\Base;
use think\Cache;
use DateTime;

class WithdrawCashUserType extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'withdraw_cash_user_type';

    /**
     * 数据入库
     * @param type $data
     */
    public function insertInfo($data){   
        Db::name($this->_table)->insert($data);
    }
    
    /**
     * 获取用户信息
     * @param type $userId
     * @return type
     * @author lyq
     */
    public function getInfoByUserId($userId){
        //查询表
        $where['user_id'] = array('eq', $userId);
        $info         = Db::name($this->_table)->where($where)->find();
        return empty($info) ? array() : $info;
    }
}
