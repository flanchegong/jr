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

namespace application\common\model\borrow;
use application\common\model\Base;
class ItemAutoInvest extends Base
{
    /**
	 * @desc  表名
	 * @var    string
	 * @access protected
	 */
	protected $_table = 'item_auto_invest';

	/**
	 * @desc   主键
	 * @var    string
	 * @access protected
	 */
	protected $_primaryKey = 'id';

    /**
     * @desc 自己调试日志的开关
     * @var string
     * @access protected
     */
    protected $_myLog = 0;

    /**
     * @desc sql输出日志的开关
     * @var string
     * @access protected
     */
    protected $_sqlLog = 0;
    
}