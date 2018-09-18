<?php
/*******汇卡相关配置*******/
return [
    /****************商户入网、银行卡修改相关配置****************/
    'merchantAccess' => [
        // 机构号
        'organizationNumber' => [
            'development' => '99999999',
            'test' => '99999999',
            'uat' => '99999999',
            'product' => '48622884'
        ],
        // 秘钥
        'secretKey' => [
            'development' => '1a861edc704121753369894d7afc9596',
            'test' => '1a861edc704121753369894d7afc9596',
            'uat' => '1a861edc704121753369894d7afc9596',
            'product' => 'd7f31f7b331d4cd2bd64cc99f4f11e9e'
        ],
        // 请求地址(商户入网、银行卡修改)
        'requestUrl' => [
            'development' => 'http://113.108.195.242:25166/InterfaceChangeServers/toMain.do',
            'test' => 'http://113.108.195.242:25166/InterfaceChangeServers/toMain.do',
            'uat' => 'http://113.108.195.242:25166/InterfaceChangeServers/toMain.do',
            'product' => 'http://183.62.43.253:8383/InterfaceChangeServers/toMain.do'
        ],
        //业务类型
        'busiNo' => [
            'development' => '0311,812',
            'test' => '0311,812',
            'uat' => '0311,812',
            'product' => '0311,812'
        ],
        //汇率ID
        'rateId' => [
            'development' => '3091,3092,3093,3203',
            'test' => '3091,3092,3093,3203',
            'uat' => '3091,3092,3093,3203',
            'product' => '3514,5855'
        ],
    ],
    /**************扫码支付相关配置****************/
    //支付配置
    'payCreate' => [
        //机构号
        'organNo' => [
            'development' => '12999000',
            'test' => '12999000',
            'uat' => '12999000',
            'product' => '48622884',
        ],
        //机构号-无卡快捷支付配置（测试环境机构号有区别，线上环境和其他无区别）
        'organNoQuickPayment' => [
            'development' => '48622884',
            'test' => '48622884',
            'uat' => '48622884',
            'product' => '48622884',
        ],
        //版本号
        'version' => 'V001',
        
        //支付key
        'key' => [
            'development' => '036da89986cd49bcae58821af4c3156a',
            'test' => '036da89986cd49bcae58821af4c3156a',
            'uat' => '036da89986cd49bcae58821af4c3156a',
            'product' => '2db4fc780e12e20650b7c1ed5b99ddc0',
        ],
        //支付key-无卡快捷支付配置（测试环境机构号有区别，线上环境和其他无区别）
        'keyQuickPayment' => [
            'development' => '2db4fc780e12e20650b7c1ed5b99ddc0',
            'test' => '2db4fc780e12e20650b7c1ed5b99ddc0',
            'uat' => '2db4fc780e12e20650b7c1ed5b99ddc0',
            'product' => '2db4fc780e12e20650b7c1ed5b99ddc0',
        ],
        // 请求地址
        'requestUrl' => [
            'development' => 'http://113.108.195.242:38888/hicardpay/order/create',
            'test' => 'http://113.108.195.242:38888/hicardpay/order/create',
            'uat' => 'http://113.108.195.242:38888/hicardpay/order/create',
            'product' => 'http://online.icloudful.com:28888/hicardpay/order/create'
        ],
    ],
    
    /****************支付查询配置********************/
    'payQuery' => [
        // 请求地址
        'requestUrl' => [
            'development' => 'http://113.108.195.242:38888/hicardpay/order/query',
            'test' => 'http://113.108.195.242:38888/hicardpay/order/query',
            'uat' => 'http://113.108.195.242:38888/hicardpay/order/query',
            'product' => 'http://online.icloudful.com:28888/hicardpay/order/query'
        ],
    ]
];













