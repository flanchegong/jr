<?php

namespace application\common\logic\externalInterface\wwwIwxbankCom;

use application\common\model\wallet\AccountWalletMerchantCertification;
use application\common\model\user\Iuser as modelIuser;
/**
 * @uses 支付接口
 * @author jhl<liujihaoth@126.com>
 * @date 2017-09-26
 */
class Payment extends WxBankBase
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * @uses 支付接口
     * @param array $postDatas
     * @author jhl
     */
    public function pay($postDatas)
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
        
        $accountWalletMerchantCertification = new AccountWalletMerchantCertification();
        $accountcertInfo = $accountWalletMerchantCertification->getOneByWhere([
            'where' => [
                'user_id' => $postDatas['user_id']
            ]
        ],'business_license_register_no,enterprise_name,terminal_no');
        if (empty($accountcertInfo)) {
            return [
                'status' => false,
                'msg' => '您还未进行入网操作'
            ];
        }
        
        $appBody = [
            'institutionNo' => $this->organizationNumber,
            'posNo' => (string)$accountcertInfo['terminal_no'],
            'name' => (string)$accountcertInfo['enterprise_name'],
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
        
        return ['resultDatas' => $resultDatas,'userInfo' => $userInfo];
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