<?php
 
namespace application\common\model\user;

/**
 * 用户认证图片类
 * 
 * @author:ydx
 * @date 2017-7-4 16:02:16
 * @access public 
 */
class IuserPic  extends \think\Model
{
    protected $resultSetType = 'collection';
    function initialize()
    {
        parent::initialize();
    }
    
    /**
     * 按照图片类型获取图片
     * 
     * @param int $type 图片类型
     * @param int $user_id 用户ID
     * @return []
     * 
     */
    public function getPicByType($type,$user_id)
    {
        $rs= $this->get(function($query) use($type,$user_id){
            $query->where(array('ptype' => $type, 'user_id' => $user_id));
        });
        return $rs?$rs->toArray():[];
    }
    
    /**
     * 获取其他类型的图片
     * @param int $uid 用户ID
     * @return []
     * 
     */
    public function getOthersPic($uid)
    {
        
        $rs=$this->all(function($query) use($uid){
           $sql= $query->field("*")->where(['user_id'=>$uid,'status'=>1])->where('ptype','between',[1,15])->order('puttime','asc')->buildSql();
            $query->table($sql." a ")->field('ptype,puttime,status,count(*) total')->group('ptype');
        });
       
        return $rs?$rs->toArray():[];
        
    }
    
            
}
