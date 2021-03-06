<?php

/**
 * @Copyright (C), 2016, pandelin.
 * @Name $name
 * @Author pandelin
 * @Version stable 1.0
 * @Date: $date
 * @Description
 * 1. Example
 * @Function List
 * 1.
 * @History
 * pandelin $date     stable 1.0 第一次建
 */
namespace application\common\model\investAward;

use application\common\model\Base;
use application\common\Myredis;
use think\Log;

class ActivityInvestAwardBlacklist extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'activity_invest_award_blacklist';
    
    /**
     * @desc  缓存key
     * @var    string
     * @access private
     */
    private $cashKey  = 'invest_reward_BlackList';

    public function checkUserBlackList($userId, $activityId)
    {
        $blackList = Myredis::getRedisConn(4)->getFromHash($this->cashKey, $activityId);
        if (!$blackList)
        {
            $blackList = self::setBlackList($activityId, 1);
        }
        if (!$blackList)
        {
            return FALSE;
        }
        if (in_array($userId, $blackList))
        {
            Log::write(sprintf("[投资奖励]时间:%s,错误信息:%s,", date('Y-m-d H:i:s'), '用户ID：' . $userId . '：用户处于黑名单中：' . $this->cashKey . '活动ID：' . $activityId), 'info');
            return TRUE;
        }
        return FALSE;
    }

    /**
     * 获取黑名单用户--加入缓存
     * @param int $activityId 活动id
     * @param int $res [option] 是否返回数组
     * * */
    public function setBlackList($activityId, $res = '')
    {
        $temp = $this->redis->getFromHash($this->cashKey, $activityId);
        if ($temp)
        {
            $this->redis->deleteFromHash($this->cashKey, $activityId);
        }
        $list = Db::name($this->_table)->where(array('invest_award_item_id' => $activityId))->field('user_id')->select();
        if ($list)
        {
            foreach ($list as $v)
            {
                $arr[] = $v['user_id'];
            }
        }
        if ($list)
        {
            $this->redis->setToHash($this->cashKey, $activityId, $arr);
        }
        if ($res)
        {
            return $arr;
        }
    }

}
