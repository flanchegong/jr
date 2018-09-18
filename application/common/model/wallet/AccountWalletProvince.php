<?php

namespace application\common\model\wallet;

use application\common\model\Base;

/**
 * @uses 我的账户-用户业务提醒设置表
 * @author jhl
 */
class AccountWalletProvince extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'account_wallet_province';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'province_id';
    
}
