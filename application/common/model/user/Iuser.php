<?php

/**
 * @Copyright (C), 2017, lingyq
 * @Name Iuser.php
 * @Author lingyq
 * @Version stable 1.0
 * @Date 2017-6-23
 * @Description 用户模型类
 */

namespace application\common\model\user;

use think\Db;
use application\common\model\Base;
use think\Cache;
use DateTime;

class Iuser extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'iuser';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'user_id';

    /**
     * 根据用户ID查询用户信息
     * @param int $userId 用户UID
     * @param string $fields 字段
     * @return boolean/int user_id
     * @author gong
     * @date 2017-6-23
     */
    public function getUserInfoByUserId($userId, $fields)
    {
        //查询条件
        $where['user_id'] = array('eq',$userId);
        //执行查询
        $result = Db::name("iuser")->field($fields)->where($where)->find();   
        return $result;
    }

    /**
     * @desc 函数：用户认证状态判断
     * @author liujian
     * @update 2017-6-21
     * @access public
     * @param int $userId
     * @return array
     */
    public function identification($userId)
    {
        $statusInfo = Db::name('iuser')->where('user_id', $userId)
                        ->field('email_status,vip_status,real_status,phone_status')->find();
        if ($statusInfo['vip_status'] != 1)
        {
            return '请进行vip认证！';
        }
        elseif ($statusInfo['real_status'] != 1)
        {
            return '请进行实名认证！';
        }
        elseif ($statusInfo['phone_status'] != 1)
        {
            return '请进行手机认证！';
        }
        return true;
    }


    /**
     * 通过用户名获取用户信息
     * @author lingyq
     * @param type $userName
     * @return type
     */
    public function getUserInfoByUserName($field, $userName)
    {
        $result = Db::name('iuser')->field($field)->where('username', 'eq', $userName)
                    ->whereOr('phone', 'eq', $userName)->find();
        return $result ? $result : [];
    }

    /**
     * 获取指定区域客服/随机的一般客服的userID
     * @author lingyq
     * @param type $where 查询条件
     * @param type $type 区域客服/随机的一般客服   3.随机一般客服   4.区域客服
     * @return int
     */
    public function getCustomServiceId($where = [], $type = 3)
    {
        //区域客服
        if ($type == 4)
        {
            $customerServiceId = Db::name('iuser')->field('user_id')->where($where)->value('user_id');
            if (!empty($customerServiceId))
            {
                return $customerServiceId;
            }
        }
        //随机客服
        $rWhere['type_id'] = [
            'eq',
            3
        ];
        $rWhere['user_id'] = [
            'gt',
            2274
        ];
        $customerServiceId = Db::name('iuser')->where($rWhere)->order("rand()")->value('user_id');
        return $customerServiceId;
    }

    /**
     * 更新用户登陆IP
     * @param type $userId
     * @author lingyq
     */
    public function updateUserLoginIp($userId, $ip)
    {
        $where['user_id'] = [
            'eq',
            $userId
        ];
        $info['lastip'] = $ip;
        $info['lasttime'] = time();
        Db::name('Iuser')->where($where)->update($info);
    }

    /**
     * @uses 获取用户表信息
     * @author jhl
     * @param int $uid
     * @param string $field
     */
    public static function getUserField($where, $fields = '*')
    {
        if (is_array($where))
        {
            $where['status'] = [
                'egt',
                0
            ];
        }
        else
        {
            $where .= " and status >= 0 ";
        }
        $data = Db::name('iuser')->field($fields)->where($where)->find();
        return $data;
    }




    private function CheckUserpass($userobj, $pdw)
    {
        $ps = false;
        if (!is_null($userobj))
        {
            if ($userobj['status'] == -1)
            {
                return false;
            }
            if (strlen($userobj['pdw']) > 0)
            {
                $md5passV1 = md5($pdw . config('web_config.MKEY'));
                $ps = $userobj['pdw'] ? $md5passV1 == $userobj['pdw'] : false;
            }
            else
            {
                $md5pass = md5($pdw); //密码使用md5加密
                $ps = $userobj['password'] ? $md5pass == $userobj['password'] : false;
            }
        }
        return $ps;
    }

    /**
     * @desc 函数：用户首次操作日志
     * @author ljh
     * @date 2016-5-5
     * @access public
     * @params $params  = array(
     *              'user_id' => '用户id''
     * 'type' => '日志类型： 1 改交易密码 2绑定邮箱 3绑银行卡 ''
     * 'source' => '来源'
     *               )
     * @return int
     */
    public static function addOptLog($params)
    {
        $condition['user_id'] = $params['user_id'];
        $condition['type'] = $params['type'];
        $row = Db::name('user_first_optlog')->where($condition)->find();
        $log['source'] = $params['source'];
        if (empty($row))
        {
            $log['user_id'] = $params['user_id'];
            $log['type'] = $params['type'];
            $log['add_time'] = time();
            $rlog = Db::name('user_first_optlog')->insert($log);
        }
        else
        {
            $log['update_time'] = time();
            $rlog = Db::name('user_first_optlog')->where($condition)->update($log);
        }
        return $rlog;
    }


    /**
     * 获取用户邀请码
     * @param int $userId
     * @author pandelin
     */
    public function getUseriInviteCode($userId)
    {
        $cacheKey = 'model_iuser_getUseriInfo_' . $userId;
        $invite_code = Cache::get($cacheKey);
        if ($invite_code)
        {
            return $invite_code;
        }
        $where['user_id'] = [
            'eq',
            $userId
        ];
        $invite_code = Db::name('iuser_invite')->where($where)->value('invite_code');
        Cache::set($cacheKey, $invite_code, 600);
        return $invite_code;
    }

    /**
     * 根据用户id获取用户信息
     * @param int $userId
     * @author pandelin
     */
    public function getUseriInfo($userId, $fields = '*')
    {
        $cacheKey = 'model_iuser_getUseriInfo_' . $userId . '_' . $fields;
        $info = Cache::get($cacheKey);
        if ($info)
        {
            return $info;
        }
        if ($fields == '*')
        {
            $fields = 'user_id,username,realname,phone';
        }
        $where['user_id'] = [
            'eq',
            $userId
        ];
        $info = Db::name('iuser')->where($where)->field($fields)->find();
        Cache::set($cacheKey, $info, 600);
        return $info;
    }

    /**
     * @desc 函数：根据规则获取不同时间段被邀请注册的用户.
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param int $stime 查询开始时间
     * @param int $etime 查询结束时间
     * @return array
     */
    public function getUsersByMonth($stime = 0, $etime = 0)
    {
        $dateLast = strtotime('2014-06-01 00:00:00'); //活动开始时间
        $where = [];
        $where['u.invite_userid'] = [
            '>',
            0
        ];
        $where['u.real_status'] = 1;
        $where['u.mycustom'] = [
            '<>',
            'u.invite_userid'
        ];
        $startTime = $stime == 0 || $stime < $dateLast ? $dateLast : $stime;
        $where['u.addtime'] = [
            '>=',
            $startTime
        ];
        if ($etime)
        {
            $where['u.addtime'] = [
                'between',
                [
                    $startTime,
                    $etime
                ]
            ];
        }
        $time = new DateTime(date('Y-m-d', strtotime('first day of this month')));
        $where['ua.addtime'] = [
            '>',
            $time->getTimestamp()
        ];
       return  Db::name($this->_table)->alias('u')
          ->field('u.invite_userid,group_concat(CAST(u.user_id as char)) userchar,sum(if(ua.collection,ua.collection,0)) sc')
          ->join('itd_iuser_assets ua', 'ua.user_id=u.user_id', 'left')->where($where)->group('u.invite_userid')
          ->select();

    }

    /**
     * 获取工薪贷账号
     * @param type $cardId     身份证号码
     * @author lingyq
     * @date  2017/8/7
     * @return type      
     */
    public function getSalaryUserAccount($cardId)
    {
        $where['card_id'] = array('eq',$cardId);
        $where['type_id'] = array('eq',5);
        $data = Db::name('iuser')->field('user_id')->where($where)->select();
        return empty($data) ? array() : $data; 
    }
}
