<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2023 ThinkAdmin [ thinkadmin.top ]
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

namespace think\admin\service;

use think\admin\Library;
use think\admin\support\Route;
use think\admin\support\Url;
use think\App;
use think\Container;
use think\Request;
use think\Response;

/**
 * 系统运行服务
 * @class RuntimeService
 * @package think\admin\service
 */
class RuntimeService
{

    /**
     * 开发运行模式
     * @var string
     */
    public const MODE_DEVE = 'dev';

    /**
     * 演示运行模式
     * @var string
     */
    public const MODE_DEMO = 'demo';

    /**
     * 本地运行模式
     * @var string
     */
    public const MODE_LOCAL = 'local';

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
    public static function init(?App $app = null): App
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
            if (is_file($file = syspath('runtime/.env'))) {
                Library::$sapp->env->load($file);
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
        @file_put_contents(syspath('runtime/.env'), "[RUNTIME]\n" . join("\n", $rows));

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
        // 初始化调试配置
        return Library::$sapp->debug($data['mode'] !== 'product')->isDebug();
    }

    /**
     * 压缩发布项目
     * @return string
     */
    public static function push(): string
    {
        self::set('product');
        $connection = Library::$sapp->db->getConfig('default');
        Library::$sapp->console->call('optimize:schema', ["--connection={$connection}"]);
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
        $isDemo = boolval(preg_match('|v\d+\.thinkadmin\.top|', $domain));
        $isLocal = $domain === '127.0.0.1' || is_numeric(stripos($domain, 'local'));
        if ($type === static::MODE_DEVE) return $isLocal || $isDemo;
        if ($type === static::MODE_DEMO) return $isDemo;
        if ($type === static::MODE_LOCAL) return $isLocal;
        return true;
    }

    /**
     * 清理运行缓存
     * @param boolean $force 清理目录
     * @return boolean
     */
    public static function clear(bool $force = true): bool
    {
        $data = static::get();
        AdminService::clear() && Library::$sapp->cache->clear();
        $force && Library::$sapp->console->call('clear', ['--dir']);
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
     * @param ?\think\Request $request
     * @return \think\Response
     */
    public static function doWebsiteInit(?App $app = null, ?Request $request = null): Response
    {
        $http = static::init($app)->http;
        $request = $request ?: Library::$sapp->make(Request::class);
        Library::$sapp->instance('request', $request);
        ($response = $http->run($request))->send();
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