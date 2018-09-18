<?php
/**
 * @Copyright (C), 2016, jiquan
 * @Name PartnerInfo.php
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
namespace application\common\model\huodong;

use application\common\model\Base;

class PartnerInfo extends \think\Model
{
    protected $connection ='huo_dong';
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'wy_partner_info';


}