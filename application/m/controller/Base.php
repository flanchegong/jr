<?php

namespace application\m\controller;

use think\Controller;
use application\common\model\user\AuthorityAppLoginAuth;
header('Access-Control-Allow-Origin: ' . SITE_YATANG_FULL);
// 响应类型
header('Access-Control-Allow-Methods:POST');
// 响应头设置
header('Access-Control-Allow-Headers:x-requested-with,content-type');
/**
 * @uses 手机端h5基类
 * @author jhl
 */
class Base extends Controller
{
    public function __construct() {

    }
    
    /**
     * @desc 方法:输出JSON格式的成功信息
     * @author jhl
     * @params array $data 数据
     * @params string $msg 信息
     * @return void
     */
    public function okJson($data = [], $code = 1, $message = 'success')
    {
        $json = ['status' => true, 'code' => $code, 'message' => $message, 'data' => $data];
        self::outPut($json);
    }
    
    /**
     * @desc 方法:输出JSON格式的失败信息
     * @author jhl
     * @access public
     * @params string $msg 信息
     * @params array $data 数据
     * @return void
     */
    public function failJson($message = 'fail', $code = 0, $data = array())
    {
        $json = ['status' => false, 'code' => $code, 'message' => $message, 'data' => $data];
        self::outPut($json);
    }
    
    /**
     * @desc 私有方法:记录日志并输出
     * @author Liuj
     * @date 2017-6-15
     * @access private
     * @params array $json 输出数据
     * @return void
     */
    private function outPut($json)
    {
        echo json_encode($json);
        exit();
    }
    
    /**
     * @uses 用户id
     * @param string $authenticationString
     */
    public function userId($authenticationString = '')
    {
        if ($authenticationString == '') {
            return 0;
        }
        $userId = cache($authenticationString);
        if ($userId) {
            return $userId;
        }
        //缓存未命中
        $authorityAppLoginAuth = new AuthorityAppLoginAuth();
        $appLoginInfo = $authorityAppLoginAuth->getOneByWhere([
            'where' => [
                'auth_string' => $authenticationString,
                'failure_time' => ['gt',date('Y-m-d H:i:s',time())]
            ]
        ],'user_id');
        if (empty($appLoginInfo)) {
            return 0;
        }
        return $appLoginInfo['user_id'];
    }
    
}