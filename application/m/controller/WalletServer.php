<?php

namespace application\m\controller;
use application\common\logic\externalInterface\wwwIwxbankCom\MerchantAccess;
use application\common\logic\externalInterface\wwwIwxbankCom\SettleCard;
use application\common\model\wallet\AccountWalletMerchantCertification;
use think\Log;
use application\common\logic\payment\wwwIwxbankCom\Base as payBase;
use application\common\model\wallet\AccountWalletMerchantReceipt;
use application\common\logic\payment\wwwIwxbankCom\Dic as logicDic;
use application\common\logic\walletLogic\AccountWalletPay;
use application\common\model\user\Iuser as modelIuser;
use application\common\Myredis;
use application\common\model\wallet\AccountWalletBank;
use application\common\logic\account\AccountSecurity;
use think\Exception;

/**
 * @uses 汇卡-钱包类--外部接口
 * @author jhl<liujihaoth@126.com>
 * @date 2017-09-19
 * *******************************注意：汇卡商户号(hicardMerchNo)在程序中便于调试，写成固定数据，商户入网获取到的商户号暂时无法测试*********************
 */
class WalletServer extends Base {

    public function __construct() 
    {
        
    }
    
    /**
     * @uses 入网、扫码支付功能开启状态
     * @author jhl
     */
    private function registerAndPayOpenStatus()
    {
        $closeStatus = Myredis::getRedisConn(0)->get('wallet_server_register_pay_close_status');
        if ($closeStatus == 1) {
            parent::failJson('暂时关闭入网、支付功能',150);
        }
        return true;
    }
    
