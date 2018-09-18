<?php

namespace application\common\logic\borrow;

class FundTypeLogLogic
{
    /**
     * 资金使用顺序：新充值，秒回，融资，可提现
     * @param type $tenderMoney         投资金额
     * @param type $newRechargeMoney    新充值金额
     * @param type $miaoBackMoney       秒回金额
     * @param type $rongMoney           融资金额
     * @param type $withdrawalMoney     可提现金额
     * @author lingyq
     * @date 2017/8/9
     * @return type
     */
    public function capitalUsageOrderOne($tenderMoney,$newRechargeMoney,$miaoBackMoney,$rongMoney,$withdrawalMoney){   
        $needMoney = $tenderMoney;        
        $useRechargeMoney = 0;
        $useMiaoBackMoney = 0;
        $useRongMoney = 0;
        $useWithdrawalMoney = 0;
        if($newRechargeMoney > 0 ){//新充值
            $useRechargeMoney = $newRechargeMoney > $tenderMoney ? $tenderMoney : $newRechargeMoney;
            $useRechargeMoney = format_num($useRechargeMoney,4);
            $needMoney -= $useRechargeMoney;       
        }

        if($miaoBackMoney > 0 && $needMoney > 0){//秒回
            $useMiaoBackMoney = $miaoBackMoney > $needMoney ? $needMoney : $miaoBackMoney;
            $useMiaoBackMoney = format_num($useMiaoBackMoney,4);
            $needMoney -= $useMiaoBackMoney;            
        }
        
        if($rongMoney > 0 && $needMoney > 0){//融资
            $useRongMoney = $rongMoney > $needMoney ? $needMoney : $rongMoney;
            $useRongMoney = format_num($useRongMoney,4);
            $needMoney -= $useRongMoney;            
        }
        
        if($withdrawalMoney > 0 && $needMoney > 0){//可提现
            $useWithdrawalMoney = $withdrawalMoney > $needMoney ? $needMoney : $withdrawalMoney;
            $useWithdrawalMoney = format_num($useWithdrawalMoney,4);
            $needMoney -= $useMiaoBackMoney;            
        }
        return array('useRechargeMoney'=>$useRechargeMoney,'useMiaoBackMoney'=>$useMiaoBackMoney,'useRongMoney'=>$useRongMoney,'useWithdrawalMoney'=>$useWithdrawalMoney);
    }
    /**
     * 资金使用顺序：新充值，秒回，可提现, 融资
     * @param type $tenderMoney         投资金额
     * @param type $newRechargeMoney    新充值金额
     * @param type $miaoBackMoney       秒回金额
     * @param type $rongMoney           融资金额
     * @param type $withdrawalMoney     可提现金额
     * @author lingyq
     * @date 2017/8/9
     * @return type
     */
    public function capitalUsageOrderTwo($tenderMoney,$newRechargeMoney,$miaoBackMoney,$rongMoney,$withdrawalMoney){   
        $needMoney = $tenderMoney;        
        $useRechargeMoney = 0;
        $useMiaoBackMoney = 0;
        $useRongMoney = 0;
        $useWithdrawalMoney = 0;
        if($newRechargeMoney > 0 ){//新充值
            $useRechargeMoney = $newRechargeMoney > $tenderMoney ? $tenderMoney : $newRechargeMoney;
            $useRechargeMoney = format_num($useRechargeMoney,4);
            $needMoney -= $useRechargeMoney;       
        }

        if($miaoBackMoney > 0 && $needMoney > 0){//秒回
            $useMiaoBackMoney = $miaoBackMoney > $needMoney ? $needMoney : $miaoBackMoney;
            $useMiaoBackMoney = format_num($useMiaoBackMoney,4);
            $needMoney -= $useMiaoBackMoney;            
        }        
        
        if($withdrawalMoney > 0 && $needMoney > 0){//可提现
            $useWithdrawalMoney = $withdrawalMoney > $needMoney ? $needMoney : $withdrawalMoney;
            $useWithdrawalMoney = format_num($useWithdrawalMoney,4);
            $needMoney -= $useWithdrawalMoney;            
        }
        
        if($rongMoney > 0 && $needMoney > 0){//融资
            $useRongMoney = $rongMoney > $needMoney ? $needMoney : $rongMoney;
            $useRongMoney = format_num($useRongMoney,4);
            $needMoney -= $useRongMoney;            
        }

        return array('useRechargeMoney'=>$useRechargeMoney,'useMiaoBackMoney'=>$useMiaoBackMoney,'useRongMoney'=>$useRongMoney,'useWithdrawalMoney'=>$useWithdrawalMoney);
    }
      
    /**
     * 投红包/开启投资返利资金使用顺序：新充值，秒回，可提现, 融资
     * @param type $tenderMoney         投资金额
     * @param type $newRechargeMoney    新充值金额
     * @param type $miaoBackMoney       秒回金额
     * @param type $rongMoney           融资金额
     * @param type $withdrawalMoney     可提现金额     
     * @author lingyq
     * @date 2017/10/17
     * @return type
     */
    public function capitalUsageOrderThree($tenderMoney,$newRechargeMoney,$miaoBackMoney,$withdrawalMoney){ 
        $needMoney = $tenderMoney;
        $useRechargeMoney = 0;
        $useMiaoBackMoney = 0;
        $useRongMoney = 0;
        $useWithdrawalMoney = 0;
        if($newRechargeMoney > 0 ){//新充值金额
            $useRechargeMoney = $newRechargeMoney > $needMoney ? $needMoney : $newRechargeMoney;
            $useRechargeMoney = format_num($useRechargeMoney,4);
            $needMoney -= $useRechargeMoney;       
        }
        
        if($miaoBackMoney > 0 && $needMoney > 0){//秒回金额
            $useMiaoBackMoney = $miaoBackMoney > $needMoney ? $needMoney : $miaoBackMoney;
            $useMiaoBackMoney = format_num($useMiaoBackMoney,4);
            $needMoney -= $useMiaoBackMoney;
        }

        if($withdrawalMoney > 0 && $needMoney > 0){//可提现
            $useWithdrawalMoney = $withdrawalMoney > $needMoney ? $needMoney : $withdrawalMoney;     
            $useWithdrawalMoney = format_num($useWithdrawalMoney,4);
        }        
        return array('useRechargeMoney'=>$useRechargeMoney,'useMiaoBackMoney'=>$useMiaoBackMoney,'useRongMoney'=>$useRongMoney,'useWithdrawalMoney'=>$useWithdrawalMoney);
    }
}
