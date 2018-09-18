<?php

namespace application\common\model\wallet;

use application\common\model\Base;
use think\Db;

/**
 * @uses 我的账户-用户业务提醒设置表
 * @author jhl
 */
class AccountWalletMerchantReceipt extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'account_wallet_merchant_receipt';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';
    
    //支付方式
    public $receiptPlatform = [
        1 => '微信扫码收钱',
        2 => '支付宝扫码收钱',
        3 => 'QQ扫码充值',
        4 => '京东支付扫码收钱',
        5 => '银联钱包支付',
        6 => '银行快捷支付'
    ];
    
    /**
     * @uses 获取分页相关参数--解决同一时间段非自增排序字段分页问题
     * @param array $list 列表
     * @param int $limitData 分页参数
     * @param string $exitIdString：已存在的id列表
     */
    public function getPageInfo($list, $limitData, $exitIdString = '')
    {
        $offectTime = '';
        $nextExitStatus = (count($list) < $limitData) ? 0 : 1;
        if (!empty($list)) {
            $endList = end($list);
            $offectTime = $endList['pay_time'];
            if ($exitIdString) {
                $exitIdString .= ',';
            }
            foreach ($list as $v) {
                $exitIdString .= $v['id'] . ',';
            }
            $exitIdString = rtrim($exitIdString,',');
        }
        
        return [
            'offectTime' => $offectTime,
            'exitIdString' => $exitIdString,
            'nextExitStatus' => $nextExitStatus
        ];
    }
    
    /**
     * @uses 查询支付信息
     * @param int $id :订单号
     */
    public function getMerchartReceiptInfo($outputOrderNo)
    {
        $result = Db::name($this->_table)
            ->alias('a')
            ->field('a.id,a.output_order_no,a.receipt_platform,a.receipt_amount,a.pay_status,b.business_license_register_no')
            ->join('itd_account_wallet_merchant_certification b', 'a.user_id=b.user_id', 'left')
            ->where(['a.output_order_no' => $outputOrderNo])
            ->find();
        return $result;
    }
    
}
