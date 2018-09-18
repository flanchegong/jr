<?php

/**
 * @Copyright (C), 2016, Liuj.
 * @Name 标相关定时任务
 * @Author Liuj
 * @Version stable 1.0
 * @Date: 2017-3-24
 * @Description
 * 1. Example
 * @Function List
 * 1.
 * @History
 * Liuj $date     stable 1.0 第一次建
 */

namespace application\cron\controller;

use application\common\logic\account\AccountLogic;
use application\common\model\activity\TreasureChest;
use application\common\model\borrow\AutoFinancingRule;
use application\common\model\borrow\AutoInvestAccountDetail;
use application\common\model\borrow\AutoInvestRedpacket;
use application\common\model\borrow\Iborrow;
use application\common\model\system\QueenSms;
use application\common\model\system\Variable;
use application\home\controller\Treasure;
use think\Controller;
use application\cron\logic\BorrowModel;
use application\common\logic\activity\Active;
use application\common\Myredis;
use application\common\service\borrow\BorrowServ;
use think\Db;
use think\helper\Time;

/**
 * @desc        标定时任务类
 * @package  application\cron\controller;
 * @since    2017-3-24
 * @final
 */
class Borrow extends Controller
{


    /**
     * @desc 函数：初始化
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function _initialize()
    {
        if (!in_array(get_client_ip(),config('ip_list_config')))
        {
            exit('非法操作');
        }
    }


    /**
     * @desc 函数：满标
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function fullBorrow()
    {
        $list = Myredis::getRedisConn()->getList('full_borrow_queue_list', 0, 5);
        if (empty($list))
        {
            exit('没有数据');
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $borrow = new BorrowModel();
        foreach ($list as $k => $value)
        {
            if (!Myredis::getRedisConn()->existsInHash('full_borrow_queue_hash', $value['borrow_num']))
            {
                echo "hash不存在:{$value['borrow_num']}\n";
                Myredis::getRedisConn()->shiftFromList('full_borrow_queue_list');
                echo "hash不存在。移出队列\n";
                continue;
            }
            $result = $borrow->fullBorrowHandle($value);
            if (true === $result)
            {
                echo 'ok';
            }
            else
            {
                echo 'fail';
                $msg = is_array($result) ? $result['msg'] : $result;
                $error[] = $k . ":用户" . $value['user_id'] . "标编码:" . $value['borrow_num'] . "，原因:" . $msg . "<br/>" . PHP_EOL;
            }
            $re = Myredis::getRedisConn()->shiftFromList('full_borrow_queue_list');
            if (is_null($re) || !$re)
            {
                $re = Myredis::getRedisConn()->deleteFromList('full_borrow_queue_list', $value);
            }
            $result2 = Myredis::getRedisConn()->deleteFromHash('full_borrow_queue_hash', $value['borrow_num']);
            if (is_null($re) || !$re)
            {
                $error[] = "标编码:" . $value['borrow_num'] . "出队列失败<br/>" . PHP_EOL;
                continue;
            }
            if (is_null($result2))
            {
                $error[] = "标编码:" . $value['borrow_num'] . "删除hash失败<br/>" . PHP_EOL;
                continue;
            }
            Myredis::getRedisConn()->delete('most_account_' . $value['borrow_num']);
            Myredis::getRedisConn(1)->delete($value['borrow_num']);
        }
        if (!empty($error))
        {
            $this->_sendEmail(__FUNCTION__, implode(PHP_EOL, $error));
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * 函数：投资返利发放奖励
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function releaseReward()
    {
        $list = Myredis::getRedisConn(4)->getList('fullBorrow_send_money', 0, 49);
        if (empty($list))
        {
            exit('没有数据');
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
//        $active = new Active();
        $InviteAward = new \application\common\logic\InviteAward\InviteAward();
        foreach ($list as $k => $v)
        {
//            $ret = $active->rewardMoery($v);
            $ret = $InviteAward->rewardMoery($v);
            Myredis::getRedisConn(7)->setToHash('reward_send_log', $v['borrow_num'], $ret);
            Myredis::getRedisConn(4)->shiftFromList('fullBorrow_send_money'); //出队列删除
        }
        echo '执行完成';
        Myredis::getRedisConn(1)->delete(__FUNCTION__);

    }

    /**
     * 函数：满标抽奖资格
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function sendReward()
    {
        $list = Myredis::getRedisConn(4)->getList('fullBorrow_send_award', 0, 49);
        if (empty($list))
        {
            exit('没有数据');
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__, 1, 60);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $active = new Active();
        foreach ($list as $k => $v)
        {
            $active->sendAward($v);
            Myredis::getRedisConn(4)->shiftFromList('fullBorrow_send_award'); //出队列删除
        }

        echo '执行完成';
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * 函数：发放积分
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function sendCredit()
    {
        $list = Myredis::getRedisConn(4)->getList('fullBorrow_send_credit', 0, 49);
        if (empty($list))
        {
            exit('没有数据');
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $active = new Active();
        foreach ($list as $k => $v)
        {
            $active->sendCredit($v);
            Myredis::getRedisConn(4)->appendToList('fcccc', $v);
            Myredis::getRedisConn(4)->shiftFromList('fullBorrow_send_credit'); //出队列删除
        }
        echo '执行完成';
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * 函数：投资返现捡漏
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function leakReleaseReward()
    {
        $list = Myredis::getRedisConn(4)->getList('rewardMoery', 0, 49);
        if (empty($list))
        {
            exit("没有数据");
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $active = new Active();
        foreach ($list as $k => $v)
        {
            Myredis::getRedisConn(4)->shiftFromList('rewardMoery'); //出队列删除
            $active->sentReturnMoney($v['borrow_num'], $v['user_id']);
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
        echo 'ok';
    }

    /**
     * 函数：投资抽奖资格捡漏
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function leakSendReward()
    {
        $list = Myredis::getRedisConn(4)->getList('sendAward', 0, 49);
        if (empty($list))
        {
            exit('没有数据');
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $active = new Active();
        foreach ($list as $k => $v)
        {
            Myredis::getRedisConn(4)->shiftFromList('sendAward'); //出队列删除
            $active->sendAward($v, $v['user_id']);
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
        echo 'ok';
    }

    /**
     * 函数：发放积分捡漏
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return void
     */
    public function leakSendCredit()
    {
        $list = Myredis::getRedisConn(4)->getList('sendCredit', 0, 49);
        if (empty($list))
        {
            exit('没有数据');
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $active = new Active();
        foreach ($list as $k => $v)
        {
            Myredis::getRedisConn(4)->shiftFromList('sendCredit'); //出队列删除
            $active->sendCredit($v, $v['user_id']);
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
        echo 'ok';
    }

    /**
     * 函数：生产队列进消费队列
     * @author liujian
     * @date 2017-5-4
     * @access public
     * @return void
     */
    public function productQueToConsumerQue()
    {
        $list = Myredis::getRedisConn()->getList("list_setInvest", 0, 50);
        if (empty($list))
        {
            exit("没有数据");
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        foreach ($list as $key => $val)
        {
            $re = Myredis::getRedisConn()->shiftFromList("list_setInvest");
            if (is_null($re) || !$re)
            {
                $re = Myredis::getRedisConn()->deleteFromList("list_setInvest", $val);
            }
            if (is_null($re) || !$re)
            {
                Myredis::getRedisConn(7)->setToHash(__FUNCTION__ . $val['bnum'], $val['tnum'], $re);
                Myredis::getRedisConn(7)->expire(__FUNCTION__ . $val['bnum'], 604800);
                continue;
            }
            $tmpborrowInfo = $val['binfo'];
            $tmpborrowInfo['tnum'] = $val['tnum'];
            $tmpborrowInfo['tuserid'] = $val['userId'];
            $tmpborrowInfo['tusername'] = $val['tusername'];
            $tmpborrowInfo['pTaccount'] = $val['money'];
            $tmpborrowInfo['type'] = $val['client'] ? $val['client'] : 2;
            $tmpborrowInfo['cashCId'] = $val['cashCId'];
            $tmpborrowInfo['client_ip'] = isset($val['client_ip']) ? $val['client_ip'] : get_client_ip();
            $result = Myredis::getRedisConn()->appendToList('list_tender_queue_miao', $tmpborrowInfo);
            if ($result)
            {
                Myredis::getRedisConn(1)->increment($val['bnum']);
                echo $key + 1 . PHP_EOL;
            }
            else
            {
                cache('LOCKtender' . $val['userId'], null);
                echo 'false';
                $info = array(
                    "status" => '120',
                    "info"   => '进入消费队列失败'
                );
                $datalog['user_id'] = $val['tuserid'];
                $datalog['status'] = 2;
                $datalog['tnum'] = $val['tnum'];
                $datalog['cashCId'] = $val['cashCId'];
                $datalog['type'] = 1;
                $datalog['bnum'] = $val['bnum'];
                $datalog['money'] = $val['money'];
                $datalog['client'] = $$val['client'] ? $val['client'] : 2;
                $datalog['result'] = json_encode($info);
                $datalog['addtime'] = date('Y-m-d H:i:s');
                $datalog['addip'] = $val['client_ip'];
                Myredis::getRedisConn()->setToHash("memcachekey_log", $val['tnum'], $datalog);
                Myredis::getRedisConn()->expire("memcachekey_log", 604800);
            }

            $bbegin = cache('begin_' . $val['bnum']);
            $bbegin -= 1;
            cache('begin_' . $val['bnum'], $bbegin, 180);
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * 函数：消费队列出队
     * @author liujian
     * @date 2017-5-4
     * @access public
     * @return void
     */
    public function handleConsumerQue()
    {
        $list = Myredis::getRedisConn()->getList("list_tender_queue_miao", 0, 50);
        if (empty($list))
        {
            exit("无数据");
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $borrow = new BorrowModel();
        foreach ($list as $value)
        {
            if (!isset($value['borrow_num']))
            {
                Myredis::getRedisConn()->shiftFromList("list_tender_queue_miao");
                continue;
            }
            if (Myredis::getRedisConn()
                       ->existsInHash("tender_queue_miao_success_" . $value['borrow_num'], $value['tnum']))
            {
                echo "标编码：" . $value['borrow_num'] . ",投标编码：" . $value['tnum'] . '已处理过' . PHP_EOL;
                $re = Myredis::getRedisConn()->shiftFromList("list_tender_queue_miao");
                if (is_null($re) || !$re)
                {
                    $rs = Myredis::getRedisConn()->deleteFromList("list_tender_queue_miao", $value);
                }
                if ((is_null($re) || !$re) && (is_null($rs) || !$rs))
                {
                    Myredis::getRedisConn(7)
                           ->setToHash("tender_queue_miao_" . $value['borrow_num'], $value['tnum'], array(
                               $re,
                               $rs
                           ));
                    Myredis::getRedisConn(7)->expire("tender_queue_miao_" . $value['borrow_num'], 604800);
                    continue;
                }
                continue;
            }
            $re = Myredis::getRedisConn()->shiftFromList("list_tender_queue_miao");
            if (is_null($re) || !$re)
            {
                $rs = Myredis::getRedisConn()->deleteFromList("list_tender_queue_miao", $value);
            }
            if ((is_null($re) || !$re) && (is_null($rs) || !$rs))
            {
                Myredis::getRedisConn(7)->setToHash("tender_queue_miao_" . $value['borrow_num'], $value['tnum'], array(
                    $re,
                    $rs
                ));
                Myredis::getRedisConn(7)->expire("tender_queue_miao_" . $value['borrow_num'], 604800);
                continue;
            }
            //判断融资者是否在截标或撤标
            $sleep = cache('LOCKend_' . $value['borrow_num']);
            if ($sleep == 1)
            {
                $info = array("info" => "融资者正在撤标或截标中");
            }
            else
            {
                $info = $borrow->tenderQueque($value);
            }
            if ($info === true)
            {
                echo 'ok';
                $status = array(
                    "status" => 119,
                    "info"   => "投标成功"
                );
                Myredis::getRedisConn()
                       ->setToHash("tender_queue_miao_success_" . $value['borrow_num'], $value['tnum'], $value, false);
                Myredis::getRedisConn()->expire("tender_queue_miao_success_" . $value['borrow_num'], 604800);
            }
            else
            {
                echo $info['msg'];
                $status = array(
                    "status" => $info['code'],
                    "info"   => $info['msg']
                );
            }

            $datalog['user_id'] = $value['tuserid'];
            $datalog['status'] = $info == true ? 1 : 2;
            $datalog['tnum'] = $value['tnum'];
            $datalog['cashCId'] = $value['cashCId'];
            $datalog['type'] = 1;
            $datalog['bnum'] = $value['borrow_num'];
            $datalog['money'] = $value['pTaccount'];
            $datalog['client'] = $value['type'];
            $datalog['result'] = json_encode($status);
            $datalog['addtime'] = date('Y-m-d H:i:s');
            $datalog['addip'] = $value['client_ip'];
            Myredis::getRedisConn()->setToHash("memcachekey_log", $value['tnum'], $datalog);
            Myredis::getRedisConn()->expire("memcachekey_log", 604800);
            cache('LOCKtender' . $value['tuserid'], NULL);
            Myredis::getRedisConn(1)->decrement($value['borrow_num']);
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * 函数：发标
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @return void
     */
    public function addBorrow()
    {
        $queque = input('param.id');
        $list = Myredis::getRedisConn()->getList("add_borrow_" . $queque, 0, 5);
        if (empty($list))
        {
            exit("没有数据");
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__. $queque);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__. $queque, 1, 60);
        $borrow = new BorrowServ();
        foreach ($list as $value)
        {
            $result = $borrow->addBorrow($value);
            if (is_numeric($result))
            {
                echo $result;
            }
            else
            {
                echo $value['pborrow_num'] . ":发标失败-" . $result['msg'] . PHP_EOL;
                Myredis::getRedisConn(1)->deleteFromHash('add_borrow_status', $value['user_id']);
                Myredis::getRedisConn(1)->delete("auto_tender_{$value['pborrow_num']}");
               // $error[] = $value['pborrow_num'] . ":发标失败-" . $result['msg'] . PHP_EOL;
            }
            $re = Myredis::getRedisConn()->shiftFromList('add_borrow_' . $queque);
            if (is_null($re) || !$re)
            {
                $re = Myredis::getRedisConn()->deleteFromList('add_borrow_' . $queque, $value);
            }
            if (is_null($re) || !$re)
            {
                $error[] = "标编码:" . $value['pborrow_num'] . '出队失败' . "<br/>" . PHP_EOL;
            }
            Myredis::getRedisConn()->deleteFromHash('add_borrow_status', $value['user_id']);
            Myredis::getRedisConn()->delete('autotender_status');
        }
//        if (!empty($error))
//        {
//            $this->_sendEmail(__FUNCTION__. $queque, implode(PHP_EOL, $error));
//        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__ . $queque);
    }

    /**
     * @desc 函数：融资人还款
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @return void
     */
    public function repayment()
    {
        $list = Myredis::getRedisConn()->getList('repay_queue_list', 0, 15);
        if (empty($list))
        {
            exit("没有数据");
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $borrow = new BorrowServ();
        foreach ($list as $key => $value)
        {
            $result = $borrow->repaymentHandle($value);
            if (true === $result)
            {
                echo 'ok';
            }
            else
            {
                $error[] = "用户:" . $value['user_id'] . "还款编码:" . $value['prenum'] . "失败原因:" . $result['msg'] . "<br/>" . PHP_EOL;
                echo 'fail';
            }
            $re = Myredis::getRedisConn()->shiftFromList('repay_queue_list');
            if (is_null($re) || !$re)
            {
                $re = Myredis::getRedisConn()->deleteFromList('repay_queue_list', $value);
            }
            if ((is_null($re) || !$re))
            {
                $error[] = "用户:" . $value['user_id'] . "还款编码:" . $value['prenum'] . "出队失败" . "<br/>" . PHP_EOL;
            }
            Myredis::getRedisConn()->deleteFromHash('repay_queue', $value['prenum']);
            cache('AutoReplay' . $value['prenum'], null);
            Myredis::getRedisConn()->setToHash('repay_queue_back', $value['prenum'], 1);
            Myredis::getRedisConn()->appendToList('repay_queue_list_back', $value);
        }
        if (!empty($error))
        {
            $this->_sendEmail(__FUNCTION__, implode(PHP_EOL, $error));
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * @desc 函数：投资人获得还款
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @return void
     */
    public function investorGetRepay()
    {
        $list = Myredis::getRedisConn()->getList('list_repayToInvestor', 0, 50);
        if (empty($list))
        {
            exit("没有数据");
        }
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $borrow = new BorrowModel();
        foreach ($list as $key => $value)
        {
            $ret = $borrow->investorGetRepayHandle($value);
            if (true === $ret)
            {
                echo 'ok';
            }
            else
            {
                echo 'fail';
                $error[] = "标编码:" . $value['borrow_num'] . "还款编码:" . $value['renum'] . '，获得还款失败原因：' . $ret['msg'] . "<br/>" . PHP_EOL;
            }
            $re = Myredis::getRedisConn()->shiftFromList('list_repayToInvestor');
            if (is_null($re) || !$re)
            {
                $re = Myredis::getRedisConn()->deleteFromList('list_repayToInvestor', $value);
            }
            if ((is_null($re) || !$re))
            {
                $error[] = "标编码:" . $value['borrow_num'] . "还款编码:" . $value['renum'] . "出队失败" . "<br/>" . PHP_EOL;
            }
            Myredis::getRedisConn()->appendToList('list_repayToInvestor_back', $value);
        }
        if (!empty($error))
        {
            $this->_sendEmail(__FUNCTION__, implode(PHP_EOL, $error));
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * @desc 函数：自动撤标
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function autoWithdrawal()
    {
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $borrowServ = new BorrowServ();
        $return = $borrowServ->withdrawBorrow();
        if (is_string($return))
        {
            echo $return;
            $this->_sendEmail(__FUNCTION__, $return);
        }
        elseif (false === $return)
        {
            echo '无数据';
        }
        echo 'ok';
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * @desc 函数： 自动还款垫付
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function autoPaymentAndAdvance()
    {
        $date_time_array = getdate(time());
        $hours = $date_time_array["hours"];
        $minutes = $date_time_array["minutes"];
        if ($hours == 23 && $minutes > 40)
        {
            $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
            if ($lock)
            {
                exit('等待上一个任务');
            }
            Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
            $borrow = new BorrowModel();
            $return = $borrow->autoPaymentAndAdvance();
            if (is_string($return))
            {
                echo $return;
                $this->_sendEmail(__FUNCTION__, $return);
            }
            elseif (false === $return)
            {
                echo '无数据';
            }
            echo 'ok';
            Myredis::getRedisConn(1)->delete(__FUNCTION__);
        }
        else
        {
            echo '未到脚本执行时间';
        }


    }

    /**
     * @desc 函数：特殊融资人自动发标
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function autoAddBorrow()
    {
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $borrow = new BorrowModel();
        $return = $borrow->autoAddBorrow();
        if (is_string($return))
        {
            echo $return;
            //$this->_sendEmail(__FUNCTION__, $return);
        }
        elseif (false === $return)
        {
            echo '无数据';
        }
        else
        {
            echo 'ok';
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * @desc 函数：自动投资
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function autoTender()
    {
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        $sys_var=new Variable();
        $stop_auto=$sys_var->getAutoTenderSwitch();
        if (!$stop_auto)
        {
            $model = new Iborrow();
            $sqlAttr = [
                'where' => ['status' => 0],
                'field' => 'id,borrow_num,status'
            ];
            $list = $model->getList($sqlAttr);
            if (!empty($list))
            {
                foreach ($list as $v)
                {
                    $up = ['status' => 1];
                    $where['id'] = $v['id'];
                    $where['status'] = 0;
                    $ret = $model->editByWhere($up, $where);
                    if ($ret)
                    {
                        echo $v['borrow_num'].'发布到大厅成功'.PHP_EOL;
                    }
                    else
                    {
                        echo $v['borrow_num'].'发布到大厅失败'.PHP_EOL;
                    }
                }
            }

            exit('自动投资已关闭');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $borrow = new BorrowServ();
        echo $borrow->autoTender();
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * @desc 函数：自动结标（只针对特殊融资人系统自动发的标）
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function autoEndBorrow()
    {
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        $ret = Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $borrow = new BorrowServ();
        $msg = $borrow->autoEndBorrow();
        echo $msg;
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * @desc 函数：自动还款（只针对特殊融资人系统自动发的标）
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function specialAutoPayment()
    {
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $borrow = new BorrowModel();
        echo $borrow->specialAutoPayment();
        Myredis::getRedisConn(1)->delete(__FUNCTION__);

    }




    /**
     * @desc 函数：监控委托发标
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function monitorAutoAddBorrow()
    {
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
        $where['financing_time_start'] = ['<=',date('Y-m-d',time())];
        $where['financing_time_end'] = ['>=',date('Y-m-d',time())];
        $where['rule_status'] =1;
        $financingRuleModel = new AutoFinancingRule();
        $ruleInfo= $financingRuleModel->getOneByWhere(['where'=>$where,'order'=>'id asc']);
        if ($ruleInfo)
        {
            $content= '';
            if ($ruleInfo['auto_financing_status']==2)
            {
                $content = '委托融资模型编号:'.$ruleInfo['id'].'，融资状态异常，异常内容为融资项目额度不足';
            }
            elseif ($ruleInfo['auto_financing_status']==3)
            {
                $content = '委托融资模型编号:'.$ruleInfo['id'].'，融资状态异常，异常内容为无法找到有效的融资人';
            }
            $queenSmsModel = new QueenSms();
            $phoneList = config('settings.phone');
            if (!empty($phoneList) && $content!='')
            {
                $checkSendLock = Myredis::getRedisConn(1)->get('send_monitorAutoAddBorrow_sms'.$ruleInfo['auto_financing_status']);
                if (!$checkSendLock)
                {
                    foreach ($phoneList as $v)
                    {
                        $insert['phone'] = $v;
                        $insert['content'] = $content;
                        $addData[] = $insert;
                    }
                    $queenSmsModel->addAll($addData);
                    Myredis::getRedisConn(1)->setAndExpire('send_monitorAutoAddBorrow_sms'.$ruleInfo['auto_financing_status'], 1, 86400);
                    echo 'ok';
                }
                else
                {
                    echo 'Has been sent';
                }

            }
            else
            {
                echo 'no error';
            }
        }
        else
        {
            echo 'no rule open';
        }

        Myredis::getRedisConn(1)->delete(__FUNCTION__);

    }

    /**
     * @desc 函数： 每天清理还款成功3天前key信息
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function deleteRepaymentRedis()
    {
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        $time = strtotime(date('Y-m-d', strtotime('-3 days'))) - 1;
        $list =  Myredis::getRedisConn()->getHash('success_repayment');
        if ($list)
        {
            Myredis::getRedisConn(1)->setAndExpire(__FUNCTION__, 1, 60);
            foreach ($list as $v)
            {
                if ($v['time'] <= $time)
                {
                    Myredis::getRedisConn()->deleteFromHash('success_repayment', $v['prenum']);
                }
            }
            echo 'ok';
        }
        else
        {
            echo '暂无数据';
        }
        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * @desc 函数： 每天清理满标成功key信息
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function deleteFullBorrowSuccessRedis()
    {
        $lock = Myredis::getRedisConn(1)->get(__FUNCTION__);
        if ($lock)
        {
            exit('等待上一个任务');
        }
        list($start, $end) = Time::today();
        $model = new Iborrow();
        $where['status'] = ['in','7,8'];
        $where['repayment_time'] = [
            'between',
            [
                $start,
                $end
            ]
        ];
        $sqlAttr = [
            'where' => $where
        ];
        $borrowList = $model->getList($sqlAttr);
        $i = 0;
        if ($borrowList)
        {
            foreach ($borrowList as $v)
            {
                $ret = Myredis::getRedisConn()->deleteFromHash('full_borrow_success', $v['borrow_num']);
                if ($ret)
                {
                    $i++;
                }
            }
        }
        echo '当天还款或还款完成数据:'.count($borrowList).'个,清除redis个数:'.$i.'个';

        Myredis::getRedisConn(1)->delete(__FUNCTION__);
    }

    /**
     * @desc 函数：自动投资资金返还
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function backAutoInvestMoney()
    {
        set_time_limit(0);
        $where['invest_status'] = 0;
        $sqlAttr = [
            'where' => $where,
            'limit' => 2
        ];
        $autoInvestDetailModel = new AutoInvestAccountDetail();
        $accountLogicModel = new AccountLogic();
        $redModel = new AutoInvestRedpacket();
        $treasureModel = new TreasureChest();
        $list = $autoInvestDetailModel->getList($sqlAttr);
        unset($sqlAttr);
        unset($where);
        if (!empty($list))
        {

            Db::startTrans();
            //$redMoney = 0;
            foreach ($list as $v)
            {
                try
                {
                    $msg = '用户:' . $v['user_id'] . '-' . $v['user_name'];
                    $up = [
                        'finish_time' => date('Y-m-d H:i:s'),
                        'invest_status' => 1
                    ];
                    $r3 = $autoInvestDetailModel->edit($up, $v['id']);
                    if ($r3)
                    {
                        $msg .= '自动投资规则状态更新成功,id：' . $v['id'] . PHP_EOL;
                    }
                    else
                    {
                        $msg1= '用户:' . $v['user_id'] . '-' . $v['user_name'].'自动投资规则状态更新失败,id：' . $v['id'] . PHP_EOL;
                        triggleError($msg1);
                    }
                    if ($v['is_red_packet'] == 1)
                    {
                        $where['auto_invest_user_account_detail_id'] = $v['id'];
                        $where['is_red_packet_use'] = 0;
                        $sqlAttr = [
                            'where' => $where,
                        ];
                        $treasureList = $redModel->getList($sqlAttr);
                        unset($where);
                        $redMoney = 0;
                        $redId = [];
                        if (!empty($treasureList))
                        {
                            foreach ($treasureList as $vv)
                            {
                                array_push($redId, $vv['red_packet_id']);
                                $redMoney += $vv['red_packe_amount'];
                            }
                        }

                        if (!empty($redId))
                        {
                            $up = [
                                'modify_time' => time(),
                                'status'      => 0
                            ];
                            $where['id'] = [
                                'in',
                                $redId
                            ];
                            $r1 = $treasureModel->editByWhere($up, $where);
                            if ($r1)
                            {
                                $msg .= '百宝箱红包状态更新成功,红包id：' . var_export($redId, true) . PHP_EOL;
                            }
                            else
                            {
                                $msg1 = '用户:' . $v['user_id'] . '-' . $v['user_name'].'百宝箱红包状态更新失败,红包id：' . var_export($redId, true) . PHP_EOL;
                                triggleError($msg1);
                            }
                            unset($where);
                            unset($up);
                            $up = [
                                'is_red_packet_use' => -1
                            ];
                            $where['red_packet_id'] = [
                                'in',
                                $redId
                            ];
                            $r2 = $redModel->editByWhere($up, $where);
                            unset($up);
                            unset($where);
                            if ($r2)
                            {
                                $msg .= '自动投资红包详情状态更新成功,红包id：' . var_export($redId, true) . PHP_EOL;
                            }
                            else
                            {
                                $msg1 = '用户:' . $v['user_id'] . '-' . $v['user_name'].'自动投资红包详情状态更新失败,红包id：' . var_export($redId, true) . PHP_EOL;
                                triggleError($msg1);
                            }
                            unset($redId);

                        }
                    }

                    //资金返还
                    $data['uid'] = $v['user_id'];
                    $data['to_uid'] = 1;
                    $data['num'] = $v['id'];
                    $data['remark'] = "自动投资设置时间:" . $v['create_time'] . '，项目期限:' . $v['auto_invest_item_term_month'] . '个月';
                    $data['use_change'] = $v['auto_invest_amount_surplus'];
                    $data['nouse_change'] = -$v['auto_invest_amount_surplus'];
                    $data['type'] = 207;
                    $data['btype'] = 0;
                    $data['addtime'] = time();
                    $accountLogicModel->upChange($data);
                    unset($data);
                    $msg .= '自动投资资金返回成功,金额:' . $v['auto_invest_amount_surplus'] . PHP_EOL;
                    if ($v['is_red_packet'] == 1)
                    {
                        if ($redMoney > 0)
                        {
                            //红包资金返还
                            $data['uid'] = $v['user_id'];
                            $data['to_uid'] = 1;
                            $data['num'] = $v['id'];
                            $data['remark'] = "自动投资设置时间:" . $v['create_time'] . '，项目期限:' . $v['auto_invest_item_term_month'] . '个月';
                            $data['total_change'] = -$redMoney;
                            $data['use_change'] = -$redMoney;
                            $data['type'] = 205;
                            $data['btype'] = 0;
                            $data['addtime'] = time();
                            $accountLogicModel->upChange($data);
                            $msg .= '红包资金返回成功,金额：' . $redMoney . PHP_EOL;
                            unset($redMoney);
                            unset($data);
                        }
                    }
                    Db::commit();
                    echo $msg;
                    unset($msg);
                } catch (\Exception $exception)
                {
                    Db::rollback();
                    echo msg($exception)['msg'];
                }
            }

        }
        else
        {
            echo '无数据';
        }
    }

    /**
     * @desc 函数：修复错误数据
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function backAutoInvestMoneyRepair()
    {
        set_time_limit(0);
        $where['finish_time'] = [
            '>=',
            '2018-01-24 18:03:31'
        ];
        $where['finish_time'] = [
            '<=',
            '2018-01-24 18:03:32'
        ];
        $sqlAttr = [
            'where' => $where,
        ];
        $autoInvestDetailModel = new AutoInvestAccountDetail();
        $accountLogicModel = new AccountLogic();
        $list = $autoInvestDetailModel->getList($sqlAttr);
        unset($sqlAttr);
        unset($where);
        if (!empty($list))
        {
            foreach ($list as $v)
            {
                $tableName = get_account_log_table($v['user_id']);
                $where['type'] = 205;
                $where['user_id'] = $v['user_id'];
                $log = Db::name($tableName)->where($where)->find();
                $msg = '用户:' . $v['user_id'] . '-' . $v['user_name'];
                if ($log)
                {
                    if ($log['nouse_change'] !=0)
                    {
                        $nouseChange = $log['nouse_change'] >0 ? -$log['nouse_change']: abs($log['nouse_change']);
                        //红包资金返还
                        $data['uid'] = $v['user_id'];
                        $data['to_uid'] = 1;
                        $data['num'] = $v['id'];
                        $data['remark'] = "自动投资设置时间:" . $v['create_time'] . '，项目期限:' . $v['auto_invest_item_term_month'] . '个月';
                        $data['nouse_change'] = $nouseChange;
                        $data['type'] = 205;
                        $data['btype'] = 0;
                        $data['addtime'] = time();
                        $accountLogicModel->upChange($data);
                        $msg .= '205修复nouse_change：' . $nouseChange . PHP_EOL;
                    }

                }
                $where1['type'] = 207;
                $where1['user_id'] = $v['user_id'];
                $log1 = Db::name($tableName)->where($where1)->find();
                if ($log1)
                {
                    if ($log1['total_change'] !=0)
                    {
                        $totalChange = $log1['total_change'] >0 ? -$log1['total_change']: abs($log1['total_change']);;
                        $data1['uid'] = $v['user_id'];
                        $data1['to_uid'] = 1;
                        $data1['num'] = $v['id'];
                        $data1['remark'] = "自动投资设置时间:" . $v['create_time'] . '，项目期限:' . $v['auto_invest_item_term_month'] . '个月';
                        $data1['total_change'] = $totalChange;
                        $data1['type'] = 207;
                        $data1['btype'] = 0;
                        $data1['addtime'] = time();
                        $accountLogicModel->upChange($data1);
                        $msg .= 'total_change：' . $totalChange . PHP_EOL;
                    }

                }
                echo $msg;
                unset($msg);
            }
        }
    }

    /**
     * 函数：发送邮件
     * @author liujian
     * @date 2017-3-24
     * @access private
     * @param string $subject 标题
     * @param string $content 内容
     * @return void
     */
    private function _sendEmail($subject, $content)
    {
        $phone = array(// '13424380919',
                       // '18124799796',
                       // '15820460512',
                       // '18565693063',
                       // '13265848050'
        );
        // '18617043035'

        foreach ($phone as $v)
        {
            $data['phone'] = $v;
            $data['content'] = $content;
            $addData[] = $data;
        }
        // M('queen_sms')->addAll($dataList);
        $toUsers = array(
            'liujian@yatang.cn',
            //'pujuanjuan@yatang.cn',
            // 'xiebaobin@yatang.cn',
            // 'sunjingwen@yatang.cn',
            // 'gongzhuqi@yatang.cn',
            // 'xietianming@yatang.cn',
            // 'zhangwenming@yatang.cn'
        );
        // printp($subject . $content); //调试
        $subject = input('server.SERVER_NAME') . '---' . $subject;
        foreach ($toUsers as $to)
        {
            send_mail($to, '', $subject, $content);
        }
    }

}
