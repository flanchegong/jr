<?php

namespace application\common\model\wallet;

use application\common\model\Base;
use think\Db;

/**
 * @uses 我的账户-用户业务提醒设置表
 * @author jhl
 */
class AccountWalletCity extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'account_wallet_city';
    
    public function getJoinCityList($where = [],$order = '') {
        $result = Db::name($this->_table)
            ->alias('a')
            ->field('a.city_id,a.city_name,b.area_code')
            ->join('itd_account_wallet_city_huika b', 'a.province_id=b.province_id AND a.city_id=b.city_id', 'LEFT')
            ->where($where)
            ->order($order)
            ->select();
        return $result;
    }
    
}
