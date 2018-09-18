<?php

/**
 * @Copyright (C), 2017, lingyq
 * @Name Iuser.php
 * @Author lingyq
 * @Version stable 1.0
 * @Date 2017-6-23
 * @Description 用户模型类
 */
namespace application\common\logic\user;

use think\Db;
use application\common\model\user\Iuser as IuserModel;
use application\common\Model\account\IuserAccount;
use application\common\Model\account\ContinueInvest;
use application\common\logic\credit\CreditLogic;
use application\common\model\user\Plat;
use Crypt3Des;
use application\common\Myredis;
use application\common\logic\system\Task;
use application\common\model\system\QueenSms;
use application\common\model\user\IuserInvite;
use Exception;
use think\Cookie;
use think\Cache;
use Xxtea;

class Iuser
{    
    /**
     * 检测用户账号与密码是否正确，是否匹配
     * @param type $data
     * @return boolean
     * @author lingyq
     * @date 2017-6-23
     */
    public function checkUserLoginInfo($userName, $password)
    {
        if ($userName && $password)
        {
            $userObj  = new IuserModel();
            $field    = "password,username,user_id,pdw,status,phone";
            $userInfo = $userObj->getUserInfoByUserName($field, $userName);            
            if (!empty($userInfo))
            {
                $re = $this->checkUserPwd($userInfo, $password);
                return $re;
            }
            else
            {
                return false;
            }
        }
        return false;
    }

    /**
     * 传递的密码与数据库中的密码进行比较，是否一致
     * @param type $userInfo
     * @param type $password
     * @author lingyq
     * @return boolean
     */
    public function checkUserPwd($userInfo, $password)
    {
        if (!empty($userInfo['pdw']))
        {
            $md5pass = md5($password . config('settings.mkey'));
            if ($md5pass != $userInfo['pdw'])
            {
                return false;
            }
        }
        else
        {
            $md5pass = md5($password); //密码使用md5加密
            if ($md5pass != $userInfo['password'])
            {
                return false;
            }
        }
        return true;
    }

    /**
     * 用户注册
     * @return type
     * @author lingyq
     */
    public function register()
    {
        $decryptResult = $this->decryptYztCookie();
        if (!$decryptResult['status'])
        {//一账通Cookie解密失败
            return $decryptResult;
        }


        $cookieArr = $decryptResult['data'];        

        $userName       = $cookieArr['userName'];
        $password       = $cookieArr['pwd'];
        $phone          = $cookieArr['mobile'];
        $ip             = $cookieArr['ip'];
        $inviteCode     = $cookieArr['inviteCode'] ? $cookieArr['inviteCode'] : ''; //个人邀请码
        $inviteUserName = $cookieArr['inviteUserName'] ? $cookieArr['inviteUserName'] : ''; //邀请人用户名

        $userObj    = new IuserModel();
        //获取邀请者ID
        $inviterId  = 0;
        $inviterStr = $cookieArr['inviteUserName'] ? $cookieArr['inviteUserName'] : '';
        if (!empty($inviterStr))
        {
            $inviterInfo = $userObj->getUserInfoByUserName("user_id", $inviteUserName);
            $inviterId   = $inviterInfo['user_id'];
        }

        //注册用户
        $insertData = array('userName' => $userName, 'password' => $password, 'phone' => $phone, 'ip' => $ip, 'inviteCode' => $inviteCode, 'inviteUserName' => $inviteUserName);
        $userId     = $this->addUser($insertData);
        if (!empty($userId))
        {
            //更新用户最近本次登录IP以及登录时间
            $userObj->updateUserLoginIp($userId, $ip);

            //生成cookie，以及将用户ID，用户名存进cache
            $this->createCookie($userId, $userName, $password, $cookieArr);

            ###########UC同步注册登陆？？？############
            ##########消息推送？？？？###########
            ##########老推新？？？？###########
            return array('status' => 1, 'msg' => '注册成功');
        }
        else
        {
            return array('status' => 2, 'msg' => '注册失败');
        }
    }



    /**
     * 解密账户中心登陆后生成的cookie
     * author lingyq
     * @return type
     * @throws Exception
     */
    protected function decryptYztCookie()
    {
        try
        {
            $cookieString = cookie::get('_yatang_ac_cookie_');
            if (empty($cookieString))
            {
                throw new Exception('cookie不存在');
            }
           
            $cookieStr  = str_replace(" ", '+', $cookieString);
            $cookieInfo = Crypt3Des::decrypt($cookieStr, config('settings.yzt.YZT_COOKIE_KEY'));
            $cookieArr  = json_decode($cookieInfo, TRUE);
            
            if (!$cookieArr)
            {
                throw new Exception('一账通信息校验失败');
            }
            $password = Crypt3Des::decrypt($cookieArr['pwd'], config('settings.yzt.YZT_KEY'));
            if (empty($password))
            {
                throw new Exception('一账通信息校验失败');
            }
            $cookieArr['pwd'] = $password;
            if (empty($cookieArr['userName']) || empty($password) || empty($cookieArr['mobile']))
            {
                return array('status' => 0, 'message' => '一账通反馈数据出错');
            }

            return array('status' => 1, 'data' => $cookieArr);
        }
        catch (\Exception $exc)
        {
            return array('status' => 0, 'message' => $exc->getMessage());
        }
    }



