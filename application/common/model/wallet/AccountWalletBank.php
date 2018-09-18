<?php

namespace application\common\model\wallet;

use application\common\model\Base;

/**
 * @uses 我的账户-我的钱包-银行信息
 * @author jhl
 */
class AccountWalletBank extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'account_wallet_bank';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'bank_id';
    
}
