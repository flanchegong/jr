<?php

namespace application\common\model\borrow;

use think\Db;
use application\common\model\user\IuserPic;
use application\common\model\Base;

/**
 * 投标模型
 * @author ydx
 */
class IborrowTender extends Base
{

    private $pic;

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'iborrow_tender';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';


    public function initialize()
    {
        parent::initialize();
        $this->pic = new IuserPic();
    }

    /*
     * 获取创业标信息
     * @param string $uid 用户ID 
     * 
     * @return  返回数组，包括 雅堂之家合作协议，以及各种图片
     * * */

    public function getEntrepreneurs($uid)
    {

        $array['xieyi'] = [
            'title' => '雅堂之家项目计划书',
            'data'  => $this->pic->getPicByType(16, $uid)
        ];
        $array['hetong'] = [
            'title' => '雅堂金融融资协议',
            'data'  => $this->pic->getPicByType(17, $uid)
        ];
        $array['huankuan'] = [
            'title' => '还款承诺书',
            'data'  => $this->pic->getPicByType(18, $uid)
        ];
        $array['others'] = [
            'title' => '其他',
            'data'  => $this->pic->getPicByType(0, $uid)
        ];

        $rs = Db::name('verify')->where([
            'etype'   => 205,
            'user_id' => $uid,
            'result1' => 1
        ])->order(['applydate' => 'desc'])->find();
        if ($rs)
        {
            $array2 = $this->pic->getOthersPic($uid);
            $type = [
                "其他",
                "身份证",
                "工资银行流水",
                "地址证明 ",
                "其他资产证明",
                "人行征信报告",
                "话费清单 ",
                "工资月流水",
                "鹏元资料xml",
                "鹏元资料pdf",
                "手持身份照",
                "房产证明",
                "购车证明",
                "婚姻证明",
                "户籍证明",
                "学历证明",
                "房屋租赁合同书",
                "融资咨询服务合同书",
                "还款承诺书"
            ];

            foreach ($array2 as $key => $value)
            {
                $array2[$key]['title'] = $type[$value['ptype']];
                $array2[$key]['time'] = date('Y-m-d', strtotime($value['puttime']));
            }
        }
        return [
            'cooperative'       => $array,
            'other_certificate' => $array2
        ];
    }

    /**
     * 获取10条投标记录
     * @param int $borrow_num 标编码
     * @param int $rows 行数  默认值 10
     */
    public function getTenderList($borrow_num, $rows = 10)
    {
        return $this->field('id,account,money,addtime,type,username')->where(['borrow_num' => $borrow_num])
                    ->order('id desc')->paginate($rows)->toArray();
    }

    public function getTypeAttr($param)
    {
        //1：网站自动；2：网站手动；3：移动端】
        $type = [
            1 => '网站自动',
            '网站手动',
            '安卓',
            'IOS',
            '微信'
        ];
        return $type[$param];
    }

    public function getAwardLeft($uid, $borrow_num)
    {
        return $this->where([
            'borrow_num' => $borrow_num,
            'user_id'    => $uid,
            'status'     => 0
        ])->sum('account');
    }

    /**
     * 获取用户总投资金额
     * @param int $uid 用户ID
     * @param string $borrow_num 标编码
     */
    public function getUserTenderAmount($uid, $borrow_num)
    {
        return $this->where([
            'borrow_num' => $borrow_num,
            'user_id'    => $uid
        ])->sum('account');
    }



    /**
     * 投资记录列表
     * @param type $userId
     * @param type $borrowType           标类型
     * @param type $tenderStartTime      投标区间开始时间
     * @param type $tenderEndTime        投标区间结束时间
     * @param type $page                 页码
     * @param type $pagesize             每页显示数据大小
     * @author lingyq
     * @date  2017/7/31
     * @return type
     */
    public function investBorrowRecordList($userId, $borrowType, $tenderStartTime, $tenderEndTime,$page,$pagesize)
    {
        $field = "b.name,b.id,b.borrow_num,b.borrow_type,b.status,b.time_limit,b.apr,b.award_rate,sum(t.account) as tender_money,max(t.addtime) as tender_time,"
                . "sum(c.repay_account) as collect_money,sum(c.interest_manage) as manage_fee";
        //获取待收列表
        $where['t.user_id'] = $userId;
        if ($borrowType)
        {
            $where['b.borrow_type'] = $borrowType;
        }
        $where['t.addtime'] = array(array('egt',$tenderStartTime),array('elt',$tenderEndTime));  
        $data = Db::name('iborrow_tender')
                ->alias('t')
                ->join('itd_iborrow b','b.borrow_num = t.borrow_num','inner')
                ->join('itd_iborrow_collection c','c.borrow_num = t.borrow_num','inner')
                ->field($field)
                ->where($where)     
                ->group('t.borrow_num')
                ->order('b.id desc')
                ->paginate($pagesize, false, ['page' => $page])
                ->toArray();     
        return $data;
    }
}
