<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2022 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkLibrary
// | github 代码仓库：https://github.com/zoujingli/ThinkLibrary
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
use think\admin\multiple\BuildUrl;
use think\admin\multiple\Route;
use think\admin\Service;
use think\admin\storage\LocalStorage;
use think\App;
use think\Container;
use think\db\Query;
use think\helper\Str;
use think\Model;

/**
 * 系统参数管理服务
 * Class SystemService
 * @package think\admin\service
 */
class SystemService extends Service
{
    /**
     * 配置数据缓存
     * @var array
     */
    private static $data = [];

    /**
     * 系统服务初始化
     * @return void
     */
    protected function initialize()
    {
        static::init($this->app);
    }

    /**
     * 系统服务初始化
     * @param ?\think\App $app
     * @return App
     */
    private static function init(?App $app): App
    {
        // 替换 ThinkPHP 地址，并初始化运行环境
        Library::$sapp = $app ?: Container::getInstance()->make(App::class);
        Library::$sapp->bind('think\Route', Route::class);
        Library::$sapp->bind('think\route\Url', BuildUrl::class);
        return Library::$sapp->debug(static::isDebug());
    }

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
        $oplog = static::getOplog($action, $content);
        return SystemOplog::mk()->insert($oplog) !== false;
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
        if (is_null($file)) $file = Library::$sapp->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . date('Ymd') . '.log';
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
                throw new Exception('无效的原文件地址！');
            }
            if (preg_match('#/upload/(\w{2}/\w{30}.\w+)$#i', $icon, $vars)) {
                $info = LocalStorage::instance()->info($vars[1]);
            }
            if (empty($info) || empty($info['file'])) {
                $info = LocalStorage::down($icon);
            }
            if (empty($info) || empty($info['file'])) return false;
            $favicon = new FaviconExtend($info['file'], [48, 48]);
            return $favicon->saveIco(Library::$sapp->getRootPath() . 'public/favicon.ico');
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 判断运行环境
     * @param string $type 运行模式（dev|demo|local）
     * @return boolean
     */
    public static function checkRunMode(string $type = 'dev'): bool
    {
        $domain = Library::$sapp->request->host(true);
        $isDemo = is_numeric(stripos($domain, 'thinkadmin.top'));
        $isLocal = $domain === '127.0.0.1' || is_numeric(stripos($domain, 'localhost'));
        if ($type === 'dev') return $isLocal || $isDemo;
        if ($type === 'demo') return $isDemo;
        if ($type === 'local') return $isLocal;
        return true;
    }

    /**
     * 压缩发布项目
     */
    public static function pushRuntime(): void
    {
        $connection = Library::$sapp->db->getConfig('default');
        Library::$sapp->console->call("optimize:schema", ["--connection={$connection}"]);
        foreach (NodeService::getModules() as $module) {
            $path = Library::$sapp->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . $module;
            file_exists($path) && is_dir($path) || mkdir($path, 0755, true);
            Library::$sapp->console->call("optimize:route", [$module]);
        }
    }

    /**
     * 清理运行缓存
     */
    public static function clearRuntime(): void
    {
        $data = static::getRuntime();
        Library::$sapp->cache->clear();
        Library::$sapp->console->call('clear', ['--dir']);
        static::setRuntime($data['mode'], $data['appmap'], $data['domain']);
    }

    /**
     * 是否为开发模式运行
     * @return boolean
     */
    public static function isDebug(): bool
    {
        return static::getRuntime('mode') !== 'product';
    }

    /**
     * 设置实时运行配置
     * @param null|mixed $mode 支持模式
     * @param null|array $appmap 应用映射
     * @param null|array $domain 域名映射
     * @return boolean 是否调试模式
     */
    public static function setRuntime(?string $mode = null, ?array $appmap = [], ?array $domain = []): bool
    {
        $data = static::getRuntime();
        $data['mode'] = $mode ?: $data['mode'];
        $data['appmap'] = static::uniqueArray($data['appmap'], $appmap);
        $data['domain'] = static::uniqueArray($data['domain'], $domain);

        // 组装配置文件格式
        $rows[] = "mode = {$data['mode']}";
        foreach ($data['appmap'] as $key => $item) $rows[] = "appmap[{$key}] = {$item}";
        foreach ($data['domain'] as $key => $item) $rows[] = "domain[{$key}] = {$item}";

        // 数据配置保存文件
        $env = Library::$sapp->getRootPath() . 'runtime/.env';
        file_put_contents($env, "[RUNTIME]\n" . join("\n", $rows));
        return static::bindRuntime($data);
    }

    /**
     * 获取实时运行配置
     * @param null|string $name 配置名称
     * @param array $default 配置内容
     * @return array|string
     */
    public static function getRuntime(?string $name = null, array $default = [])
    {
        $env = Library::$sapp->getRootPath() . 'runtime/.env';
        if (file_exists($env)) Library::$sapp->env->load($env);
        $data = [
            'mode'   => Library::$sapp->env->get('RUNTIME_MODE') ?: 'debug',
            'appmap' => Library::$sapp->env->get('RUNTIME_APPMAP') ?: [],
            'domain' => Library::$sapp->env->get('RUNTIME_DOMAIN') ?: [],
        ];
        return is_null($name) ? $data : ($data[$name] ?? $default);
    }

    /**
     * 绑定应用实时配置
     * @param array $data 配置数据
     * @return boolean 是否调试模式
     */
    public static function bindRuntime(array $data = []): bool
    {
        if (empty($data)) $data = static::getRuntime();
        // 设置模块绑定
        $bind['app_map'] = static::uniqueArray(Library::$sapp->config->get('app.app_map', []), $data['appmap']);
        $bind['domain_bind'] = static::uniqueArray(Library::$sapp->config->get('app.domain_bind', []), $data['domain']);
        Library::$sapp->config->set($bind, 'app');
        // 模板常用变量
        $vars = array_merge(static::uris(), Library::$sapp->config->get('view.tpl_replace_string', []));
        Library::$sapp->config->set(['tpl_replace_string' => $vars], 'view');
        // 初始化配置信息
        return Library::$sapp->debug($data['mode'] !== 'product')->isDebug();
    }

    /**
     * 初始化并运行主程序
     * @param ?\think\App $app
     */
    public static function doInit(?App $app = null)
    {
        $http = static::init($app)->http;
        ($response = $http->run())->send();
        $http->end($response);
    }

    /**
     * 初始化命令行主程序
     * @param ?\think\App $app
     * @throws \Exception
     */
    public static function doConsoleInit(?App $app = null)
    {
        static::init($app)->console->run();
    }

    /**
     * 获取唯一数组参数
     * @param array ...$args
     * @return array
     */
    private static function uniqueArray(...$args): array
    {
        return array_unique(array_reverse(array_merge(...$args)));
    }
}