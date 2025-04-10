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
     * 环境配置文件位置
     * @var string
     */
    private static $envFile = './runtime/.env';

    /**
     * 初始化文件哈希值
     * @var string
     */
    private static $evnHash = '';

    /**
     * 系统服务初始化
     * @param ?\think\App $app
     * @return App
     */
    public static function init(?App $app = null): App
    {
        // 初始化运行环境
        Library::$sapp = $app ?: Container::getInstance()->make(App::class);
        Library::$sapp->bind('think\Route', Route::class);
        Library::$sapp->bind('think\route\Url', Url::class);
        // 初始化运行配置位置
        self::$envFile = syspath('runtime/.env');
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
        $keys = 'think.admin.runtime';
        if (empty($envs = sysvar($keys) ?: [])) {
            // 读取默认配置
            clearstatcache(true, self::$envFile);
            is_file(self::$envFile) && Library::$sapp->env->load(self::$envFile);
            // 动态判断赋值
            $envs['mode'] = Library::$sapp->env->get('RUNTIME_MODE') ?: 'debug';
            $envs['appmap'] = Library::$sapp->env->get('RUNTIME_APPMAP') ?: [];
            $envs['domain'] = Library::$sapp->env->get('RUNTIME_DOMAIN') ?: [];
            sysvar($keys, $envs);
        }
        return is_null($name) ? $envs : ($envs[$name] ?? $default);
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
        $envs = self::get();
        $envs['mode'] = is_null($mode) ? $envs['mode'] : $mode;
        $envs['appmap'] = static::uniqueMergeArray($envs['appmap'], $appmap);
        $envs['domain'] = static::uniqueMergeArray($envs['domain'], $domain);

        // 组装配置文件格式
        $rows[] = "mode = {$envs['mode']}";
        foreach ($envs['appmap'] as $key => $item) $rows[] = "appmap[{$key}] = {$item}";
        foreach ($envs['domain'] as $key => $item) $rows[] = "domain[{$key}] = {$item}";

        // 写入并刷新文件希值
        @file_put_contents(self::$envFile, "[RUNTIME]\n" . join("\n", $rows));

        // 同步更新当前环境
        sysvar('think.admin.runtime', $envs);

        //  应用当前的配置文件
        return static::apply($envs);
    }

    /**
     * 同步运行配置
     * @return void
     */
    public static function sync()
    {
        clearstatcache(true, self::$envFile);
        is_file(self::$envFile) && md5_file(self::$envFile) !== self::$evnHash && self::apply();
    }

    /**
     * 绑定动态配置
     * @param array $data 配置数据
     * @return boolean 是否调试模式
     */
    public static function apply(array $data = []): bool
    {
        // 设置模块绑定
        $data = array_merge(static::get(), $data);
        $appmap = static::uniqueMergeArray(Library::$sapp->config->get('app.app_map', []), $data['appmap']);
        $domain = static::uniqueMergeArray(Library::$sapp->config->get('app.domain_bind', []), $data['domain']);
        Library::$sapp->config->set(['app_map' => $appmap, 'domain_bind' => $domain], 'app');

        // 记录配置文件
        is_file(self::$envFile) && self::$evnHash = md5_file(self::$envFile);

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
        return static::get('mode') !== 'product';
    }

    /**
     * 生产模式运行
     * @return boolean
     */
    public static function isOnline(): bool
    {
        return static::get('mode') === 'product';
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