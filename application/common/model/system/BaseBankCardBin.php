<?php

namespace application\common\model\system;

use application\common\model\Base;

/**
 * @uses 基础资料-银行卡发卡行标识代码
 * @author jhl
 */
class BaseBankCardBin extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'base_bank_card_bin';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'bank_card_issue_bin';
    
}
