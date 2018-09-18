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
use application\common\model\investAward\AwardActivitiesInfo;
use application\common\model\user\Plat;

class ActivityInvestAwardPromotionSet extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'activity_invest_award_promotion_set';

    /**
     * @desc  缓存key
     * @var    string
     * @access private
     */
    private $cashKey = 'invest_reward_BlackList';

    /**
     * 检查用户是否在渠道黑白名单中
     * @param int $userId 用户ID
     * @param int $activityId 活动ID
     * @param int $type 黑白名单类型：1:黑名单，2:白名单
     * * */
    public function checkPromotionSetList($userId, $activityId, $type)
    {
        $promotionSetList = Myredis::getRedisConn(4)->getFromHash($this->cashKey, $activityId);
        if (!$promotionSetList)
        {
            $promotionSetList = self::setPromotionSetList($activityId, 1);
        }
        $msg = $type == 1 ? '：用户处于黑名单中：' : '：用户处于白名单中：';
        if (!$promotionSetList)
        {
            if ($type == 1)
            {
                return FALSE;
            }
            else
            {
                return TRUE;
            }
        }
        if (in_array($userId, $promotionSetList))
        {
            if ($type == 1)
            {
                Log::write(sprintf("[投资奖励]时间:%s,错误信息:%s,", date('Y-m-d H:i:s'), '用户ID：' . $userId . $msg . $this->cashKey . '活动ID：' . $activityId), 'info');
            }
            return TRUE;
        }
        if ($type == 2)
        {
            Log::write(sprintf("[投资奖励]时间:%s,错误信息:%s,", date('Y-m-d H:i:s'), '用户ID：' . $userId . $msg . $this->cashKey . '活动ID：' . $activityId), 'info');
        }
        return FALSE;
    }

    /**
     * 获取黑名单用户--加入缓存
     * @param int $activityId 活动id
     * @param int $res [option] 是否返回数组
     * * */
    public function setPromotionSetList($activityId, $res = '')
    {
        $temp = Myredis::getRedisConn(4)->getFromHash($this->cashKey, $activityId);
        if ($temp)
        {
            Myredis::getRedisConn(4)->deleteFromHash($this->cashKey, $activityId);
        }

        $list = Db::name($this->_table)->where(array('invest_award_item_id' => $activityId))->field('activity_promotion_set_id')->select();
        foreach ($list as $v)
        {
            $arr[] = $v['activity_promotion_set_id'];
        }

        $where['pid']        = array('in', $arr);
        $AwardActivitiesInfo = new AwardActivitiesInfo();
        $codeList            = $AwardActivitiesInfo->getAwardActivitiesInfoWhereSelect($where, 'pid,activities_code');
        foreach ($codeList as $value)
        {
            $code[] = $value['activities_code'];
        }
        $wherePlat['plat'] = array('in', $code);
        $Plat              = new Plat();
        $userList          = $Plat->getPlatWhereSelect($wherePlat, 'iuser_uid');
        foreach ($userList as $Uvalue)
        {
            $iuser[] = $Uvalue['iuser_uid'];
        }
        if ($iuser)
        {
            Myredis::getRedisConn(4)->setToHash($this->cashKey, $activityId, $iuser);
        }
        if ($res)
        {
            return $iuser;
        }
    }

}
