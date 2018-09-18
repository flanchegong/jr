<?php

namespace application\common\logic\borrow;

use application\common\model\borrow\ProjectProcess;
use application\common\model\system\Variable;
use think\Db;
use application\common\Myredis;
use application\common\logic\account\AccountLogic;
use application\common\model\borrow\Iborrow;
use application\common\model\borrow\IborrowTender;
use application\common\model\account\IuserAmount;
use application\common\model\account\AmountLog;
use application\common\model\system\Linkage;
use application\common\model\system\LinkageType;
use application\common\model\borrow\BtypeManage;
use application\common\model\borrow\BmemberManage;
use application\common\model\borrow\BcontentManage;
use application\common\model\borrow\IborrowRepayment;
use application\common\logic\credit\CreditLogic;
use application\common\model\user\Iuser;

class BorrowLogic
{

    /**
     * @desc   redis
     * @var    string
     * @access protected
     */
    protected $_redis;

    /**
     * @desc   account
     * @var    string
     * @access protected
     */
    protected $_account;

    /**
     * @desc   iborrowModel
     * @var    string
     * @access protected
     */
    protected $_borrowModel;

    /**
     * @desc   iborrowTenderModel
     * @var    string
     * @access protected
     */
    protected $_borrowTenderModel;

    /**
     * @desc   iuserAmountModel
     * @var    string
     * @access protected
     */
    protected $_iuserAmountModel;

    /**
     * @desc   iuserAmountLogModel
     * @var    string
     * @access protected
     */
    protected $_iuserAmountLogModel;

    function __construct()
    {
        $this->_account = new AccountLogic();
        $this->_borrowModel = new Iborrow();
        $this->_borrowTenderModel = new IborrowTender();
        $this->_iuserAmountModel = new IuserAmount();
        $this->_iuserAmountLogModel = new AmountLog();
    }

    /**
     * 获取标详情
     * @param string $borrow_num 标编码
     * @return array
     */
    public function getBorrowDetails($borrow_num)
    {
        $where = $this->buildWhere($borrow_num, '', false);

//        $data = $this->table("iborrow")->field()
//                ->where($where)
//                ->find();
        $fields = 'fatalism,user_id,id,borrow_num,most_account,lowest_account,content,success_time,each_time,  `name` , borrow_type ,  tender_times,  repayment_time, repaystyle, time_limit, `use`,  apr, award_type, award_rate , award_account,account,account_yes,`status`,finacing_amount_virtual, addtime, end_time, borrowpwd, truncate((account_yes/account)*100,2) as bar , truncate((account-account_yes)*1,2) as remain, pear_base,destine_time,destine_type';
        $data = $this->_borrowModel->getOneByWhere(['where' => $where], $fields);
        //数据为空，返回
        if (empty($data))
        {
            return [];
        }

        //计算最后还款时间
        if ($data['status'] == 8)
        {
            $iborrowRepaymentModel = new IborrowRepayment();
            $sqlArr = [
                'where' => ['borrow_num' => $data['borrow_num']],
                'order' => 'repayment_yestime desc'
            ];
            $rs2 = $iborrowRepaymentModel->getOneByWhere($sqlArr, 'repayment_yestime');
            $data['repayment_yestime'] = $rs2['repayment_yestime'];
        }

        $status_text = array(
            "草稿",
            "募集中",
            '初审失败',
            '满标',
            '复审失败',
            '撤销',
            '待初审',
            '还款中',
            '已还清',
            '逾期'
        );
        $data['statusText'] = $status_text[$data['status']];

        $repay = array(
            0 => "按月分期",
            3 => "到期还本",
            4 => "按天到期",
            5 => "到期全还"
        );
        $repay_style = array(
            0 => "icon-time-month",
            3 => "icon-repay-basis",
            4 => "icon-time-day",
            5 => "icon-repay-all "
        );
        $data['repaystyle_text'] = $data['borrow_type'] == 5 ? "秒还" : $repay[$data['repaystyle']];
        if ($data['borrow_type'] == 5)
        {
            $data['repaystyle_style'] = "icon-36 icon-36-mb";
        }
        else
        {
            $data['repaystyle_style'] = $repay_style[$data['repaystyle']];
        }

        $borrow_type = array(
            1  => '企业',
            2  => '信用',
            3  => '抵押',
            4  => '担保',
            5  => '秒还',
            6  => '净值',
            7  => '股东',
            9  => '创业融资',
            11 => '工薪贷'
        );
        $borrow_type_style = array(
            1  => 'qy',
            2  => 'xy',
            3  => '抵押',
            4  => '担保',
            5  => 'mh',
            6  => 'jz',
            7  => 'gd',
            9  => 'cy',
            11 => 'gx'
        );
        $data['borrow_type_text'] = $borrow_type[$data['borrow_type']];
        $data['borrow_type_style'] = $borrow_type_style[$data['borrow_type']];
        $data['start_time'] = date("Y-m-d H:i:s", $data['success_time']);
        $data['succed_time'] = $data['repayment_time'] ? date("Y-m-d H:i:s", $data['repayment_time']) : "-";
        $data['award_left'] = $data['account'] - $data['account_yes'];
        $data['each_time'] = date("Y-m-d", $data['each_time']);
        $data['progress'] = $data['bar'];
        $data['progress_style'] = $data['status'] >= 7 ? "gray" : "";
        if ($data['award_type'] == 0)
        {
            $data['awardV'] = '无';
        }
        if ($data['award_type'] == 1)
        {
            $data['awardV'] = empty($data['award_account']) ? "无" : $data['award_account'] . '元';
        }
        if ($data['award_type'] == 2)
        {
            $data['awardV'] = empty($data['award_rate']) ? "无" : $data['award_rate'] . '%';
        }
        //项目历程
        if ($data['borrow_type'] == 9)
        {
            $project = new ProjectProcess();
            $data['progressList'] = $project->getProcessList($data['user_id']);
        }
        if ($data['borrow_type'] == 11 || $data['borrow_type'] == 9)
        {
            //getEntrepreneurs
            $tender = new IborrowTender();
            $data = $tender->getEntrepreneurs($data['user_id']);
        }
        $rep = new IborrowRepayment();
        if ($data['status'] == 8)
        {
            $data['plan'] = $rep->getRepayPlan($data['user_id'], $data['borrow_num']);
        }
        else
        {
            $data['plan'] = $rep->getVirtualPlan($data);
        }

        return $data;
    }

