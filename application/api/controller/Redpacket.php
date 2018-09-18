<?php
/**
 * @Copyright (C), 2016, jiquan
 * @Name RedPacket.php
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

use application\common\model\activity\AwardActivitiesInfo;
use application\common\model\activity\AwardParam;
use application\common\model\activity\AwardParamItem;
use application\common\model\activity\AwardRule;
use application\common\model\activity\TreasureChest;
use application\common\model\huodong\PartnerInfo;
use application\common\model\user\Iuser;
use application\common\Myredis;
use think\Db;

class Redpacket extends Base
{

    /**
     * @desc   密钥key
     * @var string
     * @access protected
     */
    protected static $_key = "red_packet_encrype_key";

    /**
     * @desc 函数：获取红包列表
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function getRedPacketList()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'account',
                'label_name' => '账户',
                'param_type' => 'string',
                'is_require' => true,

            ],
            [
                'param_name' => 'token',
                'label_name' => '令牌',
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
        $partnerInfoModel = new PartnerInfo();
        $partnerInfo = $partnerInfoModel->get(['partnerName' => $param['account']]);
        if (!$partnerInfo)
        {
            $this->failJson('账户不存在');
        }
        $partnerInfo = $partnerInfo->getData();
        $this->_checkToken($param);
        $awardActivitiesInfo = new AwardActivitiesInfo();
        $list = $awardActivitiesInfo->getActivityRedPacket($partnerInfo['id']);
        if (false === $list)
        {
            $this->failJson('参数错误');
        }
        $this->okJson($list);

    }

    /**
     * @desc 函数：发放红包
     * @author liuj
     * @update 2017-7-24
     * @access public
     * @return void
     */
    public function sendRedPacket()
    {
        //参数验证
        $filterParams = [
            [
                'param_name' => 'account',
                'label_name' => '账户',
                'param_type' => 'string',
                'is_require' => true,

            ],
            [
                'param_name' => 'token',
                'label_name' => '令牌',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'datetime',
                'label_name' => '请求时间',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'user_name',
                'label_name' => '返现用户名',
                'param_type' => 'string',
                'is_require' => true,
            ],
            [
                'param_name' => 'id',
                'label_name' => '返现模版id',
                'param_type' => 'string',
                'is_require' => true,
            ],

        ];
        list($valid, $param) = $this->getFilterParams($filterParams);
        if ($valid !== true)
        {
            $this->failJson($valid);
        }

        $partnerInfoModel = new PartnerInfo();
        $partnerInfo = $partnerInfoModel->get(['partnerName' => $param['account']]);
        if (!$partnerInfo)
        {
            $this->failJson('账户不存在');
        }
        $this->_checkToken($param);
        $userModel = new Iuser();
        $info = $userModel->getOneByWhere(['where' => ['username' => $param['user_name']]], 'user_id');
        if (empty($info))
        {
            $this->failJson('用户不存在',2);
        }
        $templateId = explode(',', $param['id']);
        if (!is_array($templateId) || empty($templateId))
        {
            $this->failJson('红包规则id格式错误');
        }
        $templateModel = new AwardRule();
        $awardParamModel = new AwardParam();
        $awardParamItemModel = new AwardParamItem();
        $treasureModel = new TreasureChest();
        $redPacket = [];
        try
        {
            Db::startTrans();
            foreach ($templateId as $v)
            {
                $templateInfo = $templateModel->getOneByWhere([
                    'where' => [
                        'id'     => $v,
                        'status' => 1
                    ]
                ]);
                if (empty($templateInfo))
                {
                    triggleError('红包规则id:' . $v . ',不存在');
                }
                $awardInfo = $awardParamModel->getList(['where' => ['ruleId' => $v]]);
                if ($templateInfo['userRuleType'] == 2001)
                {

                    foreach ($awardInfo as $kk => $vv)
                    {
                        if ($vv['splitNum'] > 0)
                        {
                            $item = $awardParamItemModel->getList(['where' => ['award_value_id' => $vv['id']]]);
                            foreach ($item as $value)
                            {
                                $redPacket[] = $value;
                            }
                        }
                        else
                        {
                            $redPacket[] = $vv;
                        }
                    }
                }
                else
                {
                    foreach ($awardInfo as $kk => $vv)
                    {
                        $redPacket[] = $vv;
                    }
                }
                if (empty($redPacket))
                {
                    triggleError('红包数据错误');
                }

                foreach ($redPacket as $v)
                {
                    $data['user_id'] = $info['user_id'];
                    $data['award_type'] = 0; //现金券
                    $data['user_constraint'] = $v['value'] * $v['multiple']; //使用约束
                    $data['value'] = $v['value']; //奖品值
                    $data['startTime'] = $templateInfo['timeEffect'] == 1 ? strtotime(date("Y-m-d H:i:s")) : $templateInfo['ruleStartTime'];; //奖品有效期开始时间
                    $data['end_time'] = strtotime(date("Y-m-d", $data['startTime'])) + $templateInfo['ruleValidDay'] * 86400 - 1;; //奖品有效期结束时间
                    $data['add_time'] = time(); //奖品添加时间
                    $data['borrowType'] = $templateInfo['borrowType']; //投标类型（1.企业9.创业6.净值7.股权11.工薪），多种类型用逗号分隔
                    $data['borrowTimeLimit'] = isset($templateInfo['borrowTimeLimit']) ? $templateInfo['borrowTimeLimit'] : 0; //投标期限1.不限2.天标,0.没填
                    $data['borrowStartMonth'] = $templateInfo['borrowStartMonth']; //投标期限起始月份
                    $data['borrowEndMonth'] = $templateInfo['borrowEndMonth']; //投标期限结束月份
                    $data['awardRuleId'] = $templateInfo['id']; //奖品规则id
                    $data['ruleType'] = $templateInfo['ruleType']; //规则类型:1001.红包1002.加息券1003.体验金
                    $data['drawType'] = 3001; //抽奖类型:3001.自动3002.手动
                    $data['status'] = 0; //奖品状态
                    $data['remark'] = $templateInfo['ruleRemark']; //备注
                    $id = $treasureModel->add($data);
                    if (!$id)
                    {
                        triggleError('添加红包数据错误' . $treasureModel->getLastSql());
                    }
                }
                unset($redPacket);
            }
            Db::commit();
            $this->okJson();
        } catch (\Exception $exception)
        {
            Db::rollback();
            $this->failJson(msg($exception));
        }
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
        return md5($data['account'].$data['datetime'].$string.self::$_key);
    }



    function test()
    {
//        $data['user_id'] = 53429;
//        $data['award_type'] = 0; //现金券
//        $data['user_constraint'] = 6000; //使用约束
//        $data['value'] = 100; //奖品值
//        $data['value_used'] = 100; //奖品值
//        $data['out_brrow_num'] = '1216EFLVZ0001716'; //奖品值
//        $data['money'] = 6000; //奖品值
//        $data['tnum'] = '1048IVPHN0004642'; //奖品值
//        $data['startTime'] = 1508396832; //奖品有效期开始时间
//        $data['end_time'] = 1508947199; //奖品有效期结束时间
//        $data['add_time'] = 1508396832; //奖品添加时间
//        $data['borrowType'] = '1,9,6,7,11,10'; //投标类型（1.企业9.创业6.净值7.股权11.工薪），多种类型用逗号分隔
//        $data['borrowTimeLimit'] = 0; //投标期限1.不限2.天标,0.没填
//        $data['borrowStartMonth'] =1; //投标期限起始月份
//        $data['borrowEndMonth'] = 24; //投标期限结束月份
//        $data['awardRuleId'] = 10965; //奖品规则id
//        $data['ruleType'] = 1001; //规则类型:1001.红包1002.加息券1003.体验金
//        $data['drawType'] = 3001; //抽奖类型:3001.自动3002.手动
//        $data['status'] = 2; //奖品状态
//        $data['remark'] = '1028号自动投资补红包'; //备注
//        $treasureModel = new TreasureChest();
//        $id = $treasureModel->add($data);
//        echo $treasureModel->getLastSql();
//        dump(json_decode($s));
//        exit;
//        $d['account'] = 'yatang_vip';
//        $d['datetime'] = '2017-10-16 12:12:12';
//        ksort($d);
//        foreach ($d as $k => $v)
//        {
//            $strArr[] = "{$k}={$v}";
//        }
//        $string= join('&',$strArr);
//        echo md5($d['account'].$d['datetime'].$string.self::$_key);

//
        $data['account'] = 'yatang_xc';
        $data['datetime'] = '2017-10-16 12:12:12';
        //$data['signature'] = "967c6a164a2146d69398f4f7e97d6e77";
        ksort($data);
        foreach ($data as $k => $v)
        {
            $strArr[] = "{$k}={$v}";
        }
        $string= join('&',$strArr);
        echo md5($data['account'].$data['datetime'].$string.self::$_key);


    }


}