    /**
     * @uses 商户入网接口
     * @author jhl
     */
    public function register()
    {
        //临时开关控制
        self::registerAndPayOpenStatus();
        //防重复提交逻辑处理
        
        $auth = input('post.auth', '', 'string,trim');
        
        //商户名称
        $merName = input('post.merName', '', 'string,trim');

        //商户地址
        $merAddressProvince = input('post.merAddressProvince', '', 'string,trim');
        $merAddressCity = input('post.merAddressCity', '', 'string,trim');
        $merAddressStreet = input('post.merAddressStreet', '', 'string,trim');
        
        //绑定的开户银行支行地址之省
        $bankBranchAddressProvince = input('post.bankBranchAddressProvince', '', 'string,trim');
        
        //绑定的开户银行支行地址之省
        $bankBranchAddressCity = input('post.bankBranchAddressCity', '', 'string,trim');
        
        //商户地址-地区码city.areacode
        $posAreaCode = input('post.posAreaCode', '', 'string,trim');

        //开户银行行号clearbankcode.bankid
        $mbiBankNo = input('post.mbiBankNo', '', 'string,trim');

        //开户银行名称
        $mbiBankName = input('post.mbiBankName', '', 'string,trim');
        
        //支行名称
        $bankBranchName = input('post.bankBranchName', '', 'string,trim');
        
        //银行卡账号
        $mbiAccountNo = input('post.mbiAccountNo', '', 'string,trim');
        if (!$auth){
            parent::failJson('验证串为空',100);
        }
        $userId = parent::userId($auth);
        if (!$userId){
            parent::failJson('用户不存在',101);
        }
        
        if ($merName == '' || mb_strlen($merName,'UTF8') < 7 || mb_strlen($merName,'UTF8') > 20) {
            parent::failJson('商户名称为7-20个字符',102);
        }
        
        if ($merAddressProvince == '') {
            parent::failJson('省份信息未选择',103);
        }
        if ($merAddressCity == '') {
            parent::failJson('市区未选择',112);
        }
        if ($merAddressStreet == '') {
            parent::failJson('详细信息未选择',113);
        }
        if (mb_strlen($merAddressStreet,'UTF8') > 30) {
            parent::failJson('详细信息长度不能超过30个字符',118);
        }
        
        $merAddress = $merAddressProvince . $merAddressCity . $merAddressStreet;
        if ($merAddress == '' || mb_strlen($merAddress,'UTF8') > 100) {
            parent::failJson('商户地址为必填，且不长于100个字符',114);
        }
        
        if ($bankBranchName == '') {
             parent::failJson('支行名称不能为空',115);
        }
        
        if ($bankBranchAddressProvince == '') {
            parent::failJson('银行卡省份不能为空',116);
        }
        
        if ($bankBranchAddressCity == '') {
            parent::failJson('银行卡市不能为空',117);
        }
        
        if ($posAreaCode == '') {
            parent::failJson('地区码不能为空',104);
        }
        
        if ($mbiBankNo == '') {
            parent::failJson('开户银行行号不能为空',105);
        }
        
        if ($mbiBankName == '') {
            parent::failJson('开户银行名称不能为空',106);
        }
        
        if ($mbiAccountNo == '') {
            parent::failJson('银行卡账号不能为空',107);
        }
        
        if (strlen($mbiAccountNo) > 20) {
            parent::failJson('银行账户长度不能超过20个字符', 118);
        }
        
        $bankModel = new AccountWalletBank();
        $bankInfo = $bankModel->getOneByWhere([
            'where' => ['bank_name' => $mbiBankName]
        ],'bank_id');
        if (empty($bankInfo)) {
            parent::failJson('对应的银行不存在',112);
        }
        
        $cacheNockName = 'walletServerRegister_' . $userId;
        if (cache($cacheNockName)) {
            parent::failJson('请稍后请求',108);
        }
        cache($cacheNockName,1,15);
        
        //参与加密的数据，不要新增
        $requestInfo = [
            'user_id' => $userId,
            'merName' => $merName,
            'merNameShort' => $merName,
            'merAddress' => $merAddress,
            'posAreaCode' => $posAreaCode,
            'mbiBankNo' => $mbiBankNo,
            'mbiBankName' => $mbiBankName,
            'mbiAccountNo' => $mbiAccountNo
        ];
        
        $merchantAccess = new MerchantAccess();
        $result = $merchantAccess->register($requestInfo);
        if ($result['status'] == false) {
            cache($cacheNockName,null);
            parent::failJson($result['msg'],119);
        }
        
        if (empty($result['resultDatas']) || !isset($result['resultDatas']['RetCode'])) {
            cache($cacheNockName,null);
            parent::failJson('请求超时',109);
        }
        if ($result['resultDatas']['RetCode'] != $merchantAccess::REGIST_SUCCESS_CODE || $result['resultDatas']['AppBody']['RetCode'] != $merchantAccess::REGIST_SUCCESS_CODE) {
            $returnMsg = isset($result['resultDatas']['AppBody']['RetMessage']) ? $result['resultDatas']['AppBody']['RetMessage'] : '入网失败';
            //判断是否重复注册
            $doubleRegStatus = $merchantAccess->duplicateRegist($result['userInfo']['card_id'],$returnMsg);
            if ($doubleRegStatus == true) {
                //查询信息，补录数据
                //入网认证校验
                $accountWalletMerchantCertification = new AccountWalletMerchantCertification();
                $accountcertInfo = $accountWalletMerchantCertification->getOneByWhere([
                    'where' => [
                        'user_id' => $result['userInfo']['user_id']
                    ]
                ],'business_license_register_no,enterprise_name,terminal_no');
                if (empty($accountcertInfo)) {
                    //查询接口，补录表数据@todo无相关接口
                    Myredis::getRedisConn(5)->setToHash('wallet_server_register_insert_jr_false', 'register_info_' . $userId, ['requestInfo' => $requestInfo,'registResult' => $result]);
                }
            }
            parent::failJson($returnMsg,110);
        }
            
        //入网成功，存储金融信息
        $requestInfo['province'] = $merAddressProvince;
        $requestInfo['city'] = $merAddressCity;
        $requestInfo['street'] = $merAddressStreet;
        $requestInfo['bankBranchName'] = $bankBranchName;
        $requestInfo['bankBranchAddressProvince'] = $bankBranchAddressProvince;
        $requestInfo['bankBranchAddressCity'] = $bankBranchAddressCity;
        $requestInfo['bankBranchUnionpayNo'] = $mbiBankNo;
        $requestInfo['bank_id'] = $bankInfo['bank_id'];
        $updateInfo = $merchantAccess->insertRegisterInfo($requestInfo, $result);
        
        cache($cacheNockName,null);
        if ($updateInfo['status'] == false) {
            Log::write(['logName' => "[iwxbankRegisterSaver_{$userId}]",'requestInfo' => $requestInfo, 'result' => $result], 'debug');
            parent::failJson('入网成功，金融资料保存失败',111);
        } else {
            parent::okJson([],200,'入网成功');
        }
    }

