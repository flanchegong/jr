<?php

namespace application\common\logic\externalInterface\wwwIwxbankCom;

use application\common\model\user\Iuser as modelIuser;
use application\common\model\wallet\AccountWalletMerchantCertification;
use think\Log;
use application\common\Myredis;
use think\Exception;
use application\common\logic\AccountService\AccountService;
/**
 * @uses 商户入网接口
 * @author jhl<liujihaoth@126.com>
 * @date 2017-09-08
 */
class MerchantAccess extends WxBankBase
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * @uses 商户入网接口
     * @author jhl
     * @param array $postDatas
     */
    public function register($postDatas)
    {
        $modelIuser = new modelIuser();
        $userInfo = $modelIuser->getOneByWhere(['where' => ['user_id' => $postDatas['user_id']]],'user_id,username,real_status,phone_status,realname,card_id,phone');
        if (empty($userInfo)) {
            return [
                'status' => false,
                'msg' => '相关用户不存在'
            ];
        }
        if (!$userInfo['real_status'] || !$userInfo['phone_status']) {
            return [
                'status' => false,
                'msg' => '入网用户必须通过实名以及手机认证'
            ];
        }
        
        //入网认证校验
        $accountcertInfo = Myredis::getRedisConn(8)->getFromHash('account_wallet_merchant_certification', 'register_info_' . $postDatas['user_id']);
        if (!isset($accountcertInfo['user_id'])) {
            $accountWalletMerchantCertification = new AccountWalletMerchantCertification();
            $accountcertInfo = $accountWalletMerchantCertification->getOneByWhere([
                'where' => [
                    'user_id' => $postDatas['user_id']
                ]
            ],'business_license_register_no,enterprise_name,terminal_no');
            $accountcertEnterpriseNameInfo = $accountWalletMerchantCertification->getOneByWhere([
                'where' => [
                    'enterprise_name' => $postDatas['merName']
                ]
            ],'business_license_register_no');
            if (!empty($accountcertEnterpriseNameInfo)) {
                return [
                    'status' => false,
                    'msg' => '对应的商户名称已经存在'
                ];
            }
        }

        if (!empty($accountcertInfo)) {
            return [
                'status' => false,
                'msg' => '您已经通过入网认证'
            ];
        }
        
        $userBankInfo = [
                'userName'=> $userInfo['username'],//用户名必填
                'origin'=> 1,//来源：1雅堂金融
                'name'=> $userInfo['realname'],//持卡人姓名
                'idCard' => $userInfo['card_id'],//持卡人身份证号码
                'accountNO' => $postDatas['mbiAccountNo'],//银行卡号
                'bankPreMobile' => $userInfo['phone']//持卡人预留手机号
            ];
        try{
            //发起验证
            //身份证、银行卡实名校验
            $accountServiceLogic = new AccountService();
            $validTrueCard = $accountServiceLogic->subAccountActionSaveUserBankCardInfo($userBankInfo);
        } catch (Exception $e) {
            Log::write(['logName' => "[mechantaccessRegisterCheckUserBankInfo{$postDatas['mbiAccountNo']}ErrorInfo]" ,'datas' => $userBankInfo], 'debug');
            // 错误信息统一提示
            return [
                'status' => false,
                'msg' => '检测失败，请重新发起请求'
            ];
        }

        //0:校验，添加成功；-4：校验成功，已经添加相关银行卡
        if (!isset($validTrueCard['code']) || ($validTrueCard['code'] != '0' && $validTrueCard['code'] != -4)) {
            Log::write(['logName' => "[mechantaccessRegisterCheckUserBankInfo{$postDatas['mbiAccountNo']}returnInfo]" ,'datas' => $validTrueCard], 'debug');
            // 错误信息统一提示
            return [
                'status' => false,
                'msg' => '银行卡信息有误'
            ];
        }
        
        $appBody = [
            //机构编号
            'institutionNo' => $this->organizationNumber,
            
            //商户名称
            'merName' => (string)$postDatas['merName'],
            
            //商户简称
            'merNameShort' => (string)$postDatas['merName'],
            
            //商户地址
            'merAddress' => (string)$postDatas['merAddress'],
            
            //法人姓名
            'merLegalName' => (string)$userInfo['realname'],
            
            //法人身份证号
            'merLegalNo' => (string)$userInfo['card_id'],
            
            //法人联系手机号
            'merLegalMobilePhone' => (string)$userInfo['phone'],
            
            //地区码
            'posAreaCode' => (string)$postDatas['posAreaCode'],
            
            //开户银行行号
            'mbiBankNo' => (string)$postDatas['mbiBankNo'],

            //开户银行名称
            'mbiBankName' => (string)$postDatas['mbiBankName'],
            
            //开户名称 (开户名称，开户人姓名)
            'mbiAccountUser' => (string)$userInfo['realname'],
            
            //银行卡账号
            'mbiAccountNo' => (string)$postDatas['mbiAccountNo'],
            
            //开户类型(值为：对公/对私。如果是对公账号，不进行实名认证，不支持T0。非必填字段，不参与签名校验。)
            'mbiType' => '对私',
            
            //业务类型编号(业务类型编号：现在暂时只开放的业务有：一码付（578）、汇云付网关（789）、T+0（0311），可以传多个业务，以应为逗号（,）为分隔符)
            'busiNo' => $this->busiNo,
            
            //费率ID(费率ID,可以传多个，以英文逗号（,）为分隔符，如1611,1622)
            'rateId' => $this->rateId,

            //外部商户号(外部商户号，是机构自己定义的商户号。应保证一个外部商户号与一个汇卡商户号对应。非必填字段，不参与签名校验。)
//             'extMerNo' => ''
        ];
        
        //销毁不参与签名的数据成员
        $signBody = $appBody;
        unset($signBody['mbiType'],$signBody['extMerNo']);
        //签名
        $appBody['sign'] = parent::madeSign($signBody);

        $requestDatas = parent::madeRequstDatas('100', 'S1050', $appBody);

        //执行入网请求
        $resultDatas = parent::baseRequestDatas($this->requestUrl,$requestDatas,'register' . $userInfo['username']);
        return ['status' => true,'resultDatas' => $resultDatas,'userInfo' => $userInfo];
    }
    
    /**
     * @uses 入网成功，存储金融信息
     * @author jhl
     * @param array $requestInfo:请求信息
     * @param array $result:返回信息
     */
    public function insertRegisterInfo($requestInfo, $result)
    {
        Log::write(['logName' => "[iwxbankinsertRegisterInfoRequestInfoResult{$requestInfo['user_id']}]",'requestInfo' => $requestInfo, 'result' => $result], 'debug');
        try {
            $insertDatas = [
                'user_id' => $requestInfo['user_id'],
                'user_name' => $result['userInfo']['username'],
                'business_license_register_no' => $result['resultDatas']['AppBody']['merNo'],//汇卡商户号
                'enterprise_name' => $requestInfo['merName'],
                'business_operator_name' => $result['userInfo']['realname'],
                'enterprise_address_province' => $requestInfo['province'],
                'enterprise_address_city' => $requestInfo['city'],
                'enterprise_address_district' => $requestInfo['city'],
                'enterprise_address_street' => $requestInfo['street'],
                'bank_account' => $requestInfo['mbiAccountNo'],
                'bank_id' => $requestInfo['bank_id'],
                'bank_name' => $requestInfo['mbiBankName'],
                'bank_branch_name' => $requestInfo['bankBranchName'],
                'bank_branch_address_province' => $requestInfo['bankBranchAddressProvince'],
                'bank_branch_address_city' => $requestInfo['bankBranchAddressCity'],
                'bank_branch_unionpay_no' => $requestInfo['bankBranchUnionpayNo'],
                'terminal_no' => $result['resultDatas']['AppBody']['posHcunitcode'],//汇卡终端号
                'certification_time' => date('Y-m-d H:i:s',time())
            ];
            //入网成功信息写入redis
            Myredis::getRedisConn(8)->setToHash('account_wallet_merchant_certification', 'register_info_' . $requestInfo['user_id'], $insertDatas);
            $accountCertificationModel = new AccountWalletMerchantCertification();
            $insertId = $accountCertificationModel->insert($insertDatas);
        } catch (Exception $e) {
            Log::write(['logName' => "[iwxbankinsertRegisterInfoTryCatchError{$requestInfo['user_id']}]",'insertDatas' => $insertDatas, 'errorMsg' => $e->getMessage()], 'debug');
        }
        if (!$insertId) {
             Log::write(['logName' => "[iwxbankinsertRegisterInfoError{$requestInfo['user_id']}]",'insertDatas' => $insertDatas, 'insertSql' => $accountCertificationModel->getLastSql()], 'debug');
             return [
                 'status' => false,
                 'msg' => '写入失败'
             ];
        } else {
            return [
                'status' => true,
                'msg' => '写入成功'
            ];
        }
        
    }
    
    /**
     * @uses 判断是否重复注册(无对应的code)
     * @author jhl
     * @param string $cardId:身份证号码
     * @param string $returnMsg:返回信息
     * @return true:重复注册；false:非重复注册
     */
    public function duplicateRegist($cardId,$returnMsg = '')
    {
        $msg = "该法人身份证号【{$cardId}】已经注册过商户了，不能重复注册";
        if ($msg == $returnMsg) {
            return true;
        } else {
            return false;
        }
    }
    
    
}