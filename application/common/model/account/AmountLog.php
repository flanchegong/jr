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
namespace application\common\model\account;

use application\common\model\Base;
class AmountLog extends Base
{
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'iuseramount_log';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';
}