    /**
     * @uses 修改银行卡(结算卡)
     * @author jhl
     */
    public function editCard()
    {
        $auth = input('post.auth', '', 'string,trim');
        $userId = parent::userId($auth);
        if (!$userId) {
            parent::failJson('请重新进入页面',101);
        }
        
        //收款卡号
        $cardNo = input('post.cardNo', '', 'string,trim');
        
        //开户行行号
        $bankNo = input('post.bankNo', '', 'string,trim');
        
        //银行名称
        $bankName = input('post.bankName', '', 'string,trim');
        
        //支行名称
        $bankBranchName =  input('post.bankBranchName', '', 'string,trim');
        
        //省份
        $bankProvince =  input('post.bankProvince', '', 'string,trim');
        
        //城市
        $bankCity =  input('post.bankCity', '', 'string,trim');
        
        //地区码city.areacode
        $posAreaCode = input('post.posAreaCode', '', 'string,trim');
        
        if ($bankProvince == '') {
            parent::failJson('省份不能为空',120);
        }
        
        if ($bankCity == '') {
            parent::failJson('城市不能为空',121);
        }
        
        if ($posAreaCode == '') {
            parent::failJson('地区选择不全',102);
        }

        $payPassword = input('post.paypassword', '', 'string');
        if ($payPassword == '') {
            parent::failJson('交易密码不能为空', 111);
        }
        if ($cardNo == '') {
            parent::failJson('银行卡账号不能为空', 113);
        }
        
        if (strlen($cardNo) > 20) {
            parent::failJson('银行卡账号不能超过20个字符', 122);
        }
        
        if ($bankNo == '') {
            parent::failJson('开户行行号不能为空', 114);
        }
        if ($bankName == '') {
            parent::failJson('银行名称不能为空', 115);
        }
        
        $bankModel = new AccountWalletBank();
        $bankInfo = $bankModel->getOneByWhere([
            'where' => ['bank_name' => $bankName]
        ],'bank_id');
        if (empty($bankInfo)) {
            parent::failJson('对应的银行不存在',119);
        }
        
        if ($bankBranchName == '') {
            parent::failJson('支行不能为空', 116);
        }
        if ($bankProvince == '') {
            parent::failJson('省份不能为空', 117);
        }
        if ($bankCity == '') {
            parent::failJson('城市不能为空', 118);
        }

        $payPassword = str_replace(' ', '+', $payPassword);
        $xxtea = new \Xxtea();
        $payPassword = $xxtea->decrypt(base64_decode($payPassword), md5($userId));//key在获取银行卡信息接口过程已经返回
        $modelIuser = new modelIuser();
        $userInfo = $modelIuser->getOneByWhere([
            'where' => ['user_id' => $userId]
        ], 'paypassword,user_id,username,real_status,phone_status,realname,card_id,phone');
        if (empty($userInfo['paypassword'])) {
            parent::failJson('您还未设置交易密码', 111);
        }
        
        $accountSecurity = new AccountSecurity();
        $payPasswordTimesCheck = $accountSecurity->logonTimes($userId,($userInfo['paypassword'] == md5($payPassword)) ? $userInfo['username'] : '');
        if ($payPasswordTimesCheck['status'] == false) {
            parent::failJson($payPasswordTimesCheck['msg'], $payPasswordTimesCheck['code']);
        }
        
        $cacheNockName = 'walletServerEditCard_' . $userId;
        if (cache($cacheNockName)) {
            parent::failJson('请稍后请求',112);
        }
        cache($cacheNockName,1,15);
        
        //参与加密的数据，不要新增
        $requestInfo = [
            'user_id' => $userId,
            'cardNo' => $cardNo,
            'bankNo' => $bankNo
        ];
        
        $settleCard = new SettleCard();
        $result = $settleCard->update($requestInfo,$userInfo);
        
        if ($result['status'] == false) {
            cache($cacheNockName,null);
            parent::failJson($result['msg'],105);
        }
        if (empty($result['resultDatas']) || !isset($result['resultDatas']['RetCode'])) {
            cache($cacheNockName,null);
            parent::failJson('请求超时',109);
        }
        $merchantAccess = new MerchantAccess();
        if ($result['resultDatas']['RetCode'] != $merchantAccess::EDITCARD_SUCCESS_CODE || $result['resultDatas']['AppBody']['RetCode'] != $merchantAccess::EDITCARD_SUCCESS_CODE) {
            cache($cacheNockName,null);
            parent::failJson(isset($result['resultDatas']['AppBody']['RetMessage']) ? $result['resultDatas']['AppBody']['RetMessage'] : '入网失败',110);
        }
        
        //修改成功
        $updateDatas = [
            'bank_account' => $cardNo,
            'bank_name' => $bankName,
            'bank_branch_name' => $bankBranchName,
            'bank_branch_address_province' => $bankProvince,
            'bank_branch_address_city' => $bankCity,
            'bank_id' => $bankInfo['bank_id'],
            'bank_branch_unionpay_no' => $bankNo
        ];
        //redishash更新
        $updateHashDatas = array_merge($updateDatas,$result['accountCertInfo']);
        Myredis::getRedisConn(8)->setToHash('account_wallet_merchant_certification', 'register_info_' . $userId, $updateHashDatas);
        $accountCertificationModel = new AccountWalletMerchantCertification();
        
        //数据库更新
        $updateResult = $accountCertificationModel->save($updateDatas,[
            'user_id' => $userId
        ]);
        cache($cacheNockName,null);
        if (!$updateResult) {
            Log::write(['logName' => "[iwxbankupdateeditCard{$userId}]",'msg' => '汇卡修改成功，金融修改失败','updateDatas' => $updateDatas], 'debug');
            parent::failJson('修改成功',200);
        } else {
            parent::okJson([],200,'修改成功');
        }
    }
    
