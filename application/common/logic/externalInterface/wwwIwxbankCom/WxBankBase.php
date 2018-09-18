<?php

namespace application\common\logic\externalInterface\wwwIwxbankCom;

use think\Exception;
use think\Log;

/**
 *
 * @uses 汇卡钱包接口基类
 * @author jhl<liujihaoth@126.com>
 */
class WxBankBase
{

    //机构号
    public $organizationNumber;
    
    //秘钥
    public $secretKey;
    
    //请求地址
    public $requestUrl;
    
    //业务类型
    public $busiNo;
    
    //费率ID
    public $rateId;
    
    //商户入网成功编号
    const REGIST_SUCCESS_CODE = 'S1050_00';
    
    //商户入网失败编号
    const REGIST_FAIL_CODE = 'S1050_01';
    
    //修改银行卡成功编号
    const EDITCARD_SUCCESS_CODE = 'S1059_00';
    
    //修改银行卡失败编号
    const EDITCARD_FAIL_CODE = 'S1059_01';
    
    public function __construct()
    {
        //配置信息
        $iwxbankConfig = config('iwxbank');
        $iwxbankMerchantAccess = $iwxbankConfig['merchantAccess'];
        $this->organizationNumber = $iwxbankMerchantAccess['organizationNumber'][PRODUCT_ENV];
        $this->secretKey = $iwxbankMerchantAccess['secretKey'][PRODUCT_ENV];
        $this->requestUrl = $iwxbankMerchantAccess['requestUrl'][PRODUCT_ENV];
        $this->busiNo = $iwxbankMerchantAccess['busiNo'][PRODUCT_ENV];
        $this->rateId = $iwxbankMerchantAccess['rateId'][PRODUCT_ENV];
    }

    /**
     * @uses AppHead组装
     * @author jhl
     * @param string $version 版本号
     * @param string $transType 接口编号
     */
    private function appHead($version, $transType)
    {
        $appHead = [
            'Version' => $version,
            'TransType' => $transType,
            'DataTime' => date('Y-m-d H:i:s',time()),
            'SerialNo' => self::madeUniqueCode($transType)
        ];
        return $appHead;
    }

    /**
     * @uses 组装请求报文内容
     * @author jhl
     */
    public function madeRequstDatas($version, $transType, $appBody)
    {
        $appHead = self::appHead($version,$transType);
        $reqDatas = ['AppHead' => $appHead,'AppBody' => $appBody];
        foreach ($reqDatas['AppBody'] as $k => $v) {
            $reqDatas['AppBody'][$k] = urlencode($v);
        }
        $return = urldecode(json_encode($reqDatas));
        return $return;
    }
    
    /**
     * @uses 签名
     * @author jhl
     */
    public function madeSign($signBody = [])
    {
        //英文字典排序
        ksort($signBody);
        $string = '';
        foreach ($signBody as $k => $v) {
            $string .= $k . '=' . $v . '&';
        }
        $string = $string . 'key=' . $this->secretKey;
        return strtoupper(md5($string));
    }
    
    /**
     *
     * @uses 流水号
     * @author jhl
     * @param string $prefix 前缀
     */
    private function madeUniqueCode($prefix = '')
    {
        $microtime = microtime();
        $uniqueCodeUnkey = substr(PRODUCT_ENV, 0,1) . $prefix . substr($microtime, - 10, 10) . substr($microtime, 2, 6);
        $uniqueCode = $uniqueCodeUnkey . rand(100, 999);
        if (cache($uniqueCode)) {
            $uniqueCode = $uniqueCodeUnkey . rand(100, 999);
        }
        cache($uniqueCode,1,10);
        return $uniqueCode;
    }

    /**
     * @uses 请求主体封装
     * @author jhl
     * @param string $url 请求地址
     * @param array $postData 请求数据
     * @param string $cachaName 缓存名称
     */
    public function baseRequestDatas($url, $postData = [], $cachaName = '')
    {
        Log::write(['logName' => "[iwxbank{$cachaName}RequestInfo]" ,'datas' => $postData], 'debug');
        // content
        $logName = str_replace('/' ,'', $url);
        try {
            $response = self::postCurl($url,$postData);
        } catch (Exception $e) {
            Log::write(['logName' => "[iwxbank{$cachaName}Errow]" ,'datas' => ['tranceAsString' => $e->getTraceAsString(), 'message' => $e->getMessage()]], 'debug');
            return [
                'status' => false,
                'msg' => $e->getMessage(),
                'requestInfo' => []
            ];
        }
        
        Log::write(['logName' => "[iwxbank{$cachaName}ReturnInfo]" ,'datas' => $response], 'debug');
        
        return json_decode($response,true);
    }
    
    /**
     * @uses curl post请求数据
     * @author jhl
     */
    private function postCurl($url, $params = [])
    {
        $postFieldString = is_array($params) ? http_build_query($params) : $params;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFieldString);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,55);//超时时间设置（线上nginx超时时间为60s）
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    
}
















