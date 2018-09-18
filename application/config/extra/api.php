<?php

 
return array(
    "API" => array(       
		/**
		 * 首页
		 * **/		
	   "HOME_BANNER" => array("url" => API_HOST, "method" => "articleAction.getHomeCarouselPicture"), //首页轮播图
        "FINANCIA_ARTICAL" => array("url" => API_HOST, "method" => "articleAction.getFinancialRecommendation"), //首页金融推荐文章
        "MEDIA_ARTICAL" => array("url" => API_HOST, "method" => "articleAction.getMediaeports"), //首页媒体报道
        "NEWS_ARTICAL" => array("url" => API_HOST, "method" => "articleAction.getNewsInformation"), //首页快速了解投资城
        "NOTICE_ARTICAL" => array("url" => API_HOST, "method" => "articleAction.getNewsNotice"), //首页企业最新公告
        "NAVIGATION" => array("url" => API_HOST, "method" => "navigationAction.queryMainNavigation"), //导航栏接口
        "HOME_BORROW" => array("url" => API_HOST, "method" => "borrowAction.getHomePageBorrow"), //首页显示标的信息接口
        "HOME_COUNT" => array("url" => API_HOST, "method" => "countDayAction.getAmountCount"), //首页金额统计数据显示
			  /**
         * 邀请码
         */
	"GET_INVITE_CODE" => array("url" => YZT_API_HOST, "method" => "accountAction.getInviteInfo"), //获取邀请码		
	"GET_USER_ID_BY_INVITE_CODE" => array("url" => API_HOST, "method" => "userInviteAction.queryUserInviteByUserId"), //根据邀请码获取用户ID
        /**
         * 微信
         ***/
        "AWARDACTION_APPDRAW" => array("url" => API_HOST, "method" => "awardAction.appDraw"), //抽奖
        "AWARDACTION_APPDRAWCHECK" => array("url" => API_HOST, "method" => "awardCheckAction.getAwardCheck"), //抽奖规则
        "IUSERACTION_COUNTUSERBYPHONE" => array("url" => API_HOST,"method"=>"iuserAction.countUserByPhone"), // 获取手机号码注册人数
        "AWARDACTION_AWARDBIND" => array("url"=>API_HOST,"method"=>"awardAction.awardBind"), // 绑定用户和奖品
        "AWARDACTION_CHESTLIST" => array("url" => API_HOST, "method" => "treasureChestAction.getTreasureChestList"),//百宝箱
        "AWARDACTION_TYPELIST" => array("url" => API_HOST, "method" => "awardTypeAction.getAwardTypeList"),//百宝箱分类
        "AWARDACTION_DELETE" => array("url" => API_HOST, "method" => "treasureChestAction.deleteTreasureChest"),//百宝箱数据删除
        "AWARDACTION_CASHCOUPON" => array("url" => API_HOST, "method" => "treasureChestAction.getValidCashCoupon"),//现金券
        "AWARDACTION_LOTTERYDRAW" => array("url" => API_HOST, "method" => "awardAction.appDrawComm"),//微信三方抽奖
		"IUSERACTION_COUNTUSERBYPHONE" => array("url" => API_HOST,"method"=>"iuserAction.countUserByPhone"), // 获取手机号码注册人数
		"AWARDACTION_AWARDBIND" => array("url"=>API_HOST,"method"=>"awardAction.awardBind"), // 绑定用户和奖品
        "AWARDACTION_WALLETBIND" => array("url" => API_HOST, "method" => "awardAction.walletBind"),//双十二红包绑定
        "AWARDACTION_NEWRULEDRAW" => array("url" => API_HOST, "method" => "awardNewRuleAction.draw"),//抽奖接口--new-jhl
        "AWARDACTION_NEWRULEDRAWBIND" => array("url" => API_HOST, "method" => "awardNewRuleAction.drawBind"),//奖品绑定--new-jhl
		"AWARDACTION_XYXDRAW" => array("url" => API_HOST, "method" => "awardNewRuleAction.draw"),//幸运秀抽奖
		"AWARDACTION_XYXDRAWBIND" => array("url" => API_HOST, "method" => "awardNewRuleAction.drawBind"),//幸运秀抽奖奖品绑定接口
		"AWARDACTION_XYXOUTBIND" => array("url" => API_HOST, "method" => "awardNewRuleAction.outDrawBind"),//幸运秀外部抽奖接口绑定
		  /**
         * 百宝箱
         ***/
		"AWARDACTION_TREASURELIST" => array("url" => API_HOST, "method" => "treasureChestAction.getTreasureChestByAWTypeId"),//百宝箱各种券汇总列表
        "AWARDACTION_INVALIDDRAW" => array("url" => API_HOST, "method" => "treasureChestAction.getAllInvalidDrawByUserId"),//取得所有失效奖品接口
        "AWARDACTION_ENERGYORPOINTCADE" => array("url" => API_HOST, "method" => "treasureChestAction.userVipEnergyOrPointCade"),//使用vip能量或积分卡
        "AWARDACTION_GETUSERVIPINFO" => array("url" => API_HOST, "method" => "treasureChestAction.getUserVipInfo"),//取得用户vip时间信息接口
        "AWARDACTION_CHESTBYAWTYPE" => array("url" => API_HOST, "method" => "treasureChestAction.getTreasureChestByAWType"), //百宝箱分类数据接口
		"AWARDACTION_NEWREDPACKET" => array("url" => API_HOST, "method" => "treasureChestAction.getNewRuleTreasureChestList"), //新红包规则接口(可控红包可投的标种类，标期限以及红包生效时间)
        
        
        /**
         * 双11抽奖相关接口
         * **/
        "AWARDNEWRULEACTION_APPDRAWFOR1111" => array("url" => API_HOST, "method" => "awardNewRuleAction.appDrawFor1111"), //双11抽奖接口
        "AWARDNEWRULEACTION_STOPDRAWFOR1111" => array("url" => API_HOST, "method" => "awardNewRuleAction.stopDrawFor1111"), //双11抽奖接口清理内存中的所有奖品配置信息
        "AWARDNEWRULEACTION_OPENDRAWFOR1111" => array("url" => API_HOST, "method" => "awardNewRuleAction.openDrawFor1111"), //双11抽奖接口初始化奖品库存接口
		
		/**
         * 一账通相关接口
         ***/
        "YZT_REGISTER" => array("url" => YZT_API_HOST, "method" => "accountAction.register"), //注册
        "YZT_LOGIN" => array("url" => YZT_API_HOST, "method" => "accountAction.login"), //登陆
        "YZT_MODIFY_PWD" => array("url" => YZT_API_HOST, "method" => "accountAction.modifyPwd"), //修改密码
        "YZT_MODIFY_EMAIL" => array("url" => YZT_API_HOST, "method" => "accountAction.modifyEmail"), //修改邮箱
        "YZT_MODIFY_MOBILE" => array("url" => YZT_API_HOST, "method" => "accountAction.modifyMobile"), //修改电话号码
        "YZT_QUERY_USERINFO" => array("url" => YZT_API_HOST, "method" => "accountAction.queryUserInfo"), //查询用户信息
        "YZT_UPGRADE_USER" => array("url" => YZT_API_HOST, "method" => "accountAction.upgradeUser"), //用户信息升级为商家用户
        "YZT_QUERY_USERNAME_EXIT" => array("url" => YZT_API_HOST, "method" => "accountAction.userNameIsExist"), //用户名是否存在
        "YZT_QUERY_MOBILE_EXIT" => array("url" => YZT_API_HOST, "method" => "accountAction.mobileIsExist"), //手机号码是否存在
        "YZT_QUERY_EMAIL_EXIT" => array("url" => YZT_API_HOST, "method" => "accountAction.emailIsExist"), //邮箱是否存在
        "YZT_RESET_PWD" => array("url" => YZT_API_HOST, "method" => "accountAction.resetPwd"), //重置用户密码
        "YZT_VERIFY" => array("url" => YZT_API_HOST, "method" => "accountAction.userVerify"), //用户校验
        "YZT_BIND_USER" => array("url" => YZT_API_HOST, "method" => "accountAction.bindUser"), //用户绑定
        "YZT_ACCOUNT_CHOICE" => array("url" => YZT_API_HOST, "method" => "accountAction.accountChoice"), //多账户选择
        "YZT_CHOICE_BIND" => array("url" => YZT_API_HOST, "method" => "accountAction.itbtUserBind"), //多账户,选择之一进行升级
        "YZT_ADD_SYS_INTERFACE" => array("url" => YZT_API_HOST, "method" => "sysInterfaceAction.addSysInterface"), //新后台接口管理添加新增接口
        "YZT_UPDATE_SYS_INTERFACE" => array("url" => YZT_API_HOST, "method" => "sysInterfaceAction.updataSysInterface"), //新后台接口管理修改接口    
        "YZT_GET_SYS_INTERFACE_LIST" => array("url" => YZT_API_HOST, "method" => "sysInterfaceAction.getSysInterfaceList"), //新后台接口管理查询接口 
        "YZT_GET_SYSTEM_OPTION_INTERFACE_LIST" => array("url" => YZT_API_HOST, "method" => "sysInterfaceAction.getSystemOptionList"), //新后台接口管理查询接口 
	"YZT_NOTUSER_LIST" => array("url" => YZT_API_HOST, "method" => "accountAction.queryNotUserManageInfo"), //非一账通查询接口
        "YZT_USER_LIST" => array("url" => YZT_API_HOST, "method" => "accountAction.queryUserManageInfo"), //一账通查询接口
        "YZT_CANCEUSER_LIST" => array("url" => YZT_API_HOST, "method" => "accountAction.queryCancelUserInfo"), //已注销用户查询接口
        "YZT_NOTUSER_UPGRADE" => array("url" => YZT_API_HOST, "method" => "accountAction.notUserUpgrade"), //非一账通用户管理升级操作接口
        "YZT_CANCENOTUSER" => array("url" => YZT_API_HOST, "method" => "accountAction.cancelNotUser"), //非一账通用户管理注销操作接口
        "YZT_NOTUSER_ENABLEANDNOT" => array("url" => YZT_API_HOST, "method" => "accountAction.notUserEnableAndNot"), //非一账通用户管理启用停用操作接口
        "YZT_CANCEUSER" => array("url" => YZT_API_HOST, "method" => "accountAction.cancelUser"), //一账通用户管理注销操作接口
        "YZT_USER_ENABLEANDNOT" => array("url" => YZT_API_HOST, "method" => "accountAction.enableAndNot"), //一账通用户管理启用停用操作接口
        "YZT_CANCEUSERBYNAME" => array("url" => YZT_API_HOST, "method" => "accountAction.cancelUserByName"), //一账通用户管理注销操作接口根据用户名
        "YZT_REALNAME" => array("url" => YZT_API_HOST, "method" => "accountAction.receiveRealName"), //金融实名认证同步到一站通
        
        "YZT_GETACCOUNTTYPELIST" => array("url" => YZT_API_HOST, "method" => "accountTypeRelationAction.getAccountTypeList"), //一账通用户管理接口:查询用户角色列表(角色列表)
        "YZT_GETACCOUNTTYPERELATIONLISTBYUSERID" => array("url" => YZT_API_HOST, "method" => "accountTypeRelationAction.getAccountTypeRelationListByUserId"), //一账通用户管理:接口根据用户id查询用户角色列表(单个用户的角色)
        "YZT_SAVEACCOUNTTYPERELATION" => array("url" => YZT_API_HOST, "method" => "accountTypeRelationAction.saveAccountTypeRelation"), //一账通用户管理:接口根据用户id查询用户角色列表(单个用户的角色)
        "YZT_DELETESINGLEACCOUNTTYPERELATION" => array("url" => YZT_API_HOST, "method" => "accountTypeRelationAction.deleteSingleAccountTypeRelation"), //一账通用户管理:接口根据用户id删除用户角色列表(单个用户的角色)
        "YZT_DELETEALLACCOUNTTYPERELATION" => array("url" => YZT_API_HOST, "method" => "accountTypeRelationAction.deleteAllAccountTypeRelation"), //一账通用户管理:接口根据用户id删除用户角色列表(单个用户的角色)

        "YZT_REGISTERBYSPECIAL" => array("url" => YZT_API_HOST, "method" => "accountAction.registerBySpecial"), //金融一站通后台特殊用户注册
        "YZT_MODIFYACCOUNTINVITEINFO" => array("url" => YZT_API_HOST, "method" => "userInviteAction.modifyAccountInviteInfo"), //金融一站通后台:修改用户邀请码
        "YZT_UPREALNAME" => array("url" => YZT_API_HOST, "method" => "accountAction.updateAccountRealNameBeach"), //一站通批量实名接口
        
        "YZT_DELETESEPEIALUSER" => array("url" => YZT_API_HOST, "method" => "specialUserAction.deleteSepeialUser"), //金融一站通后台:注册用户名敏感词删除
        "YZT_UPDATESPEICALUSER" => array("url" => YZT_API_HOST, "method" => "specialUserAction.updateSpeicalUser"), //金融一站通后台:注册用户名敏感词修改
        "YZT_ADDSEPPEIALUSER" => array("url" => YZT_API_HOST, "method" => "specialUserAction.addSepeialUser"), //金融一站通后台:注册用户名敏感词添加
        "YZT_GETSPEICALUSER" => array("url" => YZT_API_HOST, "method" => "specialUserAction.getSpeicalUserPage"), //金融一站通后台:注册用户名敏感词查询
        
        "YZT_CLEARREALNAME" => array("url" => YZT_API_HOST, "method" => "accountAction.clearRealName"), //金融一站通后台:清理实名认证信息接口
		
        
		"YZT_SUBACCOUNTSAVESUBACCOUNTINFO" => array("url" => YZT_API_HOST, "method" => "subAccountAction.saveSubAccountInfo"), //保存小超门店编码接口
        "YZT_SUBACCOUNTDELETESUBACCOUNTINFO" => array("url" => YZT_API_HOST, "method" => "subAccountAction.deleteSubAccountInfo"), //删除小超编码接口
        "YZT_SUBACCOUNTGETSUBACCOUNTINFOLIST" => array("url" => YZT_API_HOST, "method" => "subAccountAction.getSubAccountInfoList"), //查询用户小超编码信息接口
        'YZT_SAVEUSERBANKCARDINFO' => array("url" => YZT_API_HOST, "method" => "userBankCardAction.saveUserBankCardInfo"), //保存并校验银联4要素接口
    )
);
