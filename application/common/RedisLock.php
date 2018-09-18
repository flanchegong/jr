<?php

namespace application\common;

use think\cache\driver\Redis;

/**
 *  Redis锁操作类
 *  Date:   2016-06-30
 *  Author: fdipzone
 *  Ver:    1.0
 *  Func:
 *  public  lock    获取锁
 *  public  unlock  释放锁
 *  private connect 连接
 */
class RedisLock
{ // class start

    private $_redis;

    /**
     * 初始化
     * @param Array $config redis连接设定
     */
    public function __construct($db = 0)
    {
        $this->_redis = $this->connect($db);
    }

    /**
     * 获取锁
     * @param  String $key 锁标识
     * @param  Int $expire 锁过期时间
     * @return Boolean
     */
    public function lock($key, $expire = 180)
    {
        if ($key != '')
        {
            $key = "LOCK_" . $key;
            $lockStatus = $this->_redis->get($key);
            if ($lockStatus)
            {
                return false;
            }
            else
            {
                $this->_redis->setAndExpire($key, 1, $expire);
                return true;
            }
        }
        return false;
    }

    /**
     * 释放锁
     * @param  String $key 锁标识
     * @return Boolean
     */
    public function unlock($key)
    {
        if ($key != '')
        {
            $key = "LOCK_" . $key;
            $this->_redis->delete($key);
            return true;
        }
        return false;
    }

    /**
     * 创建redis连接
     * @return Link
     */
    private function connect($db)
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

} // class end

?>