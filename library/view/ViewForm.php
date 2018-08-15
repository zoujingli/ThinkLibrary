<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2018 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://library.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace library\view;

use think\Db;
use think\db\Query;
use library\tools\Data;
use library\Controller;

/**
 * 表单视图管理器
 * Class ViewForm
 * @package library\view
 */
class ViewForm
{
    /**
     * 数据库操作对象
     * @var Query
     */
    protected $db;

    /**
     * 当前操作控制器引用
     * @var Controller
     */
    protected $class;

    /**
     * 表单额外更新条件
     * @var array
     */
    protected $where;

    /**
     * 表单模板文件
     * @var string
     */
    protected $tplFile;

    /**
     * 数据对象主键名称
     * @var array|string
     */
    protected $pkField;

    /**
     * 数据对象主键值
     * @var string
     */
    protected $pkValue;

    /**
     * 表单扩展数据
     * @var array
     */
    protected $extendData;

    /**
     * ViewForm constructor.
     * @param string|Query $dbQuery
     * @param string $tplFile 模板名称
     * @param string $pkField 指定数据对象主键
     * @param array $where 额外更新条件
     * @param array $extendData 表单扩展数据
     */
    public function __construct($dbQuery, $tplFile = '', $pkField = '', $where = [], $extendData = [])
    {
        // 生成数据库操作对象
        $this->db = is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery;
        // 传入的参数赋值处理
        list($this->tplFile, $this->where, $this->extendData) = [$tplFile, $where, $extendData];
        // 获取表单主键的名称
        $this->pkField = empty($pkField) ? ($this->db->getPk() ? $this->db->getPk() : 'id') : $pkField;;
        // 从where及extend中获取主键的默认值
        $pkWhereValue = isset($where[$this->pkField]) ? $where[$this->pkField] : (isset($extendData[$this->pkField]) ? $extendData[$this->pkField] : null);
        $this->pkValue = request()->request($this->pkField, $pkWhereValue);
    }

    /**
     * 组件应用器
     * @param Controller $contrlloer
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function apply($contrlloer)
    {
        $this->class = $contrlloer;
        // GET请求, 获取数据并显示表单页面
        if (request()->isGet()) {
            return $this->display();
        }
        // POST请求, 数据自动存库处理
        if (request()->isPost()) {
            $this->update();
        }
    }

    /**
     * 表单数据更新
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    protected function update()
    {
        $post = request()->post();
        $data = array_merge($post, $this->extendData);
        if (false !== $this->class->_callback('_form_filter', $data, $post)) {
            $result = Data::save($this->db, $data, $this->pkField, $this->where);
            if (false !== $this->class->_callback('_form_result', $result, $data)) {
                if ($result !== false) {
                    $this->class->success('恭喜, 数据保存成功!', '');
                }
                $this->class->error('数据保存失败, 请稍候再试!');
            }
        }
    }

    /**
     * 数据显示处理
     * @param array $vo
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function display($vo = [])
    {
        if ($this->pkValue !== null) {
            $where = [$this->tplFile => $this->pkValue];
            $vo = (array)$this->db->where($where)->where($this->where)->find();
        }
        $vo = array_merge($vo, $this->extendData);
        if (false !== $this->class->_callback('_form_filter', $vo)) {
            return $this->class->fetch($this->tplFile, ['vo' => $vo]);
        }
        return $vo;
    }

}