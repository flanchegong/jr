<?php
/**
 * @Copyright (C), 2016, jiquan
 * @Name Cashback.php
 * @Author liuj
 * @Version stable 1.0
 * @Date 2017-7-24
 * @Description 模型基类
 * @Class List
 *    1.
 * @Function List
 *    1.
 * @History
 * <author>  <time>              <version>    <desc>
 *  liuj   2017-07-24          stable 1.0   第一次建立该文件
 */

namespace application\api\controller;

use application\common\logic\account\AccountLogic;
use application\common\model\account\AccountModel;
use application\common\model\activity\ActivityAddCash;
use application\common\model\user\Iuser;
use application\common\Myredis;
use application\common\model\api\CashBackOrder;
use think\Db;

class Cashback extends Base
{


    /**
     * @desc 平台
     * @var array
     * @access protected
     */
    protected $_type = array(
        '1' => 'HY',
        '2' => 'DS'
    );

    /**
     * @desc 类型
     * @var array
     * @access protected
     */
    protected $_businessType = array(
        '1' => 'B',//会员中心增值会员购买
    );




    function test()
    {
        $data['return_cash_amount'] = 1.00;
        $data['source_platform'] = 2;
        $data['clear_account'] = 1;
        $data['datetime'] = date('2017-10-27 16:24:00');
        $data['source_platform_order_no'] = 'aaaaaa';
        $data['withdrawal_cash_limit_day'] = 1;
        $data['return_cash_type'] = 1;
        $data['user_name_receive'] = '刘建1123';
        $data['async_call_back_url'] = 'http://tp5.com:8080/api/cashback/test1';
        $data['token'] = $this->_createSign($data);
        echo '<pre>';
        var_dump($data);
    }

    function test1()
    {
        //echo 'success';
        echo 'fail';
    }
    function test2()
    {
        echo 'success';
//        echo 'fail';
    }


