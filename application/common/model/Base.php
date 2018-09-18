<?php

/**
 * @Copyright (C), 2017, liujian
 * @Name Base.php
 * @Author liujian
 * @Version stable 1.0
 * @Date 2017-6-9
 * @Description 模型基类
 * @Class List
 *    1.
 * @Function List
 *    1.
 * @History
 * <author>  <time>              <version>    <desc>
 *  jiquan   2017-6-9          stable 1.0   第一次建立该文件
 */

namespace application\common\model;

use think\Model;
use think\Db;

class Base extends Model
{

    /**
     * @desc  表名
     * @var    string
     * @access protected
     */
    protected $_table = '';

    /**
     * @desc   主键
     * @var    string
     * @access protected
     */
    protected $_primaryKey = '';

    /**
     * @desc   自增主键
     * @var    string
     * @access protected
     */
    protected $_autoIncrPrimaryKey = true;


    /**
     * @desc   前缀
     * @var    string
     * @access protected
     */
    protected $_prefix;

    /**
     * @desc   字段映射
     * @var    string
     * @access protected
     */
    protected $_map = [];

    /**
     * @desc 自己调试日志的开关
     * @var string
     * @access protected
     */
    protected $_myLog = 0;

    /**
     * @desc sql输出日志的开关
     * @var string
     * @access protected
     */
    protected $_sqlLog = 0;

    /**
     * @desc 慢查询临界毫秒数
     * @var string
     * @access protected
     */
    protected $_maxSqlSecond = 500;

    //自定义初始化
    protected function initialize()
    {
        //需要调用`Model`的`initialize`方法
        parent::initialize();
        //TODO:自定义的初始化
        if (strstr($this->_primaryKey,','))
        {
            return false;
        }
    }

    /**
     * @desc 获取一条记录
     * @access public
     * @author liujian
     * @date 2017-06-20
     * @param  string $field 查询字段
     * @param int $id 自增ID
     * @return array
     */
    public function getOne($id = 0, $field = '*',$master=false)
    {
        // 调试
        $this->deBugLog('[输入参数]getOneByPrimaryKey ', array($id));

        // id无效返回失败
        $id = (int)$id;
        if ($id <= 0)
        {
            return array();
        }

        $t1 = get_milli_second();
        $where[$this->_primaryKey] = $id;
        if ($master)
        {
            $result = Db::name($this->_table)->field($field)->where($where)->master($master)->find();
        }
        else
        {
            $result = Db::name($this->_table)->field($field)->where($where)->find();
        }

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]getOneByPrimaryKey ', array($result));

