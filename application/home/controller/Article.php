<?php

/**
 * @Copyright (C), 2017, pandelin
 * @Name Article.php
 * @Author pandelin
 * @Version stable 1.0
 * @Date 2017-6-27
 * @Description 文章广告相关
 * @Class List
 *    1.
 * @Function List
 *    1.
 * @History
 * <author>  <time>              <version>    <desc>
 *  pandelin   2017-11-01          stable 1.0     第一次建立该文件
 */
namespace application\home\controller;

use application\common\model\article\Article as Marticle;
use application\common\model\article\ArticleCate;
use application\common\model\article\LayerManage;
use think\Cookie;
use think\Db;
use application\common\logic\user\Iuser;

//header("Content-type:text/html;charset=utf-8");
class Article extends Base
{

    /**
     * @desc   用户id
     * @var    int
     * @access private
     */
    private $userId;
    /**
     * @desc   文章默认排序
     * @var    string
     * @access private
     */
    private $order='is_best desc,addtime desc';
    /**
     * @desc   文章默认查找字段
     * @var    string
     * @access private
     */
    private $fields='addtime,url,remark1,title,cate_id,id';
    
    /**
     * @desc   广告默认排序
     * @var    string
     * @access private
     */
    private $orderAd='sort desc,addtime desc';
    /**
     * @desc   广告默认查找字段
     * @var    string
     * @access private
     */
    private $fieldsAd='url,remark1,title,id';
    private $articleUrl='/AboutUs/news/type/';

    public function _initialize()
    {
        parent::_initialize();
        $this->articleUrl=SITE_YATANG_FULL.$this->articleUrl;
    }
    public function test(){
        $array=[
            [
                'img'=>  parent::checkImg('/Uploads/ueditor/20171031/59f7d140beaa0.jpg'),
                'url'=>SITE_YATANG_FULL.'/AboutUs/ViewC/cid/partners/aid/3681'
                ],
        ];
        $tmp='336cvpBDcdjNXBgQCLeXiTZdYlQkQzKkErRL5MYW1WH4E1Jn0qqVhVWJ';
        $userInfo=parent::decodeCookie($tmp);
        printp($userInfo);exit;
        $article=new Marticle();
        $info=$article->getSlideimg(249,18,$this->fieldsAd,$this->orderAd);
        printp($info);
    }
    
    /**
     * 获取合作伙伴
     * **/
    public function getPartner(){
        $article=new Marticle();
        $list=$article->getSlideimg(249,18,$this->fieldsAd,$this->orderAd);
        if(empty($list)){
            return [];
        }
        foreach ($list as &$value)
        {
            $value['ar_url']=parent::checkImg(trim($value['ar_url'],'.'));
        }
        return $list;
    }
    /**
     * 获取关联企业
     * **/
    public function getAffiliated(){
        $article=new Marticle();
        $list=$article->getSlideimg(248,18,$this->fieldsAd,$this->orderAd);
        if(empty($list)){
            return [];
        }
        foreach ($list as &$value)
        {
            $value['ar_url']=parent::checkImg(trim($value['ar_url'],'.'));
        }
        return $list;
    }
    /**
     * 首页底部广告
     * **/
    public function getFooterAd(){
        $article=new Marticle();
        $list=$article->getSlideimg(247,1,$this->fieldsAd,$this->orderAd);
        foreach ($list as &$value)
        {
            $value['ar_url']=parent::checkImg(trim($value['ar_url'],'.'));
        }
        parent::okJson($list);
    }
    /**
     * 首页轮播图
     * **/
    public function getHomeAd(){
        $article=new Marticle();
        $list=$article->getSlideimg(237,4,$this->fieldsAd,$this->orderAd);
        if(empty($list)){
            return [];
        }
        foreach ($list as &$value)
        {
            $value['ar_url']=parent::checkImg(trim($value['ar_url'],'.'));
			$value['ar_remark1']=htmlspecialchars_decode($value['ar_remark1']);
        }
        return $list;
    }
    /**
     * 首页轮播图下面的广告
     * **/
    public function getHomeUnderAd(){
        $article=new Marticle();
        $list=$article->getSlideimg(242,2,$this->fieldsAd,$this->orderAd);
        if(empty($list)){
            return [];
        }
        foreach ($list as &$value)
        {
            $value['ar_url']=parent::checkImg(trim($value['ar_url'],'.'));
			
        }
        return $list;
    }
    /**
     * 体验项目两侧的广告
     * **/
    public function getExperienceProjectAd(){
        $article=new Marticle();
        $left=$article->getSlideimg(240,1,$this->fieldsAd,$this->orderAd);
        $right=$article->getSlideimg(241,1,$this->fieldsAd,$this->orderAd);
        $rightInfo=$leftInfo=[];
        if($left){
            $left[0]['ar_url']=parent::checkImg(trim($left[0]['ar_url'],'.'));
            $leftInfo=$left[0];
        }
        if($right){
            $right[0]['ar_url']=parent::checkImg(trim($right[0]['ar_url'],'.'));
            $rightInfo=$right[0];
        }
        $date['left']=$leftInfo;
        $date['right']=$rightInfo;
        return $date;
    }
    /**
     * 获取理财项目广告
     * **/
    public function getManageItemAd(){
        $article=new Marticle();
        $ManageItem=$article->getSlideimg(244,1,$this->fieldsAd,$this->orderAd);
        if(empty($ManageItem)){
            return [];
        }
        $ManageItem[0]['ar_url']=parent::checkImg(trim($ManageItem[0]['ar_url'],'.'));
        return $ManageItem[0];
    }
    /**
     * 获取政治视窗的广告
     * **/
    public function getYaTangViewportAd(){
        $article=new Marticle();
        $Viewport=$article->getSlideimg(356,1,$this->fieldsAd,$this->orderAd);
        if(!$Viewport){
            return FALSE;
        }
        return $Viewport[0];
    }

