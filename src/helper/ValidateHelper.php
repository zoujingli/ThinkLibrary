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

namespace think\admin\helper;

use think\admin\Helper;
use think\Validate;

/**
 * 快捷输入验证器
 * @class ValidateHelper
 * @package think\admin\helper
 */
class ValidateHelper extends Helper
{
    /**
     * 快捷输入并验证（ 支持 规则 # 别名 ）
     * @param array $rules 验证规则（ 验证信息数组 ）
     * @param string|array $input 输入内容 ( post. 或 get. )
     * @param callable|null $callable 异常处理操作
     * @return array|void
     *
     * age.require => message // 最大值限定
     * age.between:1,120 => message // 范围限定
     * name.require => message // 必填内容
     * name.default => 100 // 获取并设置默认值
     * region.value => value // 固定字段数值内容
     *
     * 更多规则参照 ThinkPHP 官方的验证类
     */
    public function init(array $rules, $input = '', ?callable $callable = null)
    {
        if (is_string($input)) {
            $type = trim($input, '.') ?: 'param';
            $input = $this->app->request->$type();
        }
        [$data, $rule, $info] = [[], [], []];
        foreach ($rules as $key => $value) {
            if (is_numeric($key)) {
                [$key, $alias] = explode('#', "{$value}#");
                $data[$key] = $input[$alias ?: $key] ?? null;
            } elseif (strpos($key, '.') === false) {
                $data[$key] = $value;
            } elseif (preg_match('|^(.*?)\.(.*?)#(.*?)#?$|', "{$key}#", $matches)) {
                [, $_key, $_rule, $alias] = $matches;
                if (in_array($_rule, ['value', 'default'])) {
                    if ($_rule === 'value') $data[$_key] = $value;
                    if ($_rule === 'default') $data[$_key] = $input[$alias ?: $_key] ?? $value;
                } else {
                    $info[explode(':', "{$_key}.{$_rule}")[0]] = $value;
                    $data[$_key] = $data[$_key] ?? ($input[$alias ?: $_key] ?? null);
                    $rule[$_key] = isset($rule[$_key]) ? "{$rule[$_key]}|{$_rule}" : $_rule;
                }
            }
        }
        $validate = new Validate();
        if ($validate->rule($rule)->message($info)->check($data)) {
            return $data;
        } elseif (is_callable($callable)) {
            return call_user_func($callable, lang($validate->getError()), $data);
        } else {
            $this->class->error(lang($validate->getError()));
        }
    }
}