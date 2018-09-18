<?php

namespace application\common\logic\walletLogic;

use application\common\model\wallet\AccountWalletMerchantReceipt;
use think\Log;
use application\common\Myredis;
/**
 * @uses 汇卡钱包-订单支付
 * @author Administrator
 *
 */
class AccountWalletPay
{

    /**
     * @uses 支付成功
     * @author jhl
     */
    public function paySuccess($data)
    {
        $saveDatas = [
            'pay_status' => 1,
            'pay_time' => $data['payTime'] ? $data['payTime'] : date('Y-m-d H:i:s')
        ];
        if (isset($data['hicardOrderNo']) && $data['hicardOrderNo']) {
            $saveDatas['huika_order_no'] = $data['hicardOrderNo'];
        }
        $accountWalletMerchantReceipt = new AccountWalletMerchantReceipt();
        $affectedRows = $accountWalletMerchantReceipt->save($saveDatas,['output_order_no' => $data['merchOrderNo']]);
        $data['sql'] = $accountWalletMerchantReceipt->getLastSql();
        Log::write(['logName' => 'paySuccessSql' . $data['merchOrderNo'], 'msg' => '签名验证失败','data' => $data],'debug');
        //处理完成，写状态
        cache(self::payReturnCacheName($data['merchOrderNo']), 1, 86400);
        //如果金融平台处理失败，写日志
        if (!$affectedRows) {
            Log::write(['logName' => 'IwxbankPayhandlemerchantreceiptfail' . $data['merchOrderNo'],'msg' => $data['merchOrderNo'] . '影响记录条数为0，请确定修改状态'],'debug');
        }
        //不对金融平台修改状态判断
        return [
            'code' => $data['merchOrderNo']
        ];
    }
    
    /**
     * @uses 支付失败
     * @author jhl
     */
    public function payFalse($data)
    {
        $saveDatas = [
            'pay_status' => -1,
            'pay_time' => date('Y-m-d H:i:s')
        ];
        if (isset($data['hicardOrderNo']) && $data['hicardOrderNo']) {
            $saveDatas['huika_order_no'] = $data['hicardOrderNo'];
        }
        $accountWalletMerchantReceipt = new AccountWalletMerchantReceipt();
        $affectedRows = $accountWalletMerchantReceipt->save($saveDatas,['output_order_no' => $data['merchOrderNo']]);
        //处理完成，写状态2
        cache(self::payReturnCacheName($data['merchOrderNo']), 2, 86400);
        //如果金融平台处理失败，写日志
        if (!$affectedRows) {
            Log::write(['logName' => 'IwxbankPayhandlemerchantreceiptfail' . $data['merchOrderNo'],'msg' => $data['merchOrderNo'] . '影响记录条数为0，请确定修改状态'],'debug');
        }
        //不对金融平台修改状态判断
        return [
            'code' => $data['merchOrderNo']
        ];
    }

    /**
     * @uses 写入redis列表
     * @author jhl
     */
    public function insertRedisList($data) 
    {
        Myredis::getRedisConn(5)->appendToList(self::welletServerUnpayRedisListName(),[
            'hicardMerchNo' => $data['hicardMerchNo'],
            'merchOrderNo' => $data['merchOrderNo']
        ]);
    }
    
    /**
     * @uses 需要查询状态的订单redis列表名称
     * @return string
     */
    public function welletServerUnpayRedisListName()
    {
        return 'welletServerUnpay';
    }
    
    /**
     * @uses 支付成功cache前缀
     * @author jhl
     * cache($cachename) == 1:成功;==2:失败
     */
    public function payReturnCacheName($orderNo)
    {
        return 'walletPayStatus_' . $orderNo;
    }
    
}