    /**
     * 识别标ID或编码，返回where
     * @param string $number 标编码或ＩＤ
     * @param string $alias borrow别名
     * @param bool $string 是否返回字符串，默认返回数组
     * @return mixed
     */
    private function buildWhere($number, $alias = "", $string = false)
    {
        $alias = $alias ? "{$alias}." : "";
        $where = '';
        if (strlen($number) < 8 && preg_match("/\d{3,7}/", $number))
        {
            if ($string)
            {
                $where = $alias . "id=" . $number;
            }
            else
            {
                $where["{$alias}id"] = $number;
            }
        }
        if (preg_match("/(121|120|104)[A-Z0-9]{6}\d{3,7}/", $number))
        {
            if ($string)
            {
                $where = $alias . "borrow_num='{$number}'";
            }
            else
            {
                $where["{$alias}borrow_num"] = $number;
            }
        }
        return $where;
    }

    /**
     * 返回标的实时状态
     * @param string $borrow_id 标编号
     * @return array 返回标的金额，已投，状态
     * */
    public function getBorrowStatus($borrow_id)
    {
        $field = 'id,fatalism,pear_base,borrow_num,user_id,borrow_type,lowest_account,most_account,username,repaystyle,account,account_yes,status,(account-account_yes) as remain,repayment_time,end_time,apr,time_limit,award_rate,award_account,open_account,name, truncate((account_yes/account)*100,2) as progress,end_time,destine_type,end_time>UNIX_TIMESTAMP() as overdue,borrowpwd';
        return $this->_borrowModel->getOneByWhere(['where' => $this->buildWhere($borrow_id)], $field);
    }

    /**
     * @desc 函数：写入满标队列
     * @author liujian
     * @date 2017-5-5
     * @access public
     * @param array $params 标信息
     * @return bool
     */
    public function addFullBorrowQueue($params = [])
    {
        //清理投标最大可投计数缓存
        Myredis::getRedisConn()->delete("borrow_" . $params['borrow_num']);
        if (!Myredis::getRedisConn()->existsInHash("full_borrow_queue_hash", $params['borrow_num'])
            && !Myredis::getRedisConn()->existsInHash("full_borrow_success", $params['borrow_num']))
        {
            Myredis::getRedisConn()
                         ->setToHash('full_borrow_queue_hash', $params['borrow_num'], $params['user_id']); //hash锁
            $params['Advance'] = 'AddRepaymentWithData';
            $params['status'] = 3;
            $params['lastaccount'] = 0;
            Myredis::getRedisConn()->appendToList("full_borrow_queue_list", $params);
            //Myredis::getRedisConn()->appendToList("full_borrow_queue_list_full", $params);
            return true;
        }
        return false;
    }

