<?php

use think\Db;
use application\common\Myredis;

if (!function_exists("interest"))
{


    /**
     * 利息计算
     * @param float $account 贷款总额
     * @param float $apr 年利率
     * @param int $counts 期数
     * @param int $repayment_way 还款方式 可选参数：0(按月分期) 3(到期还本) 4(按天到期)
     * @param float $borrowing_date 贷款日期
     * @param string $type [option]利息计算类型 默认值：1（表示 投资，截取2位小数，其他值表示 融资，四舍五入）
     * @return array
     */
    function interest($account, $apr, $counts, $repayment_way, $borrowing_date = 0, $type = 1)
    {
        $func = $type == 1 ? "truncate" : "round";
        if ($repayment_way == 4)
        {
            $apr = $apr / (365 * 100);
        }
        else
        {
            $apr = $apr / (12 * 100);
        }
        $repayment_plan = array();
        $_li = pow((1 + $apr), $counts);
        $repayment = round($account * ($apr * $_li) / ($_li - 1), 4);

        $total_interest = $interest = $balance = $capital = 0;
        if ($repayment_way == 3)
        {
            $interest = round($account * $apr, 4);
        }
        if ($repayment_way == 4)
        {
            $interest = round($account * $apr * $counts, 4);
        }
        if ($repayment_way == 4)
        {
            $month = 1;
        }
        else
        {
            $month = $counts;
        }
        for ($i = 0; $i < $month; $i++)
        {
            //按月分期
            if ($repayment_way == 0)
            {

                if ($i == 0)
                {
                    $interest = round($account * $apr, 4);
                }
                else
                {
                    $interest = round(($account * $apr - $repayment) * pow((1 + $apr), $i) + $repayment, 4);
                }
                $capital = $repayment - $interest;
            }

            //到期还本
            if ($repayment_way == 3)
            {
                if ($i + 1 == $counts)
                {
                    $capital = $account; // 最后的本金
                }
                $repayment = $func($interest + $capital, 2);
            }
            //按天到期
            if ($repayment_way == 4)
            {
                $capital = $account;
                $repayment = $account + $interest;
            }
            $repayment = $func($repayment, 2);
            $capital = $func($capital, 2);
            $borrowing_date = $borrowing_date == 0 ? time() : $borrowing_date;
            $unit = $repayment_way == 4 ? "day" : "month";
            $repayment_plan[$i]['times'] = $i;
            $repayment_plan[$i]['repayment_time'] = strtotime(sprintf("%s +%d %s", date('Y-m-d', $borrowing_date), $repayment_way == 4 ? $counts : $i + 1, $unit));
            $repayment_plan[$i]['repayment_time_formated'] = date("Y-m-d", $repayment_plan[$i]['repayment_time']);
            $repayment_plan[$i]['repayment_account'] = $repayment;
            $repayment_plan[$i]['interest'] = $func($interest,2);
            $repayment_plan[$i]['capital'] = $capital;
            if ($repayment_way != 4)
            {
                $balance += $capital;
                $repayment_plan[$i]['balance'] = $func($account - $balance,2);
            }
            else
            {
                $repayment_plan[$i]['balance'] = 0;
            }
            $total_interest += $repayment_plan[$i]['interest'];
        }

        if ($repayment_way == 3)
        {
            $total_interest = $func($apr * $account * $counts, 2);
        }
        return [
            'total_interest' => $total_interest,
            //总利息
            'repayment_plan' => count($repayment_plan) > 1 ? calc_diff($account, $total_interest, $balance, $repayment_plan) : $repayment_plan,
            //还款计划
        ];
    }

}


if (!function_exists("log_table"))
{

    /**
     * 获取日志表
     * @param int $user_id 用户ID
     * @return string 用户对应的日志表
     */
    function log_table($user_id)
    {
        return sprintf("iaccount_log_%s", substr($user_id, -1));
    }

}


