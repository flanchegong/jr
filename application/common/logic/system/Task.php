<?php

/**
 * @Copyright (C), 2016, Liuj.
 * @Name 任务类
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
namespace application\common\logic\system;

use think\Model;
use think\Db;
use Sms\Monternet\Monternet;
use application\common\Myredis;

class Task extends Model
{

    /**
     * 队列长度
     * @var obj
     * @access protected
     */
    protected $_length = 10;

    //获取要发送的队列
    private function get_queen($businesstype = "")
    {
        $from              = strtotime('today');
        $to                = strtotime('tomorrow');
        $where['dateline'] = array("between", array($from, $to));
        $where['status']   = 0;
        if ($businesstype)
        {
            $where['businesstype'] = $businesstype;
        }
        $array = Db::name("queen")->where($where)->limit($this->_length)->select();
        $data  = array();
        if (is_array($array))
        {
            foreach ($array as $key => $value)
            {
                if (!isset($value['reID']))
                {
                    continue;
                }
                //发标人撤标 -过期系统撤标
                if ($value["businesstype"] == "cheborrow" || $value["businesstype"] == "borrow_end")
                {
                    $sql = "SELECT a.user_id,a.username,a.`name`,u.phone,borrow_type,CONCAT('尊敬的',hidden_unm(a.username),',您的融资\"',a.name,'\"已经撤消融资。请知悉!') as sms, 
                            CONCAT('您的融资',a.name,'已撤消融资') as message_title,
                            CONCAT('尊敬的',a.username,'，您的融资\"',a.name,'\"已经撤消融资。请知悉！<br>愿健康与快乐天天伴随您!') as message_content,'融资失败' as app_title
                            from itd_iborrow  a  left join itd_iuser u on u.user_id=a.user_id  where  borrow_num='{$value["reID"]}'";
                }
                //投资失败
                if ($value["businesstype"] == "invest_false")
                {
                    $sql = " SELECT a.username,a.user_id,sum(a.money) money,b.`name` as borrow_title,b.username as publisher,u.phone,borrow_type ,
                            CONCAT('尊敬的:',hidden_unm(a.username),',您投资 ”',hidden_unm(b.username),'“ 的项目 ”',b.`name`,'“ 已撤消融资，总额￥',money,'已经返回到您的账户上') as sms,
                            CONCAT('您投资',b.username,'的项目“ ',b.`name`,'“ 已撤消融资。') as message_title,
                            CONCAT('尊敬的:',a.username,',您投资 ',b.username,' 的项目 ',b.name,' 已撤消融资，金额:￥%',money,'<br>愿健康与快乐天天伴随您!') as message_content,'投资失败' as app_title
                            from itd_iborrow_tender a    
                            left join itd_iuser u on a.user_id=u.user_id  
                            left join itd_iborrow b on a.borrow_num=b.borrow_num  where a.borrow_num='{$value["reID"]}'  GROUP BY a.user_id";
                }
                //收到还款提醒 
                if ($value["businesstype"] == "invest_repayment")
                {
                    $repayment = Db::name("iborrow_repayment")->field("borrow_num")->where(array("repayment_num" => $value['reID']))->find();

                    $sql = "SELECT allinterest*(1-interestManagementFee)+allcap as ttotal,phone,borrow_type,user_id, 
                            CONCAT('尊敬的:',hidden_unm(username),'，您已收到项目《',a.name,'》的投资回款，金额为￥',sub_str(allinterest*(1-interestManagementFee)+allcap),'。感谢您的支持！') sms,
                            CONCAT('您已收到项目《',a.name,'》的投资回款，金额为￥',sub_str(allinterest*(1-interestManagementFee)+allcap),'。') message_title,
                            CONCAT( '尊敬的:',username,'，您已收到项目《',a.name,'》的投资回款，金额为￥',sub_str(allinterest*(1-interestManagementFee)+allcap),'。详细情况如下：<br>项目的标题：',name,'<br>实际到期还款日期：',redate,'<br>当前还款为：第',`order`+1,'期/共',time_limit,'期<br>还款本金：￥',sub_str(allcap),'<br>还款利息：￥',sub_str(allinterest),'<br>愿健康与快乐天天伴随您！') as message_content, 
                             '收到回款' as app_title from (
                            SELECT bu.user_id,bu.vip_status, bu.username,  bu.phone,ab.username as tusername,ab.borrow_type,ab.time_limit,ar.`order`, ab.`name`,
                                                                       SUM(ac.capital) as allcap,SUM(ac.interest) as allinterest  , 
                            FROM_UNIXTIME( ac.repay_time ) as redate ,
                            SUM(ac.repay_yesaccount ) as ttotal,
                            lv_value,if(ab.borrow_type=5,0,if(vip_status=0,0.18,interestManagementFee)) interestManagementFee
                            from itd_iborrow_repayment ar
                            LEFT JOIN (SELECT capital,interest,repay_time,repay_yesaccount,borrow_num,`order`,sr_status,user_id from itd_iborrow_collection where borrow_num='{$repayment['borrow_num']}') ac on  ac.borrow_num =ar.borrow_num and ac.`order`=ar.`order` and sr_status!=2
                            LEFT JOIN itd_iborrow ab  on ab.borrow_num= ar.borrow_num
                            LEFT join itd_iuser bu on bu.user_id=ac.user_id 
                            LEFT join itd_credit credit on credit.user_id=ac.user_id
                            left join itd_credit_rank  cr on lv_value BETWEEN point1 and point2
                            where  ar.repayment_num='{$value['reID']}' GROUP BY ac.user_id) a";
                }
                //投资成功 
                if ($value["businesstype"] == "invest_success")
                {
                    $sql = "SELECT ib.id, a.user_id, b.username, b.email, b.phone,b1.username as busername, ib.`name`,ib.borrow_type,SUM(a.account) as total ,
                            CONCAT('尊敬的',hidden_unm(a.username),'，您投资 “',hidden_unm(b1.username),'” 的项目 ”',ib.name,'“ 已成功，总额￥',SUM(a.account),'。') sms,
                            CONCAT( '您投资',b1.username,'的项目 ',ib.name,' 已成功！') message_title,
                            CONCAT('尊敬的',b.username,',您投资 ',b1.username,' 的项目 ',ib.name,' 已成功，总金额:￥',SUM(a.account),'，详细情况请至 收款中的记录 中查看。') message_content,'投资成功' as app_title
                            from itd_iborrow_tender a LEFT JOIN itd_iborrow ib on ib.borrow_num =a.borrow_num
                            LEFT join itd_iuser b on a.user_id=b.user_id
                            LEFT join itd_iuser b1 on ib.user_id=b1.user_id
                            where  a.borrow_num='{$value["reID"]}'
                            GROUP BY a.user_id";
                }
                //VIP过期
                if ($value["businesstype"] == "vip_overdue")
                {
                    $sql = "select  vip_status,username,phone,user_id,CONCAT('尊敬的',hidden_unm(username),',您的VIP服务将于',FROM_UNIXTIME(vip_time,'%Y年%m月%d日%H点'),'到期,到期后将不再享受各种优惠条件，请尽快续费。感谢您的支持！') sms,
                            CONCAT('您的VIP期限服务将于',FROM_UNIXTIME(vip_time,'%Y年%m月%d日%H点'),'到期,为了不影响你其他的操作，请及时VIP续费。') message_title,
                            CONCAT('您的VIP期限服务将于',FROM_UNIXTIME(vip_time,'%Y年%m月%d日%H点'),'到期,为了不影响你其他的操作，请及时VIP续费。') message_content,
                            '你的VIP期限即将过期' as app_title from itd_iuser where user_id='{$value['reID']}'";
                }

                $list         = Db::query($sql);
                $businesstype = array("invest_repayment" => 47, "invest_success" => 68, "invest_false" => 43, "cheborrow" => 80, "vip_overdue" => 87, "borrow_end" => 15);

                $sys_config = Myredis::getRedisConn(2)->getHash("remind");
                if ($list && $sys_config)
                {
                    foreach ($list as $k2 => $v2)
                    {
                        //消息类型
                        switch ($value["businesstype"])
                        {
                            case "invest_success": $list[$k2]["mtype"] = 11;
                                break;
                            case "invest_false": $list[$k2]["mtype"] = 12;
                                break;
                            case "borrow_end": $list[$k2]["mtype"] = 22;
                                break;
                            case "invest_repayment": $list[$k2]["mtype"] = 13;
                                break;
                            case "vip_overdue": $list[$k2]["mtype"] = 52;
                                break;
                            default:$list[$k2]["mtype"] = 5;
                        }
                        //获取每个用户配置
                        if ($value["businesstype"] == "vip_overdue")
                        {
                            $list[$k2]["uconfig"]['cp'] = 1;
                        }
                        else
                        {

                            $user_config          = Myredis::getRedisConn(2)->getFromHash("remind_user", $v2["user_id"]);
                            $list[$k2]["uconfig"] = $this->get_uconfig($user_config[$businesstype[$value['businesstype']]], $sys_config[$value['businesstype']]);
                        }
                        //app消息配置
                        $user_app_config           = Myredis::getRedisConn(2)->getFromHash("remind_user_app", 123675);
                        $list[$k2]["app_msg"]      = $user_app_config[$businesstype[$value['businesstype']]]['app_msg'];
                        $list[$k2]["businesstype"] = $value["businesstype"];
                    }
                }

                $data[$value["id"]]["list"] = $list;
            }
        }
        return $data;
    }

    /**
     * 根据用户配置,确定发与不发
     * @param array $user_config 用户配置
     * @param array $sys_config 各种业务系统配置
     * @return  array
     * 
     * * */
    private function get_uconfig($user_config, $sys_config)
    {
        if ($sys_config['message'] == 0 || $sys_config['message'] == 1)
        {
            $back['cm'] = isset($user_config['cm']) && $user_config['cm'] != '' ? $user_config['cm'] : $sys_config['message'];
        }
        if ($sys_config['message'] == 3)
        {
            $back['cm'] = 1;
        }

        if ($sys_config['phone'] == 0 || $sys_config['phone'] == 1)
        {
            $back['cp'] = isset($user_config['cp']) && $user_config['cp'] != "" ? $user_config['cp'] : $sys_config['phone'];
        }
        if ($sys_config['phone'] == 3)
        {
            $back['cp'] = 1;
        }


        return $back;
    }

    /**
     * 执行批量任务
     * 
     * @param string $businesstype 业务类型
     * @return void
     */
    function job($businesstype = "")
    {
        $data = $this->get_queen($businesstype);
        $smsg = $msg  = array();
        foreach ($data as $queenID => $v)
        {
            if (!is_array($v["list"]))
            {
                continue;
            }
            foreach ($v["list"] as $key => $value)
            {
                //$value['phone']
                if (isset($value['uconfig']['cp']) && $value['uconfig']['cp'] == 1 && $value["phone"] && $value['sms'])
                {
                    if ((isset($value["borrow_type"]) && $value["borrow_type"] != 5) || $value['mtype'] == 52)
                    {
                        $smsg[] = array("phone" => $value["phone"], "content" => $value['sms']);
                    }
                }
                //发送站内信
                if ($value['message_title'] && $value['message_content'] && isset($value['uconfig']['cm']) && $value['uconfig']['cm'] == 1)
                {
                    $msg[] = array(
                        "sent_user"      => 1,
                        'receive_user'   => $value['user_id'],
                        "name"           => $value["message_title"],
                        'receive_status' => 1,
                        'status'         => 1,
                        'content'        => $value['message_content'],
                        'addtime'        => time(),
                        'addip'          => input('server.REMOTE_ADDR'),
                        'type'           => $value['businesstype']);
                    //更新对应站内信条数 
                    cache("message_{$value['user_id']}", cache("message_{$value['user_id']}") + 1);
                }
                //app个推消息加入队列
                if ($value['message_title'] && $value['message_content'] && isset($value['app_msg']) && $value['app_msg'] == 1)
                {
                    Myredis::getRedisConn(2)->appendToList("getui_msg", array("uid" => $value['user_id'], "mtype" => $value['mtype'], "title" => $value["app_title"], "content" => $value['message_content']));
                }
                if (isset($value["mtype"]) && $value["mtype"] == 52)
                {
                    //vip过期提醒保存数据库
                    $vipmsg = [
                        'receive_user' => $value['user_id'],
                        'name'         => $value["app_title"],
                        'content'      => $value['message_content'],
                        'type'         => 5,
                        'mtype'        => 52,
                        'addtime'      => time(),
                        'addip'        => input('server.REMOTE_ADDR')
                    ];
                    Db::name('message_app')->insert($vipmsg);
                }
                elseif (isset($value["mtype"]) && $value["mtype"] == 22)
                {
                    //系统自动撤标
                    $cbmsg = [
                        'receive_user' => $value['user_id'],
                        'name'         => $value["app_title"],
                        'content'      => $value['sms'],
                        'type'         => 2,
                        'mtype'        => 22,
                        'addtime'      => time(),
                        'addip'        => input('server.REMOTE_ADDR')
                    ];
                    Db::name('message_app')->insert($cbmsg);
                }
            }
            Db::name("queen")->where(array("id" => $queenID))->update(array("status" => 1));
        }
        if ($smsg)
        {
            Db::name("queen_sms")->insertAll($smsg);
        }
        if ($msg)
        {
            Db::name("message_3")->insertAll($msg);
        }
    }

    /**
     * 发送短信
     * * */
    public function send($rows = 300)
    {
        $sms_channel = $this->get_channel();
        //梦网 一般是300-500条每秒，易美 1分钟大概1200条,玄武 20000/秒
        $row_set     = array("xuanwu" => 10000, "monternet" => 18000, "emay" => 1000);
        $rows        = $row_set[strtolower($sms_channel['value'])];
        $data        = Db::name("queen_sms")->limit($rows)->select();
        if (is_array($data))
        {
            $multixmt = array();
            $crc32    = array();
            foreach ($data as $value)
            {
                Db::name("queen_sms")->where(array("id" => $value['id']))->delete();
                $crc32_value = sprintf("%u", crc32("{$value['phone']}{$value['content']}"));
                if (in_array($crc32_value, $crc32))
                {
                    continue;
                }
                $multixmt['phone'][]   = $value['phone'];
                $multixmt['content'][] = $value['content'];
            }
        }
        $this->send_sms($multixmt, $sms_channel);
    }

    /**
     * 保存批量处理站内信与短信的业务
     * @param $btype 支持的业务类型,可以值： 109(invest_success),108( invest_repayment), 106(invest_false),201(vip_overdue) 115(cheborrow)
     * @param string $reID 标编码或还款编码
     * @return void
     * 
     */
    public function AddExeMone($btype, $reID)
    {
        $bussiness_type = array(109 => "invest_success", 108 => "invest_repayment", 106 => "invest_false", 201 => "vip_overdue", 115 => 'cheborrow', 110 => 'borrow_end');
        if (array_key_exists($btype, $bussiness_type) && $reID)
        {
            $add['businesstype'] = $bussiness_type[$btype];
            $add['reID']         = $reID;
            $add['dateline']     = time();
            $add['fromUID']      = 1;
            $add['toUID']        = 1;
            $add['toSMS']        = '1';
            $add['toEmail']      = '1';
            $add['toMSG']        = '1';
            $add['status']       = '0'; //测试条目 
            return Db::name('queen')->insert($add);
        }
    }

    /**
     * 获取发送通道
     * @param null
     * 
     * * */
    private function get_channel()
    {
        $sms_channel = cache("sms_channel");
        if (!$sms_channel)
        {
            $sms_channel = Db::name("variable")->where(array("key" => 'SYS_MANUAL_CHANNEL'))->find();
            cache('sms_channel', $sms_channel, 86400 * 30); //30天
        }
        return $sms_channel;
    }

    /**
     * @desc 函数：为满标成功涉及的用户 发送消息
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return bool
     */
    public function sendFullBorrowSuccess($uid, $bname, $username, $Account, $phone)
    {
        $userconfig = self::get_user_config($uid, 'borrow_success');
        $appconfig  = self::get_appconfig($uid, 36); //36：融资入账业务id
        $tplConfig  = config('system');
        //站内信
        if ($userconfig['cm'] == 1)
        {
            $content = $tplConfig['message_tpl']['borrow_success'];
            self::Sendmsg(1, $uid, sprintf($content["title"], $bname), sprintf($content["body"], $username, $bname, $Account));
        }
        //	发送短信 
        if ($userconfig['cp'] == 1 && isset($phone) && $phone != '')
        {
            $content = $tplConfig['sms_tpl']['borrow_success'];
            Db::name("queen_sms")->insert(array("phone" => $phone, "content" => sprintf($content, $bname)));
        }
        //发送app推送消息
        if ($appconfig['app_msg'] == 1)
        {
            $content = $tplConfig['message_tpl']['borrow_success'];
            Myredis::getRedisConn(2)->appendToList("getui_msg", array("uid" => $uid, "mtype" => 21, "title" => '融资成功', "content" => sprintf($content["body"], $username, $bname, $Account)));
        }
        return true;
    }
    
     /**
     * @desc 函数：获取app配置
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function get_appconfig($uid, $id)
    {
        $back = Db::name('remind_user_app')->field('app_msg')->where(array('user_id' => $uid, 'remind_id' => $id))->find();
        if (!is_array($back))
        {
            $back['app_msg'] = 0;
        }
        return $back;
    }
    
     /**
     * @desc 函数：获取用户配置
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function get_user_config($uid, $nid)
    {
        $rs = Db::name('remind_type')
                ->alias('t1')
                ->field("t2.id,t2.nid,t2.`name`,t2.message,t2.email,t3.message AS cm,t3.email AS ce,t3.phone AS cp,t2.phone,t1.`name` AS styleName")
                ->join("itd_remind t2", " t1.id = t2.styleId", "left")
                ->join("itd_remind_user t3", " t2.id = t3.remind_id AND t3.user_id ={$uid}", "left")
                ->where(array("t2.status" => 0, "t2.nid" => $nid))
                ->find();
        if ($rs)
        {
            if ($rs['message'] == 0 || $rs['message'] == 1)
            {
                $back['cm'] = $rs['cm'] != '' ? $rs['cm'] : $rs['message'];
            }
            if ($rs['message'] == 3)
            {
                $back['cm'] = 1;
            }
            if ($rs['phone'] == 0 || $rs['phone'] == 1)
            {
                $back['cp'] = $rs['cp'] != "" ? $rs['cp'] : $rs['phone'];
            }
            if ($rs['phone'] == 3)
            {
                $back['cp'] = 1;
            }
        }
        else
        {
            $back['uid'] = $uid;
            $back['nid'] = $nid;
            $back['cm']  = 0;
            $back['ce']  = 0;
            $back['cp']  = 0;
        }
        return $back;
    }

    /**
     * 发送内部短信
     * living 20130411
     * @param  int $send_uid 发送者ID 
     * @param  int $recv_uid 接收者ID 
     * @param  string $title 标题 
     * @param  string $content 内容 
     * @param  string $ip
     * @return void
     */
    public  function Sendmsg($send_uid, $recv_uid, $title, $content, $ip = "")
    {
        $data['name']           = $title;
        $data['content']        = $content;
        $data['sent_user']      = $send_uid;
        $data['type']           = 'web_info';
        $data['status']         = '1';
        $data['addtime']        = time();
        $data['receive_user']   = $recv_uid;
        $data['receive_status'] = 1;
        $data['addip']          = $ip ? $ip : get_client_ip();
        cache("message_$send_uid", null);
        Db::name('message_3')->insert($data);
        unset($data);
       
    }

    /**
     * 发送短信
     * 
     * 除了易美 ，其他通道均支持组发
     * * */
    private function send_sms($data, $sms_channel)
    {
        if ($sms_channel['value'] == "monternet" && !empty($data))
        {
            $client = new Monternet();
            $result = $client->send(array("phone" => $data['phone'], "content" => $data['content']));
        }
        return true;
    }

}