    /**
     * @desc 发标入队列
     * @author liuj
     * @update 2017-06-19
     * @access public
     * @param array $params 标信息
     * @param bool $isCli false 正常发标 true 系统自动发标
     * @return mixed
     */
    public function addBorrowQueque($params = [], $isCli = false)
    {
        $otherParam['destine_type'] = $params['destine_type'];
        $otherParam['destine_time'] = $params['destine_time'];
        $otherParam['companyname'] = $params['companyname'];
        $data['user_id'] = $params['user_id'];
        $data['pborrow_num'] = $params['borrow_num'];
        $data['paward_account'] = $params['award_account'];
        $data['paward_rate'] = $params['award_rate'];
        $data['pborrowpwd'] = $params['borrowpwd'];
        $data['plowestA'] = $params['munlimited'];
        $data['pmostA'] = $params['unlimited'];
        $data['popen'] = $params['opens'];
        $data['pname'] = $params['name'];
        $data['pcontent'] = $params['content'];
        $data['pearBase'] = $params['pearBase'];
        $data['pborrow_type'] = $params['ibtype'];
        $data['pAccount'] = $params['account'];
        $data['pAccount_show'] = $params['account_show'];
        $data['pyearapr'] = $params['apr'];
        $data['prepaystyle'] = $params['repaystyle'];
        $data['ptime_limit'] = $params['time_limit'];
        $data['valid_time'] = $params['valid_time'];
        $data['puse'] = $params['use'];
        $data['pend_time'] = $params['pend_time'];
        $data['fatalism'] = $params['fatalism'];
        $data['forst_account'] = $params['forst_account'];
        $data['award_type'] = $params['award_type'];
        $data['otherParam'] = $otherParam;
        $data['username'] = $params['username'];
        $data['status'] = $params['status'];
        $data['addip'] =  get_client_ip();;
        if ($isCli)
        {
            return $data;
        }
//        if ($data['pborrow_type'] == 5)
//        {
//            $json_data['action'] = 'fb';
//            $json_data['data'] = [
//                "user_id"                    => $params['user_id'],
//                "user_name"                  => $data['username'],
//                "title"                      => $params['name'],
//                'item_start_time'            => date("Y-m-d H:i:s"),
//                'item_end_time'              => date("Y-m-d H:i:s", $params['pend_time']),
//                'borrow_number'              => $params['time_limit'],
//                'item_financing_amount_plan' => $params['account'],
//                'year_rate'                  => $params['apr'],
//                'item_describe'              => $params['content'],
//                'create_ip'                  => $data['addip'],
//                'invest_amount_min'          => $data['plowestA'],
//                'invest_amount_max'          => $data['pmostA'],
//                'timed_issue_item'           => $otherParam['destine_type'] == 1 ? 1 : 0,
//                'timed_issue_time'           => $otherParam['destine_type'] == 1 ? date("Y-m-d H:i:s", $otherParam['destine_time']) : null,
//            ];
//            $result = client_send($json_data, 10);
//            return $result['status'] ? true : false;
//        }
        Myredis::getRedisConn()->setToHash('add_borrow_status', $params['user_id'], $data);

        if ($params['ibtype'] == 1 || $params['ibtype'] == 7)
        {
            //企业,股东标
            return Myredis::getRedisConn()->appendToList('add_borrow_1', $data);
        }
        else
        {
            return Myredis::getRedisConn()->appendToList('add_borrow_2', $data);
        }
    }


    /**
     * @desc 函数：融资人提前还款增加积分
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @param array $params 还款信息
     * @return bool
     */
    public function earlyRepayment($params = [])
    {
        //提前还款，增加积分
        if ($params['repayment_info']['repayment_time'] > time())
        {
            $nowTime = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
            $repaymentTime = mktime(0, 0, 0, date('m', $params['repayment_info']['repayment_time']), date('d', $params['repayment_info']['repayment_time']), date('Y', $params['repayment_info']['repayment_time']));
            $days = floor(($repaymentTime - $nowTime) / 86400);
            $score = $days > 10 ? 10 : $days;
            if ($score > 0)
            {
                $iuserModel = new Iuser();
                $userInfo = $iuserModel->getOne($params['user_id']);
                if ($userInfo['vip_status'] == 1)
                {
                    $data['user_id'] = $params['user_id'];
                    $data['credit_type'] = 'beforehand_replay';
                    $data['credit'] = $score;
                    $data['num'] = $params['prenum'];
                    $data['title'] = $params['name'] . "|" . $params['borrow_num'];
                    $creditApi = new CreditLogic();
                    return $creditApi->creditChange($data);
                }
            }
            return true;
        }
        return true;
    }

