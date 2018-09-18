<?php

namespace application\common;
/**
 * @Copyright (C), 2016, jiquan
 * @Name Myredis.php
 * @Author liuj
 * @Version stable 1.0
 * @Date 2017-7-24
 * @Description 单例模式对redis实例的操作的进一步封装 主要目的：防止过多的连接，一个页面只能存在一个声明连接
 * @Class List
 *    1.
 * @Function List
 *    1.
 * @History
 * <author>  <time>              <version>    <desc>
 *  liuj   2017-07-24          stable 1.0   第一次建立该文件
 */

class Myredis
{

    /**
     * @desc   redis
     * @var    string
     * @access protected
     */
    private static $redisInstance;

    /**
     * @desc  私有化构造函数 防止外界调用构造新的对象
     * @author liuj
     * @update 2017-06-19
     * @access private
     * @return arrray
     */
    private function __construct()
    {

    }

    /**
     * @desc   获取redis连接的唯一出口
     * @author liuj
     * @update 2017-06-19
     * @access static
     * @return object
     */
    static public function getRedisConn($db=0)
    {
        if (!self::$redisInstance instanceof self)
        {
            self::$redisInstance = new self;
        }
        // 获取当前单例
        $temp = self::$redisInstance;
        // 调用私有化方法
        return $temp->connRedis($db);
    }

    /**
     * @desc   连接redis的私有化方法
     * @author liuj
     * @update 2017-06-19
     * @access static
     * @return arrray
     */
    static private function connRedis($db)
    {
        try
        {
            $options = [
                'servers' => [
                    [
                        'host' => REDIS_HOST,
                        'port' => REDIS_PORT,
                        'password'=>REDIS_PASS
                    ]
                ]
            ];
            $redis = new \Rediska($options);
            $redis->selectDb($db);

        } catch (Exception $e)
        {
            echo $e->getMessage() . '<br/>';
        }

        return $redis;
    }



}