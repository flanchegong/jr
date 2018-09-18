<?php

/**
 * 阿里云接口
 */
namespace application\serv\controller;

use think\Controller;
use think\Log;
class Alipay extends Controller
{

    //兼容tp3 OpenSSL加密 OPENSSL_ALGO_SHA256
    function opensslDecode()
    {
        $tmp           = $_POST;
        $data          = $tmp['data'];
        $sign          = $tmp['sign_key'];
        $res           = $tmp['res'];
        $result        = openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
        $result        = $result == 1 ? true : false;
        $tmp['status'] = $result;
        Log::write(['logName' => "testAlipay",'datas' => $tmp], 'debug');
        echo json_encode(['result' => $result]);
        exit();
    }
    
    //兼容tp3 OpenSSL加密 OPENSSL_ALGO_SHA256
    function openSign(){
        $tmp           = $_POST;
        $data          = $tmp['data'];
        $res           = $tmp['res'];
        openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        $sign = base64_encode($sign);
        $tmp['sign'] = $sign;
        Log::write(['logName' => "opensslSign",'datas' => $tmp], 'debug');
        echo json_encode(['sign' => $sign]);
        exit();
    }
    
}