    /**
     * @desc 函数：逾期还款
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @return bool
     */
    public function afterAdvancesRepayment($params = [])
    {
        if ($params['last_interest'] > 0)
        {
            $data['uid'] = $params['user_id'];
            $data['to_uid'] = 1;
            $data['type'] = 410;
            $data['use_change'] = 0;
            $data['nouse_change'] = -$params['repayment_account'];
            $data['remark'] = "垫付逾期后还款[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]" . ($params['repayment_info']['order']) . '/' . $params['time_limit'];
        }
        else
        {
            $data['uid'] = $params['user_id'];
            $data['to_uid'] = 1;
            $data['type'] = 107;
            $data['use_change'] = -$params['repayment_account'];
            $data['nouse_change'] = 0;
            $data['remark'] = "垫付后还款[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]" . ($params['repayment_info']['order']) . '/' . $params['time_limit'];
        }
        $data['num'] = $params['prenum'];
        $data['total_change'] = -$params['repayment_account'];
        $data['btype'] = $params['btype'];
        $data['borrow_money'] = -$params['repayment_account'];
        $this->_account->upChange($data);
        unset($data);
        return true;
    }

    /**
     * @desc 函数：网站垫付给返钱给平台
     * @author liujian
     * @date 2017-5-9
     * @access public
     * @return bool
     */
    public function addMoneyToAdmin($params = [])
    {
        if ($params['last_interest'] > 0)
        {
            $data['remark'] = "垫付逾期后还款[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]" . ($params['repayment_info']['order']) . '/' . $params['time_limit'];
        }
        else
        {
            $data['remark'] = "垫付后还款[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]" . ($params['repayment_info']['order']) . '/' . $params['time_limit'];
        }
        $data['uid'] = 1;
        $data['to_uid'] = $params['user_id'];
        $data['type'] = 432;
        $data['use_change'] = $params['repayment_account'];
        $data['num'] = $params['prenum'];
        $data['total_change'] = $params['repayment_account'];
        $data['btype'] = $params['btype'];
        $data['borrow_money'] = $params['repayment_account'];
        $this->_account->upChange($data);
        unset($data);
        return true;
    }

    /**
     * @desc 函数：扣除罚金
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @return bool
     */
    public function deductFine($params = [])
    {
        if ($params['last_interest'] > 0)
        {
            $data['uid'] = $params['user_id'];
            $data['to_uid'] = 1;
            $data['type'] = 408;
            $data['num'] = $params['prenum'];
            $data['total_change'] = -$params['repayment_account'];
            $data['use_change'] = -$params['repayment_account'];
            $data['remark'] = "垫付后还款罚金[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]" . ($params['repayment_info']['order']) . '/' . $params['time_limit'];
            $this->_account->upChange($data);
            unset($data);
        }
        return true;
    }

    /**
     * @desc 函数：融资人还款返还额度
     * @author liujian
     * @date 2017-5-9
     * @access private
     * @param array $params
     * @return bool
     */
    public function repaymentAmountBack($params = [])
    {
        $amountTypeConfig = config('system.amount_type');
        $data['user_id'] = $params['user_id'];
        $data['code_id'] = $amountTypeConfig[$params['btype']];
        $amount = $this->getAmount($data);
        if ($amount['codeid'] != 93 && $amount['financing_type'] != 2)
        {
            $back = [
                'user_id'    => $params['user_id'],
                'code_id'    => $data['code_id'],
                'back_total' => $params['repayment_info']['capital'],
                'type'       => '122',
                'rale_num'   => $params['prenum'],
                'remark'     => '还款返回额度',
            ];
            return $this->amountUnfreeze($back);
        }
        return true;
    }

    /**
     * @desc 函数：投标冻结金额
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @return bool
     */
    public function tenderFreeze($params = [])
    {
        $data['uid'] = $params['tuserid'];
        $data['to_uid'] = $params['user_id'];
        $data['num'] = $params['tnum'];
        $data['remark'] = "投资[Óa href='/Invest/ViewBorrow/num/" . $params['borrow_num'] . "' target=_blankÔ " . $params['name'] . " Ó/aÔ]冻结资金";
        $data['use_change'] = -$params['tender_money'];
        $data['nouse_change'] = $params['tender_money'];
        $data['type'] = 104;
        $data['btype'] = $params['borrow_type'];
        $data['treasure_chest'] = $params['treasure_chest'];
        $data['borrow_num'] = $params['borrow_num'];
        $this->_account->upChange($data);
        unset($data);
        return true;
    }

    /**
     * @desc 函数：添加投标记录
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @return bool
     */
    public function addTender($params = [])
    {
        $add['tender_num'] = $params['tnum'];
        $add['user_id'] = $params['tuserid'];
        $add['status'] = 0;
        $add['borrow_num'] = $params['borrow_num'];
        $add['money'] = $params['pTaccount'];
        $add['account'] = $params['tender_money'];
        $add['addtime'] = time();
        $add['type'] = $params['type'];
        $add['addip'] = $params['client_ip'];
        $add['repayment_account'] = $params['tender_money'] + $params['total_interest'];
        $add['interest'] = $params['total_interest'];
        $add['username'] = $params['tusername'];
        $add['unique_tenter_num'] = $params['tnum'];
        $add['cash_id'] = $params['cash_id'];
        return $this->_borrowTenderModel->add($add);
    }

