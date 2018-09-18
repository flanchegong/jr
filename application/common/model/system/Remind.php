<?php

namespace application\common\model\system;

use application\common\model\Base;

/**
 * @uses 基础资料-网站业务类型
 * @author jhl
 */
class Remind extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'remind';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';
    
}