    /**
     * @uses 支付方式接口
     * @author jhl
     */
    public function payType()
    {
        $logicDic = new logicDic();
        $payTypeList = array_values($logicDic->payTypeList());
        foreach ($payTypeList as $k => $v) {
            unset($payTypeList[$k]['code']);
        }
        parent::okJson(['payTypeList' => $payTypeList],200);
    }
    
    /**
     * @uses 支付二维码地址接口--若用自增主键作为单号，同一个用户在生成支付链接过程则难以做到同时获取
     * @author jhl
     */
    public function getPayUrl()
    {
        //临时开关控制
        self::registerAndPayOpenStatus();
        
        $auth = input('post.auth', '', 'string');
        
        //支付类型(alipay:1/wechat:2/无卡快捷:6)
        $payType = input('post.payType', '', 'string');
        
        //支付金额
        $amountOrig =  input('post.amount', 0.00, 'string');
        
        //结算类型(1：*非* 快捷支付；2(快捷支付)：T + 0；3(快捷支付)：T + 1；)
        $settleAccountMode = input('post.settleAccountMode',0,'int');
        
        if ($payType == 6) {
            if (!in_array($settleAccountMode, [2,3])) {
                parent::failJson('结算类型错误',123);
            }
        } else {
            if ($settleAccountMode != 1) {
                parent::failJson('结算类型错误',123);
            }
        }
        
        $userId = parent::userId($auth);
        if (!$userId) {
            parent::failJson('用户不存在',100);
        }
        $accountWalletMerchantReceipt = new AccountWalletMerchantReceipt();
        if (!in_array($payType, array_keys($accountWalletMerchantReceipt->receiptPlatform))) {
            parent::failJson('支付类型错误',101);
        }
        
        if ($settleAccountMode == 2) {
            //早晨九点
            $nineMorningTime = strtotime(date('Y-m-d 09:00:00',time()));
            //晚上九点
            $nineNightTime = strtotime(date('Y-m-d 21:00:00',time()));
            
            if ((time() >= strtotime(date('Y-m-d 00:00:00',time())) && time() <= $nineMorningTime) || (time() >= $nineNightTime && time() <= strtotime(date('Y-m-d 23:59:59',time())))) {
                parent::failJson('T+0开放时间：9:00~21:00 ',107);
            }
            
        }
        
        $amount = sprintf('%.2f',$amountOrig);
        if ($amountOrig != $amount) {
            parent::failJson('支付金额错误',102);
        }
        if($payType == 6){
            if ($amount < 1000 || $amount > 20000) {
                parent::failJson('支付金额为大于1000小于20000的数字',111);
            }
        }else{
            if ($amount < 5 || $amount > 20000) {
                parent::failJson('支付金额为大于5小于20000的数字',111);
            }
        }
        
        //测试环境金额控制
        if (PRODUCT_ENV != 'product') {
            $amount = 0.01;
        }
        $cacheNockName = 'walletServerGetPayUrl_' . $userId;
//        if (cache($cacheNockName)) {
//            parent::failJson('请间隔30秒后再进行支付请求',107);
//        }
        cache($cacheNockName,1,30);
        //支付入网校验
        $accountCert = new AccountWalletMerchantCertification();
        $accountCertInfo = $accountCert->getCertificationInfo($userId);
        if (!isset($accountCertInfo['business_license_register_no'])) {
            cache($cacheNockName,null);
            parent::failJson('商家还未进行入网申请',104);
        }
        
        $LogicDic = new logicDic();
        $merchOrderNo = $LogicDic->madeUniqueCode($userId,'wallet');
        try{
           
            if($payType==6 && $settleAccountMode ==3){
                $feePay = 0.0046; //第三方手续费0.0046 快捷支付 T+1
                $receipt_amount_actual = ceil($amount * (1 - $feePay) * 100)/100; //实际收款金额
                $merchant_service_fee_amount = ceil($amount * $feePay * 100)/100; //商家手续费
            }elseif($payType==6 && $settleAccountMode ==2){
                $feePay = 0.0050; //第三方手续费0.0050 快捷支付 T+0
                $receipt_amount_actual = (ceil($amount * (1 - $feePay) * 100)/100)-1; //实际收款金额 T+0 每笔固定多1元转账手续费
                $merchant_service_fee_amount = (ceil($amount * $feePay * 100)/100)+1; //商家手续费 T+0 每笔固定多1元转账手续费
            }else{
                $feePay = 0.0038; //第三方手续费微信支付宝0.0038 T+1
                $receipt_amount_actual = ceil($amount * (1 - $feePay) * 100)/100; //实际收款金额
                $merchant_service_fee_amount = ceil($amount * $feePay * 100)/100; //商家手续费
            }
            //$feePay= $payType==6?0.0046:0.0038;
            
            //预先生成数据
            $insertDatas = [
                'user_id' => $userId,
                'user_name' => $accountCertInfo['user_name'],
                'output_order_no' => $merchOrderNo,
                'receipt_platform' => $payType,
                'receipt_amount' => $amount,
                'settle_account_mode'=>$settleAccountMode,
                'settle_account_bank_name' => $accountCertInfo['bank_name'],
                'settle_account_bank_account' => $accountCertInfo['bank_account'],
                'create_time' => date('Y-m-d H:i:s',time()),
                'receipt_amount_actual' => $receipt_amount_actual,//实际收款金额
                'merchant_service_fee_amount' => $merchant_service_fee_amount,//商家手续费
            ];
            if ($payType == 6) {
                $insertDatas['settle_account_mode'] = (string)($settleAccountMode - 2);
            }
            $insertId = $accountWalletMerchantReceipt->add($insertDatas);
            if (!$insertId) {
                throw new Exception('支付失败，请重新支付');
            }
        } catch (Exception $e) {
            cache($cacheNockName,null);
            Log::write(['logName' => "[iwxbankPayInsertMerchantReceipt{$userId}]",'datas' => ['insertDatas' => $insertDatas,'errorMsg' => $e->getMessage()]], 'debug');
            parent::failJson('支付失败，请重新支付',105);
        }
        $iwxbankConfig = config('iwxbank');
        $param['amount'] = (string)(100 * $amount);//以分为单位
        $payTypeCode = (string)$LogicDic->payType($payType,'code');
        $param['payType'] = $payTypeCode;
        $param['customerName'] = (string)$accountCertInfo['business_operator_name'];
        $param['hicardMerchNo'] = (string)$accountCertInfo['business_license_register_no'];//@todo:'104401569102119'/$accountCertInfo['business_license_register_no']//@todo
        $param['merchOrderNo'] = (string)$merchOrderNo;
        $param['certsNo'] = (string)$accountCertInfo['card_id'];
        $param['version'] = (string)$iwxbankConfig['payCreate']['version'];
        
        //如果是无卡快捷支付
        if ($payTypeCode == $LogicDic::QUICKPAY_CODE) {
            $param['organNo'] = (string)$iwxbankConfig['payCreate']['organNoQuickPayment'][PRODUCT_ENV];
            //如果是T + 0（数据库记录T0，T1）
            if ($settleAccountMode == 2) {
                $param['isT0'] = 1;
            }
            $payKey = $iwxbankConfig['payCreate']['keyQuickPayment'][PRODUCT_ENV];
        } else {
            $param['organNo'] = (string)$iwxbankConfig['payCreate']['organNo'][PRODUCT_ENV];
            $payKey = $iwxbankConfig['payCreate']['key'][PRODUCT_ENV];
        }
        $param['goodsName'] = "雅堂-订单号[{$param['merchOrderNo']}]";
        $param['backEndUrl'] = SITE_FULL . 'home/wallet/backEndUrl';
        $param['bizType'] = '812';//商户类型
        $param['showPage'] = '0';
        $param['frontEndUrl'] = '';
        $param['openId'] = '';
        $param['remark'] = "雅堂-订单号[{$param['merchOrderNo']}]";
        
        $payBase = new payBase();
        $result = $payBase->createOrder($iwxbankConfig['payCreate']['requestUrl'][PRODUCT_ENV],$param,$payKey);
        if (isset($result['payInfo']) && $result['payInfo']) {
            $affectedRows = $accountWalletMerchantReceipt->where(['output_order_no' => $result['merchOrderNo']])->update(['huika_order_no' => $result['hicardOrderNo']]);
            if ($affectedRows) {
                $payKind = $LogicDic->payType($payType,'payKind') ? '&' . $LogicDic->payType($payType,'payKind') : '';
                parent::okJson(['url' => $result['payInfo'] . $payKind,'orderId' => $merchOrderNo,'enterpriseName' => $accountCertInfo['enterprise_name']],200,'扫码成功');
            } else {
                Log::write(['logName' => "[iwxbankPaygetPayUrlUpdatehuikaOrderNoFail_{$userId}]",'failSql' => $accountWalletMerchantReceipt->getLastSql()], 'debug');
            }
        
        //快捷支付
        } elseif(isset($result['html']) && $result['html']) {
            $pattern = '/href=\'(.*)\'/';
            preg_match($pattern,$result['html'], $urlArr);
            if (!isset($urlArr[1])) {
                parent::failJson('未成功获取支付信息',108);
            }
            $affectedRows = $accountWalletMerchantReceipt->where(['output_order_no' => $result['merchOrderNo']])->update(['huika_order_no' => $result['hicardOrderNo']]);
            if ($affectedRows) {
                parent::okJson(['url' => $urlArr[1],'orderId' => $merchOrderNo,'enterpriseName' => $accountCertInfo['enterprise_name']],200,'扫码成功');
            } else {
                Log::write(['logName' => "[iwxbankPaygetPayUrlUpdatehuikaOrderNoFail_{$userId}]",'failSql' => $accountWalletMerchantReceipt->getLastSql()], 'debug');
            }
        }
        
        cache($cacheNockName,null);
        parent::failJson(isset($result['respMsg']) ? $result['respMsg'] : '未成功获取支付信息',106);
    }
    
