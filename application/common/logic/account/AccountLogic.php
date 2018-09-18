<?php

/**
 * Created by PhpStorm.
 * User: Gong
 * Date: 2017/6/19
 * Time: 17:10
 */
namespace application\common\logic\account;

use Rediska;
use think\Db;
use application\common\Myredis;
use application\common\model\user\Iuser;
use application\common\model\user\WithdrawCashUserType;
use application\common\model\account\AccountModel;
use application\common\model\account\AccountLogModel;
use application\common\model\account\IuserAmount;
use application\common\model\borrow\IborrowRepayment;
use application\common\model\crowdfunding\CrowdfundingDetailModel;
use application\common\model\activity\ActivityAddCash;
use application\common\model\crowd\CrowdfundingDetaiModel;
use application\common\model\system\Variable;

class AccountLogic
{

    private $redis;
    private $modeName;
    protected $iuserModel;
    private $insideAccount = [45582  => '雅堂之家_cw',
        148686 => 'json18',
        170635 => 'mqhl-2016',
        221641 => 'ytang_cw2',
        247490 => 'ytds666',
        432018 => 'YT_XC',
        482434 => 'YT_广告'];

    public function __construct()
    {
        //parent::__construct();
        $this->iuserModel = new Iuser();
    }

    /**
     * 资金变化Method
     * @params $params  = array(
     * 'uid' => '用户id''
     * 'to_uid' => '交易对象用户id'
     * 'type' => '类型id'
     * 'num' => '业务编码'
     * 'total_change' => '总金额改变量'
     * 'use_change' => '可用金额改变量'
     * 'nouse_change' => '冻结金额改变量'
     * 'collection_change' => '待收金额改变量'
     * 'waitreplay_change' => '借款金额改变量',
     * 'remark' => '备注'，
     * 'btype'=>标类型,
     * 'treasureChest'=>红包投资金额，
     * 'bnum'=>标编码，
     * 'capitial'=>投资本金（暂为秒回款时资金流水的投资本金）
     *               )
     * @param int $addtime 添加时间
     * @param int $addip 添加ip
     * @return mixted  正确 true  错误返回false 没有提示
     */
    public function upChange($params)
    {
        if (isset($params['use_change']) && $params['use_change'] != 0 && $params['uid'] != 1)
        {
            $AccountModel = new AccountModel();
            $use_money    = $AccountModel->hasMoney($params['uid'], 1);
            Myredis::getRedisConn(8)->incrementInHash("account_{$params['uid']}", 'use_money', sprintf("%.4f", $params['use_change']) * 10000);
        }
        $add['user_id']           = $params['uid'];
        $add['to_user']           = $params['to_uid'];
        $add['num']               = $params['num'];
        $add['remark']            = isset($params['remark']) ? $params['remark'] : '';
        $add['total_change']      = isset($params['total_change']) ? $params['total_change'] : 0;
        $add['use_change']        = isset($params['use_change']) ? $params['use_change'] : 0;
        $add['nouse_change']      = isset($params['nouse_change']) ? $params['nouse_change'] : 0;
        $add['collection_change'] = isset($params['collection_change']) ? $params['collection_change'] : 0;
        $add['waitreplay_change'] = isset($params['waitreplay_change']) ? $params['waitreplay_change'] : 0;
        $add['type']              = $params['type'];
        $add['btype']             = isset($params['btype']) ? $params['btype'] : 0;
        $add['treasure_chest']    = isset($params['treasure_chest']) ? $params['treasure_chest'] : 0;
        $add['borrow_num']        = isset($params['borrow_num']) ? $params['borrow_num'] : '';
        $add['principal']         = isset($params['principal']) ? $params['principal'] : 0;
        $add['addtime']           = time();
        $add['addip']             = get_client_ip();
        $queque = $params['uid'] % 10;
        Myredis::getRedisConn(8)->appendToList('list_upchange'.$queque, $add);
        return true;
    }

