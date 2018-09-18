<?php

namespace application\common\model\article;

use think\Db;
use application\common\model\Base;

/**
 * @uses 文章
 * @author pandelin
 * @date 2017/10/04
 */
class Article extends Base
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'article';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';

    /**
     * @desc   字段映射
     * @var    string
     * @access protected
     */
    protected $_map = [
        'ar_id'         => 'id',
        'ar_cate_id'    => 'cate_id',
        'ar_title'      => 'title',
        'ar_img'        => 'img',
        'ar_url'        => 'url',
        'ar_abst'       => 'abst',
        'ar_info'       => 'info',
        'ar_addtime'    => 'addtime',
        'ar_sort'       => 'sort',
        'ar_is_best'    => 'is_best',
        'ar_is_jinrong' => 'is_jinrong',
        'ar_status'     => 'status',
        'ar_seo_title'  => 'seo_title',
        'ar_seo_keys'   => 'seo_keys',
        'ar_seo_desc'   => 'seo_desc',
        'ar_remark1'    => 'remark1',
        'ar_remark2'    => 'remark2',
        'ar_is_del'     => 'is_del',
        'ar_uid'        => 'uid',
        'ar_lasttime'   => 'lasttime',
        'ar_addip'      => 'addip',
    ];

    /**
     * @uses 文章列表
     * @author jhl
     * @param $num:limit
     * @param $fields:字段
     */
    public static function getArticleList($where, $limit = 0, $fields = '*')
    {
        $list = Db::name('article')->field($fields)->where($where)
                ->order('sort desc,addtime desc')
                ->limit($limit)
                ->select();
        return $list;
    }

    /**
     * @uses 活动中心banner列表
     * @param $id:limit
     * @param $fields:字段
     */
    public function getBannerList($id, $limit = 0, $fields = '*')
    {
        $sqlArr = [
            'where' => ['cate_id' => array('eq', $id), 'status' => 1],
            'order' => 'sort asc,addtime desc',
            'field' => $fields,
            'limit' => $limit
        ];
        $data   = $this->getList($sqlArr);
        $list   = $this->parseFieldsMap($data);
        return $list;
    }
    /**
     * @param int $cateId 类型id
     * @param int $limit 查找条数目
     * @param string $fields 查找字段
     * @param string $order 排序字段
     * **/
    public function getSlideimg($cateId,$limit,$fields,$order){
        $sqlArr = [
            'where' => [ 'cate_id' => $cateId, 'status' => 1,'addtime'=>['lt',time()] ],
            'order' => $order,
            'field' => $fields,
            'limit' => $limit
        ];
        return parent::parseFieldsMap(parent::getList($sqlArr));
    }
    /**
     * @param string $name 
     * @param int $limit 查找条数目
     * @param string $fields 查找字段
     * @param string $order 排序字段
     * **/
    public function getSlideArticle($cateId,$limit,$fields,$order){
        $sqlArr = [
            'where' => [ 'cate_id' => $cateId, 'status' => 1, 'addtime'=>['ELT',  time()] ],
            'order' => $order,
            'field' => $fields,
            'limit' => $limit
        ];
        return parent::parseFieldsMap(parent::getList($sqlArr));
    }
    /**
     * @param string $name 
     * @param int $limit 查找条数目
     * @param string $fields 查找字段
     * @param string $order 排序字段
     * **/
    public function getSlideArticleWhere($sqlArr){
        return parent::parseFieldsMap(parent::getList($sqlArr));
    }
    /**
     * 获取文章分类的导航id
     * @param string $name 文章分类名
     * **/
    public function getNavIdByName($name){
        return Db::name('nav')->where(['title'=>$name,'parent'=>231])->value('id');
    }
}
