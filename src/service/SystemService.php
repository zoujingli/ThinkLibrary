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

namespace think\admin\service;

use think\admin\Exception;
use think\admin\extend\FaviconExtend;
use think\admin\Helper;
use think\admin\Library;
use think\admin\model\SystemConfig;
use think\admin\model\SystemData;
use think\admin\model\SystemOplog;
use think\admin\Service;
use think\admin\storage\LocalStorage;
use think\App;
use think\db\Query;
use think\helper\Str;
use think\Model;

/**
 * 系统参数管理服务
 * Class SystemService
 * @package think\admin\service
 *
 * @method static bool isDebug() 调式模式运行
 * @method static bool isOnline() 产品模式运行
 *
 * 运行环境配置
 * @method static array getRuntime(?string $name = null, array $default = []) 获取动态配置
 * @method static bool setRuntime(?string $mode = null, ?array $appmap = [], ?array $domain = []) 设置动态配置
 * @method static bool bindRuntime(array $data = []) 绑定动态配置
 *
 * 运行缓存管理
 * @method static bool pushRuntime() 压缩发布项目
 * @method static bool clearRuntime() 清理运行缓存
 * @method static bool checkRunMode(string $type = 'dev') 判断运行环境
 *
 * 初始化启动系统
 * @method static mixed doInit(?App $app = null) 初始化主程序
 * @method static mixed doConsoleInit(?App $app = null) 初始化命令行
 */
class SystemService extends Service
{
    /**
     * 配置数据缓存
     * @var array
     */
    private static $data = [];

    /**
     * 生成静态路径链接
     * @param string $path 后缀路径
     * @param ?string $type 路径类型
     * @param mixed $default 默认数据
     * @return string|array
     */
    public static function uri(string $path = '', ?string $type = '__ROOT__', $default = '')
    {
        static $app, $root, $full;
        empty($app) && $app = rtrim(url('@')->build(), '\\/');
        empty($root) && $root = rtrim(dirname(Library::$sapp->request->basefile()), '\\/');
        empty($full) && $full = rtrim(dirname(Library::$sapp->request->basefile(true)), '\\/');
        $data = ['__APP__' => $app . $path, '__ROOT__' => $root . $path, '__FULL__' => $full . $path];
        return is_null($type) ? $data : ($data[$type] ?? $default);
    }

    /**
     * 生成全部静态路径
     * @param string $path
     * @return string[]
     */
    public static function uris(string $path = ''): array
    {
        return static::uri($path, null);
    }

    /**
     * 设置配置数据
     * @param string $name 配置名称
     * @param mixed $value 配置内容
     * @return integer|string
     * @throws \think\db\exception\DbException
     */
    public static function set(string $name, $value = '')
    {
        static::$data = [];
        [$type, $field] = static::_parse($name);
        if (is_array($value)) {
            $count = 0;
            foreach ($value as $kk => $vv) {
                $count += static::set("{$field}.{$kk}", $vv);
            }
            return $count;
        } else {
            Library::$sapp->cache->delete('SystemConfig');
            $map = ['type' => $type, 'name' => $field];
            $data = array_merge($map, ['value' => $value]);
            $query = SystemConfig::mk()->master()->where($map);
            return (clone $query)->count() > 0 ? $query->update($data) : $query->insert($data);
        }
    }