    /**
     * 首页滚动公告
     * **/
    public function getHomeRollAd(){
        $article=new Marticle();
        $list=$article->getSlideimg(22,3,$this->fieldsAd,'addtime desc,sort desc');
        if(empty($list)){
            return [];
        }
        foreach ($list as &$value)
        {
            $value['ar_title']=htmlspecialchars_decode($value['ar_title']);
            $value['ar_remark1']=SITE_YATANG_FULL.'/notice/aid/'.$value['ar_id'];
        }
        $data['RollAdMoreUrl']=SITE_YATANG_FULL.'/ServiceCenter/Businessbulletin';
        $data['RollAdtList']=$list;
        return $data;
    }
    
    /**
     * 获取:雅堂动态
     * **/
    public function getYaTangTrends(){
        $article=new Marticle();
        $articleCate =new ArticleCate();
        $fields='addtime,url,remark1,title,abst,cate_id,id';
        $sqlArr = [
            'where' => [ 'cate_id' => ['in',[359,360,361] ], 'status' => 1 ,'addtime'=>['lt',time()] ],
            'order' => $this->order,
            'field' => $fields,
            'limit' => 3
        ];
        $list=$article->getSlideArticleWhere($sqlArr);
        $ids=[];
        $eCommerceUrl=$smallSupermarketUrl=$supplyChainFinanceUrl='';
        if($list){
            foreach ($list as &$value)
            {
                $Name='';
                $ids[]=$value['ar_id'];
                $Name=$articleCate->getNameById($value['ar_cate_id']);
                $navId=$article->getNavIdByName($Name);
                if($value['ar_cate_id']==359){
                    $smallSupermarketUrl=$this->articleUrl.$navId;
                }elseif ($value['ar_cate_id']==360) {
                    $eCommerceUrl=$this->articleUrl.$navId;
                }elseif ($value['ar_cate_id']==361) {
                    $supplyChainFinanceUrl=$this->articleUrl.$navId;
                }
                $value['ar_remark1']=$this->articleUrl.$navId.'/id/'.$value['ar_id'];
                $value['ar_addtime'] =  date('Y-m-d',$value['ar_addtime']);
                $value['ar_title']=htmlspecialchars_decode($value['ar_title']);
                $value['ar_abst']=htmlspecialchars_decode($value['ar_abst']);
            }
        }
        $SmallSupermarket=self::getYaTangSmallSupermarket($ids);
        $ECommerce=self::getYaTangECommerce($ids);
        $SupplyChainFinance=self::getYaTangSupplyChainFinance($ids);
        $data['trendsMoreUrl']=$smallSupermarketUrl?$smallSupermarketUrl:($eCommerceUrl?$eCommerceUrl:$supplyChainFinanceUrl);
        $data['trendsName']='雅堂动态';
        $data['trendsRightList']=$list?$list:[];
        $data['smallSupermarketUrl']=$smallSupermarketUrl;
        $data['smallSupermarket']=$SmallSupermarket;
        $data['eCommerceUrl']=$eCommerceUrl;
        $data['eCommerce']=$ECommerce;
        $data['supplyChainFinanceUrl']=$supplyChainFinanceUrl;
        $data['supplyChainFinance']=$SupplyChainFinance;
        return $data;
    }
    /**
     * 获取:雅堂-社会责任
     * **/
    public function getYaTangDuty(){
        $article=new Marticle();
        $articleCate =new ArticleCate();
        $list=$article->getSlideimg(362,6,$this->fields,'addtime desc');
        $dutyName=$articleCate->getNameById(362);
        $navId=$article->getNavIdByName($dutyName);
        if($list){
            foreach ($list as &$value)
            {
                $value['ar_remark1']=$this->articleUrl.$navId.'/id/'.$value['ar_id'];
                $value['ar_addtime'] =  date('Y-m-d',$value['ar_addtime']);
                $value['ar_title']=htmlspecialchars_decode($value['ar_title']);
            }
        }
        $data['dutyMoreUrl']=$this->articleUrl.$navId;
        $data['dutyName']=$dutyName;
        $data['dutyList']=$list?$list:[];
        return $data;
    }
    /**
     * 获取:雅堂-媒体报道
     * **/
    public function getYaTangMediaReport(){
        $article=new Marticle();
        $articleCate =new ArticleCate();
        $list=$article->getSlideimg(358,10,$this->fields,'addtime desc');
        $mediaReportName=$articleCate->getNameById(358);
        $navId=$article->getNavIdByName($mediaReportName);
        if($list){
            foreach ($list as &$value)
            {
                $value['ar_remark1']=$this->articleUrl.$navId.'/id/'.$value['ar_id'];
                $value['ar_addtime'] =  date('Y-m-d',$value['ar_addtime']);
                $value['ar_title']=htmlspecialchars_decode($value['ar_title']);
            }
        }
        $data['mediaReportMoreUrl']=$this->articleUrl.$navId;
        $data['mediaReportName']=$mediaReportName;
        $data['mediaReportList']=$list?$list:[];
        return $data;
    }
    /**
     * 获取:雅堂-政策视窗
     * **/
    public function getYaTangViewport(){
        $article=new Marticle();
        $articleCate =new ArticleCate();
        $adInfoArticle=$article->getSlideimg(357,1,'addtime,url,remark1,title,abst,cate_id,id',$this->order);
        if($adInfoArticle){
            $where=[ 'cate_id' => 357,'id'=>['neq',$adInfoArticle[0]['ar_id'] ] , 'status' => 1 ,'addtime'=>['lt',time()] ];
        }else{
            $where=[ 'cate_id' => 357, 'status' => 1 ,'addtime'=>['lt',time()] ];
        }
        $sqlArr = [
            'where' => $where,
            'order' => 'addtime desc',
            'field' => $this->fields,
            'limit' => 10
        ];
        $list=$article->getSlideArticleWhere($sqlArr);
        
        $viewportName=$articleCate->getNameById(357);
        $navId=$article->getNavIdByName($viewportName);
        if($list){
            foreach ($list as &$value)
            {
                $value['ar_remark1']=$this->articleUrl.$navId.'/id/'.$value['ar_id'];
                $value['ar_addtime'] =  date('Y-m-d',$value['ar_addtime']);
                $value['ar_title']=htmlspecialchars_decode($value['ar_title']);
            }
        }
        $adInfo = self::getYaTangViewportAd();
        if($adInfo && $adInfoArticle){
            $adInfo['ar_remark1']=$adInfoArticle?$this->articleUrl.$navId.'/id/'.$adInfoArticle[0]['ar_id']:'';
            $adInfo['ar_abst']=$adInfoArticle?htmlspecialchars_decode($adInfoArticle[0]['ar_abst']):'';
            $adInfo['ar_title']=$adInfoArticle?htmlspecialchars_decode($adInfoArticle[0]['ar_title']):'';
            $adInfo['ar_addtime'] =  $adInfoArticle?date('Y-m-d',$adInfoArticle[0]['ar_addtime']):'';
            $adInfo['ar_url'] = parent::checkImg(trim($adInfo['ar_url'],'.'));
        }
        $data=[
            'viewportMoreUrl'=>$this->articleUrl.$navId,
            'viewportName'=>$viewportName,
            'viewport'=>$list?array_merge($list):[],
            'viewportAd'=>$adInfo?$adInfo:[]
        ];
        return $data;
    }
    /**
     * 获取雅堂小超文章
     * **/
    public function getYaTangSmallSupermarket($ids){
        $article=new Marticle();
        $articleCate =new ArticleCate();
        $sqlArr = [
            'where' => [ 'cate_id' => 359,'id'=>['NOT IN',$ids] , 'status' => 1 ,'addtime'=>['lt',time()] ],
            'order' => 'addtime desc',
            'field' => $this->fields,
            'limit' => 4
        ];
        $list=$article->getSlideArticleWhere($sqlArr);
        $smallSupermarketName=$articleCate->getNameById(359);
        $navId=$article->getNavIdByName($smallSupermarketName);
        foreach ($list as &$value)
        {
            $value['ar_remark1']=$this->articleUrl.$navId.'/id/'.$value['ar_id'];
            $value['ar_addtime'] =  date('Y-m-d',$value['ar_addtime']);
            $value['ar_title']=htmlspecialchars_decode($value['ar_title']);
        }
        $data['smallSupermarketList']=$list;
        $data['smallSupermarketName']=$smallSupermarketName;
        return $data;
    }
    /**
     * 获取雅堂电商文章
     * **/
    public function getYaTangECommerce($ids){
        $article=new Marticle();
        $articleCate =new ArticleCate();
        $sqlArr = [
            'where' => [ 'cate_id' => 360,'id'=>['not in',$ids] , 'status' => 1 ,'addtime'=>['lt',time()] ],
            'order' => 'addtime desc',
            'field' => $this->fields,
            'limit' => 4
        ];
        $list=$article->getSlideArticleWhere($sqlArr);
        $eCommerceName=$articleCate->getNameById(360);
        $navId=$article->getNavIdByName($eCommerceName);
        foreach ($list as &$value)
        {
            $value['ar_remark1']=$this->articleUrl.$navId.'/id/'.$value['ar_id'];
            $value['ar_addtime'] =  date('Y-m-d',$value['ar_addtime']);
            $value['ar_title']=htmlspecialchars_decode($value['ar_title']);
        }
        $data['eCommerceList']=$list;
        $data['eCommerceName']=$eCommerceName;
        return $data;
    }
    /**
     * 获取雅堂供应链金融文章
     * **/
    public function getYaTangSupplyChainFinance($ids){
        $article=new Marticle();
        $articleCate =new ArticleCate();
        $sqlArr = [
            'where' => [ 'cate_id' => 361,'id'=>['not in',$ids] , 'status' => 1 ,'addtime'=>['lt',time()] ],
            'order' => 'addtime desc',
            'field' => $this->fields,
            'limit' => 4
        ];
        $list=$article->getSlideArticleWhere($sqlArr);
        $supplyChainFinanceName=$articleCate->getNameById(361);
        $navId=$article->getNavIdByName($supplyChainFinanceName);
        foreach ($list as &$value)
        {
            $value['ar_remark1']=$this->articleUrl.$navId.'/id/'.$value['ar_id'];
            $value['ar_addtime'] =  date('Y-m-d',$value['ar_addtime']);
            $value['ar_title']=htmlspecialchars_decode($value['ar_title']);
        }
        $data['supplyChainFinanceList']=$list;
        $data['supplyChainFinanceName']=$supplyChainFinanceName;
        return $data;
    }
    /**
     * 获取雅堂新闻动态
     * **/
    public function getYaTangArticle(){
        $indexmanageItemAd = 'index_getYaTangArticle';
        $data = cache($indexmanageItemAd);
        if (!$data) {
            $data=[
                'trends'=>self::getYaTangTrends(),
                'duty'=>self::getYaTangDuty(),
                'mediaReport'=>self::getYaTangMediaReport(),
                'viewport'=>self::getYaTangViewport()
            ];
            cache($indexmanageItemAd,$data,60*10);
        }
        
        parent::okJson($data);
    }
    /**
     * 获取雅堂首页大屏广告，及其下方的两个广告
     * **/
    public function getHomeIndexAd(){
        $indexmanageItemAd = 'index_getHomeIndexAd';
        $data = cache($indexmanageItemAd);
        if (!$data) {
            $data=[
                'IndexAd'=>self::getHomeAd(),
                'IndexUnderAd'=>self::getHomeUnderAd()
            ];
            cache($indexmanageItemAd,$data,60*10);
        }
        parent::okJson($data);
    }
    /**
     * 获取雅堂合作伙伴及关联企业
     * **/
    public function getRelevanceInfo(){
        $indexmanageItemAd = 'index_getRelevanceInfo';
        $data = cache($indexmanageItemAd);
        if (!$data) {
            $partnerList=self::getPartner();
            $companyList=self::getAffiliated();
            $data=[
                'partnerList'=>$partnerList?$partnerList:[],
                'companyList'=>$companyList?$companyList:[]
            ];
            cache($indexmanageItemAd,$data,60*10);
        }
        
        parent::okJson($data?$data:[]);
    }
    /**
     * 获取弹层广告
     * **/
    public function layerAd()
    {
        $key='index_layerAd';
        $layerInfo = cache($key);
        if(!$layerInfo){
            $layerManage=new LayerManage();
            $layerInfo=$layerManage->getArticleList();
            cache($key,$layerInfo,10*60);
        }
        //判断弹层
        if(!$layerInfo){
            parent::failJson();
        }
        //判断弹层是否定时设置
        if($layerInfo['lm_timing'] && $layerInfo['lm_timestart'] >  time() ){
            parent::failJson();
        }
        //判断是否登陆出现
        if($layerInfo['lm_appear']){
            //登陆后显示
            $cookie = cookie::get(COOKIE_TYPE);
            $Iuser=new Iuser();
//            $cookie='2f8ePNZoL2YI/gWcYChx/8sA0E94PPSIId6es97zSN1kJPawkByWxY9O6tz0KZ/Ta4Qe';
            $userInfo=$Iuser->authcode($cookie);
            //用户未登陆
            if(!$userInfo){
                parent::failJson();
            }
            $userInfo=explode( ',',$userInfo);
            //判断是否限制注册时间
            if(!$layerInfo['lm_regtime'] && $layerInfo['lm_user_regtime']){
                //限制注册时间
                $regtime=Db::name('iuser')->where(['user_id'=>$userInfo[0]])->value('addtime');
                if($regtime <  strtotime($layerInfo['lm_user_regtime'])){
                    //注册时间在设时间之前，不显示
                    parent::failJson();
                }
            }
            self::checkLayerAdShow($layerInfo);
        }else{
            self::checkLayerAdShow($layerInfo);
        }
      
    }
    private function checkLayerAdShow(&$layerInfo){
        $lm_frequency=$layerInfo['lm_frequency'];
        unset($layerInfo['lm_id']);
        unset($layerInfo['lm_starttime']);
        unset($layerInfo['lm_endtime']);
        unset($layerInfo['lm_appear']);
        unset($layerInfo['lm_regtime']);
        unset($layerInfo['lm_status']);
        unset($layerInfo['lm_timing']);
        unset($layerInfo['lm_timestart']);
        unset($layerInfo['lm_remark']);
        unset($layerInfo['lm_create_at']);
        unset($layerInfo['lm_frequency']);
        unset($layerInfo['lm_user_regtime']);
        
        $checkLayerAdShowKey='index_checkLayerAdShow';
        $layer = cache($checkLayerAdShowKey);
        if(!$layer){
            $layerInfo['lm_images']=  unserialize($layerInfo['lm_images']);
            $imagesInfo = getimagesize( parent::checkImg(trim($layerInfo['lm_images'][0]['image'],'.')) );
            $layerInfo['width'] = $imagesInfo[0]?$imagesInfo[0]:0;
            $layerInfo['height'] = $imagesInfo[1]?$imagesInfo[1]:0;
            foreach ($layerInfo['lm_images'] as $k=>&$value)
            {
                if($value['image']){
                    $value['image']=parent::checkImg(trim($value['image'],'.'));
                }else{
                    unset($layerInfo['lm_images'][$k]);
                }
            }
            cache($checkLayerAdShowKey,$layerInfo,86400);
        }
        $layerInfo=$layer?$layer:$layerInfo;
        $key=cookie::get('UM_distinctid');
        $res = cache($key);
        if($res){
            parent::failJson();
        }
        $time=time();
        if($lm_frequency==1){
            $time=  strtotime(date('Y-m-d 23:59:59',  $time)) - $time;
            cache($key,1,$time);
            parent::okJson($layerInfo);
        }elseif($lm_frequency==2){
            if( strtotime(date('Y-m-d 12:00:00',  $time ) ) > $time ){
                $time12=  strtotime(date('Y-m-d 12:00:00',  $time)) - $time;
                cache($key,1,$time12);
                parent::okJson($layerInfo);
            }else{
                $time24=  strtotime(date('Y-m-d 23:59:59',  $time)) - $time;
                cache($key,1,$time24);
                parent::okJson($layerInfo);
            }
        }else{
            parent::failJson();
        }
    }
    
}
