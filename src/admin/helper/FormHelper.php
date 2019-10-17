<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin\helper;

use think\admin\Controller;
use think\db\Query;

/**
 * 表单视图管理器
 * Class Form
 * @package library\logic
 */
class FormHelper extends Helper
{

    /**
     * 表单模板文件
     * @var string
     */
    protected $tpl;

    /**
     * 表单扩展数据
     * @var array
     */
    protected $data;

    /**
     * 表单额外更新条件
     * @var array
     */
    protected $where;

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
     * Form constructor.
     * @param Controller $controller
     * @param string|Query $dbQuery
     * @param string $template 模板名称
     * @param string $field 指定数据对象主键
     * @param array $where 额外更新条件
     * @param array $data 表单扩展数据
     */
    public function __construct(Controller $controller, $dbQuery, $template = '', $field = '', $where = [], $data = [])
    {
        $this->controller = $controller;
        $this->query = $this->buildQuery($dbQuery);
        list($this->tpl, $this->where, $this->data) = [$template, $where, $data];
        $this->pkField = empty($field) ? ($this->query->getPk() ? $this->query->getPk() : 'id') : $field;;
        $this->pkValue = input($this->pkField, isset($data[$this->pkField]) ? $data[$this->pkField] : null);
    }

    /**
     * 逻辑器初始化
     * @param array $data
     * @return array|boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function init($data = [])
    {
        // GET请求, 获取数据并显示表单页面
        if ($this->controller->request->isGet()) {
            if ($this->pkValue !== null) {
                $where = [$this->pkField => $this->pkValue];
                $data = (array)$this->query->where($where)->where($this->where)->find();
            }
            $data = array_merge($data, $this->data);
            if (false !== $this->controller->callback('_form_filter', $data)) {
                return $this->controller->fetch($this->tpl, ['vo' => $data]);
            }
            return $data;
        }
        // POST请求, 数据自动存库处理
        if ($this->controller->request->isPost()) {
            $data = array_merge($this->controller->request->post(), $this->data);
            if (false !== $this->controller->callback('_form_filter', $data, $this->where)) {
                $result = Data::save($this->query, $data, $this->pkField, $this->where);
                if (false !== $this->controller->callback('_form_result', $result, $data)) {
                    if ($result !== false) $this->controller->success('恭喜, 数据保存成功!', '');
                    $this->controller->error('数据保存失败, 请稍候再试!');
                }
                return $result;
            }
        }
    }

}