    /**
     * @desc 函数：添加标记录
     * @author liujian
     * @date 2017-5-5
     * @access private
     * @return bool
     */
    public function addBorrow($params = [])
    { 
        $stop_auto=new Variable();
        $isEnableAutoTender=$stop_auto->getAutoTenderSwitch();
        $add['award_account'] = isset($params['paward_account']) ? $params['paward_account'] : 0;
        $add['award_rate'] = isset($params['paward_rate']) ? $params['paward_rate'] : 0;
        $add['borrowpwd'] = !empty($params['pborrowpwd']) ? $params['pborrowpwd'] : null;
        $add['lowest_account'] = isset($params['plowestA']) ? $params['plowestA'] : 0;
        $add['most_account'] = isset($params['pmostA']) ? $params['pmostA'] : null;
        $add['open_borrow'] = isset($params['popen']) ? $params['popen'] : '';
        $add['name'] = isset($params['pname']) ? $params['pname'] : '';
        $add['content'] = isset($params['pcontent']) ? $params['pcontent'] : '';
        $add['addtime'] = time();
        $add['addip'] = isset($params['addip']) ? $params['addip'] :get_client_ip();
        $add['user_id'] = $params['user_id'];
        $add['pear_base'] = isset($params['pearBase']) ? $params['pearBase'] : 0;
        $add['borrow_num'] = $params['pborrow_num'];
        $add['borrow_type'] = $params['pborrow_type'];
        $add['account'] = isset($params['pAccount']) ? $params['pAccount'] : 0;
        $add['finacing_amount_virtual'] = isset($params['pAccount_show']) ? $params['pAccount_show'] : 0;
        $add['apr'] = isset($params['pyearapr']) ? $params['pyearapr'] : 0;
        $add['repaystyle'] = isset($params['pborrow_type']) && $params['pborrow_type'] == 5 ? 0 : $params['prepaystyle'];
        $add['time_limit'] = isset($params['ptime_limit']) ? $params['ptime_limit'] : 1;
        $add['valid_time'] = isset($params['valid_time']) ? $params['valid_time'] : 0;
        $add['use'] = isset($params['puse']) ? $params['puse'] : '7';
        $add['end_time'] = isset($params['pend_time']) ? $params['pend_time'] : 0;
        $add['fatalism'] = isset($params['prepaystyle']) && $params['prepaystyle'] == 4 ? $params['fatalism'] : 0;
        $add['destine_type'] = !empty($params['otherParam']) && isset($params['otherParam']['destine_type']) ? $params['otherParam']['destine_type'] : 0;
        $add['destine_time'] = !empty($params['otherParam']) && isset($params['otherParam']['destine_type']) ? $params['otherParam']['destine_time'] : 0;
        $add['status'] = in_array($params['pborrow_type'], [
            6,
            7,
            10,
            11
        ]) && $isEnableAutoTender ? 0 : 1;
        $add['success_time'] = isset($params['status']) && $params['status'] == 1 ? time() : 0;
        $add['forst_account'] = isset($params['forst_account']) ? $params['forst_account'] : 0;
        $add['award_type'] = isset($params['award_type']) ? $params['award_type'] : 0;
        $add['award_rate'] = isset($params['paward_rate']) ? $params['paward_rate'] : 0;
        $add['companyname'] = !empty($params['otherParam']) && isset($params['otherParam']['companyname']) ? $params['otherParam']['companyname'] : '';
        $add['username'] = isset($params['username']) ? $params['username'] : '';
        return $this->_borrowModel->add($add);

    }

