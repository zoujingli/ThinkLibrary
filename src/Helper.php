<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | ThinkAdmin Plugin for ThinkAdmin
 * +----------------------------------------------------------------------
 * | 版权所有 2014~2026 ThinkAdmin [ thinkadmin.top ]
 * +----------------------------------------------------------------------
 * | 官方网站: https://thinkadmin.top
 * +----------------------------------------------------------------------
 * | 开源协议 ( https://mit-license.org )
 * | 免责声明 ( https://thinkadmin.top/disclaimer )
 * | 会员特权 ( https://thinkadmin.top/vip-introduce )
 * +----------------------------------------------------------------------
 * | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
 * | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
 * +----------------------------------------------------------------------
 */

namespace think\admin;

use think\admin\extend\VirtualModel;
use think\App;
use think\Container;
use think\db\BaseQuery;
use think\db\Mongo;
use think\db\Query;
use think\Model;

/**
 * 控制器助手.
 * @class Helper
 */
abstract class Helper
{
    /**
     * 应用容器.
     * @var App
     */
    public $app;

    /**
     * 控制器实例.
     * @var Controller
     */
    public $class;

    /**
     * 当前请求方式.
     * @var string
     */
    public $method;

    /**
     * 自定输出格式.
     * @var string
     */
    public $output;

    /**
     * Helper constructor.
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
     * 实例对象反射.
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
     * @return BaseQuery|Mongo|Query
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
        if ($query instanceof Model) {
            return self::triggerBeforeEvent($query->db());
        }
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
     * 动态创建模型对象
     * @param mixed $name 模型名称
     * @param array $data 初始数据
     * @param mixed $conn 指定连接
     */
    public static function buildModel(string $name, array $data = [], string $conn = ''): Model
    {
        if (strpos($name, '\\') !== false) {
            if (class_exists($name)) {
                $model = new $name($data);
                if ($model instanceof Model) {
                    return $model;
                }
            }
            $name = basename(str_replace('\\', '/', $name));
        }
        return VirtualModel::mk($name, $data, $conn);
    }

    /**
     * 触发查询对象执行前事件.
     * @param BaseQuery|mixed|Model $query
     * @return BaseQuery|mixed|Model
     */
    private static function triggerBeforeEvent($query)
    {
        Library::$sapp->db->trigger('think_before_event', $query);
        return $query;
    }

    /**
     * 判断是否为子查询.
     */
    private static function isSubquery(string $sql): bool
    {
        return preg_match('/^\(?\s*select\s+/i', $sql) > 0;
    }
}
