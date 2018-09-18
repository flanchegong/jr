<?php

namespace application\common\model\wallet;

use application\common\model\Base;


/**
 * @uses 汇卡-城市列表
 * @author jhl
 */
class AccountWalletCityHuika extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'account_wallet_city_huika';

    /**
     *
     * @uses 获取地区码(如果某些区、县、县级市没有，可以用地级市的地区码代替)
     * @author jhl
     */
    public function getChildFirstAreaCode($provinceId, $cityId)
    {
        // 考虑到特殊情况很低，将查询直接写入循环中
        $cityId = substr($cityId, 0, 4);
        $addCityInfo = parent::getOneByWhere([
            'field' => 'area_code',
            'where' => [
                'province_id' => $provinceId,
                'city_id' => [
                    'like',
                    "{$cityId}%"
                ]
            ],
            'order' => 'city_id ASC'
        ]);
        return isset($addCityInfo['area_code']) ? $addCityInfo['area_code'] : '';
    }
}
