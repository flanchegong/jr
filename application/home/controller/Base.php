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
namespace application\home\controller;
use think\Db;
use think\Request;
use Xxtea;
use think\Cookie;
use think\Cache;
use Crypt3Des;
use application\common\model\user\Iuser;

class Base extends \think\Controller
{

    /**
     * @desc 请求变量
     * @var string
     * @access private
     */
    protected $_params = [];

    /**
     * @desc 初始时间
     * @var string
     * @access private
     */
    protected $_startTime = '';


    public function _initialize()
    {
        // 初始化开始时间        
        $this->_startTime = get_milli_second();
        $this->_params = input('param.');
        //检测用户是否登陆
        $this->checkUserLoginOrNot();
    }
    
   

    /**
     * @desc 方法:输出JSON格式的成功信息
     * @author Liuj
     * @date 2017-6-15
     * @access public
     * @params array $data 数据
     * @params string $msg 信息
     * @return void
     */
    public function okJson($data = array(), $code = 1, $message = 'success')
    {
        $json = array('status' => true, 'code' => $code, 'message' => $message, 'data' => $data);
        self::_outPut($json);
    }


    /**
     * @desc 方法:输出JSON格式的失败信息
     * @author Liuj
     * @date 2017-6-15
     * @access public
     * @params string $msg 信息
     * @params array $data 数据
     * @return void
     */
    public function failJson($message = 'fail', $code = 0, $data = array())
    {
        $json = array('status' => false, 'code' => $code, 'message' => $message, 'data' => $data);
        $this->_outPut($json);
    }

    /**
     * @desc 方法:获取post请求的json数据
     * @author Liuj
     * @date   2017-6-15
     * @access public
     * @params array $json 输出数据
     * @return array
     */
    public function writeLog($json)
    {   
        $logParams = array();
        $logParams['controller'] = Request::instance()->controller();
        $logParams['action'] =  Request::instance()->action();
        $logParams['request_get'] = json_encode(Request::instance()->get());
        $logParams['request_post'] = json_encode(Request::instance()->post());
        $logParams['opr_time'] = date('Y-m-d H:i:s',time());
        $logParams['clinet_ip'] = get_client_ip();
        $logParams['cost_time'] = get_milli_second() - $this->_startTime;
        $logParams['code'] = $json['code'];
        $logParams['message'] = $json['message'];
        $logParams['data'] = json_encode($json['data']);
        Db::name('action_log')->insert($logParams);
    }

    /**
     * @desc 方法:获取post请求的json数组
     * @author liujian
     * @update 2017-6-20
     * @access public
     * @return array
     */
    public function getPostJson()
    {
        $args = file_get_contents("php://input"); // 无数据时为空字符串

        if ($args == '')
        {
            return array();
        }

        $args = json_decode($args, true); // 解码JSON为数组、数字为数字、其他字符串为NULL

        if (!is_array($args))
        {
            return array();
        }

        return $args;
    }

    /**
     * @desc 方法:获取请求参数数组
     * @author liujian
     * @update 2017-6-20
     * @access public
     * @return array
     */
    public function getRequestParams()
    {
        $params = array(); // 默认为空数组
        $request = Request::instance();
        if ($request->method() == 'GET')
        {
            $params = $request->get(); // 无数据时为空数组
        }
        else if($request->method() == 'POST')
        {
            $params = $request->post(); // 无数据时为空数组
            if (empty($params))
            {
                $params = $this->getPostJson();
            }
        }

        return $params;
    }



