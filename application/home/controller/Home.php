<?php
namespace application\home\controller;

use application\home\controller\Base;
use application\common\Myredis;
use application\common\logic\Navigation\Navigation;
use application\common\model\borrow\experienceBorrow;
use application\common\model\borrow\Iborrow;
use application\home\controller\Article;
use application\common\model\account\AccountModel;
use application\common\model\system\Linkage;
use application\common\logic\user\Iuser;
use think\Cookie;
use application\common\logic\AccountService\AccountService;


/**
 * @uses 首页以及相关连接口
 * @author pandelin
 * @data：2017/10/03
 */
//header("Content-type:text/html;charset=utf-8");
class Home extends Base
{

    public $redis;
    /**
     * @desc   用户id
     * @var    int
     * @access private
     */
    private $userId;
    
    public function _initialize()
    {
        parent::_initialize();
    }
    private function topUserInfo(){
        $cookie = cookie::get(COOKIE_TYPE);
        $Iuser=new Iuser();
//        $cookie='2f8ePNZoL2YI/gWcYChx/8sA0E94PPSIId6es97zSN1kJPawkByWxY9O6tz0KZ/Ta4Qe';
        $userInfo=$Iuser->authcode($cookie);
        
        if(!$userInfo){
            return [];
        }
        $userInfo=explode( ',',$userInfo);
        $this->userId=$userInfo[0];
        $userTrue=$this->userId==490?1:0;
        $AccountModel = new AccountModel();
        $CashControl  = $AccountModel->getAccountInfoByUserId($this->userId);
        $rules=Myredis::getRedisConn()->getFromHash('vip_rules', $this->userId);
        $status=Myredis::getRedisConn()->getFromHash('vip_status', $this->userId);
        $status=$status?$status:0;
        $superIsExpire = 0;
        $superMember=$superTimeStart=$superTimeEnd=0;
        $accountservice  = Myredis::getRedisConn(3)->getFromHash('account_service',$this->userId);
        if($accountservice){
            $superMember = $accountservice['superMember']?$accountservice['superMember']:0;
            $superTimeStart = $accountservice['superTimeStart'];
            $superTimeEnd = $accountservice['superTimeEnd'];
        }else{
            $javaUserInfo=AccountService::getUserInfo(['username'=>$userInfo[2]]);
            if (isset($javaUserInfo['data']['superMember'])) {
                //保存超级会员信息
                $redisData = array(
                    'superMember'=>$javaUserInfo['data']['superMember'],
                    'superTimeStart'=>$javaUserInfo['data']['superTimeStart'],
                    'superTimeEnd'=>$javaUserInfo['data']['superTimeEnd'],
                );
                $superMember = $javaUserInfo['data']['superMember'];
                $superTimeStart = $javaUserInfo['data']['superTimeStart'];
                $superTimeEnd = $javaUserInfo['data']['superTimeEnd'];
                 Myredis::getRedisConn(3)->setToHash('account_service',$this->userId,$redisData);
            } 
        }
        
        $res=[];
        preg_match_all("/(\S)/u", $userInfo[2], $res);
      if ($res[1]) {
          array_splice($res[1], 0, count($res[1]) > 2 ? -2 : -1, "****");
          $loginUName = join("", $res[1]);
      }
        $array=[
            'userTrue'=>$userTrue,
            'userName'=>$loginUName,
            'superMember'=>  $superMember,
            'superTimeStart'=>$superTimeStart,
            'superTimeEnd'=>$superTimeEnd,
            'superIsExpire'=>$superIsExpire,
            'totalMoney'=> number_format(truncate($CashControl['total']),2),
            'useMoney'=> number_format(truncate($CashControl['use_money']),2),
            'headPortrait'=>  parent::getUserIcon($this->userId),
            'vipIcon'=>SITE_YATANG_FULL.'/Public/Images/'.(isset($rules['icon'][$status])?$rules['icon'][$status]:'account/vip/0/1.png'),
        ];
        return $array;
    }
    /**
     * 获取网站底部信息
     * **/
    public function getFooter(){
        // 首页滚屏公告
        $indexFooter = 'index_getFooter';
        $datas = cache($indexFooter);
        if(!$datas){
            $Linkage=new Linkage();
            $sqlArr = [
                'where' => [ 'pid' => 'index_group', 'status' => 1 ],
            ];
            $indexGroup=$Linkage->getOneByWhere($sqlArr);
            $QQName=$QQ=$QQLink='';
            if($indexGroup){
                $arr=explode(";",$indexGroup['name']);
                $QQName=$arr[0];
                $QQ=$arr[1];
                $QQLink=trim(htmlspecialchars_decode($indexGroup['value']),' ');
            }
            $datas=[
                'aboutUs'=>[
                    ['name'=>'关于雅堂金融','url'=>SITE_YATANG_FULL.'/AboutUs/ViewC/cid/21'],
                    ['name'=>'联系我们','url'=>SITE_YATANG_FULL.'/AboutUs/ViewC/cid/91'],
                    ['name'=>'资费说明','url'=>SITE_YATANG_FULL.'/Public/help/acateid/114'],
                    ['name'=>'常见问题','url'=>SITE_YATANG_FULL.'/Public/help/acateid/108'],
                    ['name'=>'账户安全','url'=>SITE_YATANG_FULL.'/Public/help/acateid/112'],
                    ['name'=>'政策法规','url'=>SITE_YATANG_FULL.'/Public/help/acateid/114']
                ],
                'yatang'=>'©2013 雅堂金融 All rights reserved',
                'ICP'=>'蜀ICP备16027647号-2',
                'tips'=>'投资有风险，购买需谨慎。',
                'onLineName'=>'在线客服',
                'onLineUrl'=> DEFAULT_WXSITE_URL.'/CustomerService/postGetCustUrl/type/pc/entryNumber/4/authenticationString/',
                'telephone'=> TELEPHONE,
                'telephoneString'=> TELEPHONE_STRING,
                'telephoneTime'=>'( 周一至周日：09:00--20:00 )',
                'leftTelephoneTime'=>'09:00--20:00 ',
                'QQName'=>$QQName,
                'QQ'=>$QQ,
                'QQLink'=>$QQLink,
                'address'=>'四川省成都市天府新区成都科学城天府菁蓉中心D区'
            ];
            cache($indexFooter,$datas,60*10);
        }
        $cookie = cookie::get(COOKIE_TYPE);
        $Iuser=new Iuser();
//        $cookie='2f8ePNZoL2YI/gWcYChx/8sA0E94PPSIId6es97zSN1kJPawkByWxY9O6tz0KZ/Ta4Qe';
        $userInfo=$Iuser->authcode($cookie);
        if($userInfo){
            $userInfo=explode( ',',$userInfo);
            $datas['onLineUrl']=$datas['onLineUrl'].$userInfo[0];
        }
        parent::okJson($datas);
    }
    public function getheader(){
        //seo信息
        $seo = Navigation::getSeoSetting(md5('/'));
        $datas['seo']['title'] = isset($seo['caption']) ? $seo['caption'] : '';
        $datas['seo']['description'] = isset($seo['description']) ? $seo['description'] : '';
        $datas['seo']['keyword'] = isset($seo['keyword']) ? $seo['keyword'] : '';
        $datas['userInfo']=self::topUserInfo();
        parent::okJson($datas);
    }
    public function getbodyer(){
        $cookie = cookie::get(COOKIE_TYPE);
        $Iuser=new Iuser();
//        $cookie='2f8ePNZoL2YI/gWcYChx/8sA0E94PPSIId6es97zSN1kJPawkByWxY9O6tz0KZ/Ta4Qe';
        $userInfo=$Iuser->authcode($cookie);
        if(!$userInfo){
            parent::okJson();
        }
        $userInfo=explode( ',',$userInfo);
        $article = new Article();
        // 首页滚屏公告
        $indexrollAd = 'index_rollAd';
        $datas['rollAd'] = cache($indexrollAd);
        if (!$datas['rollAd']) {
            $datas['rollAd'] = $article -> getHomeRollAd();
            cache($indexrollAd,$datas['rollAd'],60*10);
        }
        // 首页资产金融广告
        $indexmanageItemAd = 'index_manageItemAd';
        $datas['manageItemAd'] = cache($indexmanageItemAd);
        if (!$datas['manageItemAd']) {
            $datas['manageItemAd'] = $article -> getManageItemAd();
            cache($indexmanageItemAd,$datas['manageItemAd'],60*10);
        }
        
        // 理财金体验项目--广告
        $indexexperienceProjectAd = 'index_experienceProjectAd';
        $datas['experienceProjectAd'] = cache($indexexperienceProjectAd);
        if (!$datas['experienceProjectAd']) {
            $datas['experienceProjectAd'] =  $article -> getExperienceProjectAd();
            cache($indexexperienceProjectAd,$datas['experienceProjectAd'],20);
        }
        // 理财金体验项目--缓存时间：20s
        $indexFinancialGoldCacheName = 'index_financial_gold';
        $datas['financialGold'] = cache($indexFinancialGoldCacheName);
        if (!$datas['financialGold']) {
            $datas['financialGold'] = experienceBorrow::indexExperienceBorrow();
            cache($indexFinancialGoldCacheName,$datas['financialGold'],20);
        }
        // 资产金融-更新频繁，缓存时间：5s
        $indexAssetFinanceCacheName = 'index_asset_finance';
        $datas['assetFinance'] = cache($indexAssetFinanceCacheName);
        if (!$datas['assetFinance']) {
            $datas['assetFinance'] = Iborrow::indexIborrowlist('status =1 AND borrow_type IN (6,7,10,11)', 5); 
            $num = count($datas['assetFinance']);
            if ($num < 5) {
                $limit = 5 - $num;
                $assetFinanceOther = Iborrow::indexIborrowlist('(status IN (3,7,8)) AND (borrow_type IN (6,7,10,11))', $limit);
                if ($num == 0) {
                    $datas['assetFinance'] = $assetFinanceOther;
                } else {
                    $datas['assetFinance'] = array_merge($datas['assetFinance'], $assetFinanceOther);
                }
            }
            foreach ($datas['assetFinance'] as $ak => $av) {
                $datas['assetFinance'][$ak]['repayStyleMsg'] = self::getRepaystyle($av['repaystyle'], $av['borrow_type']);
                $datas['assetFinance'][$ak]['projectTermMsg'] = ($av['repaystyle'] == 4 ? $av['fatalism'] : $av['time_limit']).($av['repaystyle'] == 4 ? '天' : '个月');
                //融资进度
                $datas['assetFinance'][$ak]['financingProgress'] = floor($av['account_yes']/$av['account']*100);
                //剩余金额
                $datas['assetFinance'][$ak]['SurplusAmount'] = number_format($av['account']-$av['account_yes'],2,'.',',');
                $datas['assetFinance'][$ak]['url'] = SITE_YATANG_FULL.'/Invest/ViewBorrow/ibid/'.$av['id'];
                unset($datas['assetFinance'][$ak]['repaystyle'],
                    $datas['assetFinance'][$ak]['borrow_type'],
                    $datas['assetFinance'][$ak]['fatalism'],
                    $datas['assetFinance'][$ak]['time_limit'],
                    $datas['assetFinance'][$ak]['account'],
                    $datas['assetFinance'][$ak]['account_yes']
                );
            }
            cache($indexAssetFinanceCacheName,$datas['assetFinance'],5);
        }
        parent::okJson($datas);
    }
    /**
     * @uses 获取还款方式
     * @author jhl
     * @param $repaystyle:还款方式
     * @param $borrowType:标类型
     */
    private function getRepaystyle($repaystyle,$borrowType)
    {
        if($repaystyle == 0 && $borrowType != 5){
            $repayMsg = '按月分期';
        }else if($repaystyle == 0 && $borrowType == 5){
            $repayMsg = '秒还';
        }else if($repaystyle == 3){
            $repayMsg = '到期还本';
        }else if($repaystyle == 4){
            $repayMsg = '按天到期';
        }
        return $repayMsg;
    }
} 