    /**
     * @desc 函数：额度冻结
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @param array $params 标信息数组
     * @return bool
     */
    function amountFreeze($params = [])
    {
        if (!isset($params['user_id']) || !isset($params['code_id']) || !isset($params['rale_num']) || !isset($params['back_total']) || !isset($params['remark']) || !isset($params['type']))
        {
            return '额度冻结参数错误';
        }
        $amount = $this->getAmount($params);
        if (!$amount)
        {
            return '未找到额度信息';
        }
        if ($amount['credit_use'] >= $params['back_total'])
        {
            $up = [
                'user_id'      => $params['user_id'],
                'code_id'      => $params['code_id'],
                'credit_use'   => -$params['back_total'],
                'credit_nouse' => $params['back_total']
            ];
            $result = $this->editAmount($up);
            if ($result)
            {
                $add = [
                    'user_id'      => $params['user_id'],
                    'code_id'      => $params['code_id'],
                    'credit'       => $amount['credit'] + $params['back_total'],
                    'credit_nouse' => $amount['credit_nouse'] + $params['back_total'],
                    'credit_use'   => $amount['credit_use'] - $params['back_total'],
                    'change_total' => 0,
                    'change_use'   => -$params['back_total'],
                    'change_nouse' => $params['back_total'],
                    'rale_num'     => $params['rale_num'],
                    'remark'       => $params['remark'],
                    'type'         => $params['type']
                ];
                $ret = $this->addAmountLog($add);
                if ($ret)
                {
                    return true;
                }
                else
                {
                    return '添加额度日志失败';
                }
            }
            else
            {
                return '额度更新失败';
            }
        }
        return '额度不足';
    }

    /**
     * @desc 函数：额度解冻
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return mixed
     */
    function amountUnfreeze($params = [])
    {
        if (!isset($params['user_id']) || !isset($params['code_id']) || !isset($params['rale_num']) || !isset($params['back_total']) || !isset($params['remark']) || !isset($params['type']))
        {
            return '额度解冻参数错误';
        }
        $amount = $this->getAmount($params);
        if (!$amount)
        {
            return '未找到额度信息';
        }
        if ($amount['credit_nouse'] >= $params['back_total'])
        {
            $up = [
                'user_id'      => $params['user_id'],
                'code_id'      => $params['code_id'],
                'credit_use'   => $params['back_total'],
                'credit_nouse' => -$params['back_total'],
            ];
            $result = $this->editAmount($up);
            if ($result)
            {
                $add = [
                    'user_id'      => $params['user_id'],
                    'code_id'      => $params['code_id'],
                    'credit'       => $amount['credit'] + $params['back_total'],
                    'credit_nouse' => $amount['credit_nouse'] - $params['back_total'],
                    'credit_use'   => $amount['credit_use'] + $params['back_total'],
                    'change_total' => 0,
                    'change_use'   => $params['back_total'],
                    'change_nouse' => -$params['back_total'],
                    'rale_num'     => $params['rale_num'],
                    'remark'       => $params['remark'],
                    'type'         => $params['type']
                ];
                return $this->addAmountLog($add);
            }
            else
            {
                return '额度更新失败';
            }
        }
        return '额度不足解冻';
    }

    /**
     * @desc 函数：获取额度
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return mixed
     */
    public function getAmount($params = [])
    {

        if (!isset($params['user_id']) || !isset($params['code_id']))
        {
            return false;
        }
        //检查额度是否足够
        $where['user_id'] = $params['user_id'];
        $where['codeid'] = $params['code_id'];
        $field = 'credit,credit_use,credit_nouse,codeid,financing_type';
        //$amount = Db::name('iuser_amount')->field($field)->where($where)->find();
        $amount = $this->_iuserAmountModel->getOneByWhere(['where' => $where], $field);
        if (empty($amount))
        {
            return false;
        }
        return $amount;
    }

