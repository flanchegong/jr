<?php

namespace application\common\model\article;

use think\Db;
use application\common\model\Base;

/**
 * @uses 文章类型
 * @author ricky
 * @date 2017/07/20
 */
class ArticleCate extends Base
{
    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = 'article_cate';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = 'id';
    /**
     * 根据id获取分类名
     * **/
    public function getNameById($id,$fileds='name'){
        $info = parent::getOne($id);
        if($fileds=='name'){
            return $info['name'];
        }
        return $info;
    }
}
