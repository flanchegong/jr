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
namespace application\common\model\system;

use application\common\model\Base;

class MessageModel extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'message_3';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

    /**
     * @desc 接口:消息中心
     * @author liuj
     * @date 2017-7-17
     * @access public
     * @return data
     */
    public function getMessageList($uid, $keyWord = '',$page,$listRows)
    {
        $where['receive_user']   = $uid;
        $where['receive_status'] = 1;
        if ($keyWord != '')
        {
            $where['name'] = ['like', $keyWord . '%'];
        }
        $sqlArr = [
            'where'=>$where,
            'order'=>'addtime desc',
            'page'=>$page,
            'list_rows'=>$listRows,
        ];
        $data = $this->getList($sqlArr,true);
        if (!empty($data['data']))
        {
            foreach ($data['data'] as $key => $value)
            {
                $data['data'][$key]['addtime'] = date('Y-m-d H:i:s',$value['addtime']);
            }
        }
        return $data;
    }
    
    /**
     * @desc 接口:统计消息
     * @author liuj
     * @date 2017-7-17
     * @access public
     * @return data
     */
    public function countMessage($uid)
    {   
        $sql = "SELECT COUNT(1) AS num,`status` FROM itd_message_3 WHERE receive_user='{$uid}' GROUP By `status`";
        return \think\Db::query($sql);
    }

}
