<?php
/** 
 * @Name FundTypeLog.php
 * @Author lingyq
 * @Version stable 1.0
 * @Date 2017-10-17
 * @Description 模型基类
 */
namespace application\common\model\borrow;

use application\common\model\Base;
use think\Db;

class FundTypeLog extends  Base
{
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'fund_manage_fund_type_log';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = '';

    public function insertFundManageFundTypeLog($tenderMoney,$tenderInfo,$useData)
    {
        $data['user_id'] = $tenderInfo['user_id'];
        $data['item_type'] = $tenderInfo['borrow_type'];//标类型
        $data['business_code'] = $tenderInfo['business_num'];//业务编码(目前为投标编码)
        $data['business_type_id'] = $tenderInfo['business_type'];//网站业务类型
        $data['total_amount'] = $tenderMoney;//投标有效金额
        $data['new_recharge_amount'] = $useData['useRechargeMoney'];//使用新充值金额
        $data['second_item_back_amount'] = $useData['useMiaoBackMoney'];//使用秒回金额
        $data['financing_amount'] = $useData['useRongMoney'];//使用融资金额
        $data['can_withdraw_cash_amount'] = $useData['useWithdrawalMoney'];//使用可提现金额
        $data['create_time'] = date("Y-m-d H:i:s",time());        
        $id = Db::name($this->_table)->insert($data);
        return $id;
    }
}
