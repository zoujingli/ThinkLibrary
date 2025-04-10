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
use think\admin\service\SystemService;
use think\Container;
use think\db\BaseQuery;
use think\db\Query;
use think\Model;

/**
 * 搜索条件处理器
 * @see \think\db\Query
 * @mixin \think\db\Query
 * @class QueryHelper
 * @package think\admin\helper
 *
 * --- 动态方法调用声明
 * @method bool mSave(array $data = [], string $field = '', mixed $where = []) 快捷更新
 * @method bool mDelete(string $field = '', mixed $where = []) 快捷删除
 * @method bool|array mForm(string $tpl = '', string $field = '', mixed $where = [], array $data = []) 快捷表单
 * @method bool|integer mUpdate(array $data = [], string $field = '', mixed $where = []) 快捷保存
 */
class QueryHelper extends Helper
{
    /**
     * 分页助手工具
     * @var PageHelper
     */
    protected $page;

    /**
     * 当前数据操作
     * @var Query
     */
    protected $query;

    /**
     * 初始化默认数据
     * @var array
     */
    protected $input;

    /**
     * 获取当前Db操作对象
     * @return Query
     */
    public function db(): Query
    {
        return $this->query;
    }

    /**
     * 逻辑器初始化
     * @param BaseQuery|Model|string $dbQuery
     * @param string|array|null $input 输入数据
     * @param callable|null $callable 初始回调
     * @return $this
     * @throws \think\db\exception\DbException
     */
    public function init($dbQuery, $input = null, ?callable $callable = null): QueryHelper
    {
        $this->page = PageHelper::instance();
        $this->input = $this->getInputData($input);
        $this->query = $this->page->autoSortQuery($dbQuery);
        is_callable($callable) && call_user_func($callable, $this, $this->query);
        return $this;
    }

