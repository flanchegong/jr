<?php

namespace application\m\controller;

use application\common\model\wallet\AccountWalletMerchantReceipt;
use application\common\model\wallet\AccountWalletProvince;
use application\common\model\wallet\AccountWalletCity;
use application\common\model\wallet\AccountWalletBankBranch;
use application\common\model\wallet\AccountWalletBank;
use application\common\model\wallet\AccountWalletMerchantCertification;
use application\common\model\user\Iuser as modelIuser;
use application\common\model\system\BaseBankCardBin;
use application\common\Myredis;
use application\common\model\wallet\AccountWalletCityHuika;
use application\common\logic\account\AccountSecurity;

/**
 * @uses 钱包类--为tp3提供接口
 * @author jhl<liujihaoth@126.com>
 * @date 2017-09-19
 */
class Wallet extends Base {

    public $accountWalletMerchantReceiptModel;
    public $userId;
    public $limit = 20;
    public function __construct() 
    {
        $this->accountWalletMerchantReceiptModel = new AccountWalletMerchantReceipt();
        $this->userId = parent::userId(input('post.auth','','string'));
        if ($this->userId <= 0) {
            parent::failJson('请先登录',100);
        }
    }
    
    /**
     * @uses 今日收款列表
     * @author jhl
     */
    public function receiptTodayList()
    {
        $offectTime = input('post.offectTime','','string');
        $exitIdString = input('post.exitIdString','','string');
        $settle_account_mode = input('post.settle_account_mode','','string');
        $where['pay_status'] = 1;
        $where['user_id'] = $this->userId;
        $beginTime = date('Y-m-d H:i:s',mktime(0,0,0,date('m'),date('d'),date('Y')));
        $endTime = date('Y-m-d H:i:s',mktime(0,0,0,date('m'),date('d')+1,date('Y')));
        $where['pay_time'] = [['egt',$beginTime],['lt',$endTime],'and'];

        if($settle_account_mode !=''){
            $where['settle_account_mode'] = ['eq',$settle_account_mode];
        }
        //今日收款总额，收款笔数
        $receiptSumAmount = $this->accountWalletMerchantReceiptModel->getOneByWhere([
            'field' => 'SUM(receipt_amount) AS receipt_amount,count(id) as countValue',
            'where' => $where
        ]);
        if ($offectTime && $exitIdString) {
            $where['pay_time'] = [['egt',$beginTime],['elt',$offectTime],'and'];
            $where['id'] = ['NOT IN',$exitIdString];
        }

        $list = $this->accountWalletMerchantReceiptModel->getList([
            'field' => 'id,receipt_platform,receipt_amount,pay_time,settle_account_mode',
            'where' => $where,
            'order' => 'pay_time DESC',
            'limit' => $this->limit
        ]);

        $getPageInfo = $this->accountWalletMerchantReceiptModel->getPageInfo($list, $this->limit, $exitIdString);
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $list[$k]['receipt_platform_msg'] = $this->accountWalletMerchantReceiptModel->receiptPlatform[$v['receipt_platform']];
            }
        }
        $returnDatas = [
            'offectTime' => $getPageInfo['offectTime'],//偏移量
            'exitIdString' => $getPageInfo['exitIdString'],//已经存在的id
            'nextExitStatus' => $getPageInfo['nextExitStatus'],//下一页是否存在数据（0：不存在；1：还存在）
            'receiptSumAmount' => isset($receiptSumAmount['receipt_amount']) ? ($receiptSumAmount['receipt_amount'] ? $receiptSumAmount['receipt_amount'] : 0.00) : 0.00,
            'countValue' => isset($receiptSumAmount['countValue']) ? ($receiptSumAmount['countValue'] ? $receiptSumAmount['countValue'] : 0) : 0,
            'list' => $list//列表
        ];
        
        parent::okJson($returnDatas,200);
    }
    
    /**
     * @uses 某一天的收款记录
     * @author jhl
     */
    public function receiptSpecificdayList()
    {
        $time = input('post.time','','string');
        if ($time == '') {
            parent::failJson('请输入天数',101);
        }
        $offectTime = input('post.offectTime','','string');
        $exitIdString = input('post.exitIdString','','string');
        $settle_account_mode = input('post.settle_account_mode','','string');
        $month = date('m',strtotime($time));
        $day = date('d',strtotime($time));
        $year = date('Y',strtotime($time));
        $where['pay_status'] = 1;
        $where['user_id'] = $this->userId;
        $beginTime = date('Y-m-d H:i:s',mktime(0,0,0,$month,$day,$year));
        $endTime = date('Y-m-d H:i:s',mktime(0,0,0,$month,$day + 1,$year));
        $where['pay_time'] = [['egt',$beginTime],['lt',$endTime],'and'];

        if($settle_account_mode !=''){
            $where['settle_account_mode'] = ['eq',$settle_account_mode];
        }
        //今日收款总额，收款笔数
        $receiptSumAmount = $this->accountWalletMerchantReceiptModel->getOneByWhere([
            'field' => 'SUM(receipt_amount) AS receipt_amount,count(id) as countValue',
            'where' => $where
        ]);

        if ($offectTime && $exitIdString) {
            $where['pay_time'] = [['egt',$beginTime],['elt',$offectTime],'and'];
            $where['id'] = ['NOT IN',$exitIdString];
        }
        $list = $this->accountWalletMerchantReceiptModel->getList([
            'field' => 'id,receipt_platform,receipt_amount,pay_time,settle_account_mode',
            'where' => $where,
            'order' => 'pay_time DESC',
            'limit' => $this->limit
        ]);
        $getPageInfo = $this->accountWalletMerchantReceiptModel->getPageInfo($list, $this->limit, $exitIdString);
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $list[$k]['receipt_platform_msg'] = $this->accountWalletMerchantReceiptModel->receiptPlatform[$v['receipt_platform']];
            }
        }
        $returnDatas = [
            'offectTime' => $getPageInfo['offectTime'],//偏移量
            'exitIdString' => $getPageInfo['exitIdString'],//已经存在的id
            'nextExitStatus' => $getPageInfo['nextExitStatus'],//下一页是否存在数据（0：不存在；1：还存在）
            'receiptSumAmount' => isset($receiptSumAmount['receipt_amount']) ? ($receiptSumAmount['receipt_amount'] ? $receiptSumAmount['receipt_amount'] : 0.00) : 0.00,
            'countValue' => isset($receiptSumAmount['countValue']) ? ($receiptSumAmount['countValue'] ? $receiptSumAmount['countValue'] : 0) : 0,
            'dayTime' => date('Y') != date('Y',strtotime($time)) ? date('Y年n月j日',strtotime($time)) : date('n月j日',strtotime($time)),
            'list' => $list//列表
        ];
        
        parent::okJson($returnDatas,200);
    }
    
    /**
     * @uses 历史收款列表
     * @author jhl
     */
    public function receiptHistoryList()
    {
        $offectTime = input('post.offectTime','','string');
        $exitIdString = input('post.exitIdString','','string');
        $where = ['user_id' => $this->userId,'pay_status' => 1];
        if ($offectTime && $exitIdString) {
            $where['pay_time'] = ['elt',$offectTime];
            $where['id'] = ['NOT IN',$exitIdString];
        }
        $list = $this->accountWalletMerchantReceiptModel->getList([
            'field' => 'id,receipt_platform,receipt_amount,pay_time,settle_account_mode',
            'where' => $where,
            'order' => 'pay_time DESC',
            'limit' => $this->limit
        ]);
        $getPageInfo = $this->accountWalletMerchantReceiptModel->getPageInfo($list, $this->limit, $exitIdString);
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $list[$k]['receipt_platform_msg'] = $this->accountWalletMerchantReceiptModel->receiptPlatform[$v['receipt_platform']];
            }
        }
        $returnDatas = [
            'offectTime' => $getPageInfo['offectTime'],//偏移量
            'exitIdString' => $getPageInfo['exitIdString'],//已经存在的id
            'nextExitStatus' => $getPageInfo['nextExitStatus'],//下一页是否存在数据（0：不存在；1：还存在）
            'list' => $list//列表
        ];
        
        parent::okJson($returnDatas,200);
    }
    
    /**
     * @uses 历史收款列表--按照天排序
     * @author jhl
     */
    public function receiptHistoryDayList()
    {
        $offectTime = input('post.offectTime','','string');
        $where = ['user_id' => $this->userId,'pay_status' => 1];
        if ($offectTime) {
            $where['pay_time'] = ['lt',$offectTime];
        }
        $list = $this->accountWalletMerchantReceiptModel->getList([
            'field' => "COUNT(id) as cCount,SUM(receipt_amount) as sReceiptAmount,pay_time AS dPayTime",
            'where' => $where,
            'group' => "DATE_FORMAT(pay_time,'%Y-%m-%d')",
            'order' => 'pay_time DESC',
            'limit' => $this->limit
        ]);
        $offectTime = '';
        if (!empty($list)) {
            $endList = end($list);
            $offectTime = $endList['dPayTime'];
            foreach ($list as $k => $v) {
                if (date('Y-m-d') == date('Y-m-d',strtotime($v['dPayTime']))) {
                    $list[$k]['showTime'] = '今日';
                } else {
                    $list[$k]['showTime'] = date('n月j日',strtotime($v['dPayTime']));
                }
                if (date('Y') != date('Y',strtotime($v['dPayTime']))) {
                    $list[$k]['showTime'] = date('Y年n月j日',strtotime($v['dPayTime']));
                }
            }
        }
        $nextExitStatus = (count($list) < $this->limit) ? 0 : 1;
        parent::okJson(['offectTime' => $offectTime,'nextExitStatus' => $nextExitStatus,'list' => $list],200);
    }
    
    /**
     * @uses 收款详情
     * @author jhl
     */
    public function receiptDetail()
    {
        $id = input('post.id',0,'int');
        if ($id <= 0) {
            parent::failJson('参数错误',101);
        }
        $detail = $this->accountWalletMerchantReceiptModel->getOneByWhere([
            'where' => ['id' => $id,'user_id' => $this->userId,'pay_status' => 1],
        ],'output_order_no as id,receipt_platform,receipt_amount,pay_time,user_id,settle_account_mode');
        if (empty($detail)) {
            parent::failJson('对应的数据不存在',102);
        }
        $detail['receipt_platform_msg'] = $this->accountWalletMerchantReceiptModel->receiptPlatform[$detail['receipt_platform']];
        
        //收款总额
        $sum = $this->accountWalletMerchantReceiptModel->getOneByWhere([
            'where' => ['user_id' => $detail['user_id'],'pay_status' => 1],
        ],'SUM(receipt_amount) AS receipt_amount');
        $detail['receipt_amount_sum'] = $sum['receipt_amount'];
        unset($detail['user_id']);
        parent::okJson($detail,200);
    }
    
    /**
     * @uses 省份初始化
     * @author jhl
     */
    public function initProvince()
    {
        $accountWalletProvince = new AccountWalletProvince();
        $provinceList = $accountWalletProvince->getList([
            'field' => 'province_id,province_name',
            'where' => ['is_support' => 1],
            'order' => 'convert(province_name using gbk) ASC'
        ]);
        parent::okJson($provinceList,200);
    }
    
    /**
     * @uses 根据省份获取城市列表
     * @author jhl
     */
    public function getCityList()
    {
        $provinceId = input('post.provinceId','','string');
        if (!$provinceId) {
            parent::failJson('请选择省份',101);
        }
        
        $accountWalletCity = new AccountWalletCity();
        $cityList = $accountWalletCity->getJoinCityList(
            ['a.province_id' => $provinceId],
            'convert(a.city_name using gbk) ASC');
        $accountWalletCityHuika = new AccountWalletCityHuika();
        foreach ($cityList as $k => $v) {
            if (!$v['area_code']) {
                //考虑到特殊情况很低，将查询直接写入循环中
                $areaCode = $accountWalletCityHuika->getChildFirstAreaCode($provinceId,$v['city_id']);
                if ($areaCode) {
                    $cityList[$k]['area_code'] = $areaCode;
                } else {
                    unset($cityList[$k]);
                }
            }
        }
        sort($cityList);
        parent::okJson($cityList,200);
    }

    /**
     *
     * @uses 银行列表
     * @author jhl
     */
    public function bankList()
    {
        $bankName = input('post.bankName', '', 'string');
        $where = [];
        if ($bankName) {
            $where['bank_name'] = ['like',"%{$bankName}%"];
        }
        $accountWalletBank = new AccountWalletBank();
        $bankList = $accountWalletBank->getList([
            'field' => 'bank_id,bank_name',
            'where' => $where
        ]);
        parent::okJson($bankList,200);
    }
    
    /**
     * @uses 根据银行卡获取银行信息
     * @author jhl
     */
    public function getCardBankInfo()
    {
        //卡号
        $cardNo = input('post.cardNo','','string');
        if (!$cardNo) {
            parent::failJson('请输入银行卡',101);
        }
        $strlen = strlen($cardNo);
        if ($strlen < 5) {
            parent::failJson('请输入5位以上数字',102);
        }

        $baseBankCardBin = new BaseBankCardBin();
        
        //如果是5位
        if ($strlen == 5) {
            $cardInfo = $baseBankCardBin->getOneByWhere([
                'field' => 'bank_name',
                'where' => ['bank_card_issue_bin' => substr($cardNo, 0, 5)]
            ]);
            if (empty($cardInfo)) {
                parent::failJson('未获取到相关银行，请手动选择',103);
            } else {
                parent::okJson($cardInfo,200);
            }
        }
        
        //如果是6位，虽然5位已经做判断，考虑到网络问题，需要查询5位数据
        $cardInfo = $baseBankCardBin->getOneByWhere([
            'field' => 'bank_name',
            'where' => ['bank_card_issue_bin' => substr($cardNo, 0, 6)]
        ]);
        if (empty($cardInfo)) {
            $cardInfo = $baseBankCardBin->getOneByWhere([
                'field' => 'bank_name',
                'where' => ['bank_card_issue_bin' => substr($cardNo, 0, 5)]
            ]);
            if (empty($cardInfo)) {
                parent::failJson('未获取到相关银行，请手动选择',103);
            } else {
                parent::okJson($cardInfo,200);
            }
        }
        parent::okJson($cardInfo,200);
    }
    
    /**
     * @uses 根据省,市,银行信息获取支行列表
     * @author jhl
     */
    public function getBankBranchList()
    {
        $provinceId = input('post.provinceId','','string');
        $cityId = input('post.cityId','','string');
        $bankName = input('post.bankName','','string');
        if (!$provinceId) {
            parent::failJson('请选择省份',101);
        }
        if (!$cityId) {
            parent::failJson('请选择城市',102);
        }
        if (!$bankName) {
            parent::failJson('请填写银行名称',103);
        }
        $where = ['bank_branch_status' => 1,'province_id' => $provinceId,'city_id' => $cityId];
        if ($bankName) {
            $where['bank_name'] = ['like',"%{$bankName}%"];
        }
        
        $accountWalletBankBranch = new AccountWalletBankBranch();
        $branchList = $accountWalletBankBranch->getList([
            'field' => 'bank_branch_unionpay_no,bank_branch_name',
            'where' => $where,
            'order' => 'convert(bank_branch_name using gbk) ASC'
        ]);
        parent::okJson($branchList,200);
    }
    
    /**
     * @uses 获取绑定银行卡信息
     * @author jhl
     */
    public function getBindCardInfo()
    {
        $accountCertification = new AccountWalletMerchantCertification();
        $info = $accountCertification->getOneByWhere([
            'where' => ['user_id' => $this->userId]
        ],'bank_account,bank_name,bank_id,bank_name,bank_branch_address_province,bank_branch_address_city,bank_branch_unionpay_no,bank_branch_name');
        if (empty($info)) {
            parent::failJson('用户还未入网认证',101);
        }
        //银行卡号
        $info['cardNo'] = $info['bank_account'];
        
        $info['bank_account'] = '**** **** **** ' . substr($info['bank_account'],-4,4);
        
        //获取地区码
        $posAreaCode = '';
        if ($info['bank_branch_address_city'] && $info['bank_branch_address_province']) {
            $walletCityHuikaModel = new AccountWalletCityHuika();
            $cityHuikaInfo = $walletCityHuikaModel->getOneByWhere([
                'where' => ['city_name' => $info['bank_branch_address_city']]
            ],'area_code');//这个位置可获得省份信息
            $posAreaCode = isset($cityHuikaInfo['area_code']) ? $cityHuikaInfo['area_code'] : '';
            if ($posAreaCode == '') {
                $accountWalletCity = new AccountWalletCity();
                $areaInfo = $accountWalletCity->getOneByWhere([
                    'field' => 'province_id,city_id',
                    'where' => [
                        'province_name' => $info['bank_branch_address_province'],
                        'city_name' => $info['bank_branch_address_city'],
                    ]
                ]);
                if (!empty($areaInfo)) {
                    //考虑到特殊情况很低，将查询直接写入循环中
                    $accountWalletCityHuika = new AccountWalletCityHuika();
                    $posAreaCode = $accountWalletCityHuika->getChildFirstAreaCode($areaInfo['province_id'],$areaInfo['city_id']);
                }
            }
        }
        
        //银行行号
        $info['bankNo'] = $info['bank_branch_unionpay_no'];
        
        //支行名称
        $info['bankBranchName'] = $info['bank_branch_name'];
        
        //省份
        $info['bankProvince'] = $info['bank_branch_address_province'];
        
        //城市
        $info['bankCity'] = $info['bank_branch_address_city'];
        
        //地区码
        $info['posAreaCode'] = $posAreaCode;
        
        unset($info['bank_branch_unionpay_no'],$info['bank_branch_address_province'],$info['bank_branch_address_city'],$info['bank_branch_name']);
        
        //安全校验key
        $info['key'] = $this->userId;
        parent::okJson($info,200);
    }

    /**
     *
     * @uses 交易密码校验
     * @author jhl
     */
    public function checkPayPassword()
    {
        $payPassword = input('post.paypassword', '', 'string');
        if (! $payPassword) {
            parent::failJson('请输入交易密码', 101);
        }

        $payPassword = str_replace(' ', '+', $payPassword);
        $xxtea = new \Xxtea();
        $payPassword = $xxtea->decrypt(base64_decode($payPassword), md5($this->userId));
        $modelIuser = new modelIuser();
        $userInfo = $modelIuser->getOneByWhere([
            'where' => ['user_id' => $this->userId]
        ], 'paypassword,username');
        if (empty($userInfo)) {
            parent::failJson('用户信息不存在', 102);
        }
        if (empty($userInfo['paypassword'])) {
            parent::failJson('您还未设置交易密码', 103);
        }

        $accountSecurity = new AccountSecurity();
        $payPasswordTimesCheck = $accountSecurity->logonTimes($this->userId,($userInfo['paypassword'] == md5($payPassword)) ? $userInfo['username'] : '');
        if ($payPasswordTimesCheck['status'] == false) {
            parent::failJson($payPasswordTimesCheck['msg'], $payPasswordTimesCheck['code']);
        }
        parent::okJson([], 200, '交易密码验证成功');
    }
    
    /**
     * @uses 商户入网状态
     * @author jhl
     */
    public function registerStatus()
    {
        $accountWalletInfo = Myredis::getRedisConn(8)->getFromHash('account_wallet_merchant_certification', 'register_info_' . $this->userId);
        if (!isset($accountWalletInfo['user_id'])) {
            $accountWalletMerchantCertification = new AccountWalletMerchantCertification();
            $accountcertInfo = $accountWalletMerchantCertification->getOneByWhere([
                'where' => [
                    'user_id' => $this->userId
                ]
            ],'user_id,user_name,bank_account,bank_name,bank_branch_name,bank_branch_address_province,bank_branch_address_city,business_license_register_no,enterprise_name,business_operator_name,enterprise_address_province,enterprise_address_city,enterprise_address_street,bank_branch_unionpay_no,terminal_no,certification_time');
            if (isset($accountcertInfo['user_id'])) {
                Myredis::getRedisConn(8)->setToHash('account_wallet_merchant_certification', 'register_info_' . $this->userId, $accountcertInfo);
                parent::okJson([],200,'商户已入网');
            } else {
                //继续检测是否已经实名以及手机认证
                $modelIuser = new modelIuser();
                $userInfo = $modelIuser->getOneByWhere(['where' => ['user_id' => $this->userId]],'real_status,phone_status,card_id,realname');
                if (!$userInfo['real_status'] || !$userInfo['phone_status']) {
                    parent::okJson([],155,'亲，我的钱包-商家收款需实名认证后才能申请认证开通');//编号不要去改变
                }

                //同实名用户只允许开通一个商家收款
                $accountcardIdRealNameStatus = $accountWalletMerchantCertification->accountcardIdRealNameStatus([
                    'b.card_id' =>  $userInfo['card_id'],
                    'b.realname' => $userInfo['realname'],
                    'b.user_id' => ['neq',$this->userId]
                ]);
                if ($accountcardIdRealNameStatus == true) {
                    parent::okJson([],155,'同实名用户只允许开通一个商家收款');//编号不要去改变
                }
                
                parent::okJson([],101,'商户还未入网');
            }
        }
        parent::okJson([],200,'商户已入网');
    }
    
    /**
     * @uses 判断用户是否实名
     * @author jhl
     */
    public function realNameStatus()
    {
        $modelIuser = new modelIuser();
        $userInfo = $modelIuser->getOneByWhere([
            'where' => ['user_id' => $this->userId]
        ], 'real_status');
        parent::okJson(['real_status' => $userInfo['real_status'] ? $userInfo['real_status'] : 0],200,$userInfo['real_status'] ? '用户已经实名' : '请先进行实名认证');
    }
    
}