    /**
     * @uses 查询支付状态
     * @author jhl
     */
    public function getPayStatus()
    {
        $auth = input('post.auth', '', 'string');
        $userId = parent::userId($auth);
        if (!$userId) {
            parent::failJson('用户非法登录',100);
        }
        $orderNo = input('post.orderNo','','string');
        if (!$orderNo) {
            parent::failJson('单号为空',101);
        }
        
        $cacheNockName = 'walletServerGetPayStatus_' . $orderNo;
        if (cache($cacheNockName)) {
            parent::failJson('请等待上一次请求结果',105);
        }
        cache($cacheNockName,1,5);
        
        $accountWalletPay = new AccountWalletPay();
        $result = cache($accountWalletPay->payReturnCacheName($orderNo));
        if (!$result) {
            //查询数据库
            $accountReceipt = new AccountWalletMerchantReceipt();
            $info = $accountReceipt->getMerchartReceiptInfo($orderNo);
            if (empty($info)) {
                cache($cacheNockName,null);
                parent::failJson('对应的订单号不存在',102);
            }
            if ($info['pay_status'] == -1) {
                //写缓存
                cache($accountWalletPay->payReturnCacheName($orderNo), 2, 86400);
                cache($cacheNockName,null);
                parent::failJson('支付失败',103);
            } elseif ($info['pay_status'] == 1) {
                cache($cacheNockName,null);
                //写缓存
                cache($accountWalletPay->payReturnCacheName($orderNo), 1, 86400);
                parent::okJson('支付成功',200);
            } else {
                $iwxbankConfig = config('iwxbank');
                //查询订单接口
                //协议版本
                $param['version'] = (string)$iwxbankConfig['payCreate']['version'];
                
                $logicDic = new logicDic();
                $payTypeCode = (string)$logicDic->payType($info['receipt_platform'],'code');
                //如果是无卡快捷支付
                if ($payTypeCode == $logicDic::QUICKPAY_CODE) {
                    $param['organNo'] = (string)$iwxbankConfig['payCreate']['organNoQuickPayment'][PRODUCT_ENV];
                    $payKey = $iwxbankConfig['payCreate']['keyQuickPayment'][PRODUCT_ENV];
                } else {
                    $param['organNo'] = (string)$iwxbankConfig['payCreate']['organNo'][PRODUCT_ENV];
                    $payKey = $iwxbankConfig['payCreate']['key'][PRODUCT_ENV];
                }
                //汇卡商户号
                $param['hicardMerchNo'] = (string)$info['business_license_register_no'];
                
                //商户订单号
                $param['merchOrderNo'] = (string)$info['output_order_no'];
                
                $payBase = new payBase();
                $result = $payBase->selectOrder($iwxbankConfig['payQuery']['requestUrl'][PRODUCT_ENV],$param,$payKey);
                if(!isset($result['respCode'])) {
                    parent::failJson('请求失效',105);
                }
                
                $accountWalletPay = new AccountWalletPay();
                if ($result['respCode'] === '00') {
                    $accountWalletPay->paySuccess($result);
                    cache($cacheNockName,null);
                    parent::okJson('支付成功',200);
                //扫码完成为支付，返回62
                } elseif (in_array($result['respCode'], $logicDic->waitPayResultCodeList())) {
                    cache($cacheNockName,null);
                    parent::failJson('等待支付状态',104);
                }else {
                    //若在重新查询请求列表，发查询确认脚本--由异步回调地址获取状态
                    if (in_array($result['respCode'],$logicDic->repeatSelectTrueStatusList())) {
                        Log::write(['logName' => "[iwxbankPaygetPayStatus{$param['merchOrderNo']}]",'datas' => $result], 'debug');
                        //$accountWalletPay->insertRedisList($param);
                    } else {
                        $accountWalletPay->payFalse($result);
                        cache($cacheNockName,null);
                        parent::failJson('支付失败',103);
                    }
                }
                cache($cacheNockName,null);
                parent::failJson('等待支付状态',104);
            }
        }
        if ($result == 2) {
            cache($cacheNockName,null);
            parent::failJson('支付失败',103);
        }
        cache($cacheNockName,null);
        parent::okJson('支付成功',200);
    }
}