    /**
     * 获取可提现金额，可投红包金额，可投秒金额，可用于投资返利金额
     * @param type $userId
     * @param type $htmlStr
     * @param type $type
     * @param type $isNewest
     * @return type
     * @author lyq
     */
    public function availableMoney($userId, &$htmlStr, $isNewest = true, $serviceType = 0)
    {
        $htmlStr      = "= ￥";
        $totalControl = 0;
        //可用资金获取
        $accountModel = new AccountModel();
        $userAccount  = $accountModel->getAccountInfoByUserId($userId);
        $useableMoney = $userAccount['use_money'];
        if (is_array($userAccount)) {
            $htmlStr .= truncate($userAccount['use_money']) . "(可用金额)";

            //逾期金额
            $repaymentModel = new IborrowRepayment();
            $overdueAmount  = $repaymentModel->getUserOverdueAmount($userId);
            //有逾期待还不允许提现
            if ($overdueAmount > 0) {
                return array('status' => 12, 'info' => '逾期未还，不能提现');
            } elseif ($userId == 490 || $userId == 7949) {//特殊账户
                $htmlStr .= " <b>-</b> <i> ￥0(体验金)</i>";
            } else {
                //获取身份证信息
                $userModel = new Iuser();
                $cardId    = $userModel->getOne($userId, 'card_id');
                if (empty($cardId['card_id'])) {
                    return array('status' => 11, 'info' => '没实名不可提现'); //没实名不可提现
                }

                //获取工薪用户
                $salaryUser = $userModel->getSalaryUserAccount($cardId['card_id']);

                //总控       
                $totalControlAmount   = $this->totalControlData($userId, $htmlStr, $salaryUser); //控制股东净值提现 
                //过程控制
                $processControlData   = $this->processControlData($userId, $htmlStr, $salaryUser, $serviceType); //统计资金日志     
                $totalControl         = $totalControlAmount > 0 ? 1 : 0; //总控
                $processControlAmount = $processControlData['protocol']; //过程控制金额
                //走总控，用户不能提现
                if ($totalControlAmount > 0) {
                    $userAccount['use_money'] = 0;
                } elseif ($processControlAmount > 0) {
                    $processControlAmount     = $userAccount['use_money'] < $processControlAmount ? $userAccount['use_money'] : $processControlAmount;
                    //当$userAccount['use_money']与$processControlAmount相等时，并且$processControlAmount带小数，相减会出现结果错误问题
                    $userAccount['use_money'] = round($userAccount['use_money'] - $processControlAmount, 4);
                    $htmlStr .= " <b>-</b> <i id='txxz15'>协议金额(" . subnumber($processControlAmount, 2) . ")</i>";
                }
            }
        }
        $processControlData['miaoBackSection'] = empty($processControlData['miaoBackSection']) ? 0 : $processControlData['miaoBackSection'];
        $processControlData['recharge']        = empty($processControlData['recharge']) ? 0 : $processControlData['recharge'];


        //活动增加资金，并且N天不能提现(精确到天，比如今天赠送的金额，限制一天，则明天可提现;排除掉已经反向操作过的，即type=1的)
        $activityAddCashModel = new ActivityAddCash();
        $canNotWithdrawMoney  = $activityAddCashModel->getActivityControlMoney($userId);
        if ($canNotWithdrawMoney > 0) {
            $userAccount['use_money'] -= $canNotWithdrawMoney;
            $htmlStr .= " <b>-</b> <i> ￥" . truncate($canNotWithdrawMoney) . "(活动增加资金)</i>";
        }
        //若走总控，不展示可用与协议金额
        $htmlStr          = $totalControl == 1 ? '' : $htmlStr;
        $withdrawalsMoney = isset($userAccount['use_money']) && $userAccount['use_money'] > 0 ? $userAccount['use_money'] : 0;
        //如果android，IOS的版本不是最新的，则投秒资金不能再次用于投红包
        if (!$isNewest) {
            $processControlData['miaoBackSection'] = 0;
        }
        //可投红包金额
        $moneyForRedpacket = $withdrawalsMoney + $processControlData['recharge'] + $processControlData['miaoBackSection'];
        if ($moneyForRedpacket > $useableMoney) {
            $moneyForRedpacket = $useableMoney > 0 ? $useableMoney : 0;
        }
        //可投秒金额
        $moneyForMiao = $withdrawalsMoney + $processControlData['recharge'];
        if ($moneyForMiao > $useableMoney) {
            $moneyForMiao = $useableMoney > 0 ? $useableMoney : 0;
        }
        //融资金额(可用金额-可投红包金额)
        $rongMoney = $useableMoney - $moneyForRedpacket;
        return array(
            'useable_money'      => $useableMoney, //可使用金额
            'control_money'      => empty($processControlAmount) ? 0 : $processControlAmount, //协议总控制金额
            'withdrawal_money'  => truncate($withdrawalsMoney, 4), //可提现金额
            'new_recharge_money'  => truncate($processControlData['recharge'], 4), //新充值金额
            'miao_back_money'     => truncate($processControlData['miaoBackSection'], 4), //秒回金额
            'rong_money'         => truncate($rongMoney, 4), //融资金额（包含融资资金，活动资金，红包充值资金，投资返利资金）
            'redpacket_money' => truncate($moneyForRedpacket, 4), //可投红包金额        
            'miao_money'      => truncate($moneyForMiao, 4), //可投秒金额            
            'total_control'      => $totalControl == 1 ? 1 : 0
        );
    }

    /**
     * 总控数据
     * 查询用户净值标/股东标/工薪/资产4号/股权众筹的【代收】【代还】情况；
     * @param type $userId     用户ID
     * @param type $htmlStr        描述信息
     * @param type $wtcanM    
     * @param type $beoverdue
     * @param type $rs
     * @return type
     * @author lyq      
     */
    public function totalControlData($userId, &$htmlStr, $userArr)
    {
        //满标 105融资人获得款 同时为 净值标/股东标/工薪
        $collection   = 0;
        $accountModel = new AccountModel();
        //非工薪用户
        if (empty($userArr) || !strstr(serialize($userArr), (string) $userId)) {
            $userCollectData = $accountModel->getAccountInfoByUserId($userId);
            $collection      = $userCollectData['collection'];
            $userSqlStr      = "=$userId";
        } else {//工薪用户
            $userStr = '';
            foreach ($userArr as $value) {
                $userCollectData = $accountModel->getAccountInfoByUserId($value['user_id']);
                $collection += $userCollectData['collection'];
                $userStr .= ',' . $value['user_id'];
            }
            $userStr    = substr($userStr, 1);
            $userSqlStr = " in($userStr)";
        }


        //资产1,2,3,4号融资金额
        $iborrowRepaymentModel = new IborrowRepayment();
        $financingAmount       = $iborrowRepaymentModel->getUserFinancingAmount($userSqlStr);
        if (empty($financingAmount)) {
            return 0;
        }
        $kongTiXian            = 0;
        $htmlStr               = "<table  class=\"acc-cw35\"  ><thead><tr><th width=auto\9>操作 </th><th width=auto\9>控制金额 </th></tr></thead><tbody>";
        $typeArr               = array(6 => '资产1号', 7 => '资产2号', 10 => '资产4号', 11 => '资产3号');
        foreach ($financingAmount as $v) {
            $kongTiXian += $v['sumt'];
            $htmlStr .= "<tr><td> <B>" . $typeArr[$v['borrow_type']] . "未还融资的2倍:+" . number_format(strsubstr($v['sumt']), 2) . "</B></td><td style='color:red;'>￥" . number_format(strsubstr($kongTiXian), 2) . '</td></tr>';
        }

        //只减股权型的待收
        $crowdfundModel   = new CrowdfundingDetaiModel();
        $crowdfundCollect = $crowdfundModel->getUserCrowdfundingCollection($userId);
        $collection -= $crowdfundCollect;
        if ($collection > 0) {
            $kongTiXian -= $collection;
            $htmlStr .= "<tr><td> <B>待收:-" . truncate($collection) . "</B></td><td style='color:red;'>￥" . truncate($kongTiXian) . '</td></tr>';
        }
        $htmlStr .="</tbody><tfoot></tfoot></table>";
        return $kongTiXian;
    }