    /**
     * @desc   方法:过滤并验证输入参数
     * @author Liuj
     * @update 2017-6-19
     * @access public
     * @param  array $filterParams 数据格式：array(array('param_name' => $paramName, ...), ..)
     *         array $filterParams 数据格式：array(array('param_name' => $paramName, ...), ..)
     *         string 'param_name' => $paramName 参数名，如：orderId
     *         string 'param_type' => $paramType 参数类型，如：int、intplus、mobile、idcard、正则表达式等
     *         bool 'is_require' => $paramIsRequire 参数是否必填
     *         mix 'default' => $paramDefault 参数默认值
     *         string 'rule_name' => $ruleName 规则名：length(长度)，(size)大小，(common)范围，(list)列表，(reg)正则
     *         mix 'rule_value' => $ruleValue 规则值
     * @return mixed
     */
    public function getFilterParams($filterParams)
    {
        $params = array();
        $error = null;
        $reqParams = $this->getRequestParams();
        foreach ($filterParams as $filter)
        {
            $paramName = $filter['param_name'];
            if (isset($reqParams[$paramName]))
            {
                $param = $reqParams[$paramName];
            }
            else
            {
                $param = !isset($filter['default']) ? '' : $filter['default'];
            }
            $valid  =  new \Valid();
            list($valid, $param) = $valid::checkParam($filter, $param);

            if ($valid !== true)
            {
                $error = $valid;
                break;
            }
            if (is_null($param) || $param ==='')
            {
                continue;
            }
            $params[$paramName] = $param;
        }

        // 有错误，则返回错误数组
        if (!is_null($error))
        {
            return array($error, $params);
        }

        // 无错误，则返回参数数组
        return array(true, $params);
    }
    
     /**
     * @desc 方法:验证高并发性，防止重复执行
     * @author liuj
     * @update 2017-7-19
     * @access public
     * @params string $uniqueParam 唯一性参数
     * @params int $expires 有效期 默认10秒
     * @return boolean
     */
    public function verifyConcurrency($uniqueParam, $expires=3)
    {
        if (empty($uniqueParam))
        {
            return false;
        }

        $userId = $this->getLoginUserInfo('user_id');
        $ip = get_client_ip(); // IP地址
        $contronller = Request::instance()->controller();
        $action =  Request::instance()->action();
        $requestPost = json_encode(Request::instance()->post());

        $key = $contronller . ":" . $action . ':'.$requestPost.':' . md5($userId . $ip . $uniqueParam);
        $ret = cache($key);
        if (!$ret)
        {
            cache($key, $uniqueParam, $expires); // 保存10秒
            return false;
        }

        return true;
    }

