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

use think\admin\extend\VirtualModel;
use think\App;
use think\Container;
use think\db\BaseQuery;
use think\db\Mongo;
use think\db\Query;
use think\Model;

/**
 * 控制器助手
 * @class Helper
 * @package think\admin
 */
abstract class Helper
{
    /**
     * 应用容器
     * @var App
     */
    public $app;

    /**
     * 控制器实例
     * @var Controller
     */
    public $class;

    /**
     * 当前请求方式
     * @var string
     */
    public $method;

    /**
     * 自定输出格式
     * @var string
     */
    public $output;

    /**
     * Helper constructor.
     * @param App $app
     * @param Controller $class
     */
    public function __construct(App $app, Controller $class)
    {
        $this->app = $app;
        $this->class = $class;
        // 计算指定输出格式
        $output = $app->request->request('output', 'default');
        $method = $app->request->method() ?: ($app->runningInConsole() ? 'cli' : 'nil');
        $this->output = strtolower("{$method}.{$output}");
    }

    /**
     * 实例对象反射
     * @param array $args
     * @return static
     */
    public static function instance(...$args): Helper
    {
        return Container::getInstance()->invokeClass(static::class, $args);
    }

    /**
     * 获取数据库查询对象
     * @param BaseQuery|Model|string $query
     * @return Query|Mongo|BaseQuery
     */
    public static function buildQuery($query)
    {
        if (is_string($query)) {
            if (self::isSubquery($query)) {
                $query = Library::$sapp->db->table($query);
            } else {
                return self::triggerBeforeEvent(static::buildModel($query)->db());
            }
        }
        if ($query instanceof Model) return self::triggerBeforeEvent($query->db());
        if ($query instanceof BaseQuery && !$query->getModel()) {
            // 如果是子查询，不需要挂载模型对象
            if (!self::isSubquery($query->getTable())) {
                $name = $query->getConfig('name') ?: '';
                if (is_string($name) && strlen($name) > 0) {
                    $name = config("database.connections.{$name}") ? $name : '';
                }
                $query->model(static::buildModel($query->getName(), [], $name));
            }
        }
        return self::triggerBeforeEvent($query);
    }

    /**
     * 触发查询对象执行前事件
     * @param BaseQuery|Model|mixed $query
     * @return BaseQuery|Model|mixed
     */
    private static function triggerBeforeEvent($query)
    {
        Library::$sapp->db->trigger('think_before_event', $query);
        return $query;
    }

    /**
     * 动态创建模型对象
     * @param mixed $name 模型名称
     * @param array $data 初始数据
     * @param mixed $conn 指定连接
     * @return Model
     */
    public static function buildModel(string $name, array $data = [], string $conn = ''): Model
    {
        if (strpos($name, '\\') !== false) {
            if (class_exists($name)) {
                $model = new $name($data);
                if ($model instanceof Model) return $model;
            }
            $name = basename(str_replace('\\', '/', $name));
        }
        return VirtualModel::mk($name, $data, $conn);
    }

    /**
     * 判断是否为子查询
     * @param string $sql
     * @return bool
     */
    private static function isSubquery(string $sql): bool
    {
        return preg_match('/^\(?\s*select\s+/i', $sql) > 0;
    }
}