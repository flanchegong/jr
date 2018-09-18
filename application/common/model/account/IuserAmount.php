<?php
 
namespace application\common\model\account;
   
use application\common\model\account\AmountLog;
use application\common\model\Base;
use think\Db;
/**
 * 用户额度类
 * 
 * 
 */
class IuserAmount extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'iuser_amount';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 更新用户额度
     * 
     * @param int $user_id 用户ID
     * @param int $code_id 额度类型ID 
     * @param float $amount 冻结额度
     * @param string $borrow_num 标编码
     * @param int $type 额度业务类型，可选，默认值 ：122 
     * @param string $remark  额度日志备注，可选，默认值 ：发标冻结
     * @return bool
     * 
     */
    public function updateUserAmount($user_id, $code_id, $amount, $borrow_num, $type = 122, $remark = '发标冻结')
    {
        $this->user_id = $user_id; 
          $data          = $this->get(['user_id'=>$user_id,'codeid'=>$code_id]);
          
        if (!$data)
        {
            return false;
        }
        if ($data['credit_use'] >= $amount)
        {
            $update_result1 = $this->where(['user_id' => $this->user_id, "codeid" => $code_id])->update([
                'credit_use'   => ['exp', sprintf("%s%s%.2f", "credit_use", $amount >= 0 ? "+" : "", $amount)], //可用额度
                'credit_nouse' => ['exp', sprintf("%s%s%.2f", "credit_nouse", $amount >= 0 ? "+" : "", $amount)]//冻结额度
            ]);
 
            //写入额度修改日志
            $log            = new AmountLog();
            $update_result2 = $log->add([
                'user_id'      => $this->user_id, 'codeid'       => $code_id, 'credit'       => $data['credit'],
                'credit_use'   => $data['credit_nouse'] - $amount,
                'credit_nouse' => $data['credit_nouse'] + $amount, 'changetotal'  => 0,
                'changeuse'    => -$amount, 'changenouse'  => $amount, 'raleNum'      => $borrow_num,
                'remark'       => $remark,
                'type'         => $type
            ]);
            return $update_result1 && $update_result2;
        }
        return false;
    }

    /**
     * 获取用户类型信息
     * @param type $userId
     * @return type
     * @date 2017
     * @author lyq
     */
    function getAmountInfo($userId){
        $iWhere['user_id'] = array('eq', $userId);
        $iWhere['credit']  = array('egt', 1); //总额度大于等于1，排除掉额度被回收的用户
        $iWhere['codeid']  = array(array('eq', 93), array('eq', 96), 'or');
        $data              = Db::name('iuser_amount')->where($iWhere)->find();
        return empty($data) ? array() : $data;
    }
   
}
