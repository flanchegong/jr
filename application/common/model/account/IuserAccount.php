<?php
namespace application\common\Model\account;

use think\Db;
use application\common\model\Base;
/**
 * Description of IuserAccount
 *
 * @author lyq
 * @date 2017-06-28 03:44:55
 */
class IuserAccount extends Base
{   
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'iaccount';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';
    
    public function createUserAccount($userId,$userName) {
        $data['user_id'] = $userId;
        $data['username'] = $userName;
        $data['total'] = 0;
        $data['use_money'] = 0;
        $data['nouse_money'] = 0;
        $data['collection'] = 0;
        $data['borrow_money'] = 0;
        Db::name("iaccount")->insert($data); 
    }
}