    /**
     * 获取过程控制金额结果
     * @param type $userId
     * @param type $htmlStr
     * @return int
     * @author lyq     
     */
    public function processControlData($userId, &$htmlStr, $userArr, $serviceType = 0)
    {
        $kongTiXian      = 0; //提现数据源计算
        $kongTiXian_301  = 0; //充值数据源计算
        $kongTiXian_105  = 0; //融资数据源计算
        $miaoBackSection = 0; //秒后回款计算       
        $agreementAmount   = array();
        //获取可投红包，可投秒金额（由定时统计表的数据加上当天凌晨2点以后的日志结合得到结果）
        if ($serviceType == 2) {
            // 是否不生成缓存,在后台>>系统变量>>“不生成投秒投红包缓存”中设置
            $variableModel = new Variable();
            $where['key'] = array('eq','SYS_NOT_BUILD_RED_SECOND_REDIS_CACHE');
            $variableInfo = $variableModel->getField($where,'`value`');            
            if (!$variableInfo['value']) {
                //查询缓存数据
                $redis           = Myredis::getRedisConn();
                $agreementAmount = $redis->getFromHash("user_everyday_data_statistics", $userId);
                //若缓存时间不等于当天，则全部删除缓存
                if (!empty($agreementAmount) && date("Y-m-d", strtotime($agreementAmount['add_time'])) != date('Y-m-d', time())) {
                    $redis->delete('user_everyday_data_statistics');
                    unset($agreementAmount);
                }
                //延迟10分钟（防止从库有延迟）进行计算当天零时前受协议控制的值，写入缓存                         
                $statisticTime = strtotime(date("Y-m-d 0:10:00", time())); //每天的0时10分
                //每天0时10分去查询0时0分前的数据，写缓存（留10分钟作为数据库同步的缓冲时间）
                $time          = strtotime(date("Y-m-d 0:00:00", time()));
                if (empty($agreementAmount) && (time() - $statisticTime >= 0)) {
                    $agreementAmount = $this->userEverydayStatistics($userId, $redis, $userArr, 0, 0, 0, 0, $time);
                }
                if (!empty($agreementAmount)) {
                    $kongTiXian      = $agreementAmount['control_amount_total'] ? $agreementAmount['control_amount_total'] : 0; //提现数据源计算
                    $kongTiXian_301  = $agreementAmount['control_amount_new_recharge'] ? $agreementAmount['control_amount_new_recharge'] : 0; //充值数据源计算
                    $kongTiXian_105  = $agreementAmount['control_amount_finacing'] ? $agreementAmount['control_amount_finacing'] : 0; //融资数据源计算
                    $miaoBackSection = $agreementAmount['control_amount_second_back'] ? $agreementAmount['control_amount_second_back'] : 0; //秒后回款计算   
                }
            }
        }

        //若提现业务，或者是查询定时统计协议金额结果为空，或者是查询定时统计协议金额不为空但时间不是当天的（即定时任务统计出错）则执行原来的逻辑，查询全部流水统一计算
        if ($serviceType == 0 || empty($agreementAmount) || date("Y-m-d", strtotime($agreementAmount['add_time'])) != date('Y-m-d', time())) {
            //查找一年的资金流水
            $time           = strtotime(date('Y-m-d', strtotime('-1 year')));

        }
        $accountLogModel = new AccountLogModel();
        if (empty($userArr) || !strstr(serialize($userArr), (string) $userId)) {
            $sql = $accountLogModel->buildSqlOnCommonUser($userId, $time, $serviceType);
        } else {
            $sql = $accountLogModel->buildSqlOnSalaryUser($userArr, $time, $serviceType);
        }
        //取到id时，即时间点之后有资金日志，才查询数据库，若无ID，则返回的sql为空字符串
        
        if (!empty($sql)) {
            $runList = $accountLogModel->getAccountLog($sql);        
            $this->agreementAmount($runList, $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, $htmlStr);
        }
        return array('protocol' => $kongTiXian <= 0 ? 0 : $kongTiXian, 'recharge' => $kongTiXian_301 <= 0 ? 0 : $kongTiXian_301, 'financing' => $kongTiXian_105 <= 0 ? 0 : $kongTiXian_105, 'miaoBackSection' => $miaoBackSection < 0 ? 0 : $miaoBackSection);
    }
    /**
     * 每天凌晨00:10:00后，用户首次登陆时，计算用户当天00:10:00前，最后一笔协议金额的缓存
     * @param type $userId                 用户ID
     * @param type $redis                  redis对象
     * @param type $userArr                工薪贷用户ID数组
     * @param type $kongTiXian             协议金额
     * @param type $kongTiXian_301         新充值金额
     * @param type $kongTiXian_105         融资金额
     * @param type $miaoBackSection        秒回金额
     * @param type $endTime                截止时间
     * @author lyq
     * @date 2017/8/14
     * @return type
     */
    public function userEverydayStatistics($userId, $redis, $userArr, $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $endTime)
    {
        //可用资金获取
        if ($userId != 490 && $userId != 7949) {
            $time         = strtotime(date('Y-m-d', strtotime('-1 year')));
            $accountLogModel = new AccountLogModel();
            if (empty($userArr) || !strstr(serialize($userArr), (string) $userId)) {
                $sql = $accountLogModel->buildSqlOnCommonUser($userId, $time, 1, $endTime);
            } else {
                $sql = $accountLogModel->buildSqlOnSalaryUser($userArr, $time, 1, $endTime);
            }
            $list = array();
            if (!empty($sql)) {
                $list = $accountLogModel->getAccountLog($sql);
            }
            $data = array();
            if (!empty($list)) {
                $Exp                                 = '';
                $this->agreementAmount($list, $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, $Exp, true);
                $data['control_amount_total']        = format_num($kongTiXian, 4); //总控制金额
                $data['control_amount_finacing']     = format_num($kongTiXian_105, 4); //融资控制金额
                $data['control_amount_second_back']  = format_num($miaoBackSection, 4); //秒回控制金额
                $data['control_amount_new_recharge'] = format_num($kongTiXian_301, 4); //新充值控制金额
                $data['add_time']                    = date("Y-m-d H:i:s", time()); //创建时间
                $data['user_id']                     = $userId;
                $redis->setToHash('user_everyday_data_statistics', $userId, serialize($data));
            } else {
                $empty['user_id'] = $userId;
                $empty['time']    = date("Y-m-d H:i:s", time());
                $empty['sql']     = $sql;
                $redis->setToHash('user_everyday_data_empty_info', $userId . "_" . date("YmdHis", time()), $empty);
            }
            return $data;
        }
    }

