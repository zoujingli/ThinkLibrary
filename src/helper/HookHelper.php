<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2024 ThinkAdmin [ thinkadmin.top ]
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
use think\admin\service\SystemService;
use think\Container;
use think\db\Query;
use think\Model;

/**
 * 通用助手调用
 * @class HookHelper
 * @mixin \think\db\Query
 * @package think\admin\helper
 *
 * --- 动态方法调用声明
 * @method bool mSave(array $data = [], string $field = '', mixed $where = []) 快捷更新
 * @method bool mDelete(string $field = '', mixed $where = []) 快捷删除
 * @method bool|array mForm(string $template = '', string $field = '', mixed $where = [], array $data = []) 快捷表单
 * @method bool|integer mUpdate(array $data = [], string $field = '', mixed $where = []) 快捷保存
 * @method QueryHelper mQuery($input = null, callable $callable = null) 快捷查询
 */
class HookHelper extends Helper
{
    /**
     * 当前数据操作
     * @var \think\db\Query
     */
    protected $query;

    /**
     * 初始化通用助手
     * @param string|Model $dbQuery
     * @param callable|null $callable
     * @return $this
     */
    public function init($dbQuery, ?callable $callable = null): HookHelper
    {
        $this->query = static::buildQuery($dbQuery);
        is_callable($callable) && $callable($this->query);
        return $this;
    }

    /**
     * 静态魔术方法
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed|false|integer|QueryHelper
     */
    public function __call(string $method, array $args)
    {
        return static::make($this->query, $method, $args, function ($name, $args) {
            if (is_callable($callable = [$this->query, $name])) {
                $value = call_user_func_array($callable, $args);
                if ($name[0] === '_' || $value instanceof $this->query) {
                    return $this;
                } else {
                    return $value;
                }
            } else {
                return $this;
            }
        });
    }

    /**
     * 快捷助手调用勾子
     * @param string|Model|Query $model
     * @param string $method
     * @param array $args
     * @param callable|null $nohook
     * @return mixed|false|integer|QueryHelper
     */
    public static function make($model, string $method = 'init', array $args = [], ?callable $nohook = null)
    {
        $helpers = [
            'mHook'   => [HookHelper::class, 'init'],
            'mForm'   => [FormHelper::class, 'init'],
            'mSave'   => [SaveHelper::class, 'init'],
            'mQuery'  => [QueryHelper::class, 'init'],
            'mDelete' => [DeleteHelper::class, 'init'],
            'mUpdate' => [SystemService::class, 'save'],
        ];
        if (isset($helpers[$method])) {
            [$class, $method] = $helpers[$method];
            return Container::getInstance()->invokeClass($class)->$method($model, ...$args);
        } else {
            return is_callable($nohook) ? $nohook($method, $args) : false;
        }
    }
}