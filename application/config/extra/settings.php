<?php

/**
 * 第三方接口。以及缓存，其他数据库配置
 * 
 */
return [
    "MONTERNET" => [
        'product' => ["ACCOUNT" => "J02921", "PASSWORD" => "959891", "GATE" => "http://61.145.229.29:9006/MWGate/wmgw.asmx"],
        'others' => ["ACCOUNT" => "J52030", "PASSWORD" => "513599", "GATE" => "http://61.130.7.220:8023/MWGate/wmgw.asmx"]
    ],
    "EMAY" => [
        "WAY1" => [
            'serialNumber' => '9SDK-EMY-0999-JBRUO',
            'password' => '540820',
            'tServerNo' => '101111',//
            'gate'=>"http://sdk999ws.eucp.b2m.cn:8080/sdk/SDKService"
        ], //正式： http://sdk999ws.eucp.b2m.cn:8080/sdk/SDKService
    ],
    "XUANWU"=>array(
        "ACCOUNT"=>"ydtzc@ytzj",//
        "PASSWORD"=>"lgDO9n7b"
    ),
    'ZzcAuthentication' => [
        'accessToken' => 'xhRN-C86Cc3MabfR2uoL',
        'reqUrl' => 'https://api2.intellicredit.cn/id_check'
    ],
    
    /**
     * app消息推送配置
     */
    'app_msg' => [
        'host' => 'http://sdk.open.api.igexin.com/apiex.htm',
        'appkey' => 'Z4kAQhRK9i8R8GA1SIAm69',
        'appid' => '6FvITOOWQp9QmRFITZXBh8',
        'mastersecret' => 'H8osAGTnAj6CrCwtXxC3X'
    ],
    'email' => ['SMTP_HOST' => 'smtp.exmail.qq.com', //SMTP服务器
        'SMTP_PORT' => '25', //SMTP服务器端口
        'SMTP_USER' => 'admin@itbt.com.cn', //SMTP服务器用户名    'SMTP_PASS'   => 'password', //SMTP服务器密码
        'SMTP_PASS' => '13a24b123',
        'FROM_EMAIL' => 'admin@itbt.com.cn', //发件人EMAIL
        'FROM_NAME' => '雅堂金融', //发件人名称
        'REPLY_EMAIL' => '', //回复EMAIL（留空则为发件人EMAIL）
        'REPLY_NAME' => '', //回复名称（留空则为发件人名称）]
    ],
    'xc' => [
        'public_account' => 'ytds168',
        'public_key' => '29a87ce1c3a73e86f935ef3feb21dbf3', // 接口调用key(测试环境和线上环境保持非同步，当公钥有修改时，需通知对应的接入方)
        'settlement_failed_mail_notify_list' => [
            
        ], //小超结算失败邮件头通知列表
    ],
    'yzt' => [
        'YZT_COOKIE_KEY' => 'yzt.one.@#uiwoa000000000', //一账通cookie解密key
        'YZT_KEY' => 'yatang.udc.pw.encrypt.key.addby.sivl', //一账通密码加密传输秘钥                      
        'YZT_COOKIE_ENCRYPT_KEY' => 'i1Bt!2bN$t3&'     //一账通cookie加密key
        
    ],
    'cookie' => [
        'cookie_encrypt_key' => 'i1Bt!2bN$t3&',
        'cookie_domain'=>'',
    ],
    'mkey' => 'IT*#@!8f',//登录密码加密key
    'tender_usr'=>[7990,53261],
	'rechargekey' => 'df389d8e96d5f20fda280fd41aaca33b', //充值加密key
    'prize_type'      => [
        -1   => '全部',
        1001 => '红包',
        1004 => "VIP抵用卷",
        1005 => "积分卡",
        1006 => "能量卡",
        1003 => "理财金"],
    'rule_award_type' =>[
        1001 => 0, //红包
        1004 => 2, //vip抵用卷
        1005 => 3, //积分卡
        1006 => 1, //能量卡
        1003 => 6  //理财金
    ],
    'rule_icon'       =>[
        1001 => 5, //红包
        1004 => 7, //vip抵用卷
        1005 => 1, //积分卡
        1006 => 3, //能量卡
        1003 => 6  //理财金
    ],
    'phone' => [
        '13763391060',
		'15820460512',
        '13424380919'
    ],
    'cash_back'=>[
        '1'=>[
            'account'=>'yatang_vip',
            'signature'=>'966c6a164a2146d69398f4f7e97d6e76',
            'clear_account'=>[
                1 =>'YT_XC_PTJS'
            ],
        ],
        '2'=>[
            'account'=>'yatang_ds',
            'signature'=>'967c7a164a2146d69398f4f7e97d7e77',
            'clear_account'=>[
                1 =>'YT_XC_PTJS'
            ],
        ],
    ],
    'cross_domain'=>[
        'https://jr.yatang.cn',
        'https://uatjr.yatang.cn',
        'https://one.yatang.cn',
        'https://two.yatang.cn',
        'https://three.yatang.cn',
        'https://trunk.yatang.cn',
        'https://api.yatang.cn',
        'https://testapi.yatang.cn',
        'https://cd.yatang.cn',
        'https://wh.yatang.cn',
        'https://cs.yatang.cn',
        'https://sz.yatang.cn',
    ],
    'month_rate' => [
        1 => 5,
        2 => 7,
        3 => 9,
        4 => 11,
        5 => 13,
        6 => 15,
    ],
];
