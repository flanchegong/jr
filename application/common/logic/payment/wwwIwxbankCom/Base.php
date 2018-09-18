<?php

namespace application\common\logic\payment\wwwIwxbankCom;

use think\Exception;
use think\Log;
/**
 *
 * @uses 汇卡支付基类
 * @author jhl
 */
class Base
{

    /**
     *
     * @uses 支付-订单生成接口
     * @author jhl
     * @param string version 协议版本config('iwxbank.payCreate.version')
     * @param string organNo 机构号
     * @param string hicardMerchNo 商家商户号(对应之前支付的：汇耘富商户号)itd_account_wallet_merchant_certification:business_license_register_no
     * @param string payType 支付类型(009:支付宝扫码支付(主扫-返URL，客户端生成二维码主扫);011:微信扫码支付(主扫-返URL，客户端生成二维码主扫))
     * @param string goodsName 商品名称（雅堂-订单号[itd_account_wallet_merchant_receipt.id]）
     * @param string merchOrderNo 订单号（itd_account_wallet_merchant_receipt.id）
     * @param string amount 交易金额（分为单位，1元=100）
     * @param string customerName 持卡人姓名（itd_iuser.realname）
     * @param string certsNo 身份证号码 持卡人身份证号码（itd_iuser.card_id）
     * @param string frontEndUrl 前台回调url（同步通知地址：支付完成之后，跳转地址）
     * @param string backEndUrl 后台回调url（异步通知地址：支付成功，异步通知地址，平台进行逻辑处理）
     * @param string isT0(填：1 即可)
     *
     * @param $requestUrl:请求地址
     * @param $param 参数（见上）
     * @param $payKey:支付秘钥
     */
    public function createOrder($requestUrl, $param, $payKey)
    {
        //签名字段
        $signParam = [
            'version' => $param['version'],
            'organNo' => $param['organNo'],
            'hicardMerchNo' => $param['hicardMerchNo'],
            'payType' => $param['payType'],
            'bizType' => $param['bizType'],
            'merchOrderNo' => $param['merchOrderNo'],
            'showPage' => $param['showPage'],
            'amount' => $param['amount'],
            'frontEndUrl' => $param['frontEndUrl'],
            'backEndUrl' => $param['backEndUrl']
        ];
        $param['sign'] = self::sign($signParam,$payKey);
        $response = self::startPost($requestUrl,$param,'createOrder');
        return $response;
    }
    
    /**
     *
     * @uses 拆分表单号生成
     * @author jhl
     * @param int $userId 用户id
     * @param string $businessPrefix:业务区分前缀
     */
    public function madeUniqueCode($userId, $businessPrefix = '')
    {
        static $uniqueArr = array();
        $microtime = microtime();
        $uniqueCode = substr(PRODUCT_ENV, 0,1) . $businessPrefix . $userId . date('YmdHis');
        return $uniqueCode;
    }

    /**
     *
     * @uses 支付-订单查询接口
     * @author jhl
     * @param string version 协议版本config('iwxbank.payCreate.version')
     * @param string hicardMerchNo 商家商户号(对应之前支付的：汇耘富商户号)itd_account_wallet_merchant_certification:business_license_register_no
     * @param string merchOrderNo 订单号（itd_account_wallet_merchant_receipt.id）
     * @param string hicardOrderNo:汇卡订单号
     *
     * @param $requestUrl:请求地址
     * @param $param 参数（见上）
     *
     */
    public function selectOrder($requestUrl, $param,$signKey)
    {
        //签名字段
        $signParam = [
            'version' => $param['version'],
            'hicardMerchNo' => $param['hicardMerchNo'],
            'merchOrderNo' => $param['merchOrderNo']
        ];
        $param['sign'] = self::sign($signParam,$signKey);
        $response = self::startPost($requestUrl,$param,'selectOrder');
        return $response;
    }
    
    /**
     * @uses 执行请求
     * @author jhl
     */
    private function startPost($requestUrl, $param, $prefixLogName = '')
    {
        //执行请求
        Log::write(['logName' => "[iwxbankPay{$prefixLogName}{$param['merchOrderNo']}RequestInfo_" . time() . "]",'datas' => $param], 'debug');
        try {
            $response = self::postCurl($requestUrl,$param);
        } catch (Exception $e) {
            Log::write(['logName' => "[iwxbankPay{$prefixLogName}{$param['merchOrderNo']}Errow_" . time() . "]",'datas' => ['tranceAsString' => $e->getTraceAsString(), 'message' => $e->getMessage()]], 'debug');
            return [
                'status' => false,
                'msg' => $e->getMessage(),
                'requestInfo' => []
            ];
        }
        Log::write(['logName' => "[iwxbankPay{$prefixLogName}{$param['merchOrderNo']}ReturnInfo_" . time() . "]",'datas' => $response], 'debug');
        return $response;
    }
    
    /**
     *
     * @uses 签名
     * @author jhl
     * @param array $param
     * @param string $payKey:支付秘钥
     */
    private function sign($param, $payKey)
    {
        $string = '';
        foreach ($param as $k => $v) {
            $string .= $k . '=' . $v . '&';
        }
        $string = md5($string . $payKey);
        return $string;
    }

    /**
     * @uses curl post请求数据
     * @author jhl
     */
    private function postCurl($url, $params = [])
    {
        foreach ($params as $k => $v) {
            $params[$k] = urlencode($v);
        }
        $postFieldString = urldecode(json_encode($params));
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
          curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_TIMEOUT,55);//超时时间设置（线上nginx超时时间为60s）
          curl_setopt($ch, CURLOPT_HTTPHEADER, [                   
            'Content-Type: application/json',  
            'Content-Length: ' . strlen($postFieldString)]         
             );  
          curl_setopt($ch, CURLOPT_POSTFIELDS, $postFieldString);
          $response = json_decode(curl_exec($ch), 1);
          curl_close($ch);   
          return $response;
    }
    
    /**
     * @uses 签名验证
     * @author jhl
     * @param 支付完成
     */
    public function checkSign($data,$payKey)
    {
        $signParam = [];
        foreach ([
            'version',
            'hicardMerchNo',
            'payType',
            'merchOrderNo',
            'hicardOrderNo',
            'amount',
            'createTime',
            'payTime',
            'respCode'
        ] as $key) {
            if (!isset($data[$key])) {
                return [
                    'status' => false,
                    'msg' => '签名失败'
                ];
            } else {
                $signParam[$key] = $data[$key];
            }
        }
        $sign = self::sign($signParam,$payKey);
        $logicDic = new Dic();
        //接口签名验证成功
        if ($sign == $data['sign']) {
            return [
                'status' => true,
                'msg' => '签名成功'
            ];
        } else {
            Log::write(['logName' => 'IwxbankCallBackscanCodePayment3_' . $data['merchOrderNo'],'msg' => '回调校验失败','data' => $data],'debug');
            return [
                'status' => false,
                'msg' => '签名失败'
            ];
        }
    }
 
}