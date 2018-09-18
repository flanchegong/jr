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

namespace application\common\model\huodong;

use application\common\model\Base;

class SecondSite extends Base {

    protected $_database = 'itdb_hd';

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'second_site';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

    function __construct() {
        $this->connection = config("settings.huo_dong");
    }

}
