<?php

namespace application\common\logic\AccountService;

use application\common\logic\user\Iuser;
use Crypt3Des;
/**
 * @uses 一帐通接口
 * @author jhl
 *
 */
class AccountService {
    
    /**
     * 获取API
     *
     * ＠param string $api api名称  参见Conf/api.php
     * @return  array 返回接口配置
     *
     * * */
    public static function getAPI($api) {
        $apiConfig = config('api');
        $data = $apiConfig['API'];
        return $data[$api];
    }
    
    /**
     * 添加额外参数
     *
     * @access private
     * @param array $data 		需要附加额外参数的数组，与http_post,http_get函数一起用
     * @param int   $secret		不同的签名标识
     * @return array 			返回添加额外参数的数组
     *  */
    public static function addParam($data, $secret=0) {
    
        if (!isset($data["timestamp"])) {
            date_default_timezone_set("PRC");
            $data["timestamp"] = date("Y-m-d H:i:s");
        }
        $webConfig = config('web_config');
        if (!isset($data["v"])) {
            $data["v"] = $webConfig['APP_VERSION'];
        }
        if (!isset($data["appKey"])) {
            //调用不同的签名
            switch ($secret) {
                case 0:
                    $data["appKey"] = $webConfig['APP_KEY'];
                    break;
                case 1:
                    $data["appKey"] = $webConfig['YZT_APP_KEY'];
                    break;
                default:
                    $data["appKey"] = $webConfig['YZT_APP_KEY'];
            }
        }
        if (!isset($data['format'])) {
            $data['format'] = "json";
        }
        //调用不同的签名
        switch ($secret) {
            case 0:
                $string = $webConfig['APP_SECRET'];
                break;
            case 1:
                $string = $webConfig['YZT_APP_SECRET'];
                $data['locale'] = "zh_CN";
                break;
            default:
                $string = $webConfig['APP_SECRET'];
        }
        ksort($data);
        foreach ($data as $k => $v) {
            $string.="{$k}{$v}";
        }
        switch ($secret) {
            case 0:
                $string .= $webConfig['APP_SECRET'];
                break;
            case 1:
                $string .= $webConfig['YZT_APP_SECRET'];
                break;
            default:
        }
        $data['sign'] = strtoupper(sha1($string));
        return $data;
    }
    
    
    /**
     * CURL POST
     *
     * @param string $url URL
     * @param array $fields 参数数组
     * @param bool $addParam 是否添加额外参数
     * @param int  $secret 不同的签名标识
     * @return array
     *
     * @example
     *
     * post('http://www.example.com', array('field1'=>'value1', 'field2'=>'value2'));
     * * */
    public static function _p($url, $fields, $addParam = true, $secret=0) {
        $api = self::getAPI($url);
        $url = $api["url"];
        $fields["method"] = $api["method"];
        if ($addParam) {
            $fields = self::addParam($fields, $secret);
        }
        $post_field_string = http_build_query($fields);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if(defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        curl_setopt($ch, CURLOPT_POST, true);
        $response = json_decode(curl_exec($ch), 1);
        curl_close($ch);
        return $response;
    }
    
    /**
     * 登陆
     * @param $username     账号
     * @param $password     密码
     * @return array
     */
    public static function login($username, $password) {
        $fields = array(
            'umsearch'  => $username,
            'p'          => $password,
            'origin'    => 1,       //来源编码:1雅堂金融，2.雅堂电商，3雅堂物流，4雅堂支付，5雅堂餐饮
        );
        $json = self::_p('YZT_LOGIN', $fields, true, 1);
        return $json;
    }

    /**
     * 注册
     * @param array $fields
     * @return array
     */
    public static function register($fields=array()) {
        $register_fields = array(
            'mobile'   => $fields['tel'],
            'p'         => $fields['pas'],
            'origin'   => 1,       //来源编码:1雅堂金融，2.雅堂电商，3雅堂物流，4雅堂支付，5雅堂餐饮
            'source' => $fields['source'] ? $fields['source'] : 1,
            'inviteCode' => $fields['inviteCode'] ? $fields['inviteCode'] : 'null'
        );
        isset($fields['username']) ? $register_fields['u'] = $fields['username'] : '';
        $json = self::_p('YZT_REGISTER', $register_fields, true, 1);
        return $json;
    }

    /**
     * 检测手机号是否存在
     * @param $phonenum     手机号
     * @return bool         存在返回true,否则false
     */
    public static function checkMobileIsExist($phonenum) {
        $fields = array(
            'mobile'    => $phonenum,
        );
        $res = self::_p('YZT_QUERY_MOBILE_EXIT', $fields, true, 1);
        //手机号存在
        if($res['code']) {
            return true;
        }
        return false;
    }

    /**
     * 检测邮箱是否存在
     * @param $email     邮箱
     * @return bool      存在返回true,否则false
     */
    public static function checkEmailIsExist($email) {
        $fields = array(
            'email'    => $email,
        );
        $res = self::_p('YZT_QUERY_EMAIL_EXIT', $fields, true, 1);
        //邮箱存在
        if($res['code']) {
            return true;
        }
        return false;
    }

    /**
     * 检测用户是否存在
     * @param $username     账号
     * @return bool
     */
    public static function checkUserIsExist($username) {
        $fields = array(
            'u'    => $username,
        );
        $res = self::_p('YZT_QUERY_USERNAME_EXIT', $fields, true, 1);
        //用户名存在
        if($res['code']) {
            return true;
        }
        return false;
    }

    /**
     * 修改用户登陆密码
     * @param array $fields
     * @return bool
     */
    public static function modifyPassword($fields=array()) {
        $update_fields = array(
            'u'     => $fields['username'],
            'p'     => $fields['password'],
            'oldPw' => $fields['oldPassword']
        );
        $res = self::_p('YZT_MODIFY_PWD', $update_fields, true, 1);
        if($res['code'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * 修改用户手机号码
     * @param array $fields
     * @return array
     */
    public static function modifyMobile($fields=array()) {
        $update_fields = array(
            'u'         => $fields['username'],
            'mobile'   => $fields['mobile'],
        );
        $res = self::_p('YZT_MODIFY_MOBILE', $update_fields, true, 1);
       return $res;
    }

    /**
     * 修改用户邮箱
     * @param array $fields
     * @return array
     */
    public static function modifyEmail($fields=array()) {
        $update_fields = array(
            'u'         => $fields['username'],
            'email'     => $fields['email'],
        );
        $res = self::_p('YZT_MODIFY_EMAIL', $update_fields, true, 1);
        return $res;
    }

    /**
     * 绑定一账通
     * @param array $fields
     * @return bool
     */
    public static function bindUser($fields=array()) {
        $query_fields = array(
            'u'         => $fields['username'],
            'origin'    => $fields['origin'],
        );
        $res = self::_p('YZT_BIND_USER', $query_fields, true, 1);
        return $res['code'] ? false : true;
    }
    /**
     * 查询用户信息
     * @param array $fields
     * @return array
     */
    public static function getUserInfo($fields=array()) {
        $query_fields = array(
            'u'         => $fields['username'],
        );
        $res = self::_p('YZT_QUERY_USERINFO', $query_fields, true, 1);
        return $res;
    }

    /**
     * 重置用户密码
     * @param array $fields
     * @return bool
     */
    public static function resetPwd($fields=array()) {
        $query_fields = array(
            'u'  => $fields['username'],
            'p'  => $fields['password']
        );
        $res = self::_p('YZT_RESET_PWD', $query_fields, true, 1);
        if($res['code'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * 同一用户多个账号情况搜索(其中之一账号的用户名为手机号)
     * @param $search       搜索的账号
     * @return array
     */
    public static function accountChoice($search) {
        $fields['umsearch'] = $search;
        $res = self::_p('YZT_ACCOUNT_CHOICE', $fields, true, 1);
        return $res;
    }

    /**
     * 多账号选择其一绑定
     * @param array $fields
     * @return bool
     */
    public static function itbtUserBind($fields=array()) {
        $query_fields = array(
            'u'         => $fields['username'],
            'p'         => $fields['password'],
            'userId'    => $fields['uid'],
        );
        $res = self::_p('YZT_CHOICE_BIND', $query_fields, true, 1);
        if($res['code'] == 0) {
            return true;
        }
        return false;
    }
    /**
     * 检查子系统用户账号
     * @param $username     用户名/邮箱/手机号
     * @param $password     密码
     * @param bool $choice       多用户情况
     * @return bool
     */
    public static function checkSubsystemUser($username, $password, $choice=false) {
        if (empty($username) || !isset($username))
        {
            return 0;
        }
        if (empty($password) || !isset($password))
        {
            return 0;
        }
        if($choice) {
            $where = array(
                'username'  => $username,
                'status'    => array('egt', 0),
                'islock'    => 0
            );
            $userobj = Iuser::getUserField($where,'`password`,`username`,`user_id`,`pdw`,`status`');
        } else {
            $userobj = Iuser::getUserField("(`username`='{$username}' or phone='{$username}')",'`password`,`username`,`user_id`,`pdw`,`status`');
        }

        $decryptPassword = self::decryptSubsystemPassword($password);
        if(!empty($userobj)) {
            if ($userobj['status'] == -1) {
                //冻结
                return -1;
            }
            //新版系统的密码
            if (strlen($userobj['pdw']) > 0) {
               $webConfig = config('web_config');
                $md5pass = md5($decryptPassword . $webConfig['MKEY']);
                //返回1 用户匹配成功,0 用户匹配失败
                return ($md5pass == $userobj['pdw']) ? 1 : 0;
            } else {//老版本密码
                $md5pass = md5($decryptPassword); //密码使用md5加密
                return ($md5pass == $userobj['password']) ? 1 :0;
                //return $userobj['password'] ? intval($md5pass == $userobj['password']) : 0;
            }

        }
        return 0;
    }

    /**
     * 解密密码
     * @param $passowrd     加密密码
     * @return bool|string
     */
    public static function decryptSubsystemPassword($passowrd) {

        //密码加密秘钥
        $setting = config('settings');
        $yzt_key = $setting['yzt']['YZT_KEY'];
        return Crypt3Des::decrypt($passowrd, $yzt_key);
    }
    
    
    /**
     * 非一站通接口查询用户信息
     * @param $data array
     * @return bool|string
     */
    public static function consoleNotUserInfo($data){
        if($data){
            $json = self::_p('YZT_NOTUSER_LIST', $data, true, 1);
            return $json;
        }
    }
    /**
     * 一站通接口查询用户信息
     * @param $data array
     * @return bool|string
     */
    public static function consoleUserInfo($data){
        if($data){
            $json = self::_p('YZT_USER_LIST', $data, true, 1);
            return $json;
        }
    }
    /**
     * 已注销接口查询用户信息
     * @param $data array
     * @return bool|string
     */
    public static function consoleCancelUserInfo($data){
        if($data){
            $json = self::_p('YZT_CANCEUSER_LIST', $data, true, 1);
            return $json;
        }
    }
    /**
     * 非一账通用户管理升级操作接口
     * @param $data array
     * @return bool|string
     */
    public static function consoleNotUserUpgrade($data){
        if($data){
            $json = self::_p('YZT_NOTUSER_UPGRADE', $data, true, 1);
            return $json;
        }
    }
    /**
     * 非一账通用户管理注销操作接口
     * @param $data array
     * @return bool|string
     */
    public static function consoleCancelNotUser($data){
        if($data){
            $json = self::_p('YZT_CANCENOTUSER', $data, true, 1);
            return $json;
        }
    }
    /**
     * 非一账通用户管理启用停用操作接口
     * @param $data array
     * @return bool|string
     */
    public static function consoleNotUserEnableAndNot($data){
        if($data){
            $json = self::_p('YZT_NOTUSER_ENABLEANDNOT', $data, true, 1);
            return $json;
        }
    }
    /**
     * 一账通用户管理注销操作接口
     * @param $data array
     * @return bool|string
     */
    public static function consoleCancelUser($data){
        if($data){
            $json = self::_p('YZT_CANCEUSER', $data, true, 1);
            return $json;
        }
    }
    /**
     * 一账通用户管理注销操作接口:根据用户名注销
     * @param $data array
     * @return bool|string
     */
    public static function consoleCancelUserByName($data){
        if($data){
            $dataparam['u']=$data;
            $json = self::_p('YZT_CANCEUSERBYNAME', $dataparam, true, 1);
            return $json;
        }
    }
    /**
     * 一账通用户管理启用停用操作接口
     * @param $data array
     * @return bool|string
     */
    public static function consoleEnableAndNot($data){
        if($data){
            $json = self::_p('YZT_USER_ENABLEANDNOT', $data, true, 1);
            return $json;
        }
    }
    
    /**
     * 同步实名认证到一站通
     */
    public static function RealName($data) {
        $fields = array(
            'u'         => $data['userName'],
            'authType'  => $data['authType']?$data['authType']:1,
            'realName'  => $data['realName'],
            'idNumber'  => $data['idNumber'],       
        );
        $json = self::_p('YZT_REALNAME', $fields, true, 1);
        return $json;
    }
    
    /**
     * 2016-11-29
     * 获取邀请码
     */
    public static function inviteCode($username){
        $fields = array(
            'u'         => $username     
        );
        $json = self::_p('GET_INVITE_CODE', $fields, true, 1);
        return $json;
    }
    /**
     * 一站通接口查询用户角色列表(角色列表)
     * @param $data array
     * @return bool|string
     */
    public static function getAccountTypeList(){
        $json = self::_p('YZT_GETACCOUNTTYPELIST', array(), true, 1);
        return $json;
    }
    
    /**
     * 一站通接口根据用户id查询用户角色列表(单个用户的角色)
     * @param $data array('userId'=>111,'source'=>1)
     * @return bool|string
     */
    public static function getAccountTypeRelationListByUserId($data){
        if($data){
            $json = self::_p('YZT_GETACCOUNTTYPERELATIONLISTBYUSERID', $data, true, 1);
            return $json;
        }
    }
    /**
     * 一站通接口保存用户角色
     * @param $data array('userId'=>111,'source'=>1,'userType'=>101) source：来源1一账通0非一账通,userType:用户角色id
     * @return bool|string
     */
    public static function saveAccountTypeRelation($data){
        if($data){
            $json = self::_p('YZT_SAVEACCOUNTTYPERELATION', $data, true, 1);
            return $json;
        }
    }
     /**
     * 一站通接口删除用户角色(单个角色删除)
     * @param $data array('userId'=>111,'source'=>1,'userType'=>101)
     * @return bool|string
     */
    public static function deleteSingleAccountTypeRelation($data){
        if($data){
            $json = self::_p('YZT_DELETESINGLEACCOUNTTYPERELATION', $data, true, 1);
            return $json;
        }
    }
    /**
     * 一站通接口删除用户角色(删除全部)
     * @param $data array('userId'=>111,'source'=>1)
     * @return bool|string
     */
    public static function deleteAllAccountTypeRelation($data){
        if($data){
            $json = self::_p('YZT_DELETEALLACCOUNTTYPERELATION', $data, true, 1);
            return $json;
        }
    }
    
    /**
     * 一站通接口特殊用户注册
     * @param $data array
     * @return bool|string
     */
    public static function consoleUserRegister($data){
        if($data){
            $json = self::_p('YZT_REGISTERBYSPECIAL', $data, true, 1);
            return $json;
        }
    }
    /**
     * 一站通接口修改用户邀请码
     * @param $data array
     * @return bool|string
     */
    public static function modifyAccountInviteInfo($data){
        if($data){
            $json = self::_p('YZT_MODIFYACCOUNTINVITEINFO', $data, true, 1);
            return $json;
        }
    }
    
     /**
     * 一站通接口 注册用户名敏感词查询
     * @param $data array
     * @return bool|string
     */
    public static function getSpeicalUser($data){
        $json = self::_p('YZT_GETSPEICALUSER', $data, true, 1);
        return $json;
    }
    /**
     * 一站通接口 注册用户名敏感词添加
     * @param $data array
     * @return bool|string
     */
    public static function addSepeialUser($data){
        if($data){
            $json = self::_p('YZT_ADDSEPPEIALUSER', $data, true, 1);
            return $json;
        }
    }
    /**
     * 一站通接口 注册用户名敏感词修改
     * @param $data array
     * @return bool|string
     */
    public static function updateSpeicalUser($data){
        if($data){
            $json = self::_p('YZT_UPDATESPEICALUSER', $data, true, 1);
            return $json;
        }
    }
    /**
     * 一站通接口 注册用户名敏感词删除
     * @param $data array
     * @return bool|string
     */
    public static function deleteSepeialUser($data){
        if($data){
            $json = self::_p('YZT_DELETESEPEIALUSER', $data, true, 1);
            return $json;
        }
    }
    /**
     * 一站通接口 清理实名认证信息接口
     * @param $data array
     * @return bool|string
     */
    public static function clearRealName($data){
        if($data){
            $json = self::_p('YZT_CLEARREALNAME', $data, true, 1);
            return $json;
        }
    }
    /**
     * @uses 保留门店编号接口
     * @author jhl
     * @param $data = array(
     'userId'=> 490,//用户id:必填
     'isWhere'=>1,//来源（1.一账通0非一账通）：必填
     'code'=> 343232//编号：必填
     * );
     */
    public static function subAccountActionSaveSubAccountInfo($data)
    {
        if($data){
            $json = self::_p('YZT_SUBACCOUNTSAVESUBACCOUNTINFO', $data, true, 1);
            return $json;
        }
    }
    
    /**
     * @uses 删除小超编码接口
     * @author jhl
     * @param $data = array(
     'userId'=> 490,//用户id:必填
     'isWhere'=>1,//来源（1.一账通0非一账通）：必填
     'code'=> 343232//编号：非必填，(如果填了则删除当前编码，如果不填删除用户所有编码)
     */
    public static function subAccountActionDeleteSubAccountInfo($data)
    {
        if($data){
            $json = self::_p('YZT_SUBACCOUNTDELETESUBACCOUNTINFO', $data, true, 1);
            return $json;
        }
    }
    
    /**
     * @uses 查询用户小超编码信息接口
     * @author jhl
     * @param $data = array(
     'userId'=> 490,//用户id:必填
     'isWhere'=>1,//来源（1.一账通0非一账通）：必填
     'code'=> 343232//编号：非必填，表示查询所有
     * );
     */
    public static function subAccountActionGetSubAccountInfoList($data)
    {
        if($data){
            $json = self::_p('YZT_SUBACCOUNTGETSUBACCOUNTINFOLIST', $data, true, 1);
            return $json;
        }
    }
    
    /**
     *
     * @uses 用户中心角色
     * @author jhl
     *
     */
    public function centerUserType()
    {
        $arr = array(
            '小超自营店',
            '小超加盟店',
            '小超代理店'
        );
        return $arr;
    }
    
    /**
     * @uses //保存并校验银联4要素接口
     * @author jhl
     * @param $data = array(
         'userName'=> '',//用户名必填
         'origin'=> 1,//来源：1雅堂金融，2.雅堂电商，3雅堂物流，4雅堂支付，5雅堂餐饮6雅堂众筹7雅堂小超
         'name'=> '',//持卡人姓名
         'idCard' => '',//持卡人身份证号码
         'accountNO' => '',//银行卡号
         'bankPreMobile' => '',//持卡人预留手机号
     * );
     */
    public static function subAccountActionSaveUserBankCardInfo($data)
    {
        $json = self::_p('YZT_SAVEUSERBANKCARDINFO', $data, true, 1);
        return $json;
    }
    
}