    /**
     * @desc 函数：返现接口
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function doCashBack()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'source_platform',
                'label_name' => '返现来源平台',
                'param_type' => 'int',
                'is_require' => true,
            ],
            [
                'param_name' => 'clear_account',
                'label_name' => '结算账户类型',
                'param_type' => 'int',
                'is_require' => true,
            ],
            [
                'param_name' => 'token',
                'label_name' => '令牌',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'return_cash_amount',
               'label_name' => '返现金额',
               'param_type' => 'int',
                'is_require' => true,
            ],
            [
                'param_name' => 'withdrawal_cash_limit_day',
                'label_name' => '提现控制天数',
                'param_type' => 'int',
                'is_require' => true,
            ],
            [
                'param_name' => 'source_platform_order_no',
                'label_name' => '来源订单号',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'return_cash_type',
                'label_name' => '返现业务类型',
                'param_type' => 'int',
                'is_require' => true,
            ],
            [
                'param_name' => 'user_name_receive',
                'label_name' => '返现账户',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'async_call_back_url',
                'label_name' => '异步回调地址',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'datetime',
                'label_name' => '请求时间',
                'param_type' => 'string',
                'is_require' => true,
            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }
        $config = config('settings.cash_back');
        $account = $config[$param['source_platform']];
        if (empty($account))
        {
            $this->failJson('账户信息错误');
        }
        elseif (!isset($account['clear_account']) || empty($account['clear_account']))
        {
            $this->failJson('结算账户信息错误');
        }
        elseif (!isset($account['clear_account'][$param['clear_account']]))
        {
            $this->failJson('结算账户类型错误');
        }
        $this->_checkToken($param);
        $clearAccount = $account['clear_account'][$param['clear_account']];
        $iuserModel = new Iuser();
        $info = $iuserModel->getOneByWhere(['where' => ['username' => $clearAccount]], 'user_id');
        if (empty($info))
        {
            $this->failJson('结算帐号不存在');
        }
        $userInfo = $iuserModel->getOneByWhere(['where' => ['username' => $param['user_name_receive']]], 'user_id');
        if (empty($userInfo))
        {
            $this->failJson('返现用户不存在');
        }
//        $accountModel = new AccountModel();
//        $checkMoney = $accountModel->hasMoney($info['user_id'], $param['return_cash_amount']);
//        if (!$checkMoney)
//        {
//            $this->failJson('结算账户余额不足');
//        }
        try
        {
            Db::startTrans();
            $order = [
                'source_platform_order_no'     => $param['source_platform_order_no'],
                'finance_return_cash_order_no' => $this->_makeNum($this->_type[$param['source_platform']] . $this->_businessType[$param['return_cash_type']], $info['user_id']),
                'source_platform'              => $param['source_platform'],
                'return_cash_type'             => $param['return_cash_type'],
                'return_cash_amount'           => $param['return_cash_amount'],
                'user_id_receive'              => $userInfo['user_id'],
                'user_name_receive'            => $param['user_name_receive'],
                'user_name_pay'                => $clearAccount,
                'user_id_pay'                  => $info['user_id'],
                'async_call_back_url'          => $param['async_call_back_url'],
                'withdrawal_cash_limit_day'    => $param['withdrawal_cash_limit_day'],
            ];
            $cashBackOrderModel = new CashBackOrder();
            $orderId = $cashBackOrderModel->add($order);
            if (!$orderId)
            {
                triggleError('添加返现订单信息失败' . $cashBackOrderModel->getLastSql());
            }
            $order['id'] = $orderId;
            $ret = Myredis::getRedisConn(6)->appendToList('cash_back_pay_list', $order);
            if (!$ret)
            {
                $up['queque_status'] = -1;
                $cashBackOrderModel->edit($up, $orderId);
                triggleError('redis入返现队列失败');
            }
            Db::commit();
            $this->okJson();
        } catch (\Exception $exception)
        {
            Db::rollback();
            $this->failJson(msg($exception)['msg']);
        }
    }

    /**
     * @desc 函数：返现队列
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function doPayQueue()
    {
        $list = Myredis::getRedisConn(6)->getList("cash_back_pay_list", 0, 50);
        $cashBackModel = new CashBackOrder();
        if (!empty($list))
        {
            foreach ($list as $key => $value)
            {
                $ret = $this->_pay($value, 1);
                if (true === $ret)
                {
                    $shiftResult = Myredis::getRedisConn(6)->shiftFromList("cash_back_pay_list");
                    echo $value['source_platform_order_no'] . ":返现成功" . PHP_EOL;
                    if (!$shiftResult)
                    {
                        $delResult = Myredis::getRedisConn(6)->deleteFromList("cash_back_pay_list", $value);
                        echo date('Y-m-d H:i:s') . ":" . $value['source_platform_order_no'] . " 出队列失败，改用删除，删除结果" . $delResult . PHP_EOL;
                    }
                    if (!$shiftResult && !$delResult)
                    {
                        $data['queque_status'] = -2;
                    }
                    else
                    {
                        $data['queque_status'] = 2;
                    }
                    $cashBackModel->edit($data, $value['id']);
                    self::asyncCallback($value);
                    echo $value['source_platform_order_no'] . ":出队列成功" . PHP_EOL;
                }
                else
                {
                    Myredis::getRedisConn(6)->shiftFromList("cash_back_pay_list");
                    Myredis::getRedisConn(6)->appendToList('cash_back_pay_list_error', $value);
                    echo $value['source_platform_order_no'] . ":数据状态异常" . PHP_EOL;
                }
            }
        }
        else
        {
            echo "没有订单需要处理" . PHP_EOL;
        }
    }

    /**
     * @desc 函数：回调队列
     * @author liujian
     * @date 2017-2-22
     * @access public
     * @return array
     */
    public function callBackQueque()
    {
        header("Content-type:text/html;Charset=utf8");
        $cashBackModel = new CashBackOrder();
        $where['call_back_status'] = [
            'neq',
            '2'
        ];
        $where['call_back_exec_count'] = [
            'lt',
            5
        ];
        $where['return_cash_status'] = [
            'eq',
            1
        ];
        $sqlArr = [
            'where' => $where,
            'limit' => 50
        ];
        $order = $cashBackModel->getList($sqlArr);
        if (empty($order))
        {
            echo '没有需要回调的订单';
            exit;
        }
        $i = 0;
        foreach ($order as $key => $value)
        {
            $ret = self::asyncCallback($value);

            if ($ret)
            {
                $i++;
            }
        }
        echo '共执行了：' . $i . '条记录';
    }

