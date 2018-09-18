<?php

/**
 * @uses 投资融资-理财金项目表
 * @author jhl
 * @data 2017/07/04
 */
namespace application\common\model\borrow;
use application\common\model\Base;
use think\Db;
class experienceBorrow extends Base
{
    /**
     * @uses 首页体验标
     * @author jhl
     */
    public static function indexExperienceBorrow()
    {
        $financialGold = Db::name('experience_borrow')->field('id,title,project_day,year_apr,DATE_FORMAT(FROM_UNIXTIME(addtime),"%Y-%m-%d") as addtime,DATE_FORMAT(FROM_UNIXTIME(repay_time),"%Y-%m-%d") as repay_time')
            ->where('project_status=1')
            ->order('project_time desc')
            ->find();
        return $financialGold;
    }
    
}