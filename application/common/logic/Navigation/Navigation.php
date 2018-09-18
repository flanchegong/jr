<?php
namespace application\common\logic\Navigation;

use think\Db;
use application\common\Myredis;

/**
 *
 * @uses 导航类
 * @author jhl
 */
class Navigation
{

    /**
     *
     * @uses 获取所有的导航菜单
     * @author jhl
     */
    public static function getAllNavMenus()
    {
        $data_array = Db::name("nav")->where(array(
            'status' => 1,
            'is_navgation' => 1
        ))
            ->order('listorder')
            ->select();
        $tree = array();
        foreach ($data_array as $k => &$val) {
            if ($val['parent'] == $val['id']) {
                if (empty($tree[$val['parent']])) {
                    $tree[$val['parent']] = array();
                }
                $tree[$val['parent']] = array_merge($tree[$val['parent']], $val);
            } else {
                $tree[$val['parent']]['sub'][] = $val;
            }
        }
        
        // SEO 信息
        Myredis::getRedisConn(6)->delete("seo_setting");
        Myredis::getRedisConn(6)->delete("navgation");
        foreach ($data_array as $value) {
            $k = md5($value['url']);
            cache($k, null);
            Myredis::getRedisConn(6)->setToHash("seo_setting", $k, $value);
            cache($k, $value, 86400);
        }
        
        foreach ($tree as $k => $v) {
            Myredis::getRedisConn(6)->setToHash("navgation", $k, $v);
        }
        cache("navgation", Myredis::getRedisConn(6)->getHash('navgation'), 86400 * 10);
        return $tree;
    }

    public static function buildRouter()
    {
        $rs = Db::name('nav')->where(array(
            'dir' => array(
                'neq',
                ''
            )
        ))->select();
        foreach ($rs as $k => $v) {
            $v['url'] = rtrim(ltrim($v['url'], "/"), "/");
            $url_array = explode("/", $v['url']);
            if (count($url_array) > 2) {
                if (strtolower($url_array[0]) == 'account') {
                    $key = "{$url_array[0]}/{$v['dir']}";
                } else {
                    $key = $v['dir'];
                }
                $router[$key] = array(
                    "{$url_array[0]}/{$url_array[1]}",
                    "{$url_array[2]}={$url_array[3]}"
                );
            } else {
                $router[$v['dir']] = $v['url'];
            }
        }
        $rs = Db::name('router')->select();
        foreach ($rs as $k => $v) {
            $router[$v['key']] = preg_match("/,/", $v['value']) ? explode(",", $v['value']) : $v['value'];
        }
    }

    /**
     *
     * @uses 获取seo信息
     * @author jhl
     * @param $key:对应地址的key
     */
    public static function getSeoSetting($key = '')
    {
        if ($key == '') {
            if (preg_match("/s=/i", input('request.REQUEST_URI', '', 'string'))) {
                preg_match("@s=(.*?)&.*?@", input('request.REQUEST_URI', '', 'string'), $match);
                $key = md5("/" . $match[1]);
            } else {
                $key = md5(input('request.REQUEST_URI', '', 'string'));
                if (input('request.QUERY_STRING', '', 'string')) {
                    $key_temp = explode("s=", input('request.QUERY_STRING', '', 'string'));
                    $key = md5($key_temp[1]);
                }
                if (preg_match("/ViewBorrow/", input('request.REQUEST_URI', '', 'string'))) {
                    $key = md5("/Invest/ViewBorrow");
                }
                if (preg_match("@/Public/help@", input('request.REQUEST_URI', '', 'string'))) {
                    $key = md5("/Public/help");
                }
            }
        }
        $seo = cache($key);
        if (! $seo) {
            $seo = Myredis::getRedisConn(6)->getFromHash("seo_setting", $key);
            cache($key, $seo, 86400);
        }
        $seo['caption'] = $seo['caption'] ? $seo['caption'] : $seo['title'];
        return $seo;
    }
}
	