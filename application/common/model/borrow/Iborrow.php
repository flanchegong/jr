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
namespace application\common\model\borrow;

use application\common\model\Base;
use think\Db;

class Iborrow extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'iborrow';

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
        'bnum'             => 'borrow_num',
        'btype'            => 'borrow_type',
        'uid'              => 'user_id',
        'uname'            => 'username',
        'bname'            => 'name',
        'borrow_status'    => 'status',
        'border'           => 'order',
        'rtime'            => 'repayment_time',
        'forst_money'      => 'forst_account',
        'raccount'         => 'repayment_account',
        'ryesaccount'      => 'repayment_yesaccount',
        'ryesinterest'     => 'repayment_yesinterest',
        'repayment_method' => 'repaystyle',
        'total_money'      => 'account',
        'show_money'       => 'finacing_amount_virtual',
        'yes_money'        => 'account_yes',
        'rate'             => 'apr',
        'lowest_money'     => 'lowest_account',
        'most_money'       => 'most_account',
        'award_money'      => 'award_account',
        'remark'           => 'content',
    ];

    /**
     * @uses 首页列表查询
     * @author jhl
     * @param string/array $where
     * @param int $limit
     */
    public static function indexIborrowlist($where, $limit)
    {
        $list = Db::name('iborrow')
                ->field('id,`name`,apr,repaystyle,fatalism,time_limit,borrow_type,account,account_yes')
                ->where($where)
                ->order('addtime desc')
                ->limit($limit)
                ->select();
        return $list;
    }

    /**
     * @uses 查询平台开心利是
     * @author jhl
     */
    public static function getWelfareStatus()
    {
        $info = Db::name('iborrow')->field('id')
                ->where(array(
                    'borrow_type' => 5,
                    'status'      => 1,
                    'end_time'    => array('egt', time())
                ))
                ->find();
        return $info;
    }

    /**
     * @desc 方法:获取融资中列表数据
     * @author liuj
     * @date 2017-6-19
     * @access public
     * @return boole
     */
    public function getFinancingList($params = [], $userId)
    {
        $where['status']  = 1;
        $where['user_id'] = $userId;
        if (isset($params['date']) && $params['date'] != '')
        {
            switch ($params['date'])
            {
                case 1:
                    $where['addtime'] = ['between', [strtotime('today'), strtotime('tomorrow')]];
                    break;
                case 7:
                    $where['addtime'] = ['between', [strtotime('today -7 days'), strtotime('today')]];
                    break;
                case 30:
                    $where['addtime'] = ['between', [strtotime('today -1 month'), strtotime('today')]];
                    break;
                case 0:
                    if (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['addtime'] = ['between', [strtotime($params['stime']), strtotime($params['etime'] . ' 23:59:59')]];
                    }
                    elseif (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] == '')
                    {
                        $where['addtime'] = ['>=', strtotime($params['stime'])];
                    }
                    elseif (isset($params['stime']) && $params['stime'] == '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['addtime'] = ['<', strtotime($params['etime'] . ' 23:59:59')];
                    }
                    break;
            }
        }
        if (isset($params['borrow_name']) && $params['borrow_name'] != '')
        {
            $where['name'] = $params['borrow_name'];
        }
        $field = 'fatalism,id,borrow_num,name,account,borrow_type,success_time,apr,repaystyle,award_type,award_rate,award_account,time_limit,account_yes,end_time,addtime,destine_type,destine_time,pear_base';

        $sqlArr = [
            'where'     => $where,
            'field'     => $field,
            'order'     => 'addtime desc',
            'page'      => $params['current_page'],
            'list_rows' => $params['list_rows'],
        ];
        $data   = $this->getList($sqlArr, true);
        if (!empty($data['data']))
        {
            foreach ($data['data'] as $k => $v)
            {
                $data['data'][$k]['name']            = csubstr($v['name'], 0, 30);
                $data['data'][$k]['account']         = format_num($v['account']);
                $data['data'][$k]['award_account']   = format_num($v['award_account']);
                $data['data'][$k]['deffaccount']     = format_num($v['account'] - $v['account_yes']);
                $data['data'][$k]['bar']             = strsubstr(($v['account_yes'] / $v['account']) * 100);
                $data['data'][$k]['time_difference'] = $v['end_time'] - time();
                $data['data'][$k]['type_id']         = $v['pear_base'] > 0 ? true : false;
            }
            $data['data'] = $this->parseFieldsMap($data['data']);
        }
        return $data;
    }

    /**
     * @desc 方法:获取还款中列表数据
     * @author liuj
     * @date 2017-6-19
     * @access public
     * @return boole
     */
    public function getRepaymentList($params = [], $userId = 0)
    {
        if ($userId == 0)
        {
            return false;
        }
        $where            = [];
        $where['user_id'] = $userId;
        $where['status']  = 7;
        if (isset($params['date']) && $params['date'] != '')
        {
            switch ($params['date'])
            {
                case 1:
                    $where['addtime'] = ['between', [strtotime('today'), strtotime('tomorrow')]];
                    break;
                case 7:
                    $where['addtime'] = ['between', [strtotime('today -7 days'), strtotime('today')]];
                    break;
                case 30:
                    $where['addtime'] = ['between', [strtotime('today -1 month'), strtotime('today')]];
                    break;
                case 0:
                    if (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['addtime'] = ['between', [strtotime($params['stime']), strtotime($params['etime'] . ' 23:59:59')]];
                    }
                    elseif (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] == '')
                    {
                        $where['addtime'] = ['>=', strtotime($params['stime'])];
                    }
                    elseif (isset($params['stime']) && $params['stime'] == '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['addtime'] = ['<', strtotime($params['etime'] . ' 23:59:59')];
                    }
                    break;
            }
        }
        if (isset($params['borrow_name']) && $params['borrow_name'] != '')
        {
            $where['name'] = $params['borrow_name'];
        }
        $field  = 'fatalism,id,borrow_num,name,account,borrow_type,success_time,apr,repaystyle,award_type,award_rate,award_account,time_limit,account_yes,end_time,addtime,repayment_account,repayment_yesaccount';
        $sqlArr = [
            'where'     => $where,
            'field'     => $field,
            'order'     => 'addtime desc',
            'page'      => $params['current_page'],
            'list_rows' => $params['list_rows'],
        ];
        $data   = $this->getList($sqlArr, true);
        if (!empty($data['data']))
        {
            foreach ($data['data'] as $k => $v)
            {
                $data['data'][$k]['name']                 = csubstr($v['name'], 0, 30);
                $data['data'][$k]['repayment_no']         = format_num($v['repayment_account'] - $v['repayment_yesaccount']);
                $data['data'][$k]['repayment_yesaccount'] = format_num($v['repayment_yesaccount']);
                $data['data'][$k]['deffaccount']          = format_num($v['account'] - $v['account_yes']);
                $data['data'][$k]['bar']                  = strsubstr(($v['account_yes'] / $v['account']) * 100);
            }
            $data['data'] = $this->parseFieldsMap($data['data']);
        }
        return $data;
    }

    /**
     * @desc 方法:获取已还清项目
     * @author liuj
     * @date 2017-6-19
     * @access public
     * @return boole
     */
    public function getPaidList($params = [], $userId = 0)
    {
        if ($userId == 0)
        {
            return false;
        }
        $where                   = [];
        $where['borrow.user_id'] = $userId;
        $where['borrow.status']  = 8;
       if (isset($params['date']) && $params['date'] != '')
        {
            switch ($params['date'])
            {
                case 1:
                    $where['borrow.addtime'] = ['between', [strtotime('today'), strtotime('tomorrow')]];
                    break;
                case 7:
                    $where['borrow.addtime'] = ['between', [strtotime('today -7 days'), strtotime('today')]];
                    break;
                case 30:
                    $where['borrow.addtime'] = ['between', [strtotime('today -1 month'), strtotime('today')]];
                    break;
                case 0:
                  if (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['borrow.addtime'] = ['between', [strtotime($params['stime']), strtotime($params['etime'] . ' 23:59:59')]];
                    }
                    elseif (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] == '')
                    {
                        $where['borrow.addtime'] = ['>=', strtotime($params['stime'])];
                    }
                    elseif (isset($params['stime']) && $params['stime'] == '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['borrow.addtime'] = ['<', strtotime($params['etime'] . ' 23:59:59')];
                    }
                    break;
            }
        }
       if (isset($params['borrow_name']) && $params['borrow_name'] != '')
        {
            $where['name'] = $params['borrow_name'];
        }
        $field = 'fatalism,borrow.id,borrow.borrow_num,name,account,borrow_type,success_time,apr,repaystyle,award_type,award_rate,award_account,time_limit,account_yes,end_time,borrow.addtime,repayment.repayment_time,repayment.repayment_yestime';
        $data  = $this->alias('borrow')
                ->field($field)
                ->join('itd_iborrow_repayment repayment', 'repayment.borrow_num=borrow.borrow_num  and repayment.`order`=borrow.time_limit-1')
                ->where($where)
                ->order('borrow.addtime desc')
                ->paginate($params['list_rows'], false, ['page' => $params['current_page']])
                ->toArray();
        if (!empty($data['data']))
        {
            foreach ($data['data'] as $k => $v)
            {
                $data['data'][$k]['name']          = csubstr($v['name'], 0, 30);
                $data['data'][$k]['account']       = format_num($v['account'], 2);
                $data['data'][$k]['award_account'] = format_num($v['award_account'], 2);
                $data['data'][$k]['award_account'] = format_num($v['account_yes'], 2);
                $data['data'][$k]['deffaccount']   = format_num($v['account'] - $v['account_yes']);
                $data['data'][$k]['bar']           = format_num(($v['account_yes'] / $v['account']) * 100, 2);
            }
            $data['data'] = $this->parseFieldsMap($data['data']);
        }
        return $data;
    }

    /**
     * @desc 方法:获取融资记录
     * @author liuj
     * @date 2017-6-19
     * @access public
     * @return boole
     */
    public function getFinancingRecordsList($params=[], $userId = 0)
    {
        if ($userId == 0)
        {
            return false;
        }
        $where            = [];
        $where['user_id'] = $userId;
        $where['status']  = ['>', 0];
       if (isset($params['date']) && $params['date'] != '')
        {
             switch ($params['date'])
            {
                case 1:
                    $where['addtime'] = ['between', [strtotime('today'), strtotime('tomorrow')]];
                    break;
                case 7:
                    $where['addtime'] = ['between', [strtotime('today -7 days'), strtotime('today')]];
                    break;
                case 30:
                    $where['addtime'] = ['between', [strtotime('today -1 month'), strtotime('today')]];
                    break;
                case 0:
                    if (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['addtime'] = ['between', [strtotime($params['stime']), strtotime($params['etime'] . ' 23:59:59')]];
                    }
                    elseif (isset($params['stime']) && $params['stime'] != '' && isset($params['etime']) && $params['etime'] == '')
                    {
                        $where['addtime'] = ['>=', strtotime($params['stime'])];
                    }
                    elseif (isset($params['stime']) && $params['stime'] == '' && isset($params['etime']) && $params['etime'] != '')
                    {
                        $where['addtime'] = ['<', strtotime($params['etime'] . ' 23:59:59')];
                    }
                    break;
            }
        }
        if (isset($params['borrow_name']) && $params['borrow_name'] != '')
        {
            $where['name'] = $params['borrow_name'];
        }
        $field = 'fatalism,id,borrow_num,name,account,borrow_type,success_time,apr,repaystyle,award_type,award_rate,award_account,time_limit,account_yes,end_time,status,addtime';
         $sqlArr = [
            'where'     => $where,
            'field'     => $field,
            'order'     => 'addtime desc',
            'page'      => $params['current_page'],
            'list_rows' => $params['list_rows'],
        ];
        $data   = $this->getList($sqlArr, true);
        if (!empty($data['data']))
        {
            foreach ($data['data'] as $k => $v)
            {
                $data['data'][$k]['name']            = csubstr($v['name'], 0, 30);
                $data['data'][$k]['deffaccount']     = strsubstr($v['account'] - $v['account_yes']);
                $data['data'][$k]['bar']             = strsubstr(($v['account_yes'] / $v['account']) * 100);
                $data['data'][$k]['end_time']        = $v['end_time'];
                $data['data'][$k]['time_difference'] = $v['end_time'] - time();
            }
            $data['data'] = $this->parseFieldsMap($data['data']);
        }
        return $data;
    }

    /**
     * @desc 方法:获取我的回复
     * @author liuj
     * @date 2017-6-19
     * @access public
     * @return boole
     */
    public function getMyReply($userId)
    {
        $where['iborrow.user_id'] = $userId;
        $where['ntype']           = 2;
        $where['fid']             = 0;
        $data                     = $this->alias('iborrow')->join('itd_icomment icomment', 'iborrow.id=icomment.article_id')
                ->where($where)
                ->field('icomment.id,icomment.article_id,icomment.user_id,icomment.content,icomment.addtime,icomment.istopic,icomment.fid,iborrow.name,iborrow.username,borrow_num')
                ->order('icomment.addtime desc')
                ->paginate(20)
                ->toArray();
        if (!empty($data['data']))
        {
            foreach ($data['data'] as $k => $v)
            {
                $data['data'][$k]['content'] = csubstr($v['content'], 0, 150);
                $data['data'][$k]['eAvatar'] = file_exists(input('server.DOCUMENT_ROOT') . "/Uploads/user/$userId/small.jpg");
            }
            $data['data'] = $this->parseFieldsMap($data['data']);
        }
        return $data;
    }

    /**
     * 供应链金融
     * 
     */
    public function supplyChain()
    {

        $rs = $this->all(function($query) {
            $filed = "id,borrow_type,name,apr,award_rate,repaystyle,time_limit,fatalism,lowest_account,status,account,account_yes,award_account,user_id,borrow_num";
            $sql1  = $query->name("iborrow")->field($filed)
                            ->where([
                                'borrow_type'  => array('in', [1, 9]), //企业融资，创业融资
                                'status'       => 8, //募集中
                                'destine_type' => ['<=', 0], //非定时标
                                'end_time'     => ['>=', time()]
                            ])
                            ->order("addtime", 'desc')->buildSql(true);

            $sql2 = $query->name('iborrow')
                            ->field($filed)
                            ->where([
                                'borrow_type'  => ['in', [1, 9]], //企业融资，创业融资
                                'status'       => 7, //取还款中
                                'destine_type' => ['<=', 0], //非定时标
                            ])
                            ->order('repayment_time', 'desc')->limit(3)->buildSql(true);
            $query->table($sql1 . " a")->union(Db::table($sql2 . " b")->buildSql(), true);
        });

        return parse_fields_map(collection($rs)->toArray(), $this->_map);
    }

    /**
     * 金融超市-资产金融 
     * 
     * @return []
     * 
     */
    public function asset()
    {
        $data   = [];
        $where  = [
            'borrow_type' => array('in', [6, 7, 11]), //净值融资、股东融资、工薪融资
            'status'      => 1, //募集中
            'end_time'    => ['>', time()]
        ];
        $months = ['thirty_info' => [], 'one_month_info' => [1], 'two_month_info' => [2, 3], 'four_month_info' => [4, 5, 6]];
        foreach ($months as $key => $value)
        {
            if ($key == 'thirty_info')
            {
                $where['fatalism'] = ['>', 0];
            }
            if ($key == 'one_month_info')
            {
                $where['fatalism'] = 0;
            }
            if ($value)
            {
                $where['time_limit'] = ['in', $value];
            }
            $rs = $this->all(function($query) use ($where) {
                $query->field('id,borrow_type,name,apr,award_rate,repaystyle,time_limit,fatalism,lowest_account,status,TRUNCATE(account_yes/account*100,2) as bar,  account,account_yes,award_account')
                        ->where($where)
                        ->order('(apr + award_rate ) DESC');
            });
            if ($rs)
            {
                $data[$key]               = collection($rs)->toArray();
                $data[$key]['award_rate'] = get_award_rate($data[$key]['award_rate'], $data[$key]['award_account'], $data[$key]['account']);
            }
        }
        return parse_fields_map($data, $this->_map);
    }

    /**
     * 净值列表
     * 
     * @param int $page 页码
     * @param int $apr 利率
     * @param int $timelimit 期限
     * @param int $repaystyle 还款方式
     *
     */
    public function assetList($apr = '', $timelimit = '', $repaystyle = '')
    {
        //标查询条件
        //年利率 + 奖励排序、下一个排序状态 
        $order = 'addtime DESC ';
        //标默认刷选条件
        $where = array(
            'borrow_type' => ['in', [6, 7, 11]], //净值融资、股东融资、工薪融资
            'status'      => 8, //募集中
        );

        $nextdata = 0; //年利率下一个排序状态
        if ($apr == 0)
        {
            $nextdata = 1;
        }
        if ($apr == 1)
        {
            $order    = '(apr + award_rate ) DESC ';
            $nextdata = 2;
        }
        if ($apr == 2)
        {
            $order = '(apr + award_rate ) ASC ';
        }


        //期限    
        if ($timelimit == 1)
        {
            $where['fatalism'] = ['>', 0];
        }
        if ($timelimit == 2)
        {
            $where['fatalism']   = 0;
            $where['time_limit'] = 1;
        }
        if ($timelimit == 3)
        {
            $where['time_limit'] = ['in', [2, 3]];
        }
        if ($timelimit == 4)
        {
            $where['time_limit'] = ['in', [4, 5, 6]];
        }
        //还款方式

        if ($repaystyle == 1)
        {
            $where['repaystyle']  = ['<=', 0];
            $where['borrow_type'] = ['<>', 5];
        }
        if ($repaystyle == 2)
        {
            $where['repaystyle'] = 4;
        }
        if ($repaystyle == 3)
        {
            $where['repaystyle'] = 3;
        }
        $rs = $this->field("id,fatalism,`status`,`name`,borrow_type, repaystyle,time_limit,`use`,account,apr,
                    award_type,award_rate,award_account,account_yes,addtime,end_time,lowest_account,most_account,
                    repayment_time as fulltime,(end_time-UNIX_TIMESTAMP()) as time_difference,IF(isnull(borrowpwd),
                    '',MD5(CONCAT(MD5(borrowpwd),'IT*#@!8f'))) as borrowpwd,destine_time,destine_type, success_time,truncate(account_yes/account*100,2) bar,
                    (account-account_yes)*1 as remain")
                ->where($where)
                ->order($order)
                ->paginate(10);

        $list = [];
        if ($rs)
        {
            $list = $rs->toArray();
        }
        foreach ($list['data'] as $value)
        {
            $value['repaystyle_msg'] = get_repaystyle($value['repaystyle'], $value['borrow_type']);
            $value['showday']        = get_repay_style_name($value['repaystyle'], $value['fatalism'], $value['time_limit']);
            $value['award_rate']     = get_award_rate($value['award_rate'], $value['award_account'], $value['account']);
            $data[]                  = $value;
        }
        if ($data)
        {
            $list['data'] = parse_fields_map($data, $this->_map);
        }

        return $list;
    }

    /**
     * 已完成的标
     * 
     * @param string $from 标类型[asset|supplychain]
     * 
     */
    public function completeBorrow($from)
    {
        //取前10天的标 
        $where['success_time'] = ['>', strtotime("-300 days")];
        //还款中，完成还款的标
        $where['status']       = ['in', [7, 8]];
        //来自资产金融---移至if中，为了应付其他的搜索
        if ($from == 'asset')
        {
            $where['borrow_type'] = ['in', [6, 7, 11]];
            //供应链金融
        }
        if ($from == 'supplychain')
        {
            $where['borrow_type'] = ['in', [1, 9]];
        }
        $list  = [];
        $rs    = $this->alias('Bb FORCE INDEX(ix_success_time)')
                ->field('`status`,fatalism,id, `name`, borrow_type, repaystyle,time_limit,`use`,borrowpwd,borrow_num,
                    account,apr,award_type,award_rate,award_account,account_yes,addtime,end_time,truncate(account_yes/account*100,2) as bar,
                    repayment_time as fulltime')
                ->where($where)
                ->order('repayment_time desc')
                ->paginate(20);
        $stats = [7 => '还款中', '已还清'];
        if ($rs)
        {
            $list = $rs->toArray();
        }
        foreach ($list['data'] as $k => $v)
        {
            $list['data'][$k]['status_msg'] = $stats[$v['status']];
            if (!$v['borrowpwd'] && $v['status'] == 1 && $v['destine_type'] != 1)
            {
                $list['data'][$k]['invest_pwd_value'] = '';
            }
            else
            {
                $list['data'][$k]['invest_pwd_value'] = 1;
            }
            $list['data'][$k]['award_rate']  = get_award_rate($v['award_rate'], $v['award_account'], $v['account']);
            $list['data'][$k]['gyl_zc_type'] = $from;
        }
        if ($list['data'])
        {
            $list['data'] = parse_fields_map($list['data'], $this->_map);
        }

        return $list;
    }

}
