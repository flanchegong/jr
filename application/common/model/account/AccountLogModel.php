<?php

/**
 * @desc     资金流水
 * @package  application\common\model;
 * @since    2017-7-3
 * @final
 * Created by PhpStorm.
 * User: Gong
 * Date: 2017/7/3
 * Time: 17:10
 */
namespace application\common\model\account;

use think\Model;
use think\Db;
use think\Paginator;
use application\common\Myredis;

class AccountLogModel extends Model
{








    /**
     * 查询某个时间点到当前时间，是否存在撤标流水记录
     * @param type $userId
     * @param type $startTime
     */
    public function getCancelFinancingLog($userId, $time)
    {
        $tableName        = get_account_log_table($userId);
        $where['user_id'] = array('eq', $userId);
        $where['type']    = array('eq', 106);
        $where['addtime'] = array('egt', $time);
        $re               = Db::name($tableName)->field('id')->where($where)->order("id desc")->find();
        return empty($re) ? false : true;
    }

    /**
     * 通常用户(即非工薪贷用户)建立sql
     * @param type $userId
     * @param type $time
     * @param type $serviceType    0：通常情况下计算协议金额   1：定时任务   2.计算红包，秒可用金额时快速渠道
     * @param type $tatisticsTime  当serviceType类型为定时任务时，查询的截止时间戳
     * @author lingyq     
     */
    public static function buildSqlOnCommonUser($userId, $time, $serviceType = 0, $tatisticsTime = 0)
    {
        $tableName = get_account_log_table($userId);
        $limitId   = Db::name($tableName)->field("id")->where(array("addtime" => array("egt", $time), "user_id" => $userId))->order("id asc")->find();
        if ($serviceType == 1) {
            $maxId = Db::name($tableName)->field("MAX(id) as max_id")->where(array("addtime" => array("lt", $tatisticsTime), "user_id" => $userId))->find();
            //实名认证后的尚未有资金记录的新用户，进行投秒投红包计算时，0时前是没有资金记录的，可以直接返回
            //不返回则会存在查询到了$limitId，没有$maxId的情况，导致下面的组装sql出现语法错误
            if (empty($maxId['max_id'])) {
                return '';
            }
        }
        if ($limitId) {
            $sql = "
                SELECT log5.id,log5.num,log5.type,log5.total_change,log5.use_change,log5.nouse_change,log5.collection_change,log5.waitreplay_change,
                log5.principal,log5.addtime,log5.btype,log5.treasure_chest,log5.borrow_num from itd_remind remind
                LEFT JOIN itd_$tableName log5  on remind.numb=log5.type
                WHERE  log5.user_id=$userId and remind.cash_console=1  and log5.id>=" . $limitId["id"] . ($serviceType == 1 ? " and log5.id <= " . $maxId['max_id'] : '') . " ORDER BY log5.id ASC";
            return $sql;
        }
        return '';
    }

    /**
     * 工薪贷用户建立sql
     * @param type $userArr
     * @param type $time
     * @param type $serviceType     0：通常情况下计算协议金额   1：定时任务   2.计算红包，秒可用金额时快速渠道
     * @param type $tatisticsTime   当serviceType类型为定时任务时，查询的截止时间
     * @author lingyq      
     */
    public static function buildSqlOnSalaryUser($userArr, $time, $serviceType = 0, $tatisticsTime = 0)
    {
        $existId = 0;
        $sql     = "SELECT * from (";
        foreach ($userArr as $value) {
            $tableName = get_account_log_table($value['user_id']);
            $limitId   = Db::name($tableName)->field("id")->where(array("addtime" => array("egt", $time), "user_id" => $value['user_id']))->order("id asc")->find();
            if ($serviceType == 1) {
                $maxId = Db::name($tableName)->field("MAX(id) as max_id")->where(array("addtime" => array("lt", $tatisticsTime), "user_id" => $value['user_id']))->find();
                //实名认证后的尚未有资金记录的新用户，进行投秒投红包计算时，0时前是没有资金记录的，可以直接返回
                //不返回则会存在查询到了$limit_id，没有$max_id的情况，导致下面的组装sql出现语法错误
                if (empty($maxId['max_id'])) {
                    continue;
                }
            }
            if ($limitId) {
                $existId ++;
                $sql.=" 
                    SELECT log5.id,log5.num,log5.type,log5.total_change,log5.use_change,log5.nouse_change,log5.collection_change,log5.waitreplay_change,log5.principal,
                    log5.addtime,log5.btype,log5.treasure_chest,log5.borrow_num from itd_remind remind
                    LEFT JOIN itd_$tableName log5 on remind.numb=log5.type
                    WHERE  log5.user_id=" . $value['user_id'] . " and remind.cash_console=1  and log5.id>=" . $limitId["id"] . ($serviceType == 1 ? " and log5.id <= " . $maxId['max_id'] : '') . " 
                        Union All";
            }
        }
        $sql = substr($sql, 0, strlen($sql) - strlen('Union All'));
        $sql .=") ty ORDER BY id asc";
        if ($existId) {
            return $sql;
        }
        return '';
    }    
    /**
     * 获取用户资金流水记录
     * @param string  $sqlStr
     * @author lingyq
     */
    function getAccountLog($sqlStr){
        $data = Db::query($sqlStr);
        return empty($data) ? array() : $data;
    }
}