    /**
     * 协议金额计算
     * @param type $userid
     * @param type $Runlist
     * @param type $kongTiXian
     * @param type $kongTiXian_301
     * @param type $miaoBackSection
     * @param type $kongTiXian_105
     * @param type $htmlStr
     * @param type $returnEmptyStr
     * @author lyq
     */
    public function agreementAmount($Runlist, &$kongTiXian, &$kongTiXian_301, &$miaoBackSection, &$kongTiXian_105, &$htmlStr, $returnEmptyStr = false)
    {
        $fifteenDaysAgo      = strtotime(date('Y-m-d', strtotime('-14 days')));//15天前
        $htmlStr             = $returnEmptyStr ? '' : "<table  class=\"acc-cw35\" ><thead><tr><th width=190>时间 </th><th width=190>操作 </th><th>控制金额 </th></tr></thead><tbody>";
        $btype               = array(6 => "资产1号", 7 => "资产2号", 11 => "资产3号", 10 => "资产4号");
        $arrRecharge = array();
        $arrMiao = array();
        $arrKong = array();
        $arrRong = array();
        foreach ($Runlist as $row) {
            //非秒标还款直接略过
            if ($row['type'] == 108 && $row['btype'] != 5) {
                continue;
            }
            
            if ($kongTiXian_301 < 0) {
                $kongTiXian_301 = 0; //新充值初始化
            }
            if ($kongTiXian_105 < 0) {
                $kongTiXian_105 = 0; //融资金额初始化
            }
            if ($miaoBackSection < 0) {
                $miaoBackSection = 0; //秒回金额初始化
            }
            $needKongMoney = 0; //需要被控制的金额，用于协议金额展示用
            $kongMoneyArr  = array();
            $moneyInfo     = array();
            //满标 105融资人获得款 同时为 净值标/股东标
            if ($row['type'] == 105 && $row['num'] != '') {
                if (in_array($row['btype'], array(6, 7, 10, 11))) {
                    $btype = array(6 => "资产1号", 7 => "资产2号", 10 => "资产4号", 11 => "资产3号");
                    $this->greementAmountIncrease($row, $row['use_change'], $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 0, 0, 1);
                    $htmlStr .= $this->agreementAmountShow($row['addtime'], $btype[$row['btype']], $row['use_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
                }
            }

            //归属为新充值
            $similarRechargeArr = array(
                301   => '在线充值',
                302   => '线下充值',
                669   => '电商退货退款',
                777   => '微信扫码充值',
                778   => '支付宝扫码充值',
                6666  => '老推新奖励',
                8888  => '投资抽奖奖励',
                20050 => '老推新待收奖励',
                20051 => '老推新任务奖励'
            );
            $similarRechargeK   = array_keys($similarRechargeArr);
            if (in_array($row['type'], $similarRechargeK) && $row['addtime'] >= $fifteenDaysAgo) {
                $str = $similarRechargeArr[$row['type']];
                    $this->greementAmountIncrease($row, abs($row['use_change']), $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 1, 0, 0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], $str, abs($row['use_change']), $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            
            //红包充值，投资返利：融资加
            $arrRandI  = array(552 => '红包充值', 682 => '投资返利');
            $arrRandIK = array_keys($arrRandI);
            if (in_array($row['type'], $arrRandIK) && $row['addtime'] >= $fifteenDaysAgo) {
                $this->greementAmountIncrease($row,$row['use_change'],$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,0,0,1);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], $arrRandI[$row['type']], $row['use_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            
            //撤销电商退货退款：新充值减
            if($row['type'] == 671 && $row['addtime'] >= $fifteenDaysAgo){                
                $this->greementAmountReduce($row,abs($row['total_change']),$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,0,0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], '撤销电商退货退款', $row['total_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            
            //红包充值失败返还：融资减
            if($row['type'] == 553 && $row['addtime'] >= $fifteenDaysAgo){                
                $this->greementAmountReduce($row,abs($row['total_change']),$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,0,0,1);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], '红包充值失败返还', $row['total_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            
            //抢现金券（只能使用新充值，可提现金额）：新充值减
            if ($row['type'] == 20052) {
                $kongMoneyArr = $this->greementAmountReduce($row, abs($row['use_change']), $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 1, 0, 0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], '抢现金券', $kongMoneyArr['kong'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            //发放现金券，兑换现金券：秒回加   
            $cashArr    = array(20053 => '发放现金券', 20054 => '兑换现金券');
            $cashArrKey = array_keys($cashArr);
            if (in_array($row['type'], $cashArrKey) && $row['addtime'] >= $fifteenDaysAgo) {
                $this->greementAmountIncrease($row, $row['use_change'], $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 0, 1, 0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], $cashArr[$row['type']], $row['use_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            //年会报名扣除保证金（保证金金额：新充值+可提现）:新充值减
            if ($row['type'] == 8889) {
                $this->greementAmountReduce($row,abs($row['use_change']),$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,0,0);
                $htmlStr.= $this->agreementAmountShow($row['addtime'], '年会参会保证金', $row['use_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }

            //红包自动投资设置：新充值减，秒回减
            if ($row['type'] == 202) {
                $kongMoneyArr = $this->greementAmountReduce($row, abs($row['use_change']), $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 1, 1, 0);
                $htmlStr.= $this->agreementAmountShow($row['addtime'], '红包自动投资设置', $kongMoneyArr['kong'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            //非红包自动投资设置
            if ($row['type'] == 204) {                
                //有用到新充值或者秒回                
                if ($row['treasure_chest'] > 0) {
                    //先使用新充值，秒回
                    $kongSetMoney     = $this->greementAmountReduce($row, $row['treasure_chest'], $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 1, 1, 0);
                    $needKongMoney += $kongSetMoney['kong'];
                    //计算需要使用融资金额的值
                    $needRong         = abs($row['use_change']) - $row['treasure_chest'];
                    $kongSetRongMoney = $this->greementAmountReduce($row, $needRong, $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 0, 0, 1);
                    $needKongMoney += $kongSetRongMoney['kong'];
                } else {//完全使用融资金额                
                    $kongSetRongMoney = $this->greementAmountReduce($row, abs($row['use_change']), $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 0, 0, 1);
                    $needKongMoney += $kongSetRongMoney['kong'];
                }
                $htmlStr .= $this->agreementAmountShow($row['addtime'], '非红包自动投资设置', $needKongMoney, $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            //年会报名扣除保证金（保证金金额：新充值+可提现）：新充值减
            if ($row['type'] == 8889) {
                $kongMoneyArr = $this->greementAmountReduce($row, $row['treasure_chest'], $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 1, 0, 0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], '年会参会保证金', $kongMoneyArr['kong'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            //电商订单余额支付(使用新充值，可提现的金额进行支付)
            if ($row['type'] == 20001) {
                $kongMoneyArr = $this->greementAmountReduce($row, abs($row['use_change']), $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 1, 0, 0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], "电商订单余额支付", $kongMoneyArr['kong'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            //金融余额支付，支付冻结，支付退款：新充值减
            $payArr    = array(30001 => '支付冻结', 30005 => '支付退款');
            $payArrKey = array_keys($payArr);
            if (in_array($row['type'], $payArrKey)) {
                $this->greementAmountReduce($row,abs($row['use_change']),$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,0,0);                
                $htmlStr .= $this->agreementAmountShow($row['addtime'], $payArr[$row['type']], abs($row['use_change']), $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }

            //金融余额支付，支付成功，新充值减
            $payArr    = array(30002 => '支付成功');
            $newOnlineTime=strtotime('2018-01-19 0:20:00');
            $payArrKey = array_keys($payArr);
            if (in_array($row['type'], $payArrKey) && $row['addtime'] >= $newOnlineTime) {
                $this->greementAmountReduce($row,abs($row['use_change']),$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,0,0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], $payArr[$row['type']], abs($row['use_change']), $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }

            //金融余额支付，收款成功（永久控制不能提现）:新充值加
            if ($row['type'] == 30003) {
                $this->greementAmountIncrease($row, $row['use_change'], $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 1, 0, 0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], '收款成功', $row['use_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            //金融余额支付，收到退款(15天内控制不能提现):新充值加
            if ($row['type'] == 30004 && $row['addtime'] >= $fifteenDaysAgo) {
                $this->greementAmountIncrease($row, $row['use_change'], $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 1, 0, 0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], '收到退款', $row['use_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            //投标:104:投资   557：众筹投资    567：债权购买    999：爱心捐款
            $typeArr = array(104=>'投资',557=>'众筹投资',567=>'债权购买',999=>'爱心捐款');
            $typeArrKey = array_keys($typeArr);
            if (in_array($row['type'],$typeArrKey)) {        
                $bnum = $row['borrow_num'];  
                $moneyChange = abs($row['use_change']);
                //秒
                if (($row['btype'] == 5 && $kongTiXian_301 > 0 && $row['addtime'] >= $fifteenDaysAgo) || $row['type'] == 999) {
                    //使用新充值
                    $moneyInfo = $this->greementAmountReduce($row,$moneyChange,$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,0,0);
                    $arrRecharge[$bnum] = isset($arrRecharge[$bnum]) ? $arrRecharge[$bnum] + $moneyInfo['recharge'] : $moneyInfo['recharge'];
                    $arrKong[$bnum] = isset($arrKong[$bnum]) ? $arrKong[$bnum] + $moneyInfo['kong'] : $moneyInfo['kong'];
                    
                    $title = $row['type'] == 999 ? "爱心捐款" : "投秒";
                    $htmlStr .= $this->agreementAmountShow($row['addtime'], $title, $moneyChange, $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
                }
                
                //红包或投资返利
                if ($row['treasure_chest'] > 0) {
                    //先用新充值，秒回金额
                    $moneyInfo = $this->greementAmountReduce($row,$moneyChange,$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,1,0);
                    $arrRecharge[$bnum] = isset($arrRecharge[$bnum]) ? $arrRecharge[$bnum] + $moneyInfo['recharge'] : $moneyInfo['recharge'];  
                    $arrMiao[$bnum] = isset($arrMiao[$bnum]) ? $arrMiao[$bnum] + $moneyInfo['miao'] : $moneyInfo['miao'];
                    $needKongMoney = $moneyInfo['kong'];
                    
                    //红包溢出投资(非红包部分的金额，如果有新充值资金，或者秒回资金，则优先使用，欠缺的部分再使用融资金额)
                    if ($moneyChange > $row['treasure_chest']) {
                        $kkk_money = $moneyChange - $moneyInfo['kong'];
                        $money_kkk = $moneyChange -$row['treasure_chest'];
                        $kongMoney_105 = $money_kkk > $kkk_money ? $kkk_money : $money_kkk;
                        $kongMoney_105 = $kongTiXian_105 > $kongMoney_105 ? $kongMoney_105 : $kongTiXian_105;
                        $kongTiXian_105 -= $kongMoney_105;
                        $arrRong[$bnum] = isset($arrRong[$bnum]) ? $arrRong[$bnum] + $kongMoney_105 : $kongMoney_105;
                        $kongMoney = $kongTiXian > $kongMoney_105 ? $kongMoney_105 : $kongTiXian;
                        $kongTiXian -= $kongMoney;
                        $needKongMoney += $kongMoney_105;
                    }
                    $title = $row['type'] == 557 ? '股权投资':'红包投资';
                    $htmlStr .= $this->agreementAmountShow($row['addtime'], $title, $needKongMoney, $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
              
                }

                  
                //一般的投资（非秒,非投红包，非返利,且非年会捐款数据）
                if ($row['btype'] != 5 && $kongTiXian > 0 && ($row['addtime'] < $fifteenDaysAgo || $row['treasure_chest']==0) &&$row['type']!=999) {
                    $moneyInfo = $this->greementAmountReduce($row,$moneyChange,$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,1,1);      
                    $arrRecharge[$bnum] = isset($arrRecharge[$bnum]) ? $arrRecharge[$bnum] + $moneyInfo['recharge'] : $moneyInfo['recharge'];
                    $arrMiao[$bnum] = isset($arrMiao[$bnum]) ? $arrMiao[$bnum] + $moneyInfo['miao'] : $moneyInfo['miao'];
                    $arrRong[$bnum] = isset($arrRong[$bnum]) ? $arrRong[$bnum] + $moneyInfo['rong'] : $moneyInfo['rong'];
                    $htmlStr .= $this->agreementAmountShow($row['addtime'], '投标-', $moneyChange, $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
                }
            }

            //撤标106(投资人资金控制)
            if (($row['type'] == 106 || $row['type'] == 16110211) ) {     
                $this->greementAmountReturn($row,$row['borrow_num'],$row['use_change'],$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,$arrRecharge,$arrMiao,$arrRong,1,1,1);
                $htmlStr .= $this->agreementAmountShow($row['addtime'],'撤标',$row['use_change'],$kongTiXian,$kongTiXian_301,$kongTiXian_105,$miaoBackSection,$returnEmptyStr);   
            }
            
            //逾期罚金扣除新充值>秒回>融资>可提现)
            if ($row['type'] == 408 && $kongTiXian > 0) {
                $changeMoney = abs($row['total_change']);
                $this->greementAmountReduce($row,$changeMoney,$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,1,1);
                $htmlStr .= $this->agreementAmountShow($row['addtime'],'逾期罚金扣除',$changeMoney,$kongTiXian,$kongTiXian_301,$kongTiXian_105,$miaoBackSection,$returnEmptyStr);    
            }

            //融资人撤标收取费用(新充值>秒回>融资>可提现)
            if ($row['type'] == 338 && $kongTiXian > 0) {
                $changeMoney = abs($row['use_change']);
                $this->greementAmountReduce($row,$changeMoney,$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,1,1);
                $htmlStr .= $this->agreementAmountShow($row['addtime'],'撤标收取费用',$changeMoney,$kongTiXian,$kongTiXian_301,$kongTiXian_105,$miaoBackSection,$returnEmptyStr);    
            }

            //投秒本金以及投秒收益算秒回：秒回加
            if ($row['type'] == 108 && $row['btype'] == 5 && $row['addtime'] >= $fifteenDaysAgo) {
                $this->greementAmountIncrease($row, $row['use_change'], $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 0, 1, 0);
                $htmlStr .= $this->agreementAmountShow($row['addtime'], '投秒回款', $row['use_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }

            //融资人还款（107为老数据的还款成功，那段时间没有还款冻结这个环节)
            $rongTypeArr = array(107 => "还款成功", 666 => "还款冻结", 410 => "还款成功");
            $rongTypeArrKey = array_keys($rongTypeArr);
            if (in_array($row['type'],$rongTypeArrKey) && $kongTiXian > 0 && $row['btype'] != 5) {               
                $bNum = $row['num'];                
                $moneyInfo = $this->greementAmountReduce($row,abs($row['use_change']),$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,1,1,1);
                $arrRecharge[$bNum] = $moneyInfo['recharge'];
                $arrMiao[$bNum] = $moneyInfo['miao'];
                $arrRong[$bNum] = $moneyInfo['rong'];
                
                $title = $rongTypeArr[$row['type']];
                $htmlStr .= $this->agreementAmountShow($row['addtime'],$title,abs($row['use_change']),$kongTiXian,$kongTiXian_301,$kongTiXian_105,$miaoBackSection,$returnEmptyStr);                
            }
            //还款冻结失败
            if ($row['type'] == 667 && $row['btype'] != 5) {
                //返回投资时使用的新充值，秒回，融资金额
                $this->greementAmountReturn($row,$row['num'],$row['use_change'],$kongTiXian,$kongTiXian_301,$miaoBackSection,$kongTiXian_105,$arrRecharge,$arrMiao,$arrRong,1,1,1);
                $htmlStr .= $this->agreementAmountShow($row['addtime'],"还款冻结失败",$row['use_change'],$kongTiXian,$kongTiXian_301,$kongTiXian_105,$miaoBackSection,$returnEmptyStr);                
            }
            
            //融资管理费,奖励支出:融资金额减
            $feeTypeArr = array(407 => '融资管理费', 601 => '奖励支出');
            $feeTypeKey = array_keys($feeTypeArr);
            if (in_array($row['type'],$feeTypeKey) && $kongTiXian > 0) {               
                $this->greementAmountReduce($row, abs($row['total_change']), $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 0, 0, 1);
                $htmlStr.= $this->agreementAmountShow($row['addtime'], $feeTypeArr[$row['type']], $row['total_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            
            //利息管理费(2017-12-08 00:00:00之前)：融资减
            if ($row['type'] == 409 && $kongTiXian > 0 && $row['addtime'] <= strtotime('2017-12-08 00:00:00')) {
                $this->greementAmountReduce($row, abs($row['total_change']), $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 0, 0, 1);
                $htmlStr.= $this->agreementAmountShow($row['addtime'], '利息管理费', $row['total_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
            //VIP缴费
            if ($row['type']==403 && $kongTiXian > 0) {
                $this->greementAmountReduce($row, abs($row['total_change']), $kongTiXian, $kongTiXian_301, $miaoBackSection, $kongTiXian_105, 1, 1, 1);
                $htmlStr.= $this->agreementAmountShow($row['addtime'], 'vip会员费', $row['total_change'], $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr);
            }
        }
        $htmlStr.="</tbody><tfoot></tfoot></table>";
    }
    /**
     * 协议金额增加
     * @param type $moneyChange
     * @param type $kongTiXian
     * @param type $kongTiXian_301
     * @param type $miaoBackSection
     * @param type $kongTiXian_105
     * @param type $useRecharge
     * @param type $useMiao
     * @param type $useRong
     * @author lyq     
     */
    public function greementAmountIncrease($row, $moneyChange, &$kongTiXian, &$kongTiXian_301, &$miaoBackSection, &$kongTiXian_105, $useRecharge = 0, $useMiao = 0, $useRong = 0)
    { 
        if ($useRecharge == 1) {
            $kongTiXian_301 += $moneyChange;
        }
        if ($useMiao == 1) {
            $miaoBackSection += $moneyChange;
        }
        if ($useRong == 1) {
            $kongTiXian_105 += $moneyChange;
        }
        $kongTiXian += $moneyChange;
    }

    /**
     * 协议金额减少
     * @param type $row
     * @param type $moneyChange
     * @param type $kongTiXian
     * @param type $kongTiXian_301
     * @param type $miaoBackSection
     * @param type $kongTiXian_105
     * @param type $useRecharge
     * @param type $useMiao
     * @param type $useRong
     * @return type
     * @author lyq     
     */
    public function greementAmountReduce($row, $moneyChange, &$kongTiXian, &$kongTiXian_301, &$miaoBackSection, &$kongTiXian_105, $useRecharge = 0, $useMiao = 0, $useRong = 0)
    {
        $recharge  = 0; //新充值
        $miao      = 0; //秒回
        $rong      = 0; //融资
        $tempMoney = 0;
        if ($kongTiXian_301 > 0 && $useRecharge == 1) {
            $kongMoney_301 = $kongTiXian_301 > $moneyChange ? $moneyChange : $kongTiXian_301;
            $tempMoney += $kongMoney_301;
            $kongTiXian_301 -= $kongMoney_301;
            $recharge      = $kongMoney_301;
        }

        if ($miaoBackSection > 0 && $useMiao == 1) {
            $kongMoney_5 = $moneyChange - $tempMoney;
            $kongMoney_5 = $miaoBackSection > $kongMoney_5 ? $kongMoney_5 : $miaoBackSection;
            $miaoBackSection -= $kongMoney_5;
            $tempMoney += $kongMoney_5;
            $miao        = $kongMoney_5;
        }

        if ($kongTiXian_105 > 0 && $useRong == 1) {
            $kongMoney_105 = $moneyChange - $tempMoney;
            $kongMoney_105 = $kongTiXian_105 > $kongMoney_105 ? $kongMoney_105 : $kongTiXian_105;
            $kongTiXian_105 -= $kongMoney_105;
            $tempMoney += $kongMoney_105;
            $rong          = $kongMoney_105;
        }

        $kongMoney = $kongTiXian > $tempMoney ? $tempMoney : $kongTiXian;
        $kongTiXian -= $kongMoney;
        $kong      = $kongMoney;

        return array('kong' => $kong, 'recharge' => $recharge, 'miao' => $miao, 'rong' => $rong);
    }

    /**
     * 协议金额返还，重新加入协议金额控制（比如撤标，还款冻结失败）
     * @param type $row
     * @param type $moneyChange
     * @param type $kongTiXian
     * @param type $kongTiXian_301
     * @param type $miaoBackSection
     * @param type $kongTiXian_105
     * @param type $useRecharge
     * @param type $useMiao
     * @param type $useRong
     * @author lyq     
     */
    public function greementAmountReturn($row, $keyStr, $moneyChange, &$kongTiXian, &$kongTiXian_301, &$miaoBackSection, &$kongTiXian_105, $arrRecharge, $arrMiao, $arrRong, $useRecharge = 0, $useMiao = 0, $useRong = 0)
    {
        //返回已使用的新充值金额,
        if (isset($arrRecharge[$keyStr]) && $arrRecharge[$keyStr] > 0 && $useRecharge == 1) {
            $kongTiXian_301 += $arrRecharge[$keyStr];
            $kongTiXian += $arrRecharge[$keyStr];
        }
        //返回已使用的秒回金额
        if (isset($arrMiao[$keyStr]) && $arrMiao[$keyStr] > 0 && $useMiao == 1) {
            $miaoBackSection += $arrMiao[$keyStr];
            $kongTiXian += $arrMiao[$keyStr];
        }
        //返回已使用的融资金额
        if (isset($arrRong[$keyStr]) && $arrRong[$keyStr] > 0 && $useRong == 1) {
            $kongTiXian_105 += $arrRong[$keyStr];
            $kongTiXian += $arrRong[$keyStr];
        }
    }

    /**
     * 协议金额展示样式
     * @param type $time
     * @param type $title
     * @param type $changeMoney
     * @param type $kongTiXian
     * @param type $kongTiXian_301
     * @param type $kongTiXian_105
     * @param type $miaoBackSection
     * @return string
     * @author lyq     
     */
    public function agreementAmountShow($time, $title, $changeMoney, $kongTiXian, $kongTiXian_301, $kongTiXian_105, $miaoBackSection, $returnEmptyStr = false)
    {
        if ($returnEmptyStr) {
            return '';
        }
        $str = "<tr><td>" . date("Y-m-d H:i:s", $time) . '</td><td> <B>' . $title . '-</B>:' . truncate($changeMoney) . '</td><td>￥' . truncate($kongTiXian) . '</td>';
        //$str = "<tr><td>" . date("Y-m-d H:i:s", $time) . '</td><td> <B>'.$title.'-</B>:' . number_format($changeMoney, 2) . '</td><td>￥' . number_format($kongTiXian, 2) . '(新充值：'.number_format($kongTiXian_301, 2) .'；秒回：'.number_format($miaoBackSection, 2) .'；融资：'.number_format($kongTiXian_105, 2) .')</td>';
        return $str;
    }

    /**
     * 拥有使用红包的权利(初步排除纯融资，工薪贷用户，并把这两种用户数据插入表withdraw_cash_user_type)
     * @param type $userId   用户ID
     * @param type $isMiao   是否为秒标  0：否    1：是
     * @return type
     * @author lyq     
     */
    public function hasUseRedpacketPower($userId, $isMiao = 0)
    {
        $userTypeModel = new WithdrawCashUserType();    
        $typeInfo         = $userTypeModel->getInfoByUserId($userId);     
        //纯融资以及工薪贷用户不准使用红包
        if (!empty($typeInfo) && ($typeInfo['user_type'] == 1 || $typeInfo['user_type'] == 3)) {
            if ($isMiao) {
                $mesStr = $typeInfo['user_type'] == 1 ? '纯融资用户不能投利是' : '工薪贷用户不能投利是';
            } else {
                $mesStr = $typeInfo['user_type'] == 1 ? '纯融资用户不能使用红包进行投资' : '工薪贷用户不能使用红包进行投资';
            }
            return array('status' => 0, 'type' => $typeInfo['user_type'], 'info' => $mesStr);
        }
        $userModel = new Iuser();
        $userInfo = $userModel->getUserInfoByUserId($userId, 'user_id,username,type_id,real_status'); 
        //工薪贷用户不允许使用红包投资
        if ($userInfo['type_id'] == 5) {
            //数据入表
            $data['user_id']   = $userInfo['user_id'];
            $data['user_name'] = $userInfo['username'];
            $data['user_type'] = 3; //工薪贷用户  
            $userTypeModel->insertInfo($data);
            $mesStr            = $isMiao ? '工薪贷用户不能利是' : '工薪贷用户不能使用红包进行投资';
            return array('status' => 0, 'type' => 3, 'info' => $mesStr);
        }
        if ($userInfo['real_status'] != 1) {
            return array('status' => 0, 'type' => 3, 'info' => '无实名');
        }

        //纯融资用户，即拥有企业创业额度的用户不允许使用红包
        $userAmountModel = new IuserAmount();
        $amountInfo = $userAmountModel->getAmountInfo($userId);
        if (!empty($amountInfo)) {
            //数据入表
            $data['user_id']   = $userInfo['user_id'];
            $data['user_name'] = $userInfo['username'];
            $data['user_type'] = 1; //融资用户
            $userTypeModel->insertInfo($data);
            $mesStr            = $isMiao ? '纯融资用户不能利是' : '纯融资用户不能使用红包进行投资';
            return array('status' => 0, 'type' => 1, 'info' => $mesStr);
        }

        //纯投资判断
        $accountModel = new AccountModel();
        $accountInfo = $accountModel->getUserAccountInfo($userId);      
        if ($accountInfo['borrow_money'] < 1) {
            //15天内是否有还款，若无，则定义为纯投资  
            $repaymentModel = new IborrowRepayment();
            $repayInfo = $repaymentModel->repayBetweenFiftentDay($userId);
            if (empty($repayInfo)) {
                $mesStr = $isMiao ? '纯投资用户可以投利是' : '纯投资用户可以使用红包进行投资';
                return array('status' => 1, 'type' => 2, '纯投资用户可以使用红包进行投资');
            }
        }
        $mesStr = $isMiao ? '投融资用户可以投利是' : '投融资用户可以使用红包进行投资';
        return array('status' => 1, 'type' => 5, 'info' => $mesStr);
    }

    /**
     * 获取用户投秒金额及投红包金额
     * @param int $userId 用户ID
     * @param type $isMiao   是否为秒标  0：否    1：是
     * @author lyq    
     * * */
    public function getCashControl($userId, $isMiao = 0)
    {
        //分4种用户：纯融资，纯投资，工薪贷，投融账号
        $hasUseRedpacketPowerInfo = $this->hasUseRedpacketPower($userId, $isMiao);   

        //资金属性金额
        $htmlStr           = '';
        $availableCash = $this->availableMoney($userId, $htmlStr, true, 2);        
        if (empty($availableCash)) {
            $availableCash['rong_money']         = 0; //融资
            $availableCash['new_recharge_money']  = 0; //新充值
            $availableCash['miao_back_money']     = 0; //秒回
            $availableCash['withdrawal_money']  = 0; //可提现
            $availableCash['miao_money']      = 0; //可投秒
            $availableCash['redpacket_money'] = 0; //可投红包
        }
        
        if (isset($availableCash['status']) && $availableCash['status'] == 11) {
            return ['status' => 0, 'remesg' => '无法查询到有效身份信息！', 'overdue_status' => 0];
        } elseif (isset($availableCash['status']) && $availableCash['status'] == 12) {
            return ['status' => 0, 'remesg' => '您已逾期,请尽快还款！', 'overdue_status' => 1];
        }

        if (!$hasUseRedpacketPowerInfo['status']) {//纯融资,工薪贷用户不允许使用红包进行投资
            $availableCash['rong_money'] += $availableCash['new_recharge_money'];
            $availableCash['rong_money'] += $availableCash['miao_back_money'];
            $availableCash['rong_money'] += $availableCash['withdrawal_money'];
            $availableCash['new_recharge_money']  = 0; //新充值
            $availableCash['miao_back_money']     = 0; //秒回
            $availableCash['withdrawal_money']  = 0; //可提现
            $availableCash['miao_money']      = 0; //可投秒
            $availableCash['redpacket_money'] = 0; //可投红包
        }

        $availableCash['status'] = 1;
        return $availableCash;
    }

}
