<?php

namespace application\common\model\borrow;

use think\Model;
use application\common\model\Base;
use \think\Db;

/**
 * 还款类
 * @author Administrator
 */
class IborrowRepayment extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'iborrow_repayment';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

    /**
     * 获取当前标还款计划
     * @param string $uid 用户ID
     * @param string $borrow_num 标编号
     * @return array
     * * */
    public function getRepayPlan($uid, $borrow_num)
    {
        $rs = $this->field("`order`+1 as qishu,if(status,repayment_yesaccount,repayment_account) as money,repayment_time ,repayment_yestime ,`status` ")
                   ->where([
                       'user_id' => $uid,
                       'borrow_num' => $borrow_num
                   ])->select();

        if (is_array($rs))
        {
            $status = [
                "未还",
                "已还",
                "站点代还"
            ];
            $total = 0;
            foreach ($rs as $k => $v)
            {
                $rs[$k]['status_text'] = $status[$v['status']];
                $rs[$k]['t2'] = $v['repayment_yestime'] ? date("Y-m-d", $v['repayment_yestime']) : "-";
                $rs[$k]['t1'] = date("Y-m-d", $v['repayment_time']);
                $total += $v['money'];
            }
            return [
                'total_money' => $total,
                'plan'        => $rs
            ];
        }
        else
        {
            return [];
        }
    }

    /**
     * @uses 生成虚拟的还款计划（当项目生成的时候）
     * @author jhl
     */
    public function getVirtualPlan($iborrowinfo)
    {

        //刚发标过程，实际借款总金额取借款总金额
        $rs = interest($iborrowinfo['total_money'], $iborrowinfo['rate'] ? $iborrowinfo['rate'] : 0, $iborrowinfo['repayment_method'] == 4 ? $iborrowinfo['fatalism'] : $iborrowinfo['time_limit'], $iborrowinfo['repayment_method']);
        $plan = [];
        $total = 0;
        foreach ($rs['repayment_plan'] as $key => $value)
        {
            $plan[$key]['qishu'] = $value['times'] + 1;
            $plan[$key]['money'] = $value['repayment_account'];
            $plan[$key]['t1'] = date("Y-m-d", $value['repayment_time']);
            $plan[$key]['t2'] = '-'; //暂时未还款
            $plan[$key]['status'] = 0;
            $plan[$key]['status_text'] = '未还';
            $total += $value['repayment_account'];
        }
        return [
            'total_money' => $total,
            'plan'        => $plan
        ];
    }

    public function statistics($field, $userId, $status)
    {
        $where['status'] = $status;
        $where['user_id'] = $userId;
        $rs = $this->where($where)->field("sum($field) as interest")->find();
        return $rs['interest'];
    }

    /**
     * @desc 方法:获取还款明细
     * @author liuj
     * @date 2017-6-19
     * @access public
     * @return boole
     */
    public function getPaymentDetailList($params = [], $userId, $isExcel = 0)
    {
        if ($userId == 0)
        {
            return false;
        }
        $where = [];
        $where['iborrowrepayment.user_id'] = $userId;
        $where['iborrowrepayment.status'] = [
            '<>',
            1
        ];
        if (isset($params['date']) && $params['date'] != '')
        {
            switch ($params['date'])
            {
                case 1:
                    $where['iborrowrepayment.repayment_time'] = [
                        'between',
                        [
                            strtotime('today'),
                            strtotime('tomorrow')
                        ]
                    ];
                    break;
                case 7:
                    $where['iborrowrepayment.repayment_time'] = [
                        'between',
                        [
                            strtotime('today -7 days'),
                            strtotime('today')
                        ]
                    ];
                    break;
                case 30:
                    $where['iborrowrepayment.repayment_time'] = [
                        'between',
                        [
                            strtotime('today -1 month'),
                            strtotime('today')
                        ]
                    ];
                    break;
                case 0:
                    if (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['iborrowrepayment.repayment_time'] = [
                            'between',
                            [
                                strtotime($params['stime']),
                                strtotime($params['etime'] . ' 23:59:59')
                            ]
                        ];
                    }
                    elseif (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] == '')
                    {
                        $where['iborrowrepayment.repayment_time'] = [
                            '>=',
                            strtotime($params['stime'])
                        ];
                    }
                    elseif (isset($params['stime']) && $params['stime'] == '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['iborrowrepayment.repayment_time'] = [
                            '<',
                            strtotime($params['etime'] . ' 23:59:59')
                        ];
                    }
                    break;
            }
        }
        if (isset($params['borrow_name']) && $params['borrow_name'] != '')
        {
            $where['name'] = $params['borrow_name'];
        }
        $field = 'iborrow.pear_base,iborrowrepayment.repayment_num ,iborrowrepayment.user_id ,iborrowrepayment.status ,iborrowrepayment.order ,iborrowrepayment.borrow_num ,iborrowrepayment.repayment_time ,iborrowrepayment.repayment_yestime ,iborrowrepayment.repayment_account ,iborrowrepayment.repayment_yesaccount ,iborrowrepayment.late_days ,iborrowrepayment.late_interest ,iborrowrepayment.interest ,iborrowrepayment.capital ,iborrowrepayment.reminder_fee ,iborrowrepayment.addtime ,iborrow.name ,iborrow.time_limit ,iborrow.id ,iborrow.borrow_type';
        $db = Db::name($this->_table)->alias('iborrowrepayment')->field($field)
                ->join('itd_iborrow iborrow', 'iborrowrepayment.borrow_num=iborrow.borrow_num')->where($where)
                ->order('repayment_time ASC');
        if ($isExcel == 1)
        {
            $data = $db->select();
            if (!empty($data))
            {
                foreach ($data as $k => $v)
                {
                    $data['data'][$k]['repayment_account'] = sprintf("%.2f", $v['repayment_account']);
                    $data['data'][$k]['repayment_yesaccount'] = sprintf("%.2f", $v['repayment_yesaccount']);
                    $data['data'][$k]['late_interest'] = sprintf("%.2f", $v['late_interest']);
                    $data['data'][$k]['reminder_fee'] = sprintf("%.2f", $v['reminder_fee']);
                    $data['data'][$k]['name'] = csubstr($v['name'], 0, 12);
                }
            }
            $repaySum = [];
        }
        else
        {
            $data = $db->paginate($params['list_rows'], false, ['page' => $params['current_page']])->toArray();
            if (!empty($data['data']))
            {
                foreach ($data['data'] as $k => $v)
                {

                    $data['data'][$k]['repayment_account'] = sprintf("%.2f", $v['repayment_account']);
                    $data['data'][$k]['repayment_yesaccount'] = sprintf("%.2f", $v['repayment_yesaccount']);
                    $data['data'][$k]['late_interest'] = sprintf("%.2f", $v['late_interest']);
                    $data['data'][$k]['reminder_fee'] = sprintf("%.2f", $v['reminder_fee']);
                    $data['data'][$k]['name'] = csubstr($v['name'], 0, 12);
                }
            }
            $repaySum = Db::name($this->_table)->alias('iborrowrepayment')
                          ->join('itd_iborrow iborrow', 'iborrowrepayment.borrow_num=iborrow.borrow_num')
                          ->field('sum(iborrowrepayment.repayment_account) as repayment_account,sum(iborrowrepayment.capital) as capital,sum(iborrowrepayment.interest) as interest')
                          ->where($where)->find();
        }

        return array_merge($data, $repaySum);
    }

    /**
     * @desc 方法:获取还款信息
     * @author liuj
     * @date 2017-6-19
     * @params  int $userId 用户id
     * @paramss tring $borrowNum 标编码
     * @paramss tring $repayNum 还款编码
     * @access public
     * @return array
     */
    public function getRepaymentInfo($userId, $borrowNum, $repayNum)
    {
        $where['repayment.borrow_num'] = $borrowNum;
        $where['repayment.repayment_num'] = $repayNum;
        $where['repayment.user_id'] = $userId;
        $where['repayment.status'] = [
            [
                '=',
                0
            ],
            [
                '=',
                2
            ],
            'or'
        ];
        $filed = 'repayment.user_id,repayment_num,late_interest,repayment.`order`,repayment.addtime,repayment.repayment_time,repayment.repayment_account,repayment.status,borrow.repaystyle,borrow.borrow_type,borrow.name,borrow.borrow_num,borrow.time_limit';
        return Db::name($this->_table)->alias('repayment')
                 ->join('itd_iborrow borrow', 'repayment.borrow_num=borrow.borrow_num')->where($where)->field($filed)
                 ->order('repayment.`order` asc')->find();
    }

    /**
     * 用户逾期未还金额
     * @param type $userId
     * @author lingyq
     * @date 2017/8/3
     * @return type
     */
    public function getUserOverdueAmount($userId)
    {
        $where['user_id'] = $userId;
        $where['status']  = 2;
        $data = Db::name('iborrow_repayment')->field('sum(repayment_account) as money')->where($where)->find();
        return $data['money'] > 0 ? $data['money'] : 0;
    }

    /**
     * @desc 函数：获取还款列表
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param $where
     * @return array
     */
    public function getRepaymentList($where)
    {
        $filed = 'rep.user_id,rep.repayment_account,rep.repayment_num,rep.borrow_num,rep.user_id,rep.`status`,borrow.name,rep.`order`,borrow.time_limit,borrow.borrow_type';
        return Db::name($this->_table)->alias('rep')
                 ->join('itd_iborrow borrow', 'rep.borrow_num=borrow.borrow_num')->where($where)->field($filed)
                 ->select();
    }

    /**
     * @desc 函数：获取特殊融资还款总数
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param $where
     * @return array
     */
    public function getSpecialRepaymenCount($where)
    {
        $count =  Db::name($this->_table)->alias('rep')
                 ->join('itd_iborrow borrow', 'rep.borrow_num=borrow.borrow_num')
                 ->join('itd_item_auto_financing_log log', 'borrow.id=log.item_id')
                 ->where($where)
                 ->count();
        return $count;
    }

    /**
     * @desc 函数：获取特殊融资还款列表
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param $where
     * @param  $limit
     * @return array
     */
    public function getSpecialRepaymentList($where,$limit='')
    {
        $filed = 'rep.user_id,rep.repayment_account,rep.repayment_num,rep.borrow_num,rep.user_id,rep.`status`,borrow.name,rep.`order`,borrow.time_limit,borrow.borrow_type';
        $db = Db::name($this->_table)->alias('rep')
                 ->join('itd_iborrow borrow', 'rep.borrow_num=borrow.borrow_num')
                 ->join('itd_item_auto_financing_log log', 'borrow.id=log.item_id')
                 ->where($where)
                 ->field($filed);
        if ($limit!='')
        {
            $db->limit($limit);
        }
        $list =  $db->select();
        return $list;
    }

    /**
     * 获取用户资产1,2,3,4号的待还金额
     * @param type $userSqlStr
     * @return type
     * @author lyq
     */
    public function getUserFinancingAmount($userSqlStr)
    {
        //资产1,2,3,4号融资金额
        $sql = "select SUM(re.repayment_account * 2) as sumt,bo.borrow_type 
              from itd_iborrow_repayment  re
              LEFT JOIN itd_iborrow  bo on bo.borrow_num=re.borrow_num
              where  re.user_id $userSqlStr  and re.`status`=0 and bo.borrow_type in (6,7,10,11)  and re.addtime>1383235200 GROUP BY bo.borrow_type";
        $financingAmount  = DB::query($sql);
        return empty($financingAmount) ? array() : $financingAmount;
    }
    
    /**
     * 用户是否在十五天内有过还款记录
     * @param type $userId
     * @return type
     * @author lyq
     * @date 2017-12-27
     */
    public function repayBetweenFiftentDay($userId){
        $repaytime                = strtotime(date('Y-m-d', strtotime('-14 days')));
        $rWhere['user_id']        = array('eq', $userId);
        $rWhere['repayment_time'] = array('egt', $repaytime);
        $repayInfo                = Db::name('iborrow_repayment')->where($rWhere)->find();
        return empty($repayInfo) ? array() : $repayInfo;
    }
}