    /**
     * @desc 函数：回调队列
     * @author liujian
     * @date 2017-2-22
     * @access public
     * @return array
     */
    public function callBackStatusQueque()
    {
        header("Content-type:text/html;Charset=utf8");
        $cashBackModel = new CashBackOrder();
        $where['call_back_exec_count'] = [
            'egt',
            5
        ];
        $where['return_cash_status'] = [
            'eq',
            1
        ];
        $sqlArr = [
            'where' => $where,
            'limit' => 200
        ];
        $order = $cashBackModel->getList($sqlArr);
        if (empty($order))
        {
            echo '没有需要回调的订单';
            exit;
        }
        $i = 0;
        foreach ($order as $key => $value)
        {
            $data['call_back_status'] = -1;
            $data['call_back_exec_time'] = date('Y-m-d H:i:s');
            $ret = $cashBackModel->edit($data, $value['id']);
            if ($ret)
            {
                $i++;
            }
        }
        echo '共执行了：' . $i . '条记录';
    }

    /**
     * @desc 函数：异步回调
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @param $value
     * @return bool
     */
    private function asyncCallback($value)
    {
        $postfields['source_platform_order_no'] = $value['source_platform_order_no'];
        $postfields['finance_return_cash_order_no'] = $value['finance_return_cash_order_no'];
        $postfields['return_cash_amount'] = $value['return_cash_amount'];
        $postfields['return_cash_status'] = 1;
        $postfields['source_platform'] = $value['source_platform'];
        $postfields['datetime'] = date('Y-m-d H:i:s');
        $postfields['token'] = $this->_createSign($postfields);
        $result = $this->_httpRequest($value['async_call_back_url'], 'POST', $postfields);
        if ($result == 'success')
        {
            $data['call_back_status'] = 2;
            $data['call_back_succeed_time'] = date('Y-m-d H:i:s');
        }
        elseif ($result == 'fail')
        {
            $data['call_back_status'] = -1;
        }
        else
        {
            $data['call_back_status'] = 1;
        }
        $data['call_back_exec_time'] = date('Y-m-d H:i:s');
        $data['call_back_exec_count'] = [
            'exp',
            'call_back_exec_count+1'
        ];
        $cashBackModel = new CashBackOrder();
        $cashBackModel->edit($data, $value['id']);
        return true;
    }

    /**
     * CURL请求
     * @param $url 请求url地址
     * @param $method 请求方法 get post
     * @param null $postfields post数据数组
     * @param array $headers 请求header信息
     * @param bool|false $debug 调试开启 默认false
     * @return mixed
     */
    private function _httpRequest($url, $method, $postfields = null, $headers = array(), $debug = false)
    {
        $method = strtoupper($method);
        $ci = curl_init();
        /* Curl settings */
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ci, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0");
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60); /* 在发起连接前等待的时间，如果设置为0，则无限等待 */
        curl_setopt($ci, CURLOPT_TIMEOUT, 7); /* 设置cURL允许执行的最长秒数 */
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
        switch ($method)
        {
            case "POST":
                curl_setopt($ci, CURLOPT_POST, true);
                if (!empty($postfields))
                {
                    $tmpdatastr = is_array($postfields) ? http_build_query($postfields) : $postfields;
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $tmpdatastr);
                }
                break;
            default:
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method); /* //设置请求方式 */
                break;
        }
        $ssl = preg_match('/^https:\/\//i', $url) ? TRUE : FALSE;
        curl_setopt($ci, CURLOPT_URL, $url);
        if ($ssl)
        {
            curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
        }
//curl_setopt($ci, CURLOPT_HEADER, true); /*启用时会将头文件的信息作为数据流输出*/
        curl_setopt($ci, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ci, CURLOPT_MAXREDIRS, 2); /* 指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的 */
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ci, CURLINFO_HEADER_OUT, true);
        /* curl_setopt($ci, CURLOPT_COOKIE, $Cookiestr); * *COOKIE带过去** */
        $response = curl_exec($ci);
        $requestinfo = curl_getinfo($ci);
        $http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        if ($debug)
        {
            echo '<pre>';
            echo "=====url======\r\n";
            var_dump($url);
            echo "=====post data======\r\n";
            var_dump($postfields);
            echo "=====info===== \r\n";
            print_r($requestinfo);
            echo "=====response=====\r\n";
            print_r($response);
            echo "=====$http_code=====\r\n";
            print_r($http_code);
        }
        curl_close($ci);
        return $response;
