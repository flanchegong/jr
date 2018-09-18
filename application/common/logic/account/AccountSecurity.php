<?php
namespace application\common\logic\account;

use think\Cache;

/**
 * @uses 账户安全类
 * @author jhl
 */
class AccountSecurity
{
    /**
     *
     * @uses 交易密码检测
     * @author jhl
     * @param string $msg            
     * @param unknown $userId            
     * @param string $userName            
     */
    public function logonTimes($userId, $userName = '')
    {
        $leftTimesKey = "error_times_$userId";
        $datelineKey = "error_time_$userId";
        $errorTimes = Cache::get($leftTimesKey);
        if (! $errorTimes) {
            Cache::set($leftTimesKey, 0, 900);
        }

        $leftTimelimit = Cache::get($datelineKey);
        if ((time() - $leftTimelimit < 900) && $errorTimes == 0) {
            return [
                'status' => false,
                'code' => 166,
                'msg' => '您的交易密码输入错误次数超过5次，将禁止投资15分钟'
            ];
        }
        if (empty($userName)) {
            $times = 4 - Cache::get($leftTimesKey);
            Cache::set($leftTimesKey, Cache::get($leftTimesKey) + 1, 900);
            if ($times <= 4 && $times > 0) {
                return [
                    'status' => false,
                    'code' => 101,
                    'msg' => "交易密码错误,还能输入{$times}次"
                ];
            } else {
                Cache::set($leftTimesKey, NULL);
                Cache::set($leftTimesKey, 0, 900);
                Cache::set($datelineKey, time(), 900);
                return [
                    'status' => false,
                    'code' => 166,
                    'msg' => '您的交易密码输入错误次数超过5次，将禁止投资15分钟'
                ];
            }
        }
        
        // 客户如果交易密码输入错误，在5次之内有一次输入正确，之前的输入错误次数清零
        if (! empty($userName) && $errorTimes) {
            Cache::set($leftTimesKey, 0, 900);
        }
        
        return [
            'status' => true,
            'code' => 200,
            'msg' => '检测通过'
        ];
    }
}
