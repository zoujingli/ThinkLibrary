<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// | 免费声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin;

use think\exception\HttpResponseException;

/**
 * 表单模板构建器
 * 后面会在兼容的基础上慢慢完善
 * @class Builder
 * @deprecated 试验中建议不使用
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
     * 当前控制器
     * @var \think\admin\Controller
     */
    private $class;

    /**
     * 提交地址
     * @var string
     */
    private $action;

    /**
     * 表单变量
     * @var string
     */
    private $variable = '$vo';

    /**
     * 表单项目
     * @var array
     */
    private $fields = [];
    private $buttons = [];

    /**
     * Constructer
     * @param string $type 页面类型
     * @param string $mode 页面模式
     * @param \think\admin\Controller $class
     */
    public function __construct(string $type, string $mode, Controller $class)
    {
        $this->type = $type;
        $this->mode = $mode;
        $this->class = $class;
    }

    /**
     * 创建表单生成器
     * @param string $type 页面类型
     * @param string $mode 页面模式
     * @return \think\admin\Builder
     */
    public static function mk(string $type = 'form', string $mode = 'modal'): Builder
    {
        return Library::$sapp->invokeClass(static::class, ['type' => $type, 'mode' => $mode]);
    }

    /**
     * 设置表单地址
     * @param string $url
     * @return $this
     */
    public function setAction(string $url): Builder
    {
        $this->action = $url;
        return $this;
    }

    /**
     * 设置变量名称
     * @param string $name
     * @return $this
     */
    public function setVariable(string $name): Builder
    {
        $this->variable = $name;
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
    protected function addInput(string $name, string $title, string $subtitle = '', string $remark = '', array $attrs = []): Builder
    {
        $html = "\n\t\t" . '<label class="layui-form-item block relative">';
        $html .= "\n\t\t\t" . sprintf('<span class="help-label %s"><b>%s</b>%s</span>', empty($attrs['required']) ? '' : 'label-required-prev', $title, $subtitle);
        $html .= "\n\t\t\t" . sprintf('<input name="%s" %s placeholder="请输入%s" value="{%s.%s|default=\'\'}" class="layui-input">', $name, $this->_attrs($attrs), $title, $this->variable, $name);
        if ($remark) $html .= "\n\t\t\t" . sprintf('<span class="help-block">%s</span>', $remark);
        $this->fields[] = "{$html}\n\t\t</label>";
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
        if ($required) $attrs['required'] = 'required';
        $html = "\n\t\t" . '<label class="layui-form-item block relative">';
        $html .= "\n\t\t\t" . sprintf('<span class="help-label %s"><b>%s</b>%s</span>', empty($attrs['required']) ? '' : 'label-required-prev', $title, $substr);
        $html .= "\n\t\t\t" . sprintf('<textarea name="%s" %s placeholder="请输入%s" class="layui-textarea">{%s.%s|default=\'\'}</textarea>', $name, $this->_attrs($attrs), $title, $this->variable, $name);
        if ($remark) $html .= "\n\t\t\t" . sprintf('<span class="help-block">%s</span>', $remark);
        $this->fields[] = "{$html}\n\t\t</lable>";
        return $this;
    }

    /**
     * 字段属性转换
     * @param array $attrs
     * @param string $html
     * @return string
     */
    protected function _attrs(array $attrs, string $html = ''): string
    {
        foreach ($attrs as $k => $v) $html .= is_null($v) ? sprintf(' %s', $k) : sprintf(' %s="%s"', $k, $v);
        return $html;
    }

    /**
     * 创建 Text 输入
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param string $remark 字段备注
     * @param boolean $required 是否必填
     * @param ?string $pattern 验证规则
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addTextInput(string $name, string $title, string $substr = '', bool $required = false, string $remark = '', ?string $pattern = null, array $attrs = []): Builder
    {
        $attrs['vali-name'] = $title;
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
     * @param boolean $required 是否必填
     * @param ?string $pattern 验证规则
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
     * @param string $name 按钮名称
     * @param string $confirm 确认提示
     * @param string $type 按钮类型
     * @param string $class 按钮样式
     * @param array $attrs 附加属性
     * @return $this
     */
    protected function addButton(string $name, string $confirm, string $type, string $class = '', array $attrs = []): Builder
    {
        $attrs['type'] = $type;
        if ($confirm) $attrs['data-confirm'] = $confirm;
        $this->buttons[] = sprintf('<button class="layui-btn %s" %s>%s</button>', $class, $this->_attrs($attrs), $name);
        return $this;
    }

    /**
     * 添加取消按钮
     * @param string $name 按钮名称
     * @param string $confirm 确认提示
     * @return $this
     */
    public function addCancelButton(string $name = '取消编辑', string $confirm = '确定要取消编辑吗？'): Builder
    {
        return $this->addButton($name, $confirm, 'button', 'layui-btn-danger', ['data-close' => null]);
    }

    /**
     * 添加提交按钮
     * @param string $name 按钮名称
     * @param string $confirm 确认提示
     * @return $this
     */
    public function addSubmitButton(string $name = '保存数据', string $confirm = ''): Builder
    {
        return $this->addButton($name, $confirm, 'submit');
    }

    /**
     * 添加上传单个文件
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param array $attrs 附加属性
     * @param string $type 上传类型
     * @return $this
     */
    private function _addUploadOneView(string $name, string $title, string $substr = '', array $attrs = [], string $type = 'image'): Builder
    {
        $attrs = array_merge($attrs, ['type' => 'text', 'placeholder' => "请上传{$title}", 'vali-name' => $title]);
        $html = "\n\t\t" . '<div class="layui-form-item">';
        $html .= "\n\t\t\t" . sprintf('<span class="help-label %s"><b>%s</b>%s</span>', empty($attrs['required']) ? '' : 'label-required-prev', $title, $substr);
        $html .= "\n\t\t\t" . '<div class="relative block label-required-null">';
        $html .= "\n\t\t\t\t" . sprintf('<input class="layui-input layui-bg-gray" name="%s" %s value="{%s.%s|default=\'\'}">', $name, $this->_attrs($attrs), $this->variable, $name);
        if ($type === 'image') {
            $html .= "\n\t\t\t\t" . sprintf('<a class="layui-icon layui-icon-upload input-right-icon" data-file="image" data-field="%s" data-type="gif,png,jpg,jpeg"></a>', $name);
        } else {
            $html .= "\n\t\t\t\t" . sprintf('<a class="layui-icon layui-icon-upload input-right-icon" data-file data-field="%s" data-type="mp4"></a>', $name);
        }
        $html .= "\n\t\t\t</div>\n\t\t</div>";
        if ($type === 'image') {
            $html .= "\n\t\t" . sprintf('<script>$("input[name=%s]").uploadOneImage()</script>', $name);
        } else {
            $html .= "\n\t\t" . sprintf('<script>$("input[name=%s]").uploadOneVideo()</script>', $name);
        }
        $this->fields[] = $html;
        return $this;
    }

    /**
     * 添加上传单图字段
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param bool $required 必填字段
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addUploadOneImage(string $name, string $title, string $substr = '', bool $required = false, array $attrs = []): Builder
    {
        if ($required) $attrs['required'] = 'required';
        return $this->_addUploadOneView($name, $title, $substr, $attrs);
    }

    /**
     * 添加上传视频字段
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param bool $required 必填字段
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addUploadOneVideo(string $name, string $title, string $substr = '', bool $required = false, array $attrs = []): Builder
    {
        if ($required) $attrs['required'] = 'required';
        return $this->_addUploadOneView($name, $title, $substr, $attrs, 'video');
    }

    /**
     * 创建上传多图字段
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param bool $required 必填字段
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addUploadMulImage(string $name, string $title, string $substr = '', bool $required = false, array $attrs = []): Builder
    {
        if ($required) $attrs['required'] = 'required';
        $attrs = array_merge($attrs, ['type' => 'hidden', 'placeholder' => "请上传{$title} ( 多图 )"]);
        $html = "\n\t\t" . '<div class="layui-form-item">';
        $html .= "\n\t\t\t" . sprintf('<span class="help-label %s"><b>%s</b>%s</span>', empty($attrs['required']) ? '' : 'label-required-prev ', $title, $substr);
        $html .= "\n\t\t\t" . '<div class="layui-textarea help-images layui-bg-gray">';
        $html .= "\n\t\t\t\t" . sprintf('<input name="%s" %s value="{%s.%s|default=\'\'}">', $name, $this->_attrs($attrs), $this->variable, $name);
        $html .= "\n\t\t\t" . '</div>' . "\n\t\t" . '</div>';
        $html .= "\n\t\t" . sprintf('<script>$("input[name=%s]").uploadMultipleImage()</script>', $name);
        $this->fields[] = $html;
        return $this;
    }

    /**
     * 创建复选框字段
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param string $vname 变量名称
     * @param bool $required 是否必选
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addCheckInput(string $name, string $title, string $substr, string $vname, bool $required = false, array $attrs = [], string $type = 'checkbox'): Builder
    {
        if ($required) $attrs['required'] = 'required';
        $attrs = array_merge($attrs, ['type' => $type, 'lay-ignore' => null, 'name' => $name . ($type === 'checkbox' ? '[]' : '')]);
        $html = "\n\t\t" . '<div class="layui-form-item">';
        $html .= "\n\t\t\t" . sprintf('<span class="help-label %s"><b>%s</b>%s</span>', empty($attrs['required']) ? '' : ' label-required-prev', $title, $substr);
        $html .= "\n\t\t\t" . '<div class="layui-textarea help-checks layui-bg-gray">';
        $html .= "\n\t\t\t\t" . sprintf('<!--{foreach $%s as $k=>$v}item-->', $vname);
        $html .= "\n\t\t\t\t" . sprintf('<label class="think-%s label-required-null">', $type);
        $html .= "\n\t\t\t\t\t" . sprintf('<!--if{if isset(%s.types) and is_array(%s.types) and in_array($k,%s.types)}-->', $this->variable, $this->variable, $this->variable);
        $html .= "\n\t\t\t\t\t" . sprintf('<input value="{$k|default=\'\'}" %s checked> {$v|default=\'\'}', $this->_attrs($attrs));
        $html .= "\n\t\t\t\t\t" . '<!--{else}else-->';
        $html .= "\n\t\t\t\t\t" . sprintf('<input value="{$k|default=\'\'}" %s> {$v|default=\'\'}', $this->_attrs($attrs)) . "\n";
        $html .= "\n\t\t\t\t\t" . '<!--{/if}if-->';
        $html .= "\n\t\t\t\t" . '</label>';
        $html .= "\n\t\t\t\t" . '<!--{/foreach}end-->';
        $this->fields[] = $html . "\n\t\t\t</div>\n\t\t</div>";
        return $this;
    }

    /**
     * 添加单选框架字段
     * @param string $name 字段名称
     * @param string $title 字段标题
     * @param string $substr 字段子标题
     * @param string $vname 变量名称
     * @param bool $required 是否必选
     * @param array $attrs 附加属性
     * @return $this
     */
    public function addRadioInput(string $name, string $title, string $substr, string $vname, bool $required = false, array $attrs = []): Builder
    {
        return $this->addCheckInput($name, $title, $substr, $vname, $required, $attrs, 'radio');
    }

    /**
     * 显示模板内容
     * @return mixed
     */
    public function fetch(array $vars = [])
    {
        $html = '';
        $type = "{$this->type}.{$this->mode}";
        if ($type === 'form.page') {
            $html = $this->_buildFormPage();
        } elseif ($type === 'form.modal') {
            $html = $this->_buildFormModal();
        }
        foreach ($this->class as $k => $v) $vars[$k] = $v;
        throw new HttpResponseException(display($html, $vars));
    }

    /**
     * 生成弹层表单模板
     * @return string
     */
    private function _buildFormModal(): string
    {
        $html = sprintf('<form action="%s" method="post" data-auto="true" class="layui-form layui-card">', $this->action ?? url()->build());
        $html .= "\n\t" . '<div class="layui-card-body padding-left-40">' . join("\n", $this->fields);
        if (count($this->buttons)) {
            $html .= "\n\n\t\t" . '<div class="hr-line-dashed"></div>';
            $html .= "\n\t\t" . sprintf('{notempty name="vo.id"}<input type="hidden" value="{%s.id}" name="id">{/notempty}', $this->variable);
            $html .= "\n\t\t" . sprintf('<div class="layui-form-item text-center">%s</div>', "\n\t\t\t" . join("\n\t\t\t", $this->buttons) . "\n\t\t");
            $html .= "\n\t" . '</div>';
        }
        return $html . "\n</form>";
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