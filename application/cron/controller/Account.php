<?php

namespace application\cron\controller;

use think\Controller;
use think\Db;
use application\common\model\account\AccountModel;
use application\common\Myredis;
use think\Cache;

/**
 * @desc        标定时任务类
 * @package  application\cron\controller;
 * @since    2017-3-24
 * @final
 * Created by PhpStorm.
 * User: Gong
 * Date: 2017/6/19
 * Time: 17:10
 */
class Account extends Controller
{

    /**
     * @desc 函数：账户流水脚本
     * @author liuj
     * @update 2017-9-11
     * @access public
     * @return void
     */
    public function consumption()
    {
        $queque = input('param.id');
        $list = Myredis::getRedisConn(8)->getList('list_upchange'. $queque, 0, 50);
        if (empty($list))
        {
            exit("没有数据");
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__ . $queque);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__ . $queque, 1, 60);
        $AccountModel = new AccountModel();
        $i = 0;
        foreach ($list as $v)
        {
            $val =  Myredis::getRedisConn(8)->shiftFromList('list_upchange'. $queque);
            if (!isset($val['user_id'], $val['num']))
            {
                continue;
            }
            $accinfo = $AccountModel->getAccountInfoByUserId($val['user_id']);
            if (!$accinfo)
            {
                continue;
            }
            //账户变化值变更
            if ($val['total_change'] != 0)
            {
                $accinfo['total'] = $accinfo['total'] + $val['total_change'];
            }
            if ($val['use_change'] != 0)
            {
                $accinfo['use_money'] = $accinfo['use_money'] + $val['use_change'];
            }
            if ($val['nouse_change'] != 0)
            {
                $accinfo['nouse_money'] = $accinfo['nouse_money'] + $val['nouse_change'];
            }
            if ($val['collection_change'] != 0)
            {
                $accinfo['collection'] = $accinfo['collection'] + $val['collection_change'];
            }
            if ($val['waitreplay_change'] != 0)
            {
                $accinfo['borrow_money'] = $accinfo['borrow_money'] + $val['waitreplay_change'];
            }
            //修改账户值
            $AccountModel->batchSaveAccount($val['user_id'], $accinfo);
            //添加流水
            if ($accinfo)
            {
                $val['total'] = $accinfo['total'];
                $val['use_money'] = $accinfo['use_money'];
                $val['nouse_money'] = $accinfo['nouse_money'];
                $val['collection'] = $accinfo['collection'];
                $val['waitreplay'] = $accinfo['borrow_money'];
                $tableName = get_account_log_table($val['user_id']);
                $logId = Db::name($tableName)->insert($val);
                //备份账户数据
                if ($logId)
                {
                    $condition['user_id'] = $val['user_id'];
                    $ret = Db::name('iaccount')->where($condition)->update($accinfo);
                    if (!$ret)
                    {
                        Myredis::getRedisConn(8)->appendToList('update_iaccount_fail', $val);
                    }
                    $i++;
                }
                //投资:104  申请提现：502   提现申请冻结手续费：551   股权投资：557 
                //抢现金券:20052
                //成功的解锁代码转移到资金记录写成功后，才解锁，防止解锁后，资金记录尚未进行写入，同一笔资金多次操作的情况出现
                $typeArr = array(104,502,551,557,20052);
                if(in_array($val['type'],$typeArr)){
                    Cache::rm('LOCKtender' . $val['user_id']);
                }
                unset($val);
                unset($accinfo);
            }
        }
        echo $i;
        Myredis::getRedisConn(1)->delete(__FUNCTION__ . $queque);
    }

}
