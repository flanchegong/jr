<?php

namespace application\common\model\credit;

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
use think\Db;
use application\common\model\Base;

class CreditLog  extends Base
{
    /**
     * @desc 函数：添加积分日志
     * @author liujian
     * @date 2017-4-7
     * @access public
     * @return void
     */
    public function addLog($userid, $type, $logOp, $credit, $Exp, $opUserID)
    {
        $conlog['user_id'] = $userid;
        $conlog['type_id'] = $type;
        $conlog['op']      = $logOp;
        $conlog['value']   = $credit;
        $conlog['remark']  = $Exp;
        $conlog['op_user'] = $opUserID;
        $conlog['addtime'] = time();
        $conlog['addip']   = get_client_ip();
       return Db::name('credit_log')->insertGetId($conlog);   
    }

    /**
     * 获取用户某种类型的积分总次数
     * @param type $userId
     * @param type $type
     * @auther lingyq
     * @return type
     */
    public function getUserLogCountByType($userId,$type){
        $where['user_id'] = $userId;
        $where['type_id'] = $type;
        $count = Db::name('credit_log')->where($where)->count();
        return $count ? $count : 0;
    }
}
