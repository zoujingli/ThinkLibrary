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

namespace think\admin\support\middleware;

use think\admin\extend\ToolsExtend;
use think\admin\Library;
use think\admin\Plugin;
use think\admin\service\NodeService;
use think\admin\service\RuntimeService;
use think\admin\service\SystemService;
use think\App;
use think\Config;
use think\Container;
use think\Event;
use think\exception\HttpException;
use think\Request;
use think\Response;

/**
 * 多应用调度中间键.
 * @class MultAccess
 */
class MultAccess
{
    /**
     * 应用实例.
     * @var App
     */
    private $app;

    /** @var bool 是否由常驻 Worker 请求复用应用实例 */
    private $workerStateEnabled = false;

    /**
     * 应用路径.
     * @var string
     */
    private $appPath;

    /**
     * 应用空间.
     * @var string
     */
    private $appSpace;

    /** @var array 应用初始化时的配置快照 */
    private $baseConfig;

    /** @var array 应用初始化时的配置获取器快照 */
    private $baseConfigHooks;

    /** @var array 应用初始化时的容器绑定快照 */
    private $baseBindings;

    /** @var array 应用初始化时的容器实例快照 */
    private $baseInstances;

    /** @var array 应用初始化时的容器回调快照 */
    private $baseInvokeCallbacks;

    /** @var array 应用初始化时的服务快照 */
    private $baseServices;

    /** @var array 应用初始化时的事件快照 */
    private $baseEvents;

    /** @var string 应用初始化时的命名空间 */
    private $baseNamespace;

    /** @var string 应用初始化时的应用路径 */
    private $baseAppPath;

    /** @var array HTTP 应用上下文快照 */
    private $baseHttp;

    /**
     * App constructor.
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        // FPM/CLI 每次请求都会创建新的应用，不需要承担 Worker 状态快照的反射和复制开销。
        $this->workerStateEnabled = method_exists($app, 'resetWorkerState');
        if (!$this->workerStateEnabled) {
            return;
        }
        $this->baseConfig = $app->config->get();
        $this->baseConfigHooks = $this->readProperty($app->config, Config::class, 'hook');
        $this->baseBindings = $this->readProperty($app, App::class, 'bind');
        $this->baseInstances = $this->readProperty($app, Container::class, 'instances');
        $this->baseInvokeCallbacks = $this->readProperty($app, Container::class, 'invokeCallback');
        $this->baseServices = $this->readProperty($app, App::class, 'services');
        // 路由对象必须由下一次 Http::run() 按当前应用重新创建，不能随基线恢复。
        unset($this->baseInstances[$app->getAlias('route')]);
        $event = $app->event;
        $this->baseEvents = [
            'listener' => $this->readProperty($event, Event::class, 'listener'),
            'observer' => $this->readProperty($event, Event::class, 'observer'),
            'bind' => $this->readProperty($event, Event::class, 'bind'),
        ];
        $this->baseNamespace = $app->getNamespace();
        $this->baseAppPath = $app->getAppPath();
        $http = $app->http;
        $this->baseHttp = [
            'name' => $http->getName(),
            'path' => $http->getPath(),
            'routePath' => $http->getRoutePath(),
            'isBind' => $http->isBind(),
        ];
    }

    /**
     * 多应用解析.
     */
    public function handle(Request $request, \Closure $next): Response
    {
        [$this->appPath, $this->appSpace] = ['', ''];
        if (!$this->parseMultiApp()) {
            return $next($request);
        }
        return $this->app->middleware->pipeline('app')->send($request)->then(function ($request) use ($next) {
            return $next($request);
        });
    }

    /**
     * 恢复应用初始化状态，供常驻 Worker 在 HttpRun 事件前清理上次请求.
     */
    public function resetApplicationState(): void
    {
        if (!$this->workerStateEnabled) {
            return;
        }
        $this->restoreBaseState();
        [$this->appPath, $this->appSpace] = ['', ''];
    }

