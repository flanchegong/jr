<?php
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    'type'           => 'mysql',// 数据库类型 
    'hostname'       => '10.46.74.222,10.27.226.140,10.27.7.137', // 服务器地址
    'database'       => 'itbtdb',// 数据库名
    'username'       => 'tp5rw,tp5r,tp5r', // 用户名
    'password'       => 'FXwne@c90,FXwne@c360,FXwne@c360', // 密码
    'hostport'       => '6606,6660,6666',// 端口
    'dsn'            => '', // 连接dsn
    'charset'        => 'utf8', // 数据库编码默认采用utf8
    'prefix'         => 'itd_',// 数据库表前缀 
    'debug'          => false, // 数据库调试模式
    'deploy'         => 1, // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
    'rw_separate'    => true,// 数据库读写是否分离 主从式有效
    'master_num'     => 1, // 读写分离后 主服务器数量
    'slave_no'       => '', // 指定从服务器序号
    'fields_strict'  => false, // 是否严格检查字段是否存在
    'resultset_type' => 'array', // 数据集返回类型 array 数组 collection Collection对象
    'auto_timestamp' => true, // 是否自动写入时间戳字段
	'sql_explain'    => false, 
    'break_reconnect' => true,
    // 数据库连接参数
    'params'          => [
        // 使用长连接
        \PDO::ATTR_PERSISTENT   => false,
        \PDO::MYSQL_ATTR_FOUND_ROWS => true,
        \PDO::ATTR_EMULATE_PREPARES=>true
    ],
];

