<?php

namespace application\common\model\wallet;

use application\common\model\Base;

/**
 * @uses 我的账户-我的钱包-银行支行信息
 * @author jhl
 */
class AccountWalletBankBranch extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'account_wallet_bank_branch';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'bank_branch_unionpay_no';
    
}