    /**
     * 解析多应用.
     */
    private function parseMultiApp(): bool
    {
        $defaultApp = $this->app->config->get('route.default_app') ?: 'index';
        [$script, $pathinfo] = [$this->scriptName(), $this->app->request->pathinfo()];
        if ($script && !in_array($script, ['index', 'router', 'think'])) {
            $this->app->request->setPathinfo(preg_replace("#^{$script}\\.php(/|\\.|$)#i", '', $pathinfo) ?: '/');
            return $this->setMultiApp($script, true);
        }
        // 域名绑定处理
        $domains = $this->app->config->get('app.domain_bind', []);
        if (!empty($domains)) {
            foreach ([$this->app->request->host(true), $this->app->request->subDomain(), '*'] as $key) {
                if (isset($domains[$key])) {
                    return $this->setMultiApp($domains[$key], true);
                }
            }
        }
        $name = current(explode('/', $pathinfo));
        if (strpos($name, '.')) {
            $name = strstr($name, '.', true);
        }
        // 应用绑定与插件处理
        $addons = Plugin::get();
        $appmap = $this->app->config->get('app.app_map', []);
        if (isset($appmap[$name])) {
            $appName = $appmap[$name] instanceof \Closure ? (call_user_func_array($appmap[$name], [$this->app]) ?: $name) : $appmap[$name];
        } elseif ($name && (in_array($name, $appmap) || in_array($name, $this->app->config->get('app.deny_app_list', [])))) {
            throw new HttpException(404, "app not exists: {$name}");
        } elseif ($name && isset($appmap['*'])) {
            $appName = $appmap['*'];
        } else {
            $appName = $name ?: $defaultApp;
            if (!isset($addons[$appName]) && !is_dir($this->app->getBasePath() . $appName)) {
                return $this->app->config->get('app.app_express', false) && $this->setMultiApp($defaultApp, false);
            }
        }
        // 插件绑定处理
        if (isset($addons[$appName])) {
            [$this->appPath, $this->appSpace] = [$addons[$appName]['path'], $addons[$appName]['space']];
        }
        if ($name) {
            $this->app->request->setRoot('/' . $name);
            $this->app->request->setPathinfo(strpos($pathinfo, '/') ? ltrim(strstr($pathinfo, '/'), '/') : '');
        }

        return $this->setMultiApp($appName ?? $defaultApp, $this->app->http->isBind());
    }

