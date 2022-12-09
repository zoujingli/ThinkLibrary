<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2022 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin;

use think\exception\HttpResponseException;

/**
 * 表单构建器
 * Class Builder
 * @package think\admin
 */
class Builder
{
    /**
     * 生成类型
     * @var string
     */
    private $type;

    /**
     * 显示方式
     * @var string
     */
    private $mode;

    /**
     * 提交地址
     * @var string
     */
    private $action;

    /**
     * 表单项目
     * @var array
     */
    private $data = [];
    private $buttons = [];

    public function __construct(string $type, string $mode)
    {
        $this->type = $type;
        $this->mode = $mode;
    }

    public static function mk(string $type = 'form', string $mode = 'modal'): Builder
    {
        return new static($type, $mode);
    }

    /**
     * 设置表单提交地址
     * @param string $url
     * @return $this
     */
    public function setAction(string $url): Builder
    {
        $this->action = $url;
        return $this;
    }

    /**
     * 增加输入表单元素
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $subtitle 字段子标题
     * @param string $remark 字段备注
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addInput(string $name, string $title, string $subtitle = '', string $remark = '', array $attrs = []): Builder
    {
        $attr = '';
        foreach ($attrs as $k => $v) $attr .= is_null($v) ? sprintf(' %s', $k) : sprintf(' %s="%s"', $k, $v);
        $html = "\n\t\t" . '<label class="layui-form-item block relative">';
        $html .= sprintf("\n\t\t\t" . '<span class="help-label %s"><b>%s</b>%s</span>', empty($attrs['required']) ? '' : 'label-required-prev', $title, $subtitle);
        $html .= sprintf("\n\t\t\t" . '<input name="%s" %s placeholder="请输入%s" value="{$vo.%s|default=\'\'}" class="layui-input">', $name, $attr, $title, $name);
        if ($remark) $html .= sprintf("\n\t\t\t" . '<span class="help-block">%s</span>', $remark);
        $this->data[] = $html . "\n\t\t" . '</lable>';
        return $this;
    }

    /**
     * 创建文本输入框架
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addTextArea(string $name, string $title, string $substr = '', bool $required = false, $remark = '', array $attrs = []): Builder
    {
        $attr = '';
        if ($required) $attrs['required'] = 'required';
        foreach ($attrs as $k => $v) $attr .= is_null($v) ? sprintf(' %s', $k) : sprintf(' %s="%s"', $k, $v);
        $html = "\n\t\t" . '<label class="layui-form-item block relative">';
        $html .= sprintf("\n\t\t\t" . '<span class="help-label %s"><b>%s</b>%s</span>', empty($attrs['required']) ? '' : 'label-required-prev', $title, $substr);
        $html .= sprintf("\n\t\t\t" . '<textarea name="%s" %s placeholder="请输入%s" class="layui-textarea">{$vo.%s|default=\'\'}</textarea>', $name, $attr, $title, $name);
        if ($remark) $html .= sprintf("\n\t\t\t" . '<span class="help-block">%s</span>', $remark);
        $this->data[] = $html . "\n\t\t" . '</lable>';
        return $this;
    }

    /**
     * 创建 Text 输入
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param string $remark 字段备注
     * @param bool $required 是否必填
     * @param string|null $pattern 验证规则
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addTextInput(string $name, string $title, string $substr = '', bool $required = false, string $remark = '', ?string $pattern = null, array $attrs = []): Builder
    {
        if ($required) $attrs['required'] = 'required';
        if (is_string($pattern)) $attrs['pattern'] = $pattern;
        return $this->addInput($name, $title, $substr, $remark, $attrs);
    }

    /**
     * 创建密钥输入框
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param string $remark 字段备注
     * @param bool $required 是否必填
     * @param string|null $pattern 验证规则
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addPassInput(string $name, string $title, string $substr = '', bool $required = false, string $remark = '', ?string $pattern = null, array $attrs = []): Builder
    {
        $attrs['type'] = 'password';
        return $this->addTextInput($name, $title, $substr, $required, $remark, $pattern, $attrs);
    }

    /**
     * 添加表单按钮
     * @param string $name
     * @param string $confirm
     * @param string $type
     * @param string $class
     * @param array $attrs
     * @return $this
     */
    public function addButton(string $name, string $confirm, string $type, string $class = '', array $attrs = []): Builder
    {
        $attr = '';
        $attrs['type'] = $type;
        if ($confirm) $attrs['data-confirm'] = $confirm;
        foreach ($attrs as $k => $v) $attr .= is_null($v) ? sprintf(' %s', $k) : sprintf(' %s="%s"', $k, $v);
        $this->buttons[] = sprintf('<button class="layui-btn %s" %s>%s</button>', $class, $attr, $name);
        return $this;
    }

    /**
     * 添加取消按钮
     * @param string $name
     * @param string $confirm
     * @return $this
     */
    public function addCancelButton(string $name = '取消编辑', string $confirm = '确定要取消编辑吗？'): Builder
    {
        return $this->addButton($name, $confirm, 'button', 'layui-btn-danger', ['data-close' => null]);
    }

    /**
     * 添加提交按钮
     * @param string $name
     * @param string $confirm
     * @return $this
     */
    public function addSubmitButton(string $name = '保存数据', string $confirm = ''): Builder
    {
        return $this->addButton($name, $confirm, 'submit');
    }

    /**
     * 显示模板内容
     * @return mixed
     */
    public function fetch()
    {
        $html = '';
        $type = "{$this->type}.{$this->mode}";
        if ($type === 'form.modal') {
            $html = $this->_buildFormModal();
        } elseif ($type === 'form.page') {
            $html = $this->_buildFormPage();
        }
        throw new HttpResponseException(display($html));
    }

    /**
     * 生成弹层表单模板
     * @return string
     */
    private function _buildFormModal(): string
    {
        // 生成表单按钮
        if (count($this->buttons)) $buttons = sprintf('
    <div class="hr-line-dashed"></div>
    {notempty name="vo.id"}<input type="hidden" value="{\$vo.id}" name="id">{/notempty}
<div class="layui-form-item text-center">%s</div>', "\n\t\t" . join("\n\t\t", $this->buttons) . "\n\t");

        // 返回完整的模板
        return sprintf('<form action="%s" method="post" data-auto="true" class="layui-form layui-card">
    <div class="layui-card-body padding-left-40">%s</div>%s
</form>', $this->action ?? url()->build(), join("\n", $this->data) . "\n\t", $buttons ?? '');
    }

    /**
     * 生成页面表单模板
     * @return string
     */
    private function _buildFormPage(): string
    {
        return '';
    }
}