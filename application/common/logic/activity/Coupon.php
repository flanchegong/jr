<?php

namespace application\common\logic\activity;
use application\common\logic\account\AccountLogic;
use application\common\model\account\Account as AccountModel;


use think\Db;

/**
 * Description of Coupon.
 *
 * @author Administrator
 */
class Coupon
{

    //protected $url     = DS_PAY_URL;
    protected $errMsgs = [
        'noCouponFound'                => '优惠券不存在',
        'noUserFound'                  => '用户不存在', 'nullCoupon'                   => '优惠券为空',
        'nullProfile'                  => '用户为空',
        'claimingCouponNotAllowed'     => '优惠券无效',
        'noCouponUses'                 => '优惠券数量不足',
        'noCouponUsesForUser'          => '用户已领取',
        'noCouponPromotions'           => '优惠券无效',
        'CouponExpired'                => '优惠券已过期',
        'couponPriorToStartDate'       => '优惠券未开始',
        'noPromotions'                 => '优惠券无效',
        'anonymousProfileUserResource' => '用户未登录',
    ];

    public function send($data)
    {
        $url      = $this->url . '/smp/app/profile/claimCoupon';
        $curlPost = array(
            'userName' => $data['userName'],
            'couponId' => $data['couponId'],
            'quantity' => 1,
        );
        $params   = json_encode($curlPost);
        $ch       = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($params),
        ));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        $res      = curl_exec($ch);
        curl_close($ch);
        $temp     = json_decode($res);
        $array    = array();
        foreach ($temp as $key => $value)
        {
            $array[$key] = $value;
        }
        $add['user_id']   = $data['user_id'];
        $add['user_name'] = $data['userName'];
        $add['coupon_id'] = $data['couponId'];
        $add['info']      = json_encode($array);
        $add['remark']    = $this->errMsgs[$array['errorCode']];
        $add['add_time']  = time();
        Db::name('award_send_ds_kq_log')->insert([
            'user_id'   => $data['user_id'],
            'user_name' => $data['userName'],
            'coupon_id' => $data['couponId'],
            'info'      => json_encode($array),
            'remark'    => $this->errMsgs[$array['errorCode']],
            'add_time'  => time(),
        ]);
        return $array;
    }

    /**
     * @desc 函数：获取红包
     * @author liujian
     * @date 2017-5-5
     * @access public
     * @return bool
     */
    public function getCashCoupon($userId, $cashCId, $award_type, $borrowNum = '')
    {
        $where['user_id']    = array('eq', $userId);
        $where['id']         = array('eq', $cashCId);
        $where['award_type'] = array('eq', $award_type);
        if (empty($borrowNum))
        {
            //投标时，获取红包条件
            $where['startTime'] = array('elt', time()); //红包的开始生效要小于当前时间
            $where['end_time']  = array('egt', time()); //红包的失效要大于当前时间
            $where['status']    = array('eq', 0);
        }
        else
        {
            //撤标或者投标失败时，获取红包条件
            $where['out_brrow_num'] = array('eq', $borrowNum);
        }
        $treasureChestModel = new TreasureChest();
        return $treasureChestModel->getOneByWhere(['where'=>$where]);
    }

    /**
     * @desc 函数：获取红包允许的投标类型
     * @author liujian
     * @date 2017-5-5
     * @access public
     * @return bool
     */
    public function getBorrowTypes($str)
    {
        //标种类
        $iborrowType = array(
            1  => '供应链金融',
            6  => '资产1号',
            7  => '资产2号',
            9  => '供应链金融',
            10  => '资产4号',
            11 => '资产3号');
        $arr         = explode(',', $str);
        $returnArr   = array();
        foreach ($arr as $k => $v)
        {
            array_push($returnArr, $iborrowType[$v]);
        }
        if (empty($returnArr))
        {
            return '';
        }
        $returnArr = array_unique($returnArr);
        $returnStr = implode('、', $returnArr);
        return $returnStr;
    }
    

    /**
     * @desc 函数：更新红包状态
     * @author liujian
     * @date 2017-5-5
     * @access public
     * @return bool
     */
    public function updateCashCouponStatus($tnum, $tenderMoney, $userId, $cashCId, $status, $borrowNum = '', $valueUsed = 0)
    {
        $where['id']      = array('eq', $cashCId);
        $where['user_id'] = array('eq', $userId);
        if ($status == 0)
        {
            //修改为可用                    
            $data['value_used']    = 0;
            $data['remove']        = 0;
            $data['tnum']          = '';
            $data['out_brrow_num'] = '';
            $data['money']         = 0;
        }
        else if ($status == 2)
        {
            //修改为已用                        
            $data['out_brrow_num'] = $borrowNum;
            $data['value_used']    = $valueUsed;
            $data['tnum']          = $tnum;
            $data['money']         = $tenderMoney;
        }
        $data['status']      = $status;
        $data['modify_time'] = time();
        $treasureChestModel = new TreasureChest();
        return $treasureChestModel->editByWhere($data,$where);
    }

    /**
     * @desc 函数：投资使用红包
     * @author liujian
     * @date 2017-5-5
     * @access public
     * @return bool
     */
    public function useCashCoupon($params = [])
    {
        $data['uid']            = $params['tuserid'];
        $data['to_uid']         = 1;
        $data['num']            = $params['tnum'];
        $data['remark']         = "投资[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]红包充值(" . number_format($params['cash_value'], 2, '.', ',') . ")";
        $data['total_change']   = $params['cash_value'];
        $data['use_change']     = $params['cash_value'];
        $data['type']           = 552;
        $data['btype']          = $params['borrow_type'];
        $data['treasure_chest'] = $params['treasure_chest'];
        $account = new AccountLogic();
        $account->upChange($data);
        unset($data);
        return true;
    }



    /**
     * @desc 函数：投标判断是否可以使用红包
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @return array
     */
    public function canUseCashCoupon($params = [])
    {
        //获取红包
        $cashCoupon = $this->getCashCoupon($params['tuserid'], $params['cashCId'], 0);
        if (!$cashCoupon)
        {
            return array('status' => 1, 'mes' => '您所使用的红包不存在或已过期');
        }
        $value = $cashCoupon['user_constraint'];
        if ($params['tender_money'] < $value)
        {
            return array('status' => 1, 'mes' => '投资太火爆,剩余可投不满足红包使用条件,<br/>请调整投资金额或另选红包吧！');
        }

        $allowType = explode(',', $cashCoupon['borrowType']);
        $typeStr   = $this->getBorrowTypes($cashCoupon['borrowType']);

        $withdrawalCash = $this->getCashControl($params['tuserid'], $params['borrow_type']);
        if (!$withdrawalCash['status'])
        {
            return array('status' => 1, 'mes' => $withdrawalCash['remesg']);
        }
        if ($value > $withdrawalCash['money'])
        {
            return array('status' => 1, 'mes' => '可使用红包金额不足以使用红包');
        }

        //标类型判断
        if (!empty($allowType))
        {
            if (!in_array($params['borrow_type'], $allowType))
            {
                return array('status' => 1, 'mes' => '该红包只允许投' . $typeStr . '标！');
            }
        }
        if ($cashCoupon['borrowTimeLimit'] == 2 && empty($cashCoupon['borrowStartMonth']) && empty($cashCoupon['borrowEndMonth']))
        {
            //红包仅允许投天标
            if ($params['repaystyle'] != 4)
            {
                return array('status' => 1, 'mes' => '该红包只允许投天标！');
            }
        }
        if (empty($cashCoupon['borrowTimeLimit']) && $cashCoupon['borrowStartMonth'] && $cashCoupon['borrowEndMonth'])
        {
            //红包仅可用月标
            if ($params['repaystyle'] == 4)
            {
                return array('status' => 1, 'mes' => '该红包只允许投月标！');
            }
            if ($params['time_limit'] < $cashCoupon['borrowStartMonth'] || $params['time_limit'] > $cashCoupon['borrowEndMonth'])
            {
                return array('status' => 1, 'mes' => '该红包只允许投' . $cashCoupon['borrowStartMonth'] . '个月到' . $cashCoupon['borrowEndMonth'] . '个月的标！');
            }
        }
        if ($cashCoupon['borrowTimeLimit'] == 2 && $cashCoupon['borrowStartMonth'] && $cashCoupon['borrowEndMonth'])
        {
            //红包可用月标以及天标
            //如果投的标不是天标，则要判断标的期限与红包的允许的期限是否符合
            if ($params['repaystyle'] != 4 && ($params['time_limit'] < $cashCoupon['borrowStartMonth'] || $params['time_limit'] > $cashCoupon['borrowEndMonth']))
            {
                return array('status' => 1, 'mes' => '该红包只允许投' . $cashCoupon['borrowStartMonth'] . '个月到' . $cashCoupon['borrowEndMonth'] . '个月的标！');
            }
        }

        return array('status' => 0, 'cash' => $cashCoupon);
    }

 
    /**
     * 获取已登录用户奖券
     * 
     * 
     * @param type $uid 用户id
     * @param type $username 用户名
     * @param type $money 投标金额
     * @param type $borrow_num 标编码
     * @param type $page 页码
     * @return array 
     */
    function getUserCoupon($uid, $username, $money, $borrow_num, $page = 1) {

        if (empty($uid) || empty($username) || empty($money) || empty($borrow_num)) {
            return ['status' => 0, "msg" => '参数不完整'];
        } 
        $rs=Db::name('iuser')->where(['user_id'=>$uid,'username'=>$username])->find();
        if(!$rs){
             return ['status' => 0, "msg" => '用户名与ID不匹配'];
        }
        if (getRequestCounts("SYS_CHECKPAY", array("user_id" => $uid, "username" => $username))) {
            return ['status' => 0, "msg" => '请求太频繁'];
        } 
        $borrow = Db::name('iborrow')->field('borrow_type,repaystyle,time_limit')->where(['borrow_num'=>$borrow_num,'status'=>1])->find(); 
        if (empty($borrow)) {
            return ['status' => 0, 'msg' => "该项目已不在募集中"];
        }
        if ( $borrow['repaystyle'] == 4) {
            return ['status' => 0, 'msg' => '天标不允许使用红包'];
        }
        if ($borrow['borrow_type'] == 5) {
            return ['status' => 0, 'msg' => '秒标不允许使用红包'];
        }

        //获取用户可提现金额                

        $userCash = $this->getCashControl($uid);
        if (!$userCash['status']) {
            return ['status' => 0, 'msg' => $userCash['remesg']];
        }
        $withdrawalCash = $userCash['money'];
        //若投标金额大于可用红包金额，则搜索红包时，输入金额上限为可用红包金额
        if ($money > $withdrawalCash) {
            $investMoney = $withdrawalCash;
        }
        //若不输入投标金额，直接点击红包，则默认投标值为可使用红包金额
        $investMoney = $investMoney ? $investMoney : $withdrawalCash;
        $redpacketKey = $uid . 'red' . $borrow['borrow_type'] . "packet" . $borrow['time_limit'] . 'key' . intval($money);
        $redpacket = cache($redpacketKey);
        if (empty($redpacket) || $redpacket['code'] != 0) {
            $info['userId'] = $uid;
            $info['userConstraint'] = $investMoney == 0 ? 0.01 : $investMoney;
            $info['awardType'] = 0; //0现金券，1VIP能量卡，2VIp抵用券，3积分卡4增值券
            $info['borrowTimeType'] = 3; //2天标3月标
            $info['borrowTime'] = $borrow['time_limit']; //2天标3月标
            $info['borrowType'] = $borrow['borrow_type']; //1.企业9.创业6.净值7.股权11.工薪
            $info['status'] = 0; //0.有效1.过期2.已使用3.赠与
            $info['pageNum'] = $page;
            $info['pageSize'] = 100;

            $redpacket = api_post(config('settings.api.AWARDACTION_NEWREDPACKET'), $info);
            cache($redpacketKey, $redpacket, 5);
        }
 
        if (isset($redpacket['code']) && $redpacket['code'] == 0) {
            $data = array();
            foreach ($redpacket['data']['list'] as $k => $v) {
                $data[$k]['id'] = $v['id'];
                $data[$k]['value'] = $v['value'];
                $data[$k]['user_constraint'] = $v['userConstraint'];
                $data[$k]['nearExpire'] = $v['surplusDay'] < 3 ? 1 : 0; //小于3天则为快过期
            } 
            return ['status' => 1, 'info' => '成功', 'data' => $data, 'withdrawalCash' => number_format($withdrawalCash, 2, '.', ','), 'withdrawal_cash' => $withdrawalCash];
        } else {
            return ['status' => 0, 'info' => $redpacket['message']];
        }
    }

}