    /**
     * 用户注册或登陆后生成cookie，并且把用户ID，用户名存进cache，供后续判断是否登陆使用
     * @param type $userId
     * @param type $userName
     * @param type $password
     * @param type $cookieArr
     * @author lingyq
     */
    private function createCookie($userId, $userName, $password, $cookieArr)
    {
        //设置登录cookie有效时间
        $cookieExpireTime = $cookieArr['cookieAge'] ? $cookieArr['cookieAge'] * 60 : 1800; //cookie有效期，单位分钟
        $cookieOverTime   = $cookieArr['cookieAge'] ? time() + $cookieArr['cookieAge'] * 60 : time() + 1800; //cookie有效期，单位分钟     
        $cookieEncryptKey = config('settings.cookie.cookie_encrypt_key');
        $cookieDomain     = config('settings.cookie.cookie_domain');

        $encryption   = new Xxtea();
        $tagCookieVal = md5(md5($userId . '_' .date("YmdHis"). '_' . $userName));
        Cookie::set('itbt_userkey_tag', $tagCookieVal, $cookieExpireTime);

        //为什么要把密码存在cookie里面?
        $authCookieVal = $encryption->encrypt($userName . "|" . $password, $cookieEncryptKey);
        Cookie::set('itbt_auth', $authCookieVal, ['path' => '/', 'secure' => false, 'domain' => $cookieDomain, 'expire' => $cookieExpireTime, 'httponly' => true]);

        //生成Cache
        $cacheObj = new Cache();
        $userLoginInfo = array('userId'=>$userId,'userName'=>$userName,'cacheOverTime'=>$cookieOverTime);
        $cacheObj->set($tagCookieVal, $userLoginInfo);
    }

    /**
     * 论坛同步登录
     * @param type $userName
     * @param type $password
     * @param type $time
     * @return type
     */
    public function uCenterLogin($userName, $password, $time)
    {
        import("@.ORG.Util.UcService");
        $ucService = new UcService();
        $ucInfo    = $ucService->uc_login($userName, $password, $time);

        if ($ucInfo == '用户不存在,或者被删除')
        {
            $ucService->uc_register($userName, $password, '');
            $ucInfo = $ucService->uc_login($userName, $password, $time);
        }
        elseif ($ucInfo == '密码错误')
        {
            //同步修改论坛密码
            $ucService->uc_user_change($userName, $password, $password, 1);
            $ucInfo = $ucService->uc_login($userName, $password, $time);
        }
        return $ucInfo;
    }

    /**
     * 获取客服ID（为新注册用户分配一个客服）
     * @param string $inviteUserId    邀请者用户ID
     * @param type $customerService   区域客服真实姓名
     * @return type
     * @author lingyq
     * @date 2017-6-23 
     */
    public function getCustomServiceId($inviteUserId = '', $customService = '')
    {
        $userObj = new IuserModel();
        if (empty($customService))
        {//如果区域客服不存在,则随机分配一个客服
            $serviceId = $userObj->getCustomServiceId('user_id');
        }
        else
        {
            $where['realname'] = $customService;
            $where['type_id']  = 4;
            $serviceId         = $userObj->getCustomServiceId('user_id', $where, 4); //区域客服
        }
        return $serviceId;
    }
    
    /**
     * @uses 获取用户头像
     * @author jhl
     * @param $userId：用户id
     * @return array('portraitNew' => '@上传的头像','portraitDefault' => '@默认的头像')
     */
    public function getUserPortrait($userId)
    {
        $userInfo['portraitNew'] = "/upload/portrait/{$userId}/small.jpg";
        $userInfo['portraitMiddle'] = "/upload/portrait/{$userId}/middle.jpg";
        $userInfo['portraitBig'] = "/upload/portrait/{$userId}/big.jpg";
        $userInfo['portraitDefault'] = '/Public/Images/default.jpg';
        return $userInfo;
    }
    



    

    
    /**
     * @uses 设置用户字段缓存
     * @param int $userId 用户名
     * @param string/array $fieldName：字段名称(当为数组时，key：字段名称；对应的字段值)
     * @param string $fieldValue：字段值
     */
    public static function setUserFiled($userId, $fieldName, $fieldValue = '')
    {
        $cacheKey = "userinfo_nosession_{$userId}";
        if (!is_array($fieldName)) {
            $data = cache($cacheKey);
            if ($data) {
                $data[$fieldName] = $fieldValue;
                cache($cacheKey, $data, 108000);
            }
        } else {
            $data = cache($cacheKey);
            if ($data) {
                foreach ($fieldName as $key => $value) {
                    $data[$key] = $value;
                }
                cache($cacheKey, $data, 108000);
            }
        }
    }
    
    /**
     * 解密uccookie
     */
    public function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
        $ckey_length = 4;
        $key  = md5($key ? $key : COOKIE_TYPE_SECRET_KEY);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey   = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        $string        = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);

        $result = '';
        $box    = range(0, 255);

        $rndkey = array();
        for ($i = 0; $i <= 255; $i++)
        {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for ($j = $i = 0; $i < 256; $i++)
        {
            $j       = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp     = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $string_length; $i++)
        {
            $a       = ($a + 1) % 256;
            $j       = ($j + $box[$a]) % 256;
            $tmp     = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if ($operation == 'DECODE')
        {
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16))
            {
                return substr($result, 26);
            }
            else
            {
                return '';
            }
        }
        else
        {
            return $keyc . str_replace('=', '', base64_encode($result));
        }
    }    
}
