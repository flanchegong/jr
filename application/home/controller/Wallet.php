<?php

namespace application\home\controller;

use think\Log;
use application\common\logic\payment\wwwIwxbankCom\Base as payBase;
use application\common\logic\walletLogic\AccountWalletPay;
use application\common\logic\payment\wwwIwxbankCom\Dic as logicDic;
use application\common\model\wallet\AccountWalletMerchantReceipt;
use think\Controller;

/**
 *
 * @uses wallet回调地址--防止header('Access-Control-Allow-Origin: ' . SITE_FULL);强制性控制请求来源
 * @author jhl<liujihaoth@126.com>
 *         @date<2017-10-30>
 */
class Wallet extends Controller
{

    /**
     *
     * @uses 扫码充值异步回调
     * @author jhl
     * 带回订单号，不再进行重复通知
     */
    public function backEndUrl()
    {
        ob_clean();
        $response = file_get_contents("php://input");
        Log::write(['logName' => 'IwxbankCallBackscanCodePayment','data' => $response,'request' => $_REQUEST],'debug');
        $data = json_decode($response, 1);
        if (empty($data)) {
            exit('数据错误');
        }
        if (isset($data['respCode'])) {
            Log::write(['logName' => 'IwxbankCallBackscanCodePayment1_' . $data['merchOrderNo'],'data' => $data],'debug');
            $payBase = new payBase();
    
            //016移动快捷支付
            $iwxbankConfig = config('iwxbank');
            if($data['payType'] =='016'){
                $payKey = $iwxbankConfig['payCreate']['keyQuickPayment'][PRODUCT_ENV];
            }else{
                $payKey = $iwxbankConfig['payCreate']['key'][PRODUCT_ENV];
            }
            //签名验证
            $signResult = $payBase->checkSign($data,$payKey);
            if ($signResult['status'] == false) {
                Log::write(['logName' => 'IwxbankCallBackscanCodePayment2_' . $data['merchOrderNo'], 'msg' => '签名验证失败','data' => $data],'debug');
                exit('签名失败');
            }
            $accountWalletMerchantReceipt = new AccountWalletMerchantReceipt();
            $receiptInfo = $accountWalletMerchantReceipt->getOneByWhere([
                'where' => ['output_order_no' => $data['merchOrderNo']]
            ],'pay_status');
            if (empty($receiptInfo)) {
                exit('数据不存在');
            }
            if ($receiptInfo['pay_status']) {
                //返回单号，防止重复通知--特殊情况通过查询接口进行数据更新
                exit('SUCCESS');
            }
            
            $accountWalletPay = new AccountWalletPay();
            if ($data['respCode'] == '00') {
                $accountWalletPay->paySuccess($data);
                //返回单号，防止重复通知
                exit('SUCCESS');
            } elseif ($data['respCode'] == '62') {
                exit('等待支付状态');
            } else {
                $logicDic = new logicDic();
                //若在重新查询请求列表，发查询确认脚本
                if (in_array($data['respCode'],$logicDic->repeatSelectTrueStatusList())) {
                    $accountWalletPay->insertRedisList($data);
                } else {
                    $accountWalletPay->payFalse($data);
                    //返回单号，防止重复通知--特殊情况通过查询接口进行数据更新
                    exit('SUCCESS');
                }
            }
        } else {
            Log::write(['logName' => 'IwxbankCallBackscanCodePayment4','msg' => '回调decode失败','data' => $data],'debug');
            exit('回调decode失败');
        }
    }
    
    
}