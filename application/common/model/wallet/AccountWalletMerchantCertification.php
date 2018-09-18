<?php

namespace application\common\model\wallet;

use application\common\model\Base;
use think\Db;

/**
 * @uses 我的账户-用户业务提醒设置表
 * @author jhl
 */
class AccountWalletMerchantCertification extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'account_wallet_merchant_certification';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'user_id';
    
    public function getCertificationInfo($userId)
    {
        $result = Db::name($this->_table)
                ->alias('a')
                ->field('a.user_name,a.enterprise_name,a.business_license_register_no,a.bank_account,a.bank_name,a.business_operator_name,b.card_id,b.realname')
                ->join('itd_iuser b', 'a.user_id=b.user_id', 'left')
                ->where(['a.user_id' => $userId])
                ->find();
        return $result;
    }
    
    /**
     * @uses 同实名检测
     * @param array $where
     */
    public function accountcardIdRealNameStatus($where = [])
    {
        $result = Db::name($this->_table)
            ->alias('a')
            ->field('a.user_name')
            ->join('itd_iuser b', 'a.user_id=b.user_id', 'INNER')
            ->where($where)
            ->find();
        return isset($result['user_name']) ? true : false;
    }
}
