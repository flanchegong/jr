<?php

namespace application\common\model\article;

use application\common\model\Base;

/**
 * @uses 文章
 * @author pandelin
 * @date 2017/11/14
 */
class LayerManage extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'LayerManage';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

    /**
     * @desc   字段映射
     * @var    string
     * @access protected
     */
    protected $_map = [
        'lm_id'         => 'id',
        'lm_starttime'    => 'starttime',//弹层启用时间
        'lm_endtime'      => 'endtime',//弹层结束时间
        'lm_images'        => 'images',//图片信息
        'lm_appear'        => 'appear',//弹层出现方式【0：登录前；1：登陆后】
        'lm_regtime'       => 'regtime',//注册时间是否不限制【1：不限制；0：限制】
        'lm_user_regtime'     => 'user_register_time_first',//注册时间date格式
        'lm_frequency'       => 'frequency',//出现频率【1：一天一次；2：一天两次】
        'lm_countdown'    => 'countdown',//是否设置弹层倒计时【0：不设置；1：设置】
        'lm_lifetime'       => 'lifetime',//弹层存在时间 默认3秒 
        'lm_status'    => 'status',//活动状态【1：启用；0：关闭】
        'lm_timing' => 'timing',//是否定时发送【0：关闭；1：开启】
        'lm_timestart'     => 'timestart',//定时启动最小时间
        'lm_remark'  => 'remark',//备注
        'lm_create_at'   => 'create_at'//活动创建时间
    ];

    /**
     * @uses 获取开始的弹层广告
     * @author pandelin
     * @return array Description
     */
    public function getArticleList()
    {
        $time=  time();
        $where['starttime'] = array('ELT', $time);
        $where['endtime']   = array('EGT', $time);
        $where['status']     = 1;
        $sqlArr = [
            'where' => $where,
            'order' => 'id desc',
            'limit' => 1
        ];
        $info=parent::getOneByWhere($sqlArr);
        return parent::parseFieldsMap($info?$info:[]);
    }

}
