<?php

namespace application\common\logic\externalInterface\wwwIwxbankCom;

use application\common\model\wallet\AccountWalletMerchantCertification;
use application\common\model\user\Iuser as modelIuser;
use application\common\logic\AccountService\AccountService;
use think\Exception;
use think\Log;
/**
 * @uses 结算卡控制接口
 * @author jhl<liujihaoth@126.com>
 * @date 2017-09-25
 */
class SettleCard extends WxBankBase
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * @uses 修改结算卡
     * @param array $postDatas
     * @author jhl
     */
    public function update($postDatas,$userInfo = [])
    {
        if (empty($userInfo)) {
            $modelIuser = new modelIuser();
            $userInfo = $modelIuser->getOneByWhere(['where' => ['user_id' => $postDatas['user_id']]],'user_id,username,real_status,phone_status,realname,card_id,phone');
        }
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
        
        $accountWalletMerchantCertification = new AccountWalletMerchantCertification();
        $accountcertInfo = $accountWalletMerchantCertification->getOneByWhere([
            'where' => [
                'user_id' => $postDatas['user_id']
            ]
        ],'user_id,user_name,business_license_register_no,enterprise_name,business_operator_name,enterprise_address_province,enterprise_address_city,enterprise_address_street,bank_branch_unionpay_no,terminal_no,certification_time');
        if (empty($accountcertInfo)) {
            return [
                'status' => false,
                'msg' => '您还未进行入网操作'
            ];
        }
        
        $userBankInfo = [
             'userName'=> $userInfo['username'],//用户名必填
             'origin'=> 1,//来源：1雅堂金融
             'name'=> $userInfo['realname'],//持卡人姓名
             'idCard' => $userInfo['card_id'],//持卡人身份证号码
             'accountNO' => $postDatas['cardNo'],//银行卡号
             'bankPreMobile' => $userInfo['phone']//持卡人预留手机号
        ];
        try{
            //发起验证
            //身份证、银行卡实名校验
            $accountServiceLogic = new AccountService();
            $validTrueCard = $accountServiceLogic->subAccountActionSaveUserBankCardInfo($userBankInfo);
        } catch (Exception $e) {
            Log::write(['logName' => "[mechantaccessEditCardCheckUserBankInfo{$postDatas['cardNo']}ErrorInfo]" ,'datas' => $userBankInfo], 'debug');
            // 错误信息统一提示
            return [
                'status' => false,
                'msg' => '检测失败，请重新发起请求'
            ];
        }

        //0:校验，添加成功；-4：校验成功，已经添加相关银行卡
        if (!isset($validTrueCard['code']) || ($validTrueCard['code'] != '0' && $validTrueCard['code'] != -4)) {
            Log::write(['logName' => "[mechantaccessEditCardCheckUserBankInfo{$postDatas['cardNo']}returnInfo]" ,'datas' => $validTrueCard], 'debug');
            // 错误信息统一提示
            return [
                'status' => false,
                'msg' => '银行卡信息有误'
            ];
        }
        
        $appBody = [
            'institutionNo' => $this->organizationNumber,
            'posNo' => (string)$accountcertInfo['terminal_no'],
            'name' => (string)$userInfo['realname'],
            'cardNo' => (string)$postDatas['cardNo'],
            'bankNo' => (string)$postDatas['bankNo'],
            'mbiType' => '对私'
        ];
        
        //销毁不参与签名的数据成员
        $signBody = $appBody;
        unset($signBody['mbiType']);
        //签名
        $appBody['sign'] = parent::madeSign($signBody);
        
        $requestDatas = parent::madeRequstDatas('100', 'S1059', $appBody);

        //执行入网请求
        $resultDatas = parent::baseRequestDatas($this->requestUrl,$requestDatas,'updateCard' . $userInfo['username']);
        
        return ['status' => true,'resultDatas' => $resultDatas,'userInfo' => $userInfo,'accountCertInfo' => $accountcertInfo];
    }
    
    /**
     * @uses 查询结算卡
     * @param array $postDatas
     * @author jhl
     */
    public function select()
    {
        
    }
    
    
    
}