    /**
     * 获取当前运行入口名称.
     * @codeCoverageIgnore
     */
    private function scriptName(): string
    {
        $file = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['argv'][0] ?? '');
        return empty($file) ? '' : pathinfo($file, PATHINFO_FILENAME);
    }

    /**
     * 设置应用参数.
     * @param string $appName 应用名称
     * @param bool $appBind 应用绑定
     */
    private function setMultiApp(string $appName, bool $appBind): bool
    {
        sysvar('CurrentPluginCode', $appName);
        if (is_dir($this->appPath = $this->appPath ?: syspath("app/{$appName}/"))) {
            // 设置多应用模式
            $this->app->setNamespace($this->appSpace ?: NodeService::space($appName))->setAppPath($this->appPath);
            $this->app->http->setBind($appBind)->name($appName)->path($this->appPath)->setRoutePath($this->appPath . 'route' . DIRECTORY_SEPARATOR);
            // 修改模板参数配置
            $uris = array_merge($this->app->config->get('view.tpl_replace_string', []), SystemService::uris());
            $this->app->config->set(['view_path' => $this->appPath . 'view' . DIRECTORY_SEPARATOR, 'tpl_replace_string' => $uris], 'view');
            // 初始化多应用文件
            return $this->loadMultiApp($this->appPath);
        }
        return false;
    }

    /**
     * 加载应用文件.
     * @param string $appPath 应用路径
     * @codeCoverageIgnore
     */
    private function loadMultiApp(string $appPath): bool
    {
        [$ext, $fmaps] = [$this->app->getConfigExt(), []];
        // 加载应用函数文件
        if (is_file($file = "{$appPath}common{$ext}")) {
            Library::load($file, true);
        }
        // 加载应用配置文件
        ToolsExtend::find($appPath . 'config', 1, function (\SplFileInfo $info) use ($ext) {
            if ($info->isFile() && strtolower(".{$info->getExtension()}") === $ext) {
                $this->app->config->load($info->getPathname(), $info->getBasename($ext));
            }
        });
        // 加载应用路由配置
        if (in_array('route', $fmaps) && method_exists($this->app->route, 'reload')) {
            $this->app->route->reload();
        }
        // 加载应用映射配置
        if (is_file($file = "{$appPath}provider{$ext}")) {
            $this->app->bind(include $file);
        }
        // 加载应用事件配置
        if (is_file($file = "{$appPath}event{$ext}")) {
            $this->app->loadEvent(include $file);
        }
        // 加载应用中间键配置
        if (is_file($file = "{$appPath}middleware{$ext}")) {
            $this->app->middleware->import(include $file, 'app');
        }
        // 重新加载应用语言包
        if (method_exists($this->app->lang, 'switchLangSet')) {
            $this->app->lang->switchLangSet($this->app->lang->getLangSet());
        }
        return true;
    }

    /**
     * 恢复应用初始化状态，避免多应用请求互相污染.
     */
    private function restoreBaseState(): void
    {
        $this->writeProperty($this->app->config, Config::class, 'hook', $this->baseConfigHooks);
        $this->writeProperty($this->app->config, Config::class, 'config', $this->baseConfig);
        $this->writeProperty($this->app, App::class, 'bind', $this->baseBindings);
        $this->writeProperty($this->app, Container::class, 'invokeCallback', $this->baseInvokeCallbacks);
        $this->writeProperty($this->app, App::class, 'services', $this->baseServices);
        $currentInstances = $this->readProperty($this->app, Container::class, 'instances');
        $instances = $this->baseInstances;
        // 保留请求、会话及基础服务对象，交由现有 Worker 清理逻辑继续复用，避免丢失会话数据。
        foreach (['request', 'session', 'cookie', 'db', 'cache', 'log', 'view'] as $name) {
            $key = $this->app->getAlias($name);
            if (array_key_exists($key, $currentInstances)) {
                $instances[$key] = $currentInstances[$key];
            }
        }
        $instances[static::class] = $this;
        $this->writeProperty($this->app, Container::class, 'instances', $instances);
        $event = $this->app->event;
        foreach ($this->baseEvents as $name => $value) {
            $this->writeProperty($event, Event::class, $name, $value);
        }

        $this->app->setNamespace($this->baseNamespace)->setAppPath($this->baseAppPath);
        $http = $this->app->http;
        $this->writeProperty($http, get_class($http), 'name', $this->baseHttp['name']);
        $this->writeProperty($http, get_class($http), 'path', $this->baseHttp['path']);
        $this->writeProperty($http, get_class($http), 'routePath', $this->baseHttp['routePath']);
        $http->setBind($this->baseHttp['isBind']);

        // RuntimeService::sync() 在 HttpRun 事件中执行；恢复基线后重新应用其动态应用映射。
        RuntimeService::apply();
    }

    /**
     * 读取非公开属性，兼容 PHP 7.1 至 8.5.
     * @param object $object 对象
     * @param string $class 声明类
     * @param string $name 属性名称
     * @return mixed
     */
    private function readProperty($object, string $class, string $name)
    {
        if (!property_exists($class, $name)) {
            return null;
        }
        $property = new \ReflectionProperty($class, $name);
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        return $property->getValue($object);
    }

    /**
     * 写入非公开属性，兼容 PHP 7.1 至 8.5.
     * @param object $object 对象
     * @param string $class 声明类
     * @param string $name 属性名称
     * @param mixed $value 属性值
     */
    private function writeProperty($object, string $class, string $name, $value): void
    {
        if (!property_exists($class, $name)) {
            return;
        }
        $property = new \ReflectionProperty($class, $name);
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($object, $value);
    }
}