    /**
     * @desc 私有方法:记录日志并输出
     * @author Liuj
     * @date 2017-6-15
     * @access private
     * @params array $json 输出数据
     * @return void
     */
    private function _outPut($json)
    {
        // 写日志
       // $this->writeLog($json);
        // 输出
        if (!IS_CLI)
        {
            header('Access-Control-Allow-Credentials: *');
            $crossDomain=config("settings.cross_domain");
            foreach ($crossDomain as $value)
            {
                if(SITE_FULL==$value){
                    header('Access-Control-Allow-Origin: '.$value);
                }
                if(SITE_YATANG_FULL==$value){
                    header('Access-Control-Allow-Origin: '.$value);
                }
            }
            header('Access-Control-Allow-Credentials: true');  
            header('Content-type:application/json');
        }
        $data=json_encode($json);
        $ETag=md5($data.cookie('UM_distinctid'));
        header('ETag:'.$ETag);
        if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH']==$ETag){
            header("HTTP/1.1 304 Not Modified"); 
        }
        die($data);
    }

    /**
     * 判断用户是否登陆（先查看cookie是否存在，然后根据解密出来的信息查看cache缓存是否存在）
     * @author lingyq
     * @return boolean
     */
    private function checkUserLoginOrNot(){

        $cookieStr = cookie::get('itbt_userkey_tag');  
        if (empty($cookieStr)) {
            return false;
        }        
        //获取cache
        $cacheObj = new Cache();
        $userLoginInfo = $cacheObj->get($cookieStr);   
        if ($userLoginInfo) {
            $cacheOverTime = $userLoginInfo['cacheOverTime'];
            $leftTime = $cacheOverTime - time();
            if ($leftTime > 0 && $leftTime < 10) {
                //延长缓存有效时间
                $cacheObj->set($cookieStr ,$userLoginInfo ,1200);
                Cookie::set('itbt_userkey_tag', $cookieStr, 1200);
            }
            return $userLoginInfo;
        } else {
            Cookie::set('itbt_userkey_tag', null);
            return false;
        }
    }


    /**
     * 获取登陆后用户ID或用户名
     * @param type $str   userId:用户ID   userName：用户名
     * @author lingyq
     * @return string
     */
    public function getLoginUserInfo($str='')
    {   
        //return 123675;
        $result = $this->checkUserLoginOrNot();
        if (!$result) {
            return '';
        } else {
            switch ($str) {
                case 'userId':
                    $re = $result['userId'];
                    break;
                case 'userName':
                    $re = $result['userName'];
                    break;
                default :
                    $re = $result;
                    break;
            }
            
            return $re;
        }
    }
    
    /**
     * 拉取用户信息
     * **/
   public function decodeCookie($cookieString=''){
        $cookie = $cookieString?$cookieString:cookie::get('_yatang_ac_cookie_');
        $cookie=$cookie?$cookie:cookie::get('itbt_auth_yatang_cookie');
        if (empty($cookie))
        {
            return array('status' => 0, 'message' => 'cookie不存在');
        }
        $YZT_COOKIE_KEY=config("settings.yzt");
//        $key=  strlen($YZT_COOKIE_KEY['YZT_COOKIE_KEY'])>24?substr($YZT_COOKIE_KEY['YZT_COOKIE_KEY'], 24):$YZT_COOKIE_KEY['YZT_COOKIE_KEY'];
        $key= self::getKey($YZT_COOKIE_KEY['YZT_COOKIE_KEY']);
        $cookieStr  = str_replace(" ", '+', $cookie);
        $cookieInfo = Crypt3Des::decrypt($cookieStr, $key);
        $cookieArr  = json_decode($cookieInfo, TRUE);
        if (!$cookieArr)
        {
            return array('status' => 0, 'message' => '一账通信息-解密失败');
        }
//        $YZT_KEY=  strlen($YZT_COOKIE_KEY['YZT_KEY'])>24?substr($YZT_COOKIE_KEY['YZT_KEY'], 24):$YZT_COOKIE_KEY['YZT_KEY'];
        $YZT_KEY= self::getKey($YZT_COOKIE_KEY['YZT_KEY']);
        $password = Crypt3Des::decrypt($cookieArr['pwd'], $YZT_KEY);
        if (empty($password))
        {
            return array('status' => 0, 'message' => '一账通信息-密码校验错误');
        }
//        if (empty($cookieArr['userName']) || empty($password) || empty($cookieArr['mobile']))
//        {
//            return array('status' => 0, 'message' => '一账通-用户三要素校验错误');
//        }
//        cookie::set('_yatang_ac_cookie_',NULL);
        $Iuser=new Iuser();
        $user=$Iuser->getUserField(['username'=>$cookieArr['userName']],'user_id');
        $cookieArr['userId']=$user['user_id'];
        return array('status' => 1, 'data' => $cookieArr);
    }
    private function getKey($key){
        if(strlen($key)<=24){
            return $key;
        }
        for($i = 0; $i < 24; $i++){ 
            $bytes[] = ord($key[$i]); 
        } 
        $str='';
        foreach($bytes as $ch) { 
            $str .= chr($ch); 
        }
        return $str;
    }
    public function checkImg($imgDir)
    {
        if (preg_match("/^(http:\/\/|https:\/\/).*$/", $imgDir))
        {
            return $imgDir;
        }
        else
        {
            return SITE_YATANG_FULL.$imgDir;
        }
    }
    /**
     * 获取用户头像
     * **/
    public function getUserIcon($userId, $type = 'small')
    {
        $cookieStr='pic_'.$userId;
        $face = cache($cookieStr);   

        if ($face)
        {
            return $face[$type];
        }
        else
        {
            $face = Db::name('base_user_picture_path')->where(array('user_id' => $userId))->find();

            if ($face['head_portrait_picture_path'])
            {
                $size  = array('small' => '60x60', 'middle' => '80x80', 'big' => '120x120');
                $match=$thumb=[];
                $rs    = preg_match('/^(http|https)(.*?)\.(gif|jpg|jpeg|png)$/', $face['head_portrait_picture_path'], $match);
                if ($rs)
                {
                    foreach ($size as $k => $vv)
                    {
                        $thumb[$k] = $match[1] . $match[2] . "/{$vv}." . $match[3];
                    }
                    cache($cookieStr,$thumb,2592000);
                    return $thumb[$type];
                }else{
                     return SITE_YATANG_FULL.'/Public/Images/default.jpg';
                }
            }
            else
            {
                $dir = SITE_YATANG_FULL . "/Uploads/user/$userId/$type.jpg";
                if (file_exists($dir)) {
                    return SITE_YATANG_FULL."/Uploads/user/$userId/$type.jpg";
                } else {
                    return SITE_YATANG_FULL.'/Public/Images/default.jpg';
                } 

            }
        }
    }
}