//return array($http_code, $response,$requestinfo);
    }

    /**
     * @desc 函数：支付
     * @author liujian
     * @date 2017-2-22
     * @access private
     * @params array $data  更新数组
     * @return array
     */
    private function _pay($params, $type = 1)
    {
        try
        {
            if ($type == 1)
            {
                //开始事务处理
                Db::startTrans();
                $data['return_cash_status'] = 1;
                $data['return_cash_time'] = date('Y-m-d H:i:s');
                $cashBackModel = new CashBackOrder();
                $ret = $cashBackModel->editByWhere($data, ['id'                 => $params['id'],
                                                           'return_cash_status' => 0
                ]);
                unset($data);
                if (!$ret)
                {
                    triggleError('更新订单信息出错' . $cashBackModel->getLastSql());
                }
                $ActivityCashModel = new ActivityAddCash();
                $add = [
                    'user_id'       => $params['user_id_receive'],
                    'username'      => $params['user_name_receive'],
                    'activity_id'   => $params['id'],
                    'activity_name' => '雅堂超级会员购物返现',
                    'money'         => $params['return_cash_amount'],
                    'day'           => $params['withdrawal_cash_limit_day'],
                    'mention_time'  => strtotime(date('Y-m-d')) + (86400 * $params['withdrawal_cash_limit_day'])-1,
                    'k_userid'      => $params['user_id_pay'],
                    'k_username'    => $params['user_name_pay'],
                    'remark'        => '雅堂超级会员购物返现',
                    'addtime'       => time(),
                    'funds_source'  => 2,
                ];
                $res = $ActivityCashModel->add($add);
                if (!$res)
                {
                    triggleError('增加活动资金信息出错' . $ActivityCashModel->getLastSql());
                }

                //结算账户扣钱
                $orderSn = $params['source_platform_order_no'];
                $remark = "雅堂超级会员购物返现[" . $orderSn . "]";
                $change = array(
                    'uid'          => $params['user_id_pay'],
                    'to_uid'       => $params['user_id_receive'],
                    'num'          => $params['id'],
                    'total_change' => -$params['return_cash_amount'],
                    'use_change'   => -$params['return_cash_amount'],
                    'remark'       => $remark,
                    'type'         => 40001
                );
                $change1 = array(
                    'uid'          => $params['user_id_receive'],
                    'to_uid'       => $params['user_id_pay'],
                    'num'          => $params['id'],
                    'total_change' => $params['return_cash_amount'],
                    'use_change'   => $params['return_cash_amount'],
                    'remark'       => $remark,
                    'type'         => 40002,
                );
                $accountModel = new AccountLogic();
                $accountModel->upChange($change);
                $accountModel->upChange($change1);
                Db::commit();
                return true;
            }
        } catch (\Exception $exception)
        {
            Db::rollback();
            $data['return_cash_status'] = -1;
            $data['return_cash_time'] = date('Y-m-d H:i:s');
            $cashBackModel = new CashBackOrder();
            $ret = $cashBackModel->edit($data, $params['id']);
            return msg($exception)['msg'];
        }
    }

    /**
     * @desc 函数：生成订单号
     * @author liujian
     * @date 2017-2-22
     * @access private
     * @return array
     */
    private function _makeNum($code = 'D', $uid = 0)
    {
        return $code . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT) . $uid;
    }

    /**
     * @desc 函数：验证token
     * @author liujian
     * @date 2017-2-22
     * @access private
     * @return string
     */
    private function _checkToken($param = [])
    {
        $token = $this->_createSign($param);
        if ($token !=$param['token'])
        {
            $this->failJson('token错误');
        }
        return true;
    }

    /**
     * @desc 函数：生成令牌
     * @author pandelin
     * @date 2016-4-18
     * @param array $data
     * @access private
     * @return void
     */
    private function _createSign($data)
    {
        if (isset($data['token']))
        {
            unset($data['token']);
        }
        ksort($data);
        foreach ($data as $k => $v)
        {
            $strArr[] = "{$k}={$v}";
        }
        $string= join('&',$strArr);
        $config = config('settings.cash_back');
        $account = $config[$data['source_platform']];
        return md5($account['account'].$data['datetime'].$string.$account['signature']);
    }
}