    /**
     * @desc 函数：更新额度
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return bool
     */
    public function editAmount($params = array())
    {
        if (!isset($params['user_id']) || !isset($params['code_id']))
        {
            return false;
        }
        $data = [];
        if (isset($params['credit']) && $params['credit'] != 0)
        {
            $data['credit'] = array(
                'exp',
                sprintf("%s%s%s", "credit", $params['credit'] > 0 ? '+' : '', $params['credit'])
            );
        }
        if (isset($params['credit_use']) && $params['credit_use'] != 0)
        {
            $data['credit_use'] = array(
                'exp',
                sprintf("%s%s%s", "credit_use", $params['credit_use'] > 0 ? '+' : '', $params['credit_use'])
            );
        }
        if (isset($params['credit_nouse']) && $params['credit_nouse'] != 0)
        {
            $data['credit_nouse'] = array(
                'exp',
                sprintf("%s%s%s", "credit_nouse", $params['credit_nouse'] > 0 ? '+' : '', $params['credit_nouse'])
            );
        }
        $where['user_id'] = $params['user_id'];
        $where['codeid'] = $params['code_id'];
        if (!empty($data))
        {
            //  $result = Db::name('iuser_amount')->where($where)->update($data);
            $result = $this->_iuserAmountModel->editByWhere($data, $where);
            if (false === $result)
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @desc 函数：添加额度记录
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return bool
     */
    public function addAmountLog($params = array())
    {
        if (!isset($params['user_id']) || !isset($params['code_id']) || !isset($params['rale_num']))
        {
            return false;
        }
        $add['type'] = !isset($params['type']) || $params['type'] == 0 ? substr($params['rale_num'], 0, 3) : $params['type'];
        $add['user_id'] = $params['user_id'];
        $add['codeid'] = $params['code_id'];
        $add['credit'] = $params['credit'];
        $add['credit_nouse'] = $params['credit_nouse'];
        $add['credit_use'] = $params['credit_use'];
        $add['changetotal'] = $params['change_total'];
        $add['changeuse'] = $params['change_use'];
        $add['changenouse'] = $params['change_nouse'];
        $add['raleNum'] = $params['rale_num'];
        $add['addtime'] = $params['change_nouse'];
        $add['remark'] = $params['remark'];
        $add['addtime'] = time();
        return $this->_iuserAmountLogModel->add($add);
    }

    /**
     * @desc 函数：获取管理费
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @param int $borrowType 标类型
     * @param int $style 还款方式
     * @return int
     */
    public function getManageFeer($borrowType, $style = 0)
    {
        switch ($borrowType)
        {
            case 1:
                $fee = $this->_getBorrowFee($style == 4 ? 'BORR_FEERATEYEAR18' : 'BORR_FEERATE_M18');
                break;
            case 2:
                $fee = $this->_getBorrowFee($style == 4 ? 'BORR_FEERATEDAY267' : 'BORR_FEERATEMONTH');
                break;
            case 6:
                $fee = $this->_getBorrowFee($style == 4 ? 'BORR_FEERATEDAY267' : 'BORR_FEERATE_M267');
                break;
            case 7:
                $fee = $this->_getBorrowFee($style == 4 ? 'BORR_FEERATEDAY267' : 'BORR_FEERATE_M267');
                break;
            case 8:
                $fee = $this->_getBorrowFee($style == 4 ? 'BORR_FEERATEYEAR18' : 'BORR_FEERATE_M18');
                break;
            case 9:
                $fee = $this->_getBorrowFee($style == 4 ? 'BORR_FEERATEYEAR18' : 'BORR_FEERATE_M18');
                break;
            case 11:
                $fee = 0.006; //工薪
                break;
            default:
                $fee = 0;
                break;
        }
        return $fee;
    }

    /**
     * @desc 函数：获取年利率最大值
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return int
     */
    public function getMaxApr()
    {
        $linkageTypeModel = new LinkageType();
        $apr = $linkageTypeModel->getOneByWhere(['where' => ['nid' => 'apr']], 'id');
        $linkageModel = new Linkage();
        $maxAprInfo = $linkageModel->getOneByWhere([
            'where' => [
                'type_id' => $apr['id'],
                'name'    => [
                    'like',
                    '最大预期年化收益%'
                ]
            ]
        ], 'value');
        $maxApr = isset($maxAprInfo['value']) ? round($maxAprInfo['value'], 2) : 0;
        return $maxApr;
    }

    /**
     * @desc 函数：获取年利率最小值
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return int
     */
    public function getMinApr()
    {
        $linkageTypeModel = new LinkageType();
        $apr = $linkageTypeModel->getOneByWhere(['where' => ['nid' => 'apr']], 'id');
        $linkageModel = new Linkage();
        $minAprInfo = $linkageModel->getOneByWhere([
            'where' => [
                'type_id' => $apr['id'],
                'name'    => [
                    'like',
                    '最小预期年化收益%'
                ]
            ]
        ], 'value');
        $minApr = isset($minAprInfo['value']) ? round($minAprInfo['value'], 2) : 0;
        return $minApr;
    }

    /**
     * @desc 函数：能否编辑融资描述
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @param int $userId 用户id
     * @param int $ibtype 标类型
     * @return array
     */
    public function canEditContent($userId, $ibtype)
    {
        //默认可以修改
        $canEdit = 1;
        $templateContent = '';
        //查看该类型的标是否启用融资描述限制修改
        $model = new BtypeManage();
        $modification = $model->getOneByWhere([
            'where' => [
                'borrow_type' => $ibtype,
                'status'      => 1
            ]
        ], 'id');
        if ($modification)
        {
            //根据标类型ID获取融资者是否有权编辑“融资描述”
            $bmemberManageModel = new BmemberManage();
            $res = $bmemberManageModel->getOneByWhere([
                'where' => [
                    'user_id'     => $userId,
                    'borrow_type' => $ibtype
                ]
            ], 'id');
            //查看该类型的标是否有模板
            $bcontentManageModel = new BcontentManage();
            $template = $bcontentManageModel->getOneByWhere([
                'where' => [
                    'borrow_type' => $ibtype,
                    'status'      => 1
                ]
            ], 'borrow_content');
            //如果有模板，但是没权限，则不能修改                                        
            if (empty($res) && $template)
            {
                $canEdit = 0;
                $templateContent = stripslashes($template['borrow_content']);
            }
        }
        return array(
            'canEdit'         => $canEdit,
            'templateContent' => $templateContent
        );
    }


    /**
     * 投标待收限制类
     * @param string $type
     * @return array
     */
    function checkCollection($type)
    {
        $data = cache('borrow_' . $type);
        if (!$data)
        {
            $secondSiteModel = new SecondSite();
            $data = $secondSiteModel->getOneByWhere(['where' => ['code' => $type]], 'code,condition,explanation');
            $tmp = array(
                'code'        => trim($data['code']),
                'condition'   => trim($data['condition']),
                'explanation' => trim($data['explanation']),
            );
            cache('borrow_' . $type, $tmp, 345600); //缓 
        }
        return $data;
    }

    /**
     * 获取当前标还款计划
     * @param string $uid 用户ID
     * @param string $borrow_num 标编号
     * @return array
     * * */
    public function getRepayPlan($uid, $borrow_num)
    {
        $field = "`order`+1 as qishu,if(status,repayment_yesaccount,repayment_account) as money,repayment_time ,repayment_yestime ,`status` ";
        $iborrowRepaymentModel = new IborrowRepayment();
        $sqlAttr = [
            'field' => $field,
            'where' => [
                'user_id'    => $uid,
                'borrow_num' => $borrow_num
            ]
        ];
        $rs = $iborrowRepaymentModel->getList($sqlAttr);
        if (is_array($rs))
        {
            $status = array(
                "未还",
                "已还",
                "站点代还"
            );
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
        $rs = interest($iborrowinfo['account'], $iborrowinfo['apr'] ? $iborrowinfo['apr'] : 0, $iborrowinfo['repaystyle'] == 4 ? $iborrowinfo['fatalism'] : $iborrowinfo['time_limit'], $iborrowinfo['repaystyle'], '', 2);
        $plan = array();
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

    /**
     * @desc 函数：发标秒前冻结资金
     * @author liujian
     * @date 2017-5-5
     * @access public
     * @param array $params 标信息
     * @return bool
     */
    public function freezeMoney($params)
    {
        //秒标冻结资金
        $data['uid'] = $params['user_id'];
        $data['to_uid'] = 1;
        $data['num'] = $params['pborrow_num'];
        $data['remark'] = "秒还前[Óa href='/Invest/ViewBorrow/num/" . $params['pborrow_num'] . "' target=_blankÔ" . $params['pname'] . "Ó/aÔ]冻结保证资金";
        $data['use_change'] = -$params['forst_account'];
        $data['nouse_change'] = $params['forst_account'];
        $data['type'] = 509;
        $data['btype'] = $params['pborrow_type'];
        $this->_account->upChange($data);
        unset($data);
        return true;
    }

    /**
     * @desc 入还款队列
     * @author liuj
     * @update 2017-07-12
     * @access public
     * @param  array $params 还款信息
     * @return bool
     */
    public function addRepaymentQueque($params = [])
    {
        $data['uid'] = $params['user_id'];
        $data['to_uid'] = 1;
        $data['num'] = $params['repayment_num'];
        $data['remark'] = "还款冻结[Óa href='/Invest/ViewBorrow/num/{$params['borrow_num']}' target=_blankÔ" . $params['name'] . "Ó/aÔ]" . ($params['order'] + 1) . '/' . $params['time_limit'];
        $data['use_change'] = -$params['repayment_account'];
        $data['nouse_change'] = $params['repayment_account'];
        $data['type'] = 666;
        $data['btype'] = $params['borrow_type'];
        $data['borrow_num'] = $params['borrow_num'];
        $this->_account->upChange($data);
        Myredis::getRedisConn()
               ->incrementInHash("repay_frozen_{$params['user_id']}", $params['repayment_num'], $params['repayment_account'] * 10000);
        $repayData = [
            'prenum'            => $params['repayment_num'],
            'pmode'             => 1,
            'user_id'           => $params['user_id'],
            'time'              => time(),
            'repayment_account' => $params['repayment_account'],
            'borrow_num'        => $params['borrow_num'],
            'name'              => $params['name'],
            'order'             => $params['order'] + 1,
            'time_limit'        => $params['time_limit'],
            'btype'             => $params['borrow_type'],
        ];
        Myredis::getRedisConn()->appendToList("repay_queue_list", $repayData);
        return true;
    }


    /**
     * @desc 函数：获取管理费计算
     * @author liujian
     * @date 2017-3-31
     * @access private
     * @return int
     */
    private function _getBorrowFee($key)
    {
        $fee = config('system.fee');
        return $fee[$key];
    }

}
