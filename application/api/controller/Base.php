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

namespace application\api\controller;
use think\Db;
use think\Request;

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
        $json = array(
            'status'  => true,
            'code'    => $code,
            'message' => $message,
            'data'    => $data
        );
        $this->_outPut($json);
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
        $json = array(
            'status'  => false,
            'code'    => $code,
            'message' => $message,
            'data'    => $data
        );
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
        $logParams['action'] = Request::instance()->action();
        $logParams['request_get'] = json_encode(Request::instance()->get());
        $logParams['request_post'] = json_encode(Request::instance()->post());
        $logParams['opr_time'] = date('Y-m-d H:i:s', time());
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
        else if ($request->method() == 'POST')
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
     * @desc 函数：解析app登录信息
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param string $str
     * @return mixed|string
     */
    public function parseAppLoginInfo($str = '')
    {
        if ($str == '')
        {
            return false;
        }
        $uid = cache($str);
        if ($uid)
        {
            return $uid;
        }
        //缓存未命中
        $where['auth_string'] = $str;
        $where['failure_time'] = [
            'gt',
            date('Y-m-d H:i:s', time())
        ];
        $loginInfo = Db::name('authority_app_login_auth')->field('user_id')->where($where)->find();
        if (empty($loginInfo))
        {
            return false;
        }
        return $loginInfo['user_id'];
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
            $valid = new \Valid();
            list($valid, $param) = $valid::checkParam($filter, $param);

            if ($valid !== true)
            {
                $error = $valid;
                break;
            }
            if (is_null($param) || $param === '')
            {
                continue;
            }
            $params[$paramName] = $param;
        }

        // 有错误，则返回错误数组
        if (!is_null($error))
        {
            return array(
                $error,
                $params
            );
        }

        // 无错误，则返回参数数组
        return array(
            true,
            $params
        );
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
    public function verifyConcurrency($uniqueParam, $expires = 5)
    {
        if (empty($uniqueParam))
        {
            return false;
        }

        $ip = get_client_ip(); // IP地址
        $contronller = Request::instance()->controller();
        $action = Request::instance()->action();
        $requestPost = json_encode(Request::instance()->post());
        $key = $contronller . ":" . $action . ':' . $requestPost . ':' . md5( $ip . $uniqueParam);
        $ret = cache($key);
        if (!$ret)
        {
            cache($key, $uniqueParam, $expires); // 保存5秒
            return false;
        }

        return true;
    }

    /**
     * 获取用户头像
     * **/
    public function getUserIcon($userId, $type = 'small')
    {
        $cookieStr = 'pic_' . $userId;
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
                $size = array(
                    'small'  => '60x60',
                    'middle' => '80x80',
                    'big'    => '120x120'
                );
                $match = $thumb = [];
                $rs = preg_match('/^(http|https)(.*?)\.(gif|jpg|jpeg|png)$/', $face['head_portrait_picture_path'], $match);
                if ($rs)
                {
                    foreach ($size as $k => $vv)
                    {
                        $thumb[$k] = $match[1] . $match[2] . "/{$vv}." . $match[3];
                    }
                    cache($cookieStr, $thumb, 2592000);
                    return $thumb[$type];
                }
                else
                {
                    return SITE_YATANG_FULL . '/Public/Images/default.jpg';
                }
            }
            else
            {
                $dir = SITE_YATANG_FULL . "/Uploads/user/$userId/$type.jpg";
                if (file_exists($dir))
                {
                    return SITE_YATANG_FULL . "/Uploads/user/$userId/$type.jpg";
                }
                else
                {
                    return SITE_YATANG_FULL . '/Public/Images/default.jpg';
                }
            }
        }
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
         $this->writeLog($json);
        // 输出
        if (!IS_CLI)
        {
            if (PRODUCT_ENV!='product')
            {
                header('Access-Control-Allow-Origin:*');
                header('Access-Control-Allow-Credentials: true');
            }
            header('Content-type:application/json');
        }

        echo json_encode($json,JSON_NUMERIC_CHECK);
        exit();
    }


}
