<?php

/**
 * Created by PhpStorm.
 * User: Gong
 * Date: 2017/6/19
 * Time: 17:10
 */

namespace application\common\model\account;

use think\Model;
use think\Db;
use application\common\Myredis;
use application\common\model\user\Iuser as UserModel;

class AccountModel extends Model {

    private $redis;
    private $modeName;

    public function __construct() {
        parent::__construct();
    }
    

    /**
     * 判断某用户是否有钱还款
     * @param unknown $userId
     * @param unknown $money
     * @return boolean
     * @author gong
     * @data 2014/08/20
     */
    public  function hasMoney($userId, $money = 0) {
        $res = Myredis::getRedisConn(8)->getHash('account_' . $userId);
        if (!isset($res['use_money'])) {
            $hashAcc = $this->getAccountInfoByUserId($userId);
            Myredis::getRedisConn(8)->setToHash('account_' . $userId, array('use_money' => sprintf("%.4f", $hashAcc['use_money']) * 10000));
            Myredis::getRedisConn(8)->expire('account_' . $userId, 604800);
            $res = Myredis::getRedisConn(8)->getHash('account_' . $userId);
        }
        if (($res['use_money'] / 10000) >= $money) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 
     * @param type $userId
     * @param type $accinfo
     * @return boolean
     * @author gong
     * @data 2014/08/20
     */
    public function batchSaveAccount($userId, $accinfo) {
        if (empty($userId)){
            return false;
        } 
        $accinfo['total'] = sprintf("%.4f", $accinfo['total']);
        $accinfo['use_money'] = sprintf("%.4f", $accinfo['use_money']);
        $accinfo['nouse_money'] = sprintf("%.4f", $accinfo['nouse_money']);
        $accinfo['collection'] = sprintf("%.4f", $accinfo['collection']);
        $accinfo['borrow_money'] = sprintf("%.4f", $accinfo['borrow_money']);
        Myredis::getRedisConn(8)->setToHash('account', 'acc' . $userId, $accinfo);
    }

    /**
     * 获取指定用户的账户信息
     * 
     * @param int $userId 用户ID
     * @return array
     * @author gong
     * @data 2014/08/20
     */
    public function getAccountInfoByUserId($userId) {
        if (empty($userId)){
            return false;
        }
        $hashAcc =[];
        $hashAcc = Myredis::getRedisConn(8)->getFromHash('account', 'acc' . $userId);
        //查询条件
        if (!isset($hashAcc['user_id'])) {
            $condition['user_id'] = $userId;
            $hashAcc = Db::name('iaccount')->where($condition)->field('*')->find();
            if (empty($hashAcc)) {
                $userModel = new UserModel();
                $userInfo = $userModel->getOne($userId);
                if (empty($userInfo))
                {
                    return false;
                }
                $data['user_id'] = $userId;
                $data['username'] = $userInfo['username'];
                $data['total'] = 0;
                $data['use_money'] = 0;
                $data['nouse_money'] = 0;
                $data['collection'] = 0;
                $data['borrow_money'] = 0;
                Db::name('iaccount')->insert($data);
                Myredis::getRedisConn(8)->setToHash('account', 'acc' . $userId, $data);
                return $data;
            }
            Myredis::getRedisConn(8)->setToHash('account', 'acc' . $userId, $hashAcc);
            //$hashAcc = Myredis::getRedisConn(8)->getFromHash('account', 'acc' . $userId);
            return $hashAcc;
        }
        return $hashAcc;
    }

    /**
     * 根据用户ID查询用户信息
     * @param type $userid
     * @return boolean
     */
    public function getRealnameByuserId($userid) {
        if (empty($userid)){
            return false;
        } 
        $cardId = Db::name('iuser')->field('card_id')->where(array('user_id' => $userid))->find();
        return $cardId['card_id'];
    }




    /**
     * 通过标编码修改账户统计详情
     *
     * @param  $bnum 标编码
     * @author zhangxj
     * @data 2017/07/17
     */
    public function updateAccountStatistics($bnum) {
    	if (empty($bnum)){
    		return [
                'status' => false,
                'msg'    => "无标编码",
            ];
    	}
    	
    	//查询条件
    		$where = "borrow_num ='$bnum'";
    		
    		//融资总额，支出奖励  financing  
    		$rs_b=M()->execute("INSERT INTO itd_business_count (user_id,financing,pay_award) SELECT user_id,sum(account_yes) ,sum(award_yes) FROM itd_iborrow WHERE  $where GROUP BY user_id "
    				. "ON DUPLICATE KEY UPDATE financing=VALUES(financing),pay_award=VALUES(pay_award)");
    		
    		//已还本金,利息  repayment
    		$rs_rcyes=M()->execute("INSERT INTO itd_business_count (user_id,repayment_capital,repayment_interest) SELECT user_id,sum(capital) ,sum(interest) FROM itd_iborrow_repayment WHERE `status`=1 and $where GROUP BY user_id "
    				. "ON DUPLICATE KEY UPDATE repayment_capital=VALUES(repayment_capital),repayment_interest=VALUES(repayment_interest)");
    		
    		//网站垫付总额  system_repayment
    		$rs_s=M()->execute("INSERT INTO itd_business_count (user_id,system_repayment) SELECT user_id,sum(repayment_account) sat FROM itd_iborrow_repayment WHERE `webstatus`=1 and $where GROUP BY user_id "
    				. "ON DUPLICATE KEY UPDATE system_repayment=VALUES(system_repayment)");
    		
    		//投资总额
    		$affectedRows = M()->execute("INSERT INTO itd_business_count (user_id, lending, award)
    		SELECT c.user_id,SUM(c.lending),SUM(c.award) from ((SELECT user_id,sum(account) as lending,sum(awardat) as award
    		FROM itd_iborrow_tender WHERE  $where GROUP BY user_id) ON DUPLICATE KEY UPDATE lending = VALUES (lending), award = VALUES (award)");
    	
    	 return [
            'status' => true,
            'msg'    => 'success',
         ];
    }
    /**
     * 获取用户账号数据
     * @param  type $userId  用户ID
     * @author lyq
     * @date   2017-12-27  
     */
    function getUserAccountInfo($userId){
        $where['user_id'] = array('eq', $userId);
        $info = Db::name('iaccount')->where($where)->find();
        return empty($info) ? array() : $info;
    }

} 