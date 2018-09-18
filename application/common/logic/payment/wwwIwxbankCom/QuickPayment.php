<?php

namespace application\common\logic\payment\wwwIwxbankCom;

use think\Log;
/**
 * @uses 汇卡支付--快捷支付
 * @author jhl
 */
class QuickPayment extends Base
{
    /**
     * @uses 无卡快捷支付返回数据处理
     * @author jhl
     */
     public function getPayUrl($returnDatas = '')
     {
         if (empty($returnDatas)) {
             return [
                 'status' => false,
                 'msg' => '无数据返回',
                 'url' => ''
             ];
         }
         $pattern = '/href=\'(.*)\'/';
         preg_match($pattern,$returnDatas, $url);
         $url = $url[1];
         $info = json_decode($returnDatas,true);
         if($url){
             //使用快付通的返回格式
             return [
                 'status' => true,
                 'msg' => '获取成功',
                 'url' => $url
             ];
         }else{
             Log::write([
                    'logName' => "[QuickPaymentgetPayUrlFalse]",
                    'result' => $info
                ], 'debug');
             return [
                 'status' => false,
                 'msg' => isset($info['respMsg']) ? $info['respMsg'] : '支付链接获取失败',
                 'url' => ''
             ];
         }
          
     }
}