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

use think\admin\Library;
use think\admin\support\Route;
use think\admin\support\Url;
use think\App;
use think\Container;
use think\Response;

/**
 * 系统运行服务
 * Class RuntimeService
 * @package think\admin\service
 */
class RuntimeService
{

    /**
     * 开发运行模式
     * @var string
     */
    const MODE_DEVE = 'dev';

    /**
     * 演示运行模式
     * @var string
     */
    const MODE_DEMO = 'demo';

    /**
     * 本地运行模式
     * @var string
     */
    const MODE_LOCAL = 'local';

    /**
     * 动态环境变量
     * @var array
     */
    private static $env = [];

    /**
     * 系统服务初始化
     * @param ?\think\App $app
     * @return App
     */
    private static function init(?App $app = null): App
    {
        // 替换 ThinkPHP 地址，并初始化运行环境
        Library::$sapp = $app ?: Container::getInstance()->make(App::class);
        Library::$sapp->bind('think\Route', Route::class);
        Library::$sapp->bind('think\route\Url', Url::class);
        return Library::$sapp->debug(static::isDebug());
    }

    /**
     * 获取动态配置
     * @param null|string $name 配置名称
     * @param array $default 配置内容
     * @return array|string
     */
    public static function get(?string $name = null, array $default = [])
    {
        if (empty(static::$env)) {

            // 读取默认配置
            if (file_exists($file = with_path('runtime/.env'))) {
                is_file($file) && Library::$sapp->env->load($file);
            }

            // 动态判断赋值
            static::$env['mode'] = Library::$sapp->env->get('RUNTIME_MODE') ?: 'debug';
            static::$env['appmap'] = Library::$sapp->env->get('RUNTIME_APPMAP') ?: [];
            static::$env['domain'] = Library::$sapp->env->get('RUNTIME_DOMAIN') ?: [];
        }
        return is_null($name) ? static::$env : (static::$env[$name] ?? $default);
    }

    /**
     * 设置动态配置
     * @param null|mixed $mode 支持模式
     * @param null|array $appmap 应用映射
     * @param null|array $domain 域名映射
     * @return boolean 是否调试模式
     */
    public static function set(?string $mode = null, ?array $appmap = [], ?array $domain = []): bool
    {
        empty(static::$env) && static::get();
        static::$env['mode'] = is_null($mode) ? static::$env['mode'] : $mode;
        static::$env['appmap'] = static::uniqueMergeArray(static::$env['appmap'], $appmap);
        static::$env['domain'] = static::uniqueMergeArray(static::$env['domain'], $domain);

        // 组装配置文件格式
        $rows[] = "mode = " . static::$env['mode'];
        foreach (static::$env['appmap'] as $key => $item) $rows[] = "appmap[{$key}] = {$item}";
        foreach (static::$env['domain'] as $key => $item) $rows[] = "domain[{$key}] = {$item}";
        file_put_contents(with_path('runtime/.env'), "[RUNTIME]\n" . join("\n", $rows));

        //  应用当前的配置文件
        return static::apply(static::$env);
    }

    /**
     * 绑定动态配置
     * @param array $data 配置数据
     * @return boolean 是否调试模式
     */
    public static function apply(array $data = []): bool
    {
        // 设置模块绑定
        $data = static::get() + $data;
        $appmap = static::uniqueMergeArray(Library::$sapp->config->get('app.app_map', []), $data['appmap']);
        $domain = static::uniqueMergeArray(Library::$sapp->config->get('app.domain_bind', []), $data['domain']);
        Library::$sapp->config->set(['app_map' => $appmap, 'domain_bind' => $domain], 'app');

        // 设置模板变量
        $vars = Library::$sapp->config->get('view.tpl_replace_string', []);
        Library::$sapp->config->set(['tpl_replace_string' => array_merge(SystemService::uris(), $vars)], 'view');

        // 初始化调试配置
        return Library::$sapp->debug($data['mode'] !== 'product')->isDebug();
    }

    /**
     * 压缩发布项目
     * @return string
     */
    public static function push(): string
    {
        $connection = Library::$sapp->db->getConfig('default');
        Library::$sapp->console->call('optimize:schema', ["--connection={$connection}"]);
        foreach (NodeService::getModules() as $module) {
            $path = with_path("runtime/{$module}");
            file_exists($path) && is_dir($path) || mkdir($path, 0755, true);
            Library::$sapp->console->call('optimize:route', [$module]);
        }
        return $connection;
    }

    /**
     * 判断运行环境
     * @param string $type 运行模式（dev|demo|local）
     * @return boolean
     */
    public static function check(string $type = 'dev'): bool
    {
        $domain = Library::$sapp->request->host(true);
        $isDemo = is_numeric(stripos($domain, 'thinkadmin.top'));
        $isLocal = $domain === '127.0.0.1' || is_numeric(stripos($domain, 'local'));
        if ($type === static::MODE_DEVE) return $isLocal || $isDemo;
        if ($type === static::MODE_DEMO) return $isDemo;
        if ($type === static::MODE_LOCAL) return $isLocal;
        return true;
    }

    /**
     * 清理运行缓存
     * @return boolean
     */
    public static function clear(): bool
    {
        AdminService::clear();
        $data = static::get();
        Library::$sapp->cache->clear();
        Library::$sapp->console->call('clear', ['--dir']);
        return static::set($data['mode'], $data['appmap'], $data['domain']);
    }

    /**
     * 开发模式运行
     * @return boolean
     */
    public static function isDebug(): bool
    {
        empty(static::$env) && static::get();
        return static::$env['mode'] !== 'product';
    }

    /**
     * 生产模式运行
     * @return boolean
     */
    public static function isOnline(): bool
    {
        return !static::isDebug();
    }

    /**
     * 初始化主程序
     * @param ?\think\App $app
     * @return \think\Response
     */
    public static function doWebsiteInit(?App $app = null): Response
    {
        $http = static::init($app)->http;
        ($response = $http->run())->send();
        $http->end($response);
        return $response;
    }

    /**
     * 初始化命令行
     * @param ?\think\App $app
     * @return integer
     */
    public static function doConsoleInit(?App $app = null): int
    {
        try {
            return static::init($app)->console->run();
        } catch (\Exception $exception) {
            ProcessService::message($exception->getMessage());
            trace_file($exception);
            return 0;
        }
    }

    /**
     * 生成唯一数组
     * @param array ...$args
     * @return array
     */
    private static function uniqueMergeArray(...$args): array
    {
        return array_unique(array_reverse(array_merge(...$args)));
    }
}