<?php
namespace application\cron\controller;

use think\Controller;
use application\common\Myredis;
use application\common\logic\payment\wwwIwxbankCom\Base as payBase;
use application\common\logic\walletLogic\AccountWalletPay;
use application\common\logic\payment\wwwIwxbankCom\Dic as logicDic;
use application\common\model\wallet\AccountWalletMerchantReceipt;
/**
 *
 * @uses 我的钱包相关定时脚本
 * @author jhl<liujihaoth@126.com>
 *         @data 2017/09/29
 */
header("Content-type:text/html;Charset=utf8");

class Wallet extends Controller
{

    function __construct()
    {}

    /**
     *
     * @uses 扫码充值，定时脚本
     * @author jhl
     */
    public function handleOrder()
    {
        $accountWalletPay = new AccountWalletPay();
        $redisListName = $accountWalletPay->welletServerUnpayRedisListName();
        $redisConnectProject = Myredis::getRedisConn(5);
        $creditLength = $redisConnectProject->getListLength($redisListName);
        if ($creditLength < 0) {
            echo '没有需要执行的数据';
            exit();
        }
        $repayList = $redisConnectProject->getList($redisListName, 0, 50);
        if (empty($repayList)) {
            echo '没有需要执行的数据';
            exit();
        }
        
        // 操作加锁
        $lockname = 'itbtws_crowdfunding_sendCrowdFundingCredit';
        $back = cache($lockname);
        if ($back) {
            echo '请等待上一个任务完成';
            exit();
        }
        cache($lockname, 1, 40);
        
        $accountWalletPay = new AccountWalletPay();
        $iwxbankConfig = config('iwxbank');
        $WalletMerchantReceiptModel = new AccountWalletMerchantReceipt();
        foreach ($repayList as $rk => $repayInfo) {
            //先查询系统中状态是否已经改变，如果改变，直接删除redis
            $merchantReceiptInfo = $WalletMerchantReceiptModel->getOneByWhere([
                'field' => 'pay_status,receipt_platform',
                'where' => [
                    'output_order_no' => $repayInfo['merchOrderNo'],
                ]
            ]);
            if (!empty($merchantReceiptInfo) && $merchantReceiptInfo['pay_status']) {
                echo '商户订单编号：' . $repayInfo['merchOrderNo'] . '支付完成<br/>';
                // 将队列中数据移除
                $rs = $redisConnectProject->deleteFromList($redisListName, $repayInfo);
                if (is_null($rs) || ! $rs) {
                    $rs = $redisConnectProject->deleteFromList($redisListName, $repayInfo);
                    if (is_null($rs) || ! $rs) {
                        $rs = $redisConnectProject->shiftFromList($redisListName);
                    }
                }
                continue;
            }
            
            //协议版本
            $param['version'] = (string)$iwxbankConfig['payCreate']['version'];
            
            //机构号
            $logicDic = new logicDic();
            $payTypeCode = (string)$logicDic->payType($merchantReceiptInfo['receipt_platform'],'code');
            //如果是无卡快捷支付
            if ($payTypeCode == $logicDic::QUICKPAY_CODE) {
                $param['organNo'] = (string)$iwxbankConfig['payCreate']['organNoQuickPayment'][PRODUCT_ENV];
                $payKey = $iwxbankConfig['payCreate']['keyQuickPayment'][PRODUCT_ENV];
            } else {
                $param['organNo'] = (string)$iwxbankConfig['payCreate']['organNo'][PRODUCT_ENV];
                $payKey = $iwxbankConfig['payCreate']['key'][PRODUCT_ENV];
            }
            //汇卡商户号
            $param['hicardMerchNo'] = (string)$repayInfo['hicardMerchNo'];
            
            //商户订单号
            $param['merchOrderNo'] = (string)$repayInfo['merchOrderNo'];

            $payBase = new payBase();
            $result = $payBase->selectOrder($iwxbankConfig['payQuery']['requestUrl'][PRODUCT_ENV],$param,$payKey);
            if(!isset($result['respCode'])) {
                 echo '商户订单编号：' . $param['merchOrderNo'] . ':请求超时<br/>'; 
            }
            if ($result['respCode'] === '00') {
                $accountWalletPay->paySuccess($result);
                echo '商户订单编号：' . $param['merchOrderNo'] . '支付成功<br/>';
                // 将队列中数据移除
                $rs = $redisConnectProject->deleteFromList($redisListName, $repayInfo);
                if (is_null($rs) || ! $rs) {
                    $rs = $redisConnectProject->deleteFromList($redisListName, $repayInfo);
                    if (is_null($rs) || ! $rs) {
                        $rs = $redisConnectProject->shiftFromList($redisListName);
                    }
                }
            } elseif(in_array($result['respCode'], $logicDic->waitPayResultCodeList())) {
                echo '商户订单编号：' . $param['merchOrderNo'] . ':等待支付状态<br/>';
            } else {
                //若在重新查询请求列表，发查询确认脚本
                if (in_array($result['respCode'],$logicDic->repeatSelectTrueStatusList())) {
                    //不做处理
                    
                } else {
                    $accountWalletPay->payFalse($result);
                    echo '商户订单编号：' . $param['merchOrderNo'] . '支付失败<br/>';
                    // 将队列中数据移除
                    $rs = $redisConnectProject->deleteFromList($redisListName, $repayInfo);
                    if (is_null($rs) || ! $rs) {
                        $rs = $redisConnectProject->deleteFromList($redisListName, $repayInfo);
                        if (is_null($rs) || ! $rs) {
                            $rs = $redisConnectProject->shiftFromList($redisListName);
                        }
                    }
                }
            }
        }
        echo '本次轮询完成<br/>';
        cache($lockname, null);
    }
    
    /**
     * @uses 定时脚本，超过2个小时零2分，不再支持支付
     * @author jhl
     * 只修改pay_status,不修改pay_time
     */
    public function updateOvertimeOrder()
    {
        $WalletMerchantModel = new AccountWalletMerchantReceipt();
        $affectedRows = $WalletMerchantModel->save([
            'pay_status' => -1
        ],[
            'pay_status' => 0,
            'create_time' => ['elt',date('Y-m-d H:i:s',time() - 3660)]
        ]);
        echo '执行完成' . $affectedRows . '条数据'; 
    }
    
}
