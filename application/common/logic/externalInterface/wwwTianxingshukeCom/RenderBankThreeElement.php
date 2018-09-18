<?php

namespace application\common\logic\externalInterface\wwwTianxingshukeCom;
use think\Exception;
use think\Log;
/**
 * @uses 天行数科 接口
 * 若之前有对应的接口未写入当前文件，请开发人员写入次文件
 * @author jhl<liujihaoth@126.com>
 */
class RenderBankThreeElement
{

    private $config;

    public function __construct()
    {
        $this->config = config('real_name_auth_config');
    }

    /**
     *
     * @uses 校验银行卡信息
     * @author jhl
     * @param $params =
     *            [
     *            'name' => '张三',//姓名
     *            'idCard' => '2342342342',//身份证号码
     *            'accountNO' => '32242422342',//银行卡号
     *            ];
     */
    public function verifyBankCardInfo($params = [])
    {
        $accessToken = self::getToken();
        if ($accessToken['status'] == false) {
            return [
                'status' => false,
                'msg' => '验证失败'
            ];
        }
        $params['account'] = $this->config['txskAccount'][PRODUCT_ENV]; // 机构账号
        $params['accessToken'] = $accessToken['token']; // 授权码
        $StatusCode = '';
        try {
            $url = $this->config['txskBankcardThreeElements'][PRODUCT_ENV] . '?' . http_build_query($params);
            $httpHeader = get_headers($url, 1);
            $StatusCode = substr($httpHeader[isset($result[1])], 9, 3);
        } catch (Exception $e) {
            return [
                'status' => false,
                'msg' => '验证失败'
            ];
        }
        // 验证码已过期
        if ($StatusCode != 200) {
            // 重新获取token，重新验证
            $accessToken = self::getToken(false);
            if ($accessToken['status'] == false) {
                return [
                    'status' => false,
                    'msg' => '验证失败'
                ];
            }
            $params['account'] = $this->config['txskAccount'][PRODUCT_ENV]; // 机构账号
            $params['accessToken'] = $accessToken['token']; // 授权码
        }
        Log::write(['logName' => "[wwwTianxingshukeCom{$params['idCard']}RequestInfo]" ,'datas' => $params], 'debug');
        // 获取缓存信息
        try {
            $result = self::getUrlContent($this->config['txskBankcardThreeElements'][PRODUCT_ENV], $params);
        } catch (Exception $e) {
            Log::write(['logName' => "[wwwTianxingshukeCom{$params['idCard']}RequestInfo]" ,'datas' => $e->getMessage()], 'debug');
            return [
                'status' => false,
                'msg' => '验证失败'
            ];
        }
        Log::write(['logName' => "[wwwTianxingshukeCom{$params['idCard']}returnInfo]" ,'datas' => $result], 'debug');
        $result = json_decode($result, 1);
        if (empty($result)) {
            // 重新获取token，重新验证
            $accessToken = self::getToken(false);
            $params['account'] = $this->config['txskAccount'][PRODUCT_ENV]; // 机构账号
            $params['accessToken'] = $accessToken['token']; // 授权码
                                                            
            // 获取缓存信息
            $result = self::getUrlContent($this->config['txskBankcardThreeElements'][PRODUCT_ENV], $params);
            $result = json_decode($result, 1);
            // 重新获取非缓存token（已缓存的token将删除），再去请求接口
            if (empty($result)) {
                return [
                    'status' => false,
                    'msg' => '验证失败'
                ];
            }
        }
        if ($result['success'] == false) {
            return [
                'status' => false,
                'msg' => $result['errorDesc']
            ];
        }
        // 重复提交
        if ($result['success'] == true && isset($result['desc']) && $result['desc'] == 'resubmit') {
            return [
                'status' => false,
                'msg' => '请稍后请求'
            ];
        }
        if ($result['success'] == true) {
            if (isset($result['data']) && $result['data']['checkStatus'] == 'SAME') {
                return [
                    'status' => true,
                    'msg' => '验证成功'
                ];
            } else {
                return [
                    'status' => false,
                    'msg' => $result['data']['result']
                ];
            }
        }
        
        return [
            'status' => true,
            'msg' => '验证成功'
        ];
    }

    /**
     * 函数： 获取授权码
     *
     * @author ljh
     * @access private
     * @return string
     */
    public function getToken($cachestatus = true)
    {
        // 开启缓存，才进行判断
        $key = 'txsk_bankcard_three_elements_accessToken';
        if ($cachestatus == true) {
            $token = cache($key);
            if ($token) {
                return [
                    'status' => true,
                    'msg' => '获取成功',
                    'token' => $token
                ];
            }
        } elseif ($cachestatus == false) {
            // 关闭缓存，删除cache
            cache($key, null);
        }
        // 接口地址
        $url = $this->config['txskGettokenUrl'][PRODUCT_ENV];
        // 机构签名信息
        // 机构帐号
        $params = [
            'account' => $this->config['txskAccount'][PRODUCT_ENV],
            'signature' => $this->config['txskSignature'][PRODUCT_ENV]
        ];
        $result = '';
        try {
            $result = self::PostCurl($url, $params);
        } catch (Exception $e) {}
        // 如果请求失败，删除缓存，再次请求
        if ($result == '') {
            cache($key, null);
            try {
                $result = self::PostCurl($url, $params);
            } catch (Exception $e) {
                return [
                    'status' => false,
                    'msg' => '接口请求失败',
                    'token' => ''
                ];
            }
        }
        $result = json_decode($result, 1);
        if (empty($result) || ! $result['success']) {
            return [
                'status' => false,
                'msg' => 'token获取失败',
                'token' => ''
            ];
        }
        $token = $result['data']['accessToken'];
        // 重新设置cache
        cache($key, $token, 3600 * 24);
        
        return [
            'status' => true,
            'msg' => '获取成功',
            'token' => $token
        ];
    }

    /**
     *
     * @uses get非Curl请求
     * @author jhl
     */
    private function getUrlContent($url, $param = [])
    {
        $url = $url . '?' . http_build_query($param);
        $result = '';
        $fp = fopen($url, 'r');
        while (! feof($fp)) {
            $result = fgets($fp, 1024);
        }
        return $result;
    }

    /**
     *
     * @uses curl post请求数据
     * @author jhl
     */
    private function postCurl($url, $params = array())
    {
        $post_field_string = http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        curl_setopt($ch, CURLOPT_POST, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
 