<?php
namespace application\common\model\user;

use application\common\model\Base;
/**
 * @uses 投资融资-开心利是白名单
 * @author jhl
 * @date 2017/07/04
 */
class AuthorityAppLoginAuth extends Base
{    
    
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'authority_app_login_auth';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'user_id';
    
}
