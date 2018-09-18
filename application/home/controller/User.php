<?php
namespace application\home\controller;

use application\home\controller\Base;
use application\common\Myredis;
use application\common\model\account\AccountModel;
use application\common\logic\user\Iuser;
use application\common\model\user\Iuser as modelIuser;
use application\common\model\user\hapRedWhiteList;
use application\common\model\user\messageThree;
use think\Cookie;
/**
 *
 * @uses 金融用户相关接口
 * @author jhl
 * @data：2017/06/19
 */
class User extends Base
{

    public $userId;

    public $redis;
    
    public function _initialize()
    {
        parent::_initialize();
    }

    private function userId()
    {
        return parent::getLoginUserInfo('userId');
    }

    /**
     *
     * @uses 用户基础信息接口
     * @author jhl
     */
    public function userInfo()
    {
        $userId = self::userId();
        //登录状态
        if ($userId <= 0) {
            parent::okJson(array('loginStatus' => 0),0,'请先登录');
        }

        $modelIuser = new modelIuser();
        $userInfo = $modelIuser->getOneByWhere(array('where' => array('user_id' => $userId)), 'username,realname,real_status,card_id,email_status,phone_status,vip_status,FROM_UNIXTIME(vip_time) as vip_time,email,phone,IF(ISNULL(nickname),"",nickname) as nickname,status');
        
        $userInfo['loginStatus'] = 1;
        //portrait
        $logicIuser = new Iuser();
        $portrait = $logicIuser->getUserPortrait($userId);
        $userInfo['portraitNew'] = $portrait['portraitNew'];
        $userInfo['portraitDefault'] = $portrait['portraitDefault'];
        $userInfo['user_id'] = $userId;
        // 获取用户的总额 及可用余额
        $account = new AccountModel();
        $accountInfo = $account->getAccountInfoByUserId($userId);
        // 组装数据
        $userInfo['total'] = isset($_COOKIE['show_account']) ? '---' : number_format(strsubstr($accountInfo['total'] ? $accountInfo['total'] : 0.00), 2);
        $userInfo['use_money'] = isset($_COOKIE['show_account']) ? '---' : number_format(strsubstr($accountInfo['use_money'] ? $accountInfo['use_money'] : 0.00), 2);
        //vip等级图片
        $userInfo['icon'] = $logicIuser->getUserVipIcon($userId,$userInfo['vip_status']);
        
        $userInfo['unReadMsgInfo'] = self::unReadMsgInfo($userId);
        parent::okJson($userInfo,1,'请求成功');
    }

    /**
     *
     * @uses 用户股权投资权限、是否投资众筹
     * @author jhl
     * @param $userId:用户id
     */
    public function crowdfundingStatus()
    {
        $userId = self::userId();
        $result = array(
            'crowdFundingShow' => 0,
            'tenderCrowdStatus' => 0
        );
        if ($userId <= 0) {
            parent::okJson($result,'请先登录',1);
        }
        $crowdfundObj = new \application\common\logic\crowd\Crowdfunding();
        $power =Myredis::getRedisConn()->getFromHash('crowdfundpower', 'transverse' . $userId);
        if ($power == 1) { // 有权限
            $crowdfundingShow = 1;
        } else 
            if ($power == 2) { // 无权限
                $crowdfundingShow = 0;
            } else { // 缓存不存在则查询数据库
                $projectInfo = $crowdfundObj->getProject($userId);
                $crowdfundingShow = empty($projectInfo) ? 0 : 1;
            }
        // 用户投资众筹状态
        $tenderCrowdStatus = $crowdfundObj->hadTendCrowdfund($userId);
        
        $result = array(
            'crowdFundingShow' => $crowdfundingShow, // 股权投资权限
            'tenderCrowdStatus' => ($tenderCrowdStatus == true) ? 1 : 0
        );
        parent::okJson($result,'获取完成',1);
    }

    /**
     *
     * @uses 判断用户是否有权限发开心利是
     * @author jhl
     * @param $userId:用户id
     * @return true：有；false:无
     */
    public function getUserLuckyPowerStatus()
    {
        $userId = self::userId();
        $userId = 60754;
        if ($userId <= 0) {
            parent::failJson('请先登录',0);
        }
        $secondCacheName = 'home_financial_getusersecondhtml_user_id_'.$userId;
        $status = cache($secondCacheName);
        if ($status == 1) {
            parent::okJson(array(),1,'允许发布');
        } else {
            $whiteInfo = hapRedWhiteList::getWhite($userId);
            if (!empty($whiteInfo)) {
                //同时写入缓存
                cache($secondCacheName,1,10);
                parent::okJson(array('powerStatus' => 1),1,'有权限');
            } else {
                parent::okJson(array('powerStatus' => 0),1,'无权限');
            }
        }
    }
    
    /**
     *
     * @uses 用户未读消息总数接口
     * @author jhl
     * @param $userId:用户id
     */
    private function unReadMsgInfo($userId)
    {
        if (! $userId || $userId <= 0) {
            return 0;
        }
        $userinfoCacheName = 'home_unreadmsginfo_user_id_' . $userId;
        $cachedatas = cache($userinfoCacheName);
        if (empty($cachedatas)) {
            $cachedatas['userinfo_count'] = messageThree::unReadMsgCount($userId);
            cache($userinfoCacheName, $cachedatas, 20);
        }
        $msgcount = 0;
        if (isset($cachedatas['userinfo_count']) && $cachedatas['userinfo_count']) {
            $msgcount = isset($cachedatas['userinfo_count']) ? (($cachedatas['userinfo_count'] > 99) ? 99 : $cachedatas['userinfo_count']) : 0;
        }
        return $msgcount;
    }
    
    /**
     * 获取用户账户信息
     * 
     *  
     * 
     */
    public function getAccount() {
        $uid=input("post.uid/d");
        $account=new Account();
        $this->okJson($account->getAccountInfoByUserId($uid));
    }
    
    /**
     * 用户行为（登录、注册、找回密码）
     * @author lingyq
     * @date 2017-6-23
     */
    function userBehavior()
    {
        $urlArr = explode('*', input('url'));    
        $urlStr = isset($urlArr[1]) ? $urlArr[1] : '';
        if (empty($urlStr))
        {
            $data = array('status' => 0, 'message' => '请求数据错误');
        }
        else
        {
            $numArr  = explode(':', $urlStr);
            $typeNum = $numArr[1];
            $userObj = new Iuser();
            if ($typeNum == 1) //登录
            {
                $cookieStr = cookie::get('_yatang_ac_cookie_');          
                //$cookieStr = input('cookieStr');          
                $data = $userObj->login($cookieStr);
            }
            elseif ($typeNum == 2) //注册
            {
                $data = $userObj->register();
            }
            elseif ($typeNum == 3) //找回密码
            {
                $data = $userObj->findBackPwd();
            }
            else //请求数据错误
            {
                $data = array('status' => 0, 'message' => '请求数据错误');
            }
        }

        if ($data['status']) //成功
        {
            parent::okJson($data['info'], $data['status'], $data['message']);
        }
        else //失败
        {
            parent::failJson($data['message'], $data['status']);
        }
    }
    /**
     * 判断头像是否存在
     * @param int $userId Description
     * @author pandelin
     **/

    function getUserHeadIcon($userId)
    {
        $dir=Myredis::getRedisConn()->getFromHash('userHeadIcon', $userId);
        return $dir?$dir:SITE_YATANG_FULL.'/Public/Images/default.jpg';
    }
    
}





