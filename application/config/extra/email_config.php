<?php

/**
 * @Copyright (C), 2016, Liuj.
 * @Name $name
 * @Author Liuj
 * @Version stable 1.0
 * @Date: $date
 * @Description
 * 1. Example
 * @Function List
 * 1.
 * @History
 * Liuj $date     stable 1.0 第一次建
 */

//邮件配置
return array(
   'SMTP_HOST'   => 'smtp.yatang.cn', //SMTP服务器
   'SMTP_PORT'   => '25', //SMTP服务器端口
   'SMTP_USER'   => 'jr@yatang.cn', //SMTP服务器用户名    'SMTP_PASS'   => 'password', //SMTP服务器密码
   'SMTP_PASS'   => 'YT2017jr',
   'FROM_EMAIL'  => 'jr@yatang.cn', //发件人EMAIL
   'FROM_NAME'   => '雅堂金融', //发件人名称
   'REPLY_EMAIL' => '', //回复EMAIL（留空则为发件人EMAIL）
   'REPLY_NAME'  => '', //回复名称（留空则为发件人名称）
);