    /**
     * 读取配置数据
     * @param string $name
     * @param string $default
     * @return array|mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function get(string $name = '', string $default = '')
    {
        if (empty(static::$data)) {
            SystemConfig::mk()->cache('SystemConfig')->select()->map(function ($item) {
                static::$data[$item['type']][$item['name']] = $item['value'];
            });
        }
        [$type, $field, $outer] = static::_parse($name);
        if (empty($name)) {
            return static::$data;
        } elseif (isset(static::$data[$type])) {
            $group = static::$data[$type];
            if ($outer !== 'raw') foreach ($group as $kk => $vo) {
                $group[$kk] = htmlspecialchars(strval($vo));
            }
            return $field ? ($group[$field] ?? $default) : $group;
        } else {
            return $default;
        }
    }

    /**
     * 数据增量保存
     * @param Model|Query|string $query 数据查询对象
     * @param array $data 需要保存的数据，成功返回对应模型
     * @param string $key 更新条件查询主键
     * @param mixed $map 额外更新查询条件
     * @return boolean|integer 失败返回 false, 成功返回主键值或 true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function save($query, array &$data, string $key = 'id', $map = [])
    {
        $query = Helper::buildQuery($query)->master()->strict(false);
        if (empty($map[$key])) $query->where([$key => $data[$key] ?? null]);
        $model = $query->where($map)->findOrEmpty();
        // 当前操作方法描述
        $action = $model->isExists() ? 'onAdminUpdate' : 'onAdminInsert';
        // 写入或更新模型数据
        if ($model->save($data) === false) return false;
        // 模型自定义事件回调
        if ($model instanceof \think\admin\Model) {
            $model->$action(strval($model[$key] ?? ''));
        }
        $data = $model->toArray();
        return $model[$key] ?? true;
    }

    /**
     * 解析缓存名称
     * @param string $rule 配置名称
     * @return array
     */
    private static function _parse(string $rule): array
    {
        $type = 'base';
        if (stripos($rule, '.') !== false) {
            [$type, $rule] = explode('.', $rule, 2);
        }
        [$field, $outer] = explode('|', "{$rule}|");
        return [$type, $field, strtolower($outer)];
    }

    /**
     * 生成最短URL地址
     * @param string $url 路由地址
     * @param array $vars PATH 变量
     * @param boolean|string $suffix 后缀
     * @param boolean|string $domain 域名
     * @return string
     */
    public static function sysuri(string $url = '', array $vars = [], $suffix = true, $domain = false): string
    {
        // 读取默认节点配置
        $app = Library::$sapp->config->get('route.default_app') ?: 'index';
        $ext = Library::$sapp->config->get('route.url_html_suffix') ?: 'html';
        $act = Str::lower(Library::$sapp->config->get('route.default_action') ?: 'index');
        $ctr = Str::snake(Library::$sapp->config->get('route.default_controller') ?: 'index');
        // 生成完整链接地址
        $pre = Library::$sapp->route->buildUrl('@')->suffix(false)->domain($domain)->build();
        $uri = Library::$sapp->route->buildUrl($url, $vars)->suffix($suffix)->domain($domain)->build();
        // 替换省略链接路径
        return preg_replace([
            "#^({$pre}){$app}/{$ctr}/{$act}(\.{$ext}|^\w|\?|$)?#i",
            "#^({$pre}[\w.]+)/{$ctr}/{$act}(\.{$ext}|^\w|\?|$)#i",
            "#^({$pre}[\w.]+)(/[\w.]+)/{$act}(\.{$ext}|^\w|\?|$)#i",
            "#/\.{$ext}$#i",
        ], ['$1$2', '$1$2', '$1$2$3', ''], $uri);
    }

    /**
     * 获取数据库所有数据表
     * @return array [table, total, count]
     */
    public static function getTables(): array
    {
        $tables = Library::$sapp->db->getTables();
        return [$tables, count($tables), 0];
    }

    /**
     * 复制并创建表结构
     * @param string $from 来源表名
     * @param string $create 创建表名
     * @param array $tables 现有表集合
     * @param boolean $copy 是否复制
     * @param mixed $where 复制条件
     * @throws \think\admin\Exception
     */
    public static function copyTableStruct(string $from, string $create, array $tables = [], bool $copy = false, $where = [])
    {
        if (empty($tables)) [$tables] = static::getTables();
        if (!in_array($from, $tables)) {
            throw new Exception("待复制的数据表 {$from} 不存在！");
        }
        if (!in_array($create, $tables)) {
            Library::$sapp->db->query("CREATE TABLE IF NOT EXISTS {$create} (LIKE {$from})");
            if ($copy) {
                $sql1 = Library::$sapp->db->name($from)->where($where)->buildSql(false);
                Library::$sapp->db->query("INSERT INTO {$create} {$sql1}");
            }
        }
    }

    /**
     * 保存数据内容
     * @param string $name
     * @param mixed $value
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function setData(string $name, $value)
    {
        $data = ['name' => $name, 'value' => serialize($value)];
        return static::save('SystemData', $data, 'name');
    }

    /**
     * 读取数据内容
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public static function getData(string $name, $default = [])
    {
        try {
            // 读取原始序列化数据
            $value = SystemData::mk()->where(['name' => $name])->value('value');
            if (is_null($value)) return $default;
        } catch (\Exception $exception) {
            trace_file($exception);
            return $default;
        }
        try {
            // 尝试正常反序列解析
            return unserialize($value);
        } catch (\Exception $exception) {
            trace_file($exception);
        }
        try {
            // 尝试修复反序列解析
            $unit = 'i:\d+;|b:[01];|s:\d+:".*?";|O:\d+:".*?":\d+:\{';
            $preg = '/(?=^|' . $unit . ')s:(\d+):"(.*?)";(?=' . $unit . '|}+$)/';
            return unserialize(preg_replace_callback($preg, function ($attr) {
                return sprintf('s:%d:"%s";', strlen($attr[2]), $attr[2]);
            }, $value));
        } catch (\Exception $exception) {
            trace_file($exception);
            return $default;
        }
    }

    /**
     * 写入系统日志内容
     * @param string $action
     * @param string $content
     * @return boolean
     */
    public static function setOplog(string $action, string $content): bool
    {
        return SystemOplog::mk()->save(static::getOplog($action, $content)) !== false;
    }

    /**
     * 获取系统日志内容
     * @param string $action
     * @param string $content
     * @return array
     */
    public static function getOplog(string $action, string $content): array
    {
        return [
            'node'      => NodeService::getCurrent(),
            'action'    => $action, 'content' => $content,
            'geoip'     => Library::$sapp->request->ip() ?: '127.0.0.1',
            'username'  => AdminService::getUserName() ?: '-',
            'create_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 打印输出数据到文件
     * @param mixed $data 输出的数据
     * @param boolean $new 强制替换文件
     * @param string|null $file 文件名称
     * @return false|int
     */
    public static function putDebug($data, bool $new = false, ?string $file = null)
    {
        if (is_null($file)) $file = with_path('runtime' . DIRECTORY_SEPARATOR . date('Ymd') . '.log');
        $str = (is_string($data) ? $data : ((is_array($data) || is_object($data)) ? print_r($data, true) : var_export($data, true))) . PHP_EOL;
        return $new ? file_put_contents($file, $str) : file_put_contents($file, $str, FILE_APPEND);
    }

    /**
     * 设置网页标签图标
     * @param ?string $icon 网页标签图标
     * @return boolean
     * @throws \think\admin\Exception
     */
    public static function setFavicon(?string $icon = null): bool
    {
        try {
            $icon = $icon ?: sysconf('base.site_icon');
            if (!preg_match('#^https?://#i', $icon)) {
                throw new Exception(lang('无效的原文件地址！'));
            }
            if (preg_match('#/upload/(\w{2}/\w{30}.\w+)$#i', $icon, $vars)) {
                $info = LocalStorage::instance()->info($vars[1]);
            }
            if (empty($info) || empty($info['file'])) {
                $info = LocalStorage::down($icon);
            }
            if (empty($info) || empty($info['file'])) return false;
            $favicon = new FaviconExtend($info['file'], [48, 48]);
            return $favicon->saveIco(with_path('public/favicon.ico'));
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            trace_file($exception);
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 魔术方法调用
     * @param string $method 方法名称
     * @param array $arguments 调用参数
     * @return mixed
     * @throws \think\admin\Exception
     */
    public function __call(string $method, array $arguments)
    {
        return static::__callStatic($method, $arguments);
    }

    /**
     * 静态方法调用
     * @param string $method 方法名称
     * @param array $arguments 调用参数
     * @return mixed
     * @throws \think\admin\Exception
     */
    public static function __callStatic(string $method, array $arguments)
    {
        $map = [
            'setRuntime'    => 'set',
            'getRuntime'    => 'get',
            'bindRuntime'   => 'apply',
            'isDebug'       => 'isDebug',
            'isOnline'      => 'isOnline',
            'doInit'        => 'doWebsiteInit',
            'doConsoleInit' => 'doConsoleInit',
            'pushRuntime'   => 'push',
            'clearRuntime'  => 'clear',
            'checkRunMode'  => 'check',
        ];
        if (isset($map[$method])) {
            return RuntimeService::{$map[$method]}(...$arguments);
        } else {
            throw new Exception("method not exists: RuntimeService::{$method}()");
        }
    }
}