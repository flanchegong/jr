<?php

/**
 * @Copyright (C), 2016, Liuj.
 * @Name $name
 * @Author Liuj
 * @Version stable 1.0
 * @Date: $date
 * @Description
 * 1. Example
 * @Function List
 * 1.
 * @History
 * Liuj $date     stable 1.0 第一次建
 */
namespace application\common\model\system;

use think\Cache;
use application\common\model\Base;
use think\Db;

class Variable extends Base
{
    
    /**
	 * @desc  表名
	 * @var    string
	 * @access protected
	 */
	protected $_table = 'variable';

	/**
	 * @desc   主键
	 * @var    string
	 * @access protected
	 */
	protected $_primaryKey = 'id';
        
    /**
     * 获取某变量
     * 
     * @param string  $key 变量名称
     * @param bool $cache [option]是否缓存，默认 缓存1个月
     * @return string 变量值
     */
    public   function getVar($key,$cache=true)
    {
        if (Cache::has($key))
        {
            return Cache::get($key);
        }
        else
        { 
            $rs = $this->get(['key' => $key]);
            if($rs){
                $effect_time=$rs->getAttr('effect_time');
                $expire=$effect_time&&$effect_time>time()?$effect_time-time():2592000;
                $cache&&Cache::set($key, $rs->getAttr('value'),$expire);
                return $rs->getAttr('value');
            }

        }
    }
    
    /**
     * @uses 获取用户表信息
     * @author jhl
     * @param int $uid
     * @param string $field
     */
    public function getField($where,$fields = '*'){
        $data = Db::name($this->_table)
            ->field($fields)
            ->where($where)
            ->find();
        return $data;
    }

    /**
     * 获取自动投标开关
     * @return boolean true:启用了或者没设置或者设置不合法  false:设置无效或者停用
     */
    public function getAutoTenderSwitch(){
        $stop_auto=$this->getVar('SYS_STOP_AUTO',true);
        if($stop_auto){
            $now=time();
            $setting=explode("-",$stop_auto);
            if(count($setting)<2){
                return true;
            }
            list($start,$end)=$setting;

            if( $now<strtotime( "today $start:00:00" )||$now>strtotime( "today $end:00:00" )){
               return false;
            }else{
                return true;
            }
        }


    }

}
