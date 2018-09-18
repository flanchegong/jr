<?php

namespace application\common\model\credit;

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
use think\Db;
use application\common\model\Base;

class CreditType extends Base
{

   
      /**
     * @desc 函数：根据参数获取积分类型信息
     * @author liujian
     * @date 2017-4-7
     * @access public
     * @return void
     */
    public function getCreditTypeByNid($nid)
    {   
        $key = "credit_type_$nid";
        if (cache($key))
        {
            return cache($key);
        }
        else
        {
            $where['nid'] = $nid;
            $field        = 'value,id,name';
            $result       = $this->where($where)->field($field)->find()->toArray();
            $result['op'] = $result['value'] <= 0 ? 2 : 1;
            cache($key, $result, 86400);
            return $result;
        }
    }
    
    /**
     * 根据积分类型名称获取积分值
     * @param type $name
     * @author lingyq
     * @return type
     */
    public function getCreditTypeByName($name)
    {
        $key = "credit_type_".$name;
        if (!cache($key)) {
            return cache($key);
        } else {
            $where['name'] = $name;
            $field = 'value,id,name';
            $result = Db::name('credit_type')->where($where)->field($field)->find(); 
            $result['op'] = $result['value'] <= 0 ? 2 : 1;
            cache($key, $result, 86400);
            return $result;
        }
    }
    
    /**
     * 根据条件获取某一积分类型
     * @param type $field
     * @param type $where
     * @auther lingyq
     * @return type
     */
    public function getCreditTypeOne($field, $where)
    {
        $result = Db::name('credit_type')->where($where)->field($field)->find();
        return $result ? $result : array();
    }

}