        return $result;
    }

    /**
     * @desc 由任意条件获取一条记录
     * @access public
     * @author liujian
     * @update 2017-06-20
     * @params array $params 查询条件数组
     * @params array $field 查询字段
     * @return array
     */
    public function getOneByWhere($sqlAttr = [], $field = '*')
    {
        // 调试
        $this->deBugLog('[输入参数]getOneByWheres ', $sqlAttr);

        if (isset($sqlAttr['field']))
        {
            if (is_array($sqlAttr['field']))
            {
                $field = empty($sqlAttr['field']) ? '*' : join(',', $sqlAttr['field']);
            }
            elseif (is_string($sqlAttr['field']))
            {

                $field = empty($sqlAttr['field']) ? '*' : $sqlAttr['field'];
            }
        }

        $where = isset($sqlAttr['where']) ? $sqlAttr['where'] : '';
        $order = isset($sqlAttr['order']) ? $sqlAttr['order'] : '';
        $group = isset($sqlAttr['group']) ? $sqlAttr['group'] : '';
        $master = isset($sqlAttr['master'])  ? $sqlAttr['master'] : false;
        $t1 = get_milli_second();
        $db = Db::name($this->_table)->field($field);
        if ($where != '')
        {
            $db->where($where);
        }
        if ($group != '')
        {
            $db->group($group);
        }
        if ($order != '')
        {
            $db->order($order);
        }
        $db->limit(1);
        if ($master)
        {
            $result = $db->master($master)->find();
        }
        else
        {
            $result = $db->find();
        }

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]getOneByWheres ', array($result));

        return $result;

    }

    /**
     * @desc   统计数量
     * @access public
     * @author liujian
     * @update 2017-06-20
     * @params array $params 查询条件数组
     * @params array $field 查询字段
     * @return int
     */
    public function getCount($where)
    {
        // 调试
        $this->deBugLog('[输入参数]getCount ', array($where));

        $t1 = get_milli_second();
        if (empty($where))
        {
            $result = Db::name($this->_table)->count();
        }
        else
        {
            $result = Db::name($this->_table)->where($where)->count();
        }

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]getCount ', array($result));

        return $result;
    }

    /**
     * @desc 获取列表
     * @access public
     * @author liujian
     * @date 2017-06-20
     * @param array $sqlAttr
     *        array where     条件
     *        array $field    查询字段
     *        bool $master    是否主表
     *        bool $lock      是否锁表
     *        string $order   排序字段
     *        string $limit   返回记录行,格式 "0,10"
     *        string $group   分组字段
     *        string $having  筛选条件
     * @return array
     */
    public function getList($sqlAttr = [], $isPage = false)
    {
        // 调试
        $this->deBugLog('[输入参数]getList ', array(
            $sqlAttr,
            $isPage
        ));

        if (!isset($sqlAttr['field']))
        {
            $field = '*';
        }
        else
        {
            if (is_array($sqlAttr['field']))
            {
                $field = empty($sqlAttr['field']) ? '*' : join(',', $sqlAttr['field']);
            }
            else
            {
                if (is_string($sqlAttr['field']))
                {
                    $field = empty($sqlAttr['field']) ? '*' : $sqlAttr['field'];
                }
                else
                {
                    $field = '*';
                }
            }
        }
        $where = isset($sqlAttr['where']) ? $sqlAttr['where'] : '';
        $order = isset($sqlAttr['order']) ? $sqlAttr['order'] : '';
        $limit = isset($sqlAttr['limit']) ? $sqlAttr['limit'] : '';
        $group = isset($sqlAttr['group']) ? $sqlAttr['group'] : '';
        $having = isset($sqlAttr['having']) ? $sqlAttr['having'] : '';
        $page = isset($sqlAttr['page']) ? $sqlAttr['page'] : '1';
        $master = isset($sqlAttr['master'])  ? $sqlAttr['master'] : false;

        $listRows = isset($sqlAttr['list_rows']) ? $sqlAttr['list_rows'] : '20';
        $pageTotal = isset($sqlAttr['total']) ? $sqlAttr['total'] : false;
        $db = Db::name($this->_table)->field($field);
        $t1 = get_milli_second();
        if ($where != '')
        {
            $db->where($where);
        }
        if ($group != '')
        {
            $db->group($group);
        }
        if ($having != '')
        {
            $db->having($having);
        }
        if ($order != '')
        {
            $db->order($order);
        }
        if ($limit != '')
        {
            $db->limit($limit);
        }
        if ($isPage)
        {
            if ($master)
            {
                $result = $db->master($master)->paginate($listRows, $pageTotal, ['page' => $page]);
            }
            else
            {
                $result = $db->paginate($listRows, $pageTotal, ['page' => $page]);
            }
        }
        else
        {
            if ($master)
            {
                $result = $db->master($master)->select();
            }
            else
            {
                $result = $db->select();
            }
        }

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]getList ', array($result));

        return $result;
    }


    /**
     * @desc 添加
     * @access public
     * @author liujian
     * @date 2017-06-20
     * @param array $insertArr 插入数据
     * @return bool
     */
    public function add($insertArr = [])
    {
        // 调试
        $this->deBugLog('[输入参数]add ', $insertArr);

        // 非数组格式返回失败
        if (!is_array($insertArr))
        {
            // 调试
            $this->deBugLog('[输出结果]add ', array(false));
            return false;
        }

        $t1 = get_milli_second();
        $result = Db::name($this->_table)->insert($insertArr, false, $this->_autoIncrPrimaryKey);

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]add ', array($result));
        return $result;
    }

    /**
     * @desc 添加
     * @access public
     * @author liujian
     * @date 2017-06-20
     * @param array $insertArr 插入数据
     * @return bool
     */
    public function addAll($insertArr = [])
    {
        // 调试
        $this->deBugLog('[输入参数]addAll ', $insertArr);

        // 非数组格式返回失败
        if (!is_array($insertArr))
        {
            // 调试
            $this->deBugLog('[输出结果]addAll ', array(false));
            return false;
        }

        $t1 = get_milli_second();
        $result = Db::name($this->_table)->insertAll($insertArr);

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]addAll ', array($result));

        return $result;

    }

    /**
     * @desc 编辑
     * @access public
     * @author liujian
     * @date 2017-06-20
     * @param array $updateArr 更新数据
     * @param int $id 自增ID
     * @return bool
     */
    public function edit($updateArr = [], $id = 0)
    {
        // 调试
        $this->deBugLog('[输入参数]edit ', array(
            $updateArr,
            $id
        ));

        // 非数组格式返回失败
        if (!is_array($updateArr))
        {
            $this->deBugLog('[输出结果]edit ', array(false));
            return false;
        }

        // id无效返回失败
        $id = (int)$id;
        if ($id <= 0)
        {
            $this->deBugLog('[输出结果]edit ', array(false));
            return false;
        }

        $t1 = get_milli_second();
        $result = Db::name($this->_table)->where(" {$this->_primaryKey} = {$id} ")->update($updateArr);

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]edit ', array($result));

        return $result;
    }

    /**
     * @desc 根据指定条件修改记录
     * @access public
     * @author liujian
     * @date 2017-06-20
     * @param array $updateArr 修改数据: 字段=>value,...
     * @param array $where ：必须是"字段名=>值"格式
     * @return bool
     */
    public function editByWhere($updateArr = [], $where)
    {
        // 调试
        $this->deBugLog('[输入参数]editByWhere ', array(
            $updateArr,
            $where
        ));

        // 非数组格式返回失败
        if (!is_array($updateArr))
        {
            $this->deBugLog('[输出结果]editByWhere ', array(false));
            return false;
        }

        // 无条件返回失败
        if (empty($where))
        {
            $this->deBugLog('[输出结果]editByWhere ', array(false));
            return false;
        }

        $t1 = get_milli_second();
        $result = Db::name($this->_table)->where($where)->update($updateArr);

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]editByWhere ', array($result));

        return $result;
    }

    /**
     * @desc 删除
     * @access public
     * @author liujian
     * @date 2017-06-20
     * @param int $id 自增ID
     * @return bool
     */
    public function del($id = 0)
    {
        // 调试
        $this->deBugLog('[输入参数]del ', array($id));

        // id无效返回失败
        $id = (int)$id;
        if ($id <= 0)
        {
            $this->deBugLog('[输出结果]del ', array(false));
            return false;
        }
        $t1 = get_milli_second();
        $result = Db::name($this->_table)->where(" {$this->_primaryKey} = {$id} ")->delete();

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]del ', array($result));

        return $result;
    }

    /**
     * @desc 删除
     * @access public
     * @author liujian
     * @date 2017-06-20
     * @param int $id 自增ID
     * @return bool
     */
    public function delByWhere($where)
    {
        // 调试
        $this->deBugLog('[输入参数]delByWhere ', array($where));
        // 无条件返回失败
        if (empty($where))
        {
            // 调试
            $this->deBugLog('[输出结果]delByWhere ', array(false));
            return false;
        }

        $t1 = get_milli_second();
        $result = Db::name($this->_table)->where($where)->delete();

        // 调试
        $sql = Db::name($this->_table)->getLastSql();
        //慢查日志
        $this->slowSQLLog($t1, $sql);
        // 调试
        $this->deBugLog('[输出结果]del ', array($result));

        return $result;
    }

    /**
     * 处理字段映射
     * @access public
     * @param array $data 当前数据
     * @param integer $type 类型 0 写入 1 读取
     * @return array
     */
    public function parseFieldsMap($data, $type = 1)
    {
        return parse_fields_map($data, $this->_map, $type);

    }

    /**
     * @desc 是否输出自己调试
     * @access public
     * @param bool $switch 开关
     * @return void
     */
    public function setMyLog($switch)
    {
        $this->_myLog = (bool)$switch;
    }

    /**
     * @desc 是否输出sql调试
     * @access public
     * @param bool $switch 开关
     * @return void
     */
    public function setSQLLog($switch)
    {
        $this->_sqlLog = (bool)$switch;
    }

    /**
     * @desc 调试日志
     * @author zans
     * @update 2015-4-13
     * @access public
     * @param mixed $message 日志内容
     * @param array $extra 附加信息数组
     * @return void
     */
    public function deBugLog($message, $extra = array())
    {
        if ($this->_myLog)
        {
            if (is_object($message) || is_array($message))
            {
                $message = var_export($message, true);
            }
            if (!empty($extra))
            {
                $message .= var_export($extra, true);
            }
            \think\Log::write($message, "debug");
        }
    }

    /**
     * @desc 慢查询日志
     * @author zans
     * @update 2015-4-13
     * @access public
     * @param int $t1 时间戳
     * @param string $sql 语句
     * @return void
     */
    public function slowSQLLog($t1, $sql)
    {
        if ($this->_sqlLog)
        {
            $this->deBugLog('[SQL] ' . $sql);
        }

        $t2 = get_milli_second();
        if ($t2 - $t1 > $this->_maxSqlSecond)
        {
            \think\Log::write('SLOW_SQL', '[TIME] = ' . ($t2 - $t1) . ' [SQL] ' . $sql, "debug");
        }
    }

}