    /**
     * 设置 Like 查询条件
     * @param string|array $fields 查询字段
     * @param string $split 前后分割符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function like($fields, string $split = '', $input = null, string $alias = '#'): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            [$dk, $qk] = [$field, $field];
            if (stripos($field, $alias) !== false) {
                [$dk, $qk] = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->whereLike($dk, "%{$split}{$data[$qk]}{$split}%");
            }
        }
        return $this;
    }

    /**
     * 设置 Equal 查询条件
     * @param string|array $fields 查询字段
     * @param string|array|null $input 输入类型
     * @param string $alias 别名分割符
     * @return $this
     */
    public function equal($fields, $input = null, string $alias = '#'): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            [$dk, $qk] = [$field, $field];
            if (stripos($field, $alias) !== false) {
                [$dk, $qk] = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->where($dk, strval($data[$qk]));
            }
        }
        return $this;
    }

    /**
     * 设置 IN 区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function in($fields, string $split = ',', $input = null, string $alias = '#'): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            [$dk, $qk] = [$field, $field];
            if (stripos($field, $alias) !== false) {
                [$dk, $qk] = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->whereIn($dk, explode($split, strval($data[$qk])));
            }
        }
        return $this;
    }

    /**
     * 两字段范围查询
     * @example field1:field2#field,field11:field22#field00
     * @param string|array $fields 查询字段
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function valueRange($fields, $input = null, string $alias = '#'): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            if (strpos($field, ':') !== false) {
                if (stripos($field, $alias) !== false) {
                    [$dk0, $qk0] = explode($alias, $field);
                    [$dk1, $dk2] = explode(':', $dk0);
                } else {
                    [$qk0] = [$dk1, $dk2] = explode(':', $field, 2);
                }
                if (isset($data[$qk0]) && $data[$qk0] !== '') {
                    $this->query->where([[$dk1, '<=', $data[$qk0]], [$dk2, '>=', $data[$qk0]]]);
                }
            }
        }
        return $this;
    }

    /**
     * 设置内容区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function valueBetween($fields, string $split = ' ', $input = null, string $alias = '#'): QueryHelper
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias);
    }

    /**
     * 设置日期时间区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function dateBetween($fields, string $split = ' - ', $input = null, string $alias = '#'): QueryHelper
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias, static function ($value, $type) {
            if (preg_match('#^\d{4}(-\d\d){2}\s+\d\d(:\d\d){2}$#', $value)) return $value;
            return $type === 'after' ? "{$value} 23:59:59" : "{$value} 00:00:00";
        });
    }

    /**
     * 设置时间戳区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function timeBetween($fields, string $split = ' - ', $input = null, string $alias = '#'): QueryHelper
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias, static function ($value, $type) {
            if (preg_match('#^\d{4}(-\d\d){2}\s+\d\d(:\d\d){2}$#', $value)) return strtotime($value);
            return $type === 'after' ? strtotime("{$value} 23:59:59") : strtotime("{$value} 00:00:00");
        });
    }

    /**
     * 实例化分页管理器
     * @param boolean|integer $page 是否启用分页
     * @param boolean $display 是否渲染模板
     * @param boolean|integer $total 集合分页记录数
     * @param integer $limit 集合每页记录数
     * @param string $template 模板文件名称
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function page($page = true, bool $display = true, $total = false, int $limit = 0, string $template = ''): array
    {
        return $this->page->init($this->query, $page, $display, $total, $limit, $template);
    }

    /**
     * 清空数据并保留表结构
     * @return $this
     * @throws \think\db\exception\DbException
     */
    public function empty(): QueryHelper
    {
        $table = $this->query->getTable();
        $ctype = strtolower($this->query->getConfig('type'));
        if ($ctype === 'mysql') {
            $this->query->getConnection()->execute("truncate table `{$table}`");
        } elseif (in_array($ctype, ['sqlsrv', 'oracle', 'pgsql'])) {
            $this->query->getConnection()->execute("truncate table {$table}");
        } else {
            $this->query->newQuery()->whereRaw('1=1')->delete();
        }
        return $this;
    }

    /**
     * 中间回调处理
     * @param callable $after
     * @return $this
     */
    public function filter(callable $after): QueryHelper
    {
        call_user_func($after, $this, $this->query);
        return $this;
    }

    /**
     * Layui.Table 组件数据
     * @param ?callable $befor 表单前置操作
     * @param ?callable $after 表单后置操作
     * @param string $template 视图模板文件
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function layTable(?callable $befor = null, ?callable $after = null, string $template = '')
    {
        if (in_array($this->output, ['get.json', 'get.layui.table'])) {
            if (is_callable($after)) {
                call_user_func($after, $this, $this->query);
            }
            $this->page->layTable($this->query, $template);
        } else {
            if (is_callable($befor)) {
                call_user_func($befor, $this, $this->query);
            }
            $this->class->fetch($template);
        }
    }

    /**
     * 设置区域查询条件
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @param callable|null $callback 回调函数
     * @return $this
     */
    private function setBetweenWhere($fields, string $split = ' ', $input = null, string $alias = '#', ?callable $callback = null): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            [$dk, $qk] = [$field, $field];
            if (stripos($field, $alias) !== false) {
                [$dk, $qk] = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                [$begin, $after] = explode($split, strval($data[$qk]));
                if (is_callable($callback)) {
                    $after = call_user_func($callback, $after, 'after');
                    $begin = call_user_func($callback, $begin, 'begin');
                }
                $this->query->whereBetween($dk, [$begin, $after]);
            }
        }
        return $this;
    }

    /**
     * 获取输入数据
     * @param string|array|null $input
     * @return array
     */
    private function getInputData($input): array
    {
        if (is_array($input)) {
            return $input;
        } else {
            $input = $input ?: 'request';
            return $this->app->request->$input();
        }
    }

    /**
     * 克隆属性复制
     * @return void
     */
    public function __clone()
    {
        $this->page = clone $this->page;
        $this->query = clone $this->query;
    }

    /**
     * QueryHelper call.
     * @param string $name 调用方法名称
     * @param array $args 调用参数内容
     * @return $this|mixed
     */
    public function __call(string $name, array $args)
    {
        return static::make($this->query, $name, $args, function ($name, $args) {
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
        $hooks = [
            'mForm'   => [FormHelper::class, 'init'],
            'mSave'   => [SaveHelper::class, 'init'],
            'mQuery'  => [QueryHelper::class, 'init'],
            'mDelete' => [DeleteHelper::class, 'init'],
            'mUpdate' => [SystemService::class, 'update'],
        ];
        if (isset($hooks[$method])) {
            [$class, $method] = $hooks[$method];
            return Container::getInstance()->invokeClass($class)->$method($model, ...$args);
        } else {
            return is_callable($nohook) ? $nohook($method, $args) : false;
        }
    }
}