if (!function_exists("http_post"))
{

    /**
     * CURL POST
     * @param string $url URL
     * @param array $fields 参数数组
     * @return string
     * @example
     * post('http://www.example.com', array('field1'=>'value1', 'field2'=>'value2'));
     * * */
    function http_post($url, $fields, $json = false)
    {

        $post_field_string = $json ? json_encode($fields) . SOCKET_EOL : http_build_query($fields);
        echo $post_field_string;
        die();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4'))
        {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        if ($json)
        {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($post_field_string)
            ));
        }

        curl_setopt($ch, CURLOPT_POST, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

}


if (!function_exists("transform_new2old"))
{

    /**
     * @param array $data 待转换的数据集
     * @param string $table 待转换的标
     * @param boole $transform 是否转换为旧的表字段
     */
    function transform_new2old($data, $table, $transform = true)
    {
        $dict = config("field.TRANS_DICT");
        if (!$dict[$table] || !is_array($data) || !$transform)
        {
            return $data;
        }

        $dict_list = $dict[$table];
        foreach ($data as $key => $value)
        {
            if (isset($dict_list[$key]))
            {
                $data[$dict_list[$key]] = $value;
                unset($data[$key]);
            }
        }
        return $data;
    }

}


if (!function_exists("isJson"))
{

    /**
     * 判断字符串是否是JSON
     * @param string $string 待检测的字符串
     * @return bool
     */
    function isJson($string)
    {
        return ((is_string($string) && (is_object(json_decode($string)) || is_array(json_decode($string))))) ? true : false;
    }

}

if (!function_exists("truncate"))
{

    /**
     * 截取小数点
     * @param float $number 数字
     * @param type $length 小数位长度
     * @return float
     */
    function truncate($number, $length = 2)
    {
        return sprintf("%.{$length}f", bcdiv($number, 1, $length));
    }

}


if (!function_exists("round_half"))
{


    /**
     * 四舍六入五成双
     * @param type $val
     * @param type $precision
     * @return type
     */
    function round_half($val, $precision = 2)
    {
        return round($val, $precision, PHP_ROUND_HALF_UP);
    }

}


if (!function_exists("get_account_table"))
{

    /**
     * 函数：根据uid获取账户分表
     * @author liujian
     * @date 2017-3-24
     * @access public
     * @return string
     */
    function get_account_table($userId)
    {
        return 'iaccount_' . substr($userId, -1);
    }

}
if (!function_exists("get_client_ip"))
{

    /**
     * 函数：获取客户端IP地址
     * @author liujian
     * @date 2017-3-24
     * @param $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @access public
     * @return string
     */
    function get_client_ip($type = 0)
    {
        $type = $type ? 1 : 0;
        static $ip = NULL;
        if ($ip !== NULL)
        {
            return $ip[$type];
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos)
            {
                unset($arr[$pos]);
            }
            $ip = trim($arr[0]);
        }
        elseif (isset($_SERVER['HTTP_CLIENT_IP']))
        {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (isset($_SERVER['REMOTE_ADDR']))
        {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? array(
            $ip,
            $long
        ) : array(
            '0.0.0.0',
            0
        );
        return $ip[$type];
    }

}

if (!function_exists('make_order'))
{

    /**
     * 函数：生成充值订单号
     * @author ricky
     * @date 2017-6-27
     * @param $order 订单号
     * @access public
     * @return string
     */
    function make_order($userId, $prefix = '')
    {
        if ($prefix)
        {
            $order = sprintf("%s%s%s%s", $prefix, time(), $userId, mt_rand(1, 30)); //生成订单号
        }
        else
        {
            $order = time() . $userId . mt_rand(111, 999);
        }
        return $order;
    }

}


if (!function_exists("add_message_app"))
{

    /**
     * @desc 函数：用户首次操作日志推送消息
     * @author zxj
     * @date 2016-10-10
     * @access public
     * @params $params  = array(
     * 'user_id' => '用户id'
     * 'task_id' => '任务id:1:注册;2:实名;3:交易密码 ;4:充值 ;6:绑定银行卡'
     * 'type' => '消息类型：奖励消息:4,其他消息:5'
     * 'mtype' =>'app类型区分： 42:注册 ;43:绑定银行卡;44:实名认证;45:修改密码; 46:首次充值 '
     * 'name' => '标题'
     * 'content' => '内容')
     * @return int
     */
    function add_message_app($msg)
    {
        //判断奖励是否已领取
        if (!empty($msg['task_id']))
        {
            $map['task_id'] = $msg['task_id'];
            $map['user_id'] = $msg['user_id'];
            $res = db('user_task_log')->where($map)->find();
            if (is_array($res))
            {
                return true;
            }
        }
        $condition['receive_user'] = $msg['user_id'];
        $condition['type'] = $msg['type'];
        $condition['mtype'] = $msg['mtype'];
        $row = db('message_app')->where($condition)->find();
        if (empty($row))
        {
            $log['receive_user'] = $msg['user_id'];
            $log['type'] = $msg['type'];
            $log['mtype'] = $msg['mtype'];
            $log['name'] = $msg['name'];
            $log['content'] = $msg['content'];
            $log['addtime'] = time();
            $log['addip'] = input('session.REMOTE_ADDR');
            $rlog = db('message_app')->insert($log);
            unset($log);
            //app设置里面关闭了推送
            $rua = db('remind_user_app')->where(array(
                'user_id' => $msg['user_id'],
                'type'    => $msg['type'],
                'app_msg' => 1
            ))->find();
            if (!is_array($rua))
            {
                return $rlog;
            }
            //设置推送
            Myredis::getRedisConn(2)->appendToList("getui_msg", array(
                "uid"     => $msg['user_id'],
                "mtype"   => $msg['mtype'],
                "title"   => $msg['name'],
                "content" => $msg['content']
            ));
            unset($msg);
            return $rlog;
        }
    }

    if (!function_exists("send_mail"))
    {

        /**
         * 系统邮件发送函数
         * @param string $to 接收邮件者邮箱
         * @param string $name 接收邮件者名称
         * @param string $subject 邮件主题
         * @param string $content 邮件内容
         * @param string $attachment 附件列表
         * @return boolean
         */
        function send_mail($to, $name, $subject, $content, $attachment = null)
        {

            import("phpmailer.PHPMailerAutoload", EXTEND_PATH);
            date_default_timezone_set('UTC'); //设置时区
            //从PHPMailer目录导class.phpmailer.php类文件
            $mail = new \PHPMailer();             //PHPMailer对象
            $mail->CharSet = 'UTF-8';             //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
            $mail->IsSMTP();               // 设定使用SMTP服务
            $mail->IsHTML(true);
            $mail->SMTPDebug = 0;              // 关闭SMTP调试功能 1 = errors and messages2 = messages only
            $mail->SMTPAuth = true;              // 启用 SMTP 验证功能
            if (config('settings.email.SMTP_PORT') == 465)
            {
                $mail->SMTPSecure = 'ssl';
            }            // 使用安全协议 
            $mail->Host = config('settings.email.SMTP_HOST');           // SMTP 服务器
            $mail->Port = config('settings.email.SMTP_PORT');           // SMTP服务器的端口号
            $mail->Username = config('settings.email.SMTP_USER');          // SMTP服务器用户名
            $mail->Password = config('settings.email.SMTP_PASS');          // SMTP服务器密码
            $mail->SetFrom(config('settings.email.FROM_EMAIL'), config('settings.email.FROM_NAME'));
            $replyEmail = config('settings.email.REPLY_EMAIL') ? config('settings.email.REPLY_EMAIL') : config('settings.email.FROM_EMAIL');
            $replyName = config('settings.email.REPLY_NAME') ? config('settings.email.REPLY_NAME') : config('settings.email.FROM_NAME');
            $mail->AddReplyTo($replyEmail, $replyName);
            $mail->Subject = $subject;
            $mail->MsgHTML($content);
            $mail->AddAddress($to, $name);
            if (is_array($attachment))
            { // 添加附件
                foreach ($attachment as $file)
                {
                    if (is_array($file))
                    {
                        is_file($file['path']) && $mail->AddAttachment($file['path'], $file['name']);
                    }
                    else
                    {
                        is_file($file) && $mail->AddAttachment($file);
                    }
                }
            }
            else
            {
                is_file($attachment) && $mail->AddAttachment($attachment);
            }
            return $mail->Send() ? true : false;
        }

    }
}

if (!function_exists("ok_json"))
{

    /**
     * @desc 函数：返回成功json信息
     * @author liujian
     * @date 2016-3-18
     * @access protected
     * @param array $data 数据数组
     * @return void
     */
    function ok_json($data = array(), $message = 'success', $code = 1)
    {
        $result = array(
            'status' => true,
            'code'   => $code,
            'info'   => $message,
            'data'   => $data
        );
        header("Content-Type:text/html; charset=utf-8");
        exit(json_encode($result));
    }

}
if (!function_exists("fail_json"))
{

    /**
     * @desc 函数：返回失败json信息
     * @author liujian
     * @date 2016-3-18
     * @access protected
     * @param  string $message 失败提示信息
     *        int    $code     错误code代码
     *         array  $data    数据数组
     * @return void
     */
    function fail_json($message = 'fail', $code = 0, $data = array())
    {
        $result = array(
            'status' => false,
            'code'   => $code,
            'info'   => $message,
            'data'   => $data
        );

        header("Content-Type:text/html; charset=utf-8");
        exit(json_encode($result));
    }

}


/**
 * 生成业务编码
 * @param  chars $type 业务类型，预定义的有：Bborrow，tender，Brepayment
 * @param int $counts 期数
 * @param int $user_id 用户ID
 * @param string $pre 业务前缀 ，限数字，长度不要超过7位 默认值100，
 * @return  mixted  如果业务不在预设值。返回false. $counts大于1返回数组，等于1返回字符串
 */
if (!function_exists("made_num"))
{

    function made_num($type, $counts, $uid, $pre = '100')
    {
        //前缀最多允许5位。
        $codeList = array(
            'borrow'     => 121,
            'tender'     => 104,
            'repayment'  => 120,
            'withdrawal' => 502,
        );

        $pre = $codeList[$type] ? $codeList[$type] : $pre;
        if (!preg_match("/^\d{1,7}$/", $pre))
        {
            $pre = 100;
        }
        $preLen = strlen($codeList[$type] ? $codeList[$type] : $pre);
        $zeroPad = 16 - $preLen - 6;
        if (!$codeList[$type])
        {
            $type = "others";
        }
        $start = Myredis::getRedisConn(2)->incrementInHash($type, $uid, $counts);
        if (array_key_exists($type, $codeList))
        {
            if ($type == 'withdrawal')
            {
                $type = 'withdrawal_cash_freeze';
            }
            $sql = "insert into itd_code_counter (uid,$type) values($uid,$counts) on  DUPLICATE KEY UPDATE $type=$type+$counts ";
            Db::execute($sql);
        }
        if ($counts > 1)
        {
            $num = array();
            for ($i = 1; $i <= $counts; $i++)
            {
                $num[] = sprintf("%s%s%s", $pre, khash($uid), sprintf("%0{$zeroPad}d", $start--));
            }
            krsort($num);
            return array_values($num);
        }
        else
        {
            return sprintf("%s%s%s", $pre, khash($uid), sprintf("%0{$zeroPad}d", $start));
        }
    }

}

if (!function_exists("khash"))
{
    function khash($data)
    {
        static $map = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $hash = bcadd(sprintf('%u', crc32($data)), 0x100000000);
        $str = "";
        do
        {
            $str = $map[bcmod($hash, 62)] . $str;
            $hash = bcdiv($hash, 62);
        } while ($hash >= 1);
        return strtoupper($str);
    }
}

if (!function_exists("csubstr"))
{

    /**
     * 支持utf8中文字符截取
     * @param    string $string 待处理字符串
     * @param    int $start 从第几位截断
     * @param    int $sublen 截断几个字符
     * @param    string $ellipsis 附加省略字符
     * @param    string $code 字符串编码
     * @return    string
     */
    function csubstr($string, $start = 0, $sublen = 12, $ellipsis = '', $code = 'UTF-8')
    {
        if ($code == 'UTF-8')
        {
            $pa = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/";
            preg_match_all($pa, $string, $t_string);
            $intTemp = 0;
            foreach ($t_string[0] as $k => $v)
            {
                if (strpos("~!@#$%^&*()_+{}|\":<>?`1234567890-=[]\;',./abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ ", $v) !== false && $k <= $sublen)
                {
                    $intTemp++;
                };
            }
            $sublen = $sublen + floor($intTemp / 2);
            if (count($t_string[0]) - $start > $sublen)
            {
                return join('', array_slice($t_string[0], $start, $sublen)) . $ellipsis;
            }
            else
            {
                return join('', array_slice($t_string[0], $start, $sublen));
            }
        }
        else
        {
            $start = $start;
            $strlen = strlen($string);
            if ($sublen != 0)
            {
                $sublen = $sublen * 2;
            }
            else
            {
                $sublen = $strlen;
            }
            $tmpstr = '';
            for ($i = 0; $i < $strlen; $i++)
            {
                if ($i >= $start && $i < ($start + $sublen))
                {
                    if (ord(substr($string, $i, 1)) > 129)
                    {
                        $tmpstr .= substr($string, $i, 2);
                    }
                    else
                    {
                        $tmpstr .= substr($string, $i, 1);
                    }
                }
                if (ord(substr($string, $i, 1)) > 129)
                {
                    $i++;
                }
            }
            if (strlen($tmpstr) < $strlen)
            {
                $tmpstr .= $ellipsis;
            }
            return $tmpstr;
        }
    }

}


if (!function_exists("api_post"))
{

    /**
     * api  post
     * java接口专用
     * @param string $url URL
     * @param array $fields 参数数组
     * @param bool $addParam 是否添加额外参数
     * @param int $secret 不同的签名标识
     * @return array
     * @example
     * post('http://www.example.com', array('field1'=>'value1', 'field2'=>'value2'));
     * * */
    function api_post($url, $fields, $addParam = true, $secret = 0)
    {
        $api = config("settings.api.{$url}");
        if (!$api)
        {
            return false;
        }
        $fields["method"] = $api["method"];
        if ($addParam)
        {
            $fields = add_param($fields, $secret);
        }
        $post_field_string = http_build_query($fields);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api["url"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4'))
        {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        curl_setopt($ch, CURLOPT_POST, true);
        $response = json_decode(curl_exec($ch), 1);
        curl_close($ch);
        return $response;
    }

}


if (!function_exists("get_times"))
{

    /**
     * * 时间获得函数
     * living 20130330
     * @param ptype m 表示月 d 表示日
     * @param pdate 表示时间日期
     * @param pnum 表示变化量
     */
    function get_times($ptype, $pdate, $pnum = 1)
    {
        $currtime = isset($pdate) ? $pdate : time(); //现在时间
        $type = $ptype ? $ptype : "m";
        if ($type == "m")
        {
            $month = date("m", $currtime); //月
            $year = date("Y", $currtime); //年
            $_result = strtotime("$pnum month", $currtime); //加月 可能会跨月-联系加两个月
            $_month = (int)date("m", $_result);
            //月份处理
            if ($month + $pnum > 12 && $month + $pnum <= 24)
            {
                $_num = $month + $pnum - 12;
                $year = $year + 1;
            }
            elseif ($month + $pnum > 24 && $month + $pnum <= 36)
            {
                $_num = $month + $pnum - 24;
                $year = $year + 2;
            }
            elseif ($month + $pnum > 36)
            {
                $_num = $month + $pnum - 36;
                $year = $year + 3;
            }
            else
            {
                $_num = $month + $pnum;
            }
            if ($_num != $_month)
            {
                $_result = strtotime("-1 day", strtotime("{$year}-{$_month}-01"));
            }
            return $_result;
        }
        else
        {
            $_result = strtotime("$pnum day", $currtime);
            return $_result;
        }
    }

    /**
     * * 小数点后截取函数
     * living' 20130329
     * @param $number 要截取的变量  $pointpos 小数后的位置
     * @return 如 ：2.589   截取 2.58 不足时补回
     */
    if (!function_exists("subnumber"))
    {

        function subnumber($number, $pointpos = 2, $pFull = true)
        {
            if (strrpos($number, '.'))
            {
                $max_money = substr($number, 0, strrpos($number, '.') + $pointpos + 1);
            }
            return sprintf("%.2f", $number);
        }

    }

    /**
     * 按ID分表，根据ID获取表名
     */
    if (!function_exists("get_account_log_table"))
    {

        function get_account_log_table($userId)
        {
            return 'iaccount_log_' . $userId % 10;
        }

    }

    /**
     * 打印数组函数
     * @param array $data 待打印的数组
     * @param bool $die 是否终止脚本
     * @return void
     * * */
    if (!function_exists("printp"))
    {

        function printp($data, $die = false)
        {
            echo "<pre>";
            print_r($data);
            echo "</pre>";

            $die ? die() : "";
        }

    }

    /**
     * @desc 函数：清除用户缓存
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return void
     */
    if (!function_exists('clear_user_cache'))
    {

        function clear_user_cache($id, $redis)
        {
            $redis->appendToList('delete_cache', $id);
        }

    }


    /**
     * @desc 函数：清除标缓存
     * @author liujian
     * @date 2017-3-31
     * @access public
     * @return void
     */
    if (!function_exists('clear_borrow_cache'))
    {

        function clear_borrow_cache($id, $redis)
        {
            $redis->deleteFromHash('viewborrow', $id);
            $redis->deleteFromHash('tender_history', $id);
            $redis->deleteFromHash("viewborrow_iborrowinfo", $id);
        }

    }
    if (!function_exists('strsubstr'))
    {

        function strsubstr($maxMoney, $num = 3)
        {
            $maxMoney = sprintf("%.7f", $maxMoney);
            if (strrpos($maxMoney, '.'))
            {
                $maxMoney = substr($maxMoney, 0, strrpos($maxMoney, '.') + $num);
                return $maxMoney;
            }
            else
            {
                return $maxMoney;
            }
        }

    }
    if (!function_exists('overdue_interest'))
    {

        function overdue_interest($datetime, $account)
        {
            $lateRate = 0.008;
            $nowTime = mktime(0, 0, 0, date("m"), date("d"), date("Y")); //mktime  date("Y-m-d",time());
            $datetmp = getdate($datetime);
            $repaymentTime = mktime(0, 0, 0, date("m", $datetime), date("d", $datetime), date("Y", $datetime));
            $lateDays = ($nowTime - $repaymentTime) / (60 * 60 * 24);
            $lateDaysArr = explode(".", $lateDays);
            $lateDays = ($lateDaysArr[0] < 0) ? 0 : $lateDaysArr[0];
            if ($lateDays > 30)
            {
                $lateIterest = round($account * $lateRate * 30, 2);
                $lateRate = 0.01;
                $lateIterest += round($account * $lateRate * ($lateDays - 30), 2);
            }
            else
            {
                $lateIterest = round($account * $lateRate * $lateDays, 2);
            }

            if ($lateDays == 0)
            {
                $lateIterest = 0;
            }
            return array(
                "ld" => $lateDays,
                "li" => $lateIterest
            );
        }

    }
}


if (!function_exists("format_num"))
{

    function format_num($number, $length = 2)
    {
        return sprintf("%.2f", bcdiv($number, 1, $length));
    }

}


if (!function_exists("getSendMasterPhone"))
{

    /**
     * @uses 处理199号码，发送到主号
     * @author jhl
     * 当手机号码为199时、用主号发送短信
     */
    function getSendMasterPhone($phone, $user_id = '')
    {
        if ($phone == '')
        {
            return '';
        }
        //对199号单独处理
        if (!preg_match("/^199\d{8}$/", $phone))
        {
            return $phone;
        }
        //没有手机号对应额用户id
        if (!$user_id)
        {
            $rs = Db::name("iuser")->where(['phone' => $phone])->find();
            $user_id_sql = $rs["user_id"];
        }
        else
        {
            $user_id_sql = $user_id;
        }
        $rs2 = Db::name("verify")->field('related_username')->where([
            'user_id' => $user_id_sql,
            'etype'   => 2,
            'result1' => 1
        ])->find();
        if ($rs2['related_username'])
        {
            //查询关联账号的主账号
            $userInfo = Db::name("iuser")->field("phone")->where(['username' => $rs2['related_username']])
                          ->order("addtime", "asc")->find();
        }
        return isset($userInfo['phone']) ? $userInfo['phone'] : $phone;
    }

}


if (!function_exists("Sendmsg"))
{

    /**
     * 发送内部短信
     * living 20130411
     * @param  int $send_uid 发送者ID
     * @param  int $recv_uid 接收者ID
     * @param  string $title 标题
     * @param  string $content 内容
     * @param  string $ip
     * @return void
     */
    function Sendmsg($send_uid, $recv_uid, $title, $content)
    {
        $data['name'] = $title;
        $data['content'] = $content;
        $data['sent_user'] = $send_uid;
        $data['type'] = 'web_info';
        $data['status'] = '1';
        $data['addtime'] = time();
        $data['receive_user'] = $recv_uid;
        $data['receive_status'] = 1;
        $data['addip'] = get_client_ip();
        Db::name('message_3')->insert($data);
        \think\Cache::rm("message_{$recv_uid}");
    }

}

/**
 * @desc 返回时间戳毫秒
 * @author liuj
 * @update 2015-7-22
 * @access public
 * @return float
 */
function get_milli_second()
{
    list($t1, $t2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
}

/**
 * @desc 后台接口签名校验
 * @author pandelin
 * @update 2017-06-16
 * @access public
 * * */
function console_check($data)
{
    if (empty($data))
    {
        fail_json('非法访问');
    }
    $appKey = 'tp5_22%it+itbt+yatang';
    $data['appKey'] = $appKey;
    $sign = $data['sign'];
    unset($data['sign']);
    $data['appKey'] = $appKey;
    ksort($data);
    $string = '';
    foreach ($data as $key => $value)
    {
        if (is_array($value))
        {
            $value = 'Array';
        }
        $string .= $key . $value;
    }
    $string = $string . $appKey;
    $mysign = strtoupper(sha1($string));
    $data['mysign'] = $mysign;
    if ($mysign != $sign)
    {
        fail_json('非法访问');
    }
}

if (!function_exists('get_repay_style_name'))
{

    /**
     * @uses 获取还款期限显示
     * @author jhl
     * @param $repaystyle 还款方式
     * @param $fatalism 天标天数
     * @param $time_limit 借款期数
     * @param $type 1 返回字符串，$type=2:返回数组：数字、说明
     */
    function get_repay_style_name($repaystyle, $fatalism = 0, $time_limit = 0, $type = 1)
    {

        if ($repaystyle == 4)
        {
            $repayName = $fatalism . '天';
        }
        else
        {
            $repayName = $time_limit . '个月';
        }

        $repayArr = array(
            'data' => $repaystyle == 4 ? $fatalism : $time_limit,
            'msg'  => $repaystyle == 4 ? '天' : '个月'
        );
        return $type == 1 ? $repayName : $repayArr;
    }

}

if (!function_exists('get_repaystyle'))
{

    /**
     * 获取还款方式
     * @author jhl
     * @param $repaystyle 还款方式
     * @param $borrow_type 标类型
     */
    function get_repaystyle($repaystyle, $borrow_type)
    {

        if ($repaystyle == 0 && $borrow_type != 5)
        {
            $repaymsg = '按月分期';
        }
        if ($repaystyle == 0 && $borrow_type == 5)
        {
            $repaymsg = '秒还';
        }
        if ($repaystyle == 3)
        {
            $repaymsg = '到期还本';
        }
        if ($repaystyle == 4)
        {
            $repaymsg = '按天到期';
        }
        return $repaymsg;
    }

}

if (!function_exists('get_award_rate'))
{

    /**
     * 获取投标奖励
     * @author jhl
     * @param $award_rate 投标奖励
     * @param $award_account 分摊奖励金额
     * @param $account 借贷总金额
     */
    function get_award_rate($award_rate = 0, $award_account = 0, $account = 0)
    {
        $borrowaward = 0;
        if ($award_rate > 0)
        {
            $borrowaward = truncate($award_rate, 2);
        }
        elseif ($award_account > 0)
        {
            $borrowaward = truncate(100 * $award_account / $account, 2);
        }
        return $borrowaward > 0 ? $borrowaward : 0;
    }

}


if (!function_exists("check_borrow_num"))
{

    /**
     * 检查标编码是否合格
     * @param string $number 标编码，还款编码，投标编码
     * @return boolean
     */
    function check_borrow_num($number)
    {
        //长度小于8位的，一律识别为数字
        if (preg_match("/^sec_(?<second_id>\d+)$/i", $number))
        {
            return true;
        }
        if (strlen($number) < 8 && preg_match("/^\d{3,7}$/", $number))
        {
            return true;
        }
        if (preg_match("/^(121|120|104)[A-Z0-9]{6}\d{3,7}$/", $number))
        {
            return true;
        }
        return false;
    }

}

if (!function_exists("check_paypwd_error_times"))
{

    /**
     * 检查交易密码输入错误次数
     * @param type $userid
     * @param type $username
     * @return type
     */
    function check_paypwd_error_times($userid, $username)
    {
        $msg = '错误次数超过5次';
        $left_times_key = "error_times_$userid";
        $dateline_key = "error_time_$userid";
        $error_times = cache($left_times_key);
        if (!$error_times)
        {
            cache($left_times_key, 0, 900);
        }
        $left_timelimit = cache($dateline_key);
        if ((time() - $left_timelimit < 900) && $error_times == 0)
        {
            return [
                'status' => 0,
                'msg'    => $msg
            ];
        }
        if (empty($username))
        {
            $times = 4 - cache($left_times_key);
            cache($left_times_key, cache($left_times_key) + 1, 900);
            if ($times <= 4 && $times > 0)
            {
                return [
                    'status' => 0,
                    'msg'    => sprintf("交易密码错误,还能输入%d次", $times)
                ];
            }
            else
            {
                cache($left_times_key, NULL);
                cache($left_times_key, 0, 900);
                cache($dateline_key, time(), 900);
                return [
                    'status' => 0,
                    'msg'    => $msg
                ];
            }
        }
        //客户如果交易密码输入错误，在5次之内有一次输入正确，之前的输入错误次数清零
        if (!empty($username) && $error_times)
        {
            cache($left_times_key, 0, 900);
        }
        return [
            'status' => 1,
            'msg'    => ''
        ];
    }

}

if (!function_exists('get_second_id'))
{

    /**
     * 识别秒ID
     * @param string $second_id
     * @return mixed 如果匹配成功，返回秒ID ，失败返回false
     */
    function get_second_id($second_id)
    {
        $match = array();
        if (preg_match("/^sec_(?<second_id>\d+)$/i", $second_id, $match))
        {
            return $match["second_id"] > 0 ? $match["second_id"] : false;
        }
        else
        {
            return false;
        }
    }

}

if (!function_exists('get_request_counts'))
{


    /**
     * 獲取用戶請求次數，並判斷是否加入黑名單
     * @param int $key 請求限限制类型
     * @param array [$user] 要加入的黑名單用戶信息  array("user_id"=>2222,"user_name"=>"testss")
     * @return boolean
     */
    function get_request_counts($key, $user = array(), $prefix = '')
    {
//        $ip = get_client_ip();
//        // 白名单检测
//        if (in_array($ip, config('system.blacklist.IpAllowed')))
//        {
//            return false;
//        }
//        $times       = get_request_limit($key);
//        $module_name = think\Request::instance()->module('module_name');
//        $action_name = think\Request::instance()->action();
//        $cacheKey    = sprintf("%s_%s", $prefix ? $prefix : $module_name . '_' . $action_name, $ip);
//       // $redis       = new Myredis("lock", config('settings.redis_sec'));
//        $result      = $redis->get($cacheKey);
//        if ($result > $times && isset($user['user_id']) && isset($user["username"]))
//        {
//            Db::name("item_invest_blacklist")->insert(["user_id" => $user["user_id"], "user_name" => $user["username"]]);
//        }
//        return $result > $times ? true : false;
    }

}

if (!function_exists('get_request_limit'))
{

    /**
     * 獲取指定請求類型的請求限制
     * @param string $key
     * @return string
     */
    function get_request_limit($key)
    {
        if (!cache($key))
        {
            $array = Db::name("variable")->field("key,value")->where([
                "key" => [
                    "in",
                    [
                        'SYS_CHECKPAY',
                        'SYS_NORMAL'
                    ]
                ]
            ])->select();
            foreach ($array as $value)
            {
                cache($value['key'], $value['value'], 86400);
            }
        }
        return cache($key);
    }

}

if (!function_exists('html_filter'))
{

    /**
     * html过滤
     * @param string $html
     * @return string
     */
    function html_filter($html)
    {
        import("HTMLPurifier.HTMLPurifierauto", EXTEND_PATH);
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', config('htmlAllowed'));
        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($html);
    }

}

function client_send($data, $timeout = 5)
{
    if (!is_array($data))
    {
        return json_encode(array(
            "status" => 0,
            "msg"    => "必须传输数组"
        ));
    }
    $send_data = json_encode($data);

    if (class_exists("swoole_client"))
    {
        $client = new swoole_client(SWOOLE_SOCK_TCP);
        if (!$client->connect(SOCKET_SERVER_HOST, SOCKET_SERVER_PORT, $timeout))
        {
            return array(
                "status" => 0,
                "msg"    => "连接失败..."
            );
        }
        $send = $client->send($send_data . SOCKET_EOL);
        if ($send == false)
        {
            return array(
                "status" => 0,
                "msg"    => $client->errCode . "发送失败"
            );
        }
        $back = $client->recv();
        if ($back !== NULL || $back !== false)
        {
            $client->close();
            $result = json_decode($back, 1);
            return $result ? $result : array(
                "status" => 0,
                "msg"    => $back
            );
        }
        else
        {
            return array(
                "status" => 0,
                "msg"    => "请稍候..."
            );
        }
    }
    return [
        'status' => 0,
        'msg'    => '不支持swoole'
    ];
}

if (!function_exists('calc_diff'))
{


    /**
     * 修正最后一期还款
     * @param float $account 借贷金额
     * @param float $total_interest 总利息
     * @param float $capital 总本金
     * @param array $data_list 分期利息
     */
    function calc_diff($account, $total_interest, $capital, $data_list)
    {
        $last = count($data_list) - 1;
        $total = $repayment = 0;
        $balance = $account;

        foreach ($data_list as $k => $value)
        {

            $total += $value["interest"];
            $repayment += $value['repayment_account'];
        }
        //四舍五入后的利息总和比实际利息多，修正最后一期
        $adjust_value = abs($total - $total_interest);
        if ($total > $total_interest)
        {
            $data_list[$last]['interest'] -= $adjust_value;
            // $data_list[$last]['capital']-=$adjust_value; 
        }
        if ($total < $total_interest)
        {
            $data_list[$last]['interest'] += $adjust_value;
            //   $data_list[$last]['capital']-=$adjust_value;     
        }

        $adjust_value3 = abs($capital - $account);
        if ($account < $capital)
        {
            $data_list[$last]['capital'] -= $adjust_value3;
        }
        else
        {
            $data_list[$last]['capital'] += $adjust_value3;
        }

        $account += $total_interest;
        //四舍五入后的还款总额比实际还款总额多，修正最后一期  
        $adjust_value2 = abs(bcsub($account, $repayment, 2));

        if ($account > $repayment)
        {
            $data_list[$last]['repayment_account'] += $adjust_value2;
        }
        if ($account < $repayment)
        {
            $data_list[$last]['repayment_account'] -= $adjust_value2;
        }

        foreach ($data_list as $k => $value)
        {

            $balance = bcsub($balance, $value["repayment_account"] - $value["interest"], 2);

            $data_list[$k]["balance"] = $balance;
        }

        return $data_list;
    }

}

/**
 * @desc 判断数组是几维
 * @author liuj
 * @update 2015-7-22
 * @access public
 * @return int
 */
function get_array_latitude($array)
{
    return is_array(reset($array)) ? get_array_latitude(reset($array)) + 1 : 1;
}

/**
 * @desc 对字符串进行base64_encode加密
 * @author pandelin
 * @update 2017-7-6
 * @access public
 * @return string
 */
if (!function_exists('urlsafe_b64encode'))
{

    function urlsafe_b64encode($string)
    {
        return str_replace(array(
            '+',
            '/',
            '='
        ), array(
            '-',
            '_',
            ''
        ), base64_encode($string));
    }

}
/**
 * @desc 对base64_encode字符串进行解密
 * @author pandelin
 * @update 2017-7-6
 * @access public
 * @return string
 */
if (!function_exists('urlsafe_b64decode'))
{

    function urlsafe_b64decode($string)
    {
        $data = str_replace(array(
            '-',
            '_'
        ), array(
            '+',
            '/'
        ), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4)
        {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

}

if (!function_exists('parse_fields_map'))
{

    /**
     * 递归转换数组key
     * @param array $input 待转换数组
     * @param array $map 字典
     * @param bool 转换模式[option] true:读(旧转新)，false:写（新转旧）
     * @example
     * $dict=['f1'=>'old_filed_name1','f2'=>'old_filed_name2''f3'=>'old_filed_name3']
     * $data=['old_filed_name1'=>1,'old_filed_name2'=>2,'old_filed_name3'=>3];
     * print_r(parse_fields_map($data,$dict));
     * @return array 转换后的数组
     */
    function parse_fields_map($input, $map, $read = true)
    {

        if (!$map)
        {
            return $input;
        }
        $return = array();
        foreach ($input as $key => $value)
        {

            $new_map = $read ? array_flip($map) : $map;
            if (array_key_exists($key, $new_map))
            {
                $key = $new_map[$key];
            }
            if (is_array($value))
            {

                $value = parse_fields_map($value, $map, $read);
            }
            $return[$key] = $value;
        }
        return $return;
    }

    if (!function_exists('getSubstr'))
    {

        /**
         *   实现中文字串截取无乱码的方法
         */
        function getSubstr($string, $start, $length)
        {
            $string = strip_tags($string);
            if (mb_strlen($string, 'utf-8') > $length)
            {
                $str = mb_substr($string, $start, $length, 'utf-8');
                return $str . '...';
            }
            else
            {
                return $string;
            }
        }

    }
}

if (!function_exists('validate_phone'))
{
    /**
     * @uses 验证手机号码格式 增加199测试号段
     * @param string $phone 手机号吗
     * @author jhl
     */
    function validate_phone($phone)
    {
        if (preg_match('/^(1[34578][0-9]|199)\d{8}$/', $phone))
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}
if (!function_exists('uniqueCodeCreate'))
{
    /**
     * @uses 生成一个唯一码
     * @author pandelin
     */
    function uniqueCodeCreate()
    {
        return substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . rand(10, 99);
    }

}

function triggleError($msg = '', $code = 0)
{
    throw new Exception(json_encode([
        'msg'  => $msg,
        'code' => $code
    ]));
}

function msg($exc)
{
    if (isJson($exc->getMessage()))
    {
        $msg = json_decode($exc->getMessage(), 1);
        \think\Log::write($msg, "error");
    }
    else
    {
        $msg = $exc->getMessage();
        \think\Log::write($msg.$exc->getTraceAsString(), "error");
    }

    return [
        'status' => 0,
        'msg'    => isset($msg['msg']) ? $msg['msg'] : $msg,
        'code'   => isset($msg['code']) ? $msg['code'] : 0
    ];
}

/**
 * @uses 接口图片路径处理
 * @author jhl
 */
function imgTrueUrl($imgUrl,$prefixHost = '')
{
    if ($prefixHost == '') {
        $prefixHost = SITE_FULL;
    }
    if (!preg_match("/http/", $imgUrl)) {
        $imgUrl = $prefixHost . $imgUrl;
    }
    return $imgUrl;
}

/**
 * @desc  格式化价格
 * @access public
 * @param float   $price  商品价格
 * @param int   $type  类型
 * @return void
 */
function priceFormat($price, $type = 5)
{
    switch ($type)
    {
        case 0:

            $price = number_format($price, 2, '.', '');
            break;

        case 1: // 保留不为 0 的尾数

            $price = preg_replace('/(.*)(\\.)([0-9]*?)0+$/', '\1\2\3', number_format($price, 2, '.', ''));
            if (substr($price, -1) == '.')
            {
                $price = substr($price, 0, -1);
            }
            break;

        case 2: // 不四舍五入，保留1位

            $price = substr(number_format($price, 2, '.', ''), 0, -1);
            break;

        case 3: // 直接取整

            $price = intval($price);
            break;

        case 4: // 四舍五入，保留 1 位

            $price = number_format($price, 1, '.', '');
            break;

        case 5: // 先四舍五入，不保留小数

            $price = round($price);
            break;
        case 6:
            $price =  number_format($price, 2, '.', ',');
            break;
    }
    return $price;
}

/**
 * @uses 获取用户信息
 * @author jhl
 * @return array
 */
function getUserInfo()
{
    $cookie = \think\Cookie::get(COOKIE_TYPE);
    $Iuser = new \application\common\logic\user\Iuser();
    $userInfo = $Iuser->authcode($cookie);
    if (! $userInfo) {
        return [];
    }
    $userInfo = explode(',',$userInfo);
    return [
        'userId' => $userInfo[0],
        'userName' => $userInfo[2]
    ];
}

/**
 * 获取sql的偏移值
 * @access public
 * @return string
 */
function get_limit($page = 1, $nums = 20)
{
    $page = $page == 0 ? 1 : $page;
    $offset = ($page - 1) * $nums;
    return $offset . ',' . $nums;
}

/**
 * 返回html数据
 * @author lle.Tan
 * @access public
 * @param string $msg 状态说明信息
 * @return void
 */
function alertAppMsg($msg = '')
{
    echo "<script type=\"text/javascript\">";
    echo " if (window.itbt)
          {
            alert('$msg');
            window.itbt.finished()
          } 
          else 
          { 
            setTimeout(function() {
               alert('$msg');
                if (window.YTJingRong) {
                    window.YTJingRong.goPageUp()
                }
            }, 200)
          }";
    echo "</script>";
    exit();
}



  

  