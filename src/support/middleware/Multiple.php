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

namespace think\admin\support\middleware;

use Closure;
use think\App;
use think\exception\HttpException;
use think\Request;
use think\Response;

/**
 * 多应用支持中间键
 * Class Multiple
 * @package think\admin\support\middleware
 */
class Multiple
{
    /**
     * 应用实例
     * @var App
     */
    private $app;

    /**
     * 应用名称
     * @var string
     */
    private $name;

    /**
     * 应用路径
     * @var string
     */
    private $path;

    /**
     * 应用空间
     * @var string
     */
    private $space;

    /**
     * App constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->name = $this->app->http->getName();
        $this->path = $this->app->http->getPath();
    }

    /**
     * 多应用解析
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->parseMultiApp()) return $next($request);
        return $this->app->middleware->pipeline('app')->send($request)->then(function ($request) use ($next) {
            return $next($request);
        });
    }

    /**
     * 解析多应用
     * @return bool
     */
    private function parseMultiApp(): bool
    {
        $defaultApp = $this->app->config->get('route.default_app') ?: 'index';
        [$script, $pathinfo] = [$this->scriptName(), $this->app->request->pathinfo()];
        if ($this->name || ($script && !in_array($script, ['index', 'router', 'think']))) {
            $this->app->request->setPathinfo(preg_replace("#^{$script}\.php(/|\.|$)#i", '', $pathinfo) ?: '/');
            return $this->setMultiApp($this->name ?: $script, true);
        } else {
            // 域名绑定处理
            $domains = $this->app->config->get('app.domain_bind', []);
            if (!empty($domains)) foreach ([$this->app->request->host(true), $this->app->request->subDomain(), '*'] as $key) {
                if (isset($domains[$key])) return $this->setMultiApp($domains[$key], true);
            }
            $name = current(explode('/', $pathinfo));
            if (strpos($name, '.')) $name = strstr($name, '.', true);
            // 应用绑定与插件处理
            $map = $this->app->config->get('app.app_map', []);
            $addons = $this->app->config->get('app.addons', []);
            if (isset($map[$name])) {
                $appName = $map[$name] instanceof Closure ? (call_user_func_array($map[$name], [$this->app]) ?: $name) : $map[$name];
            } elseif ($name && (in_array($name, $map) || in_array($name, $this->app->config->get('app.deny_app_list', [])))) {
                throw new HttpException(404, "app not exists: {$name}");
            } elseif ($name && isset($map['*'])) {
                $appName = $map['*'];
            } else {
                $appName = $name ?: $defaultApp;
                if (!isset($addons[$appName]) && !is_dir($this->path ?: $this->app->getBasePath() . $appName)) {
                    return $this->app->config->get('app.app_express', false) && $this->setMultiApp($defaultApp, false);
                }
            }
            // 插件绑定处理
            $this->app->config->set(['view_path' => ''], 'view');
            if (isset($addons[$appName])) {
                [$this->path, $this->space] = explode('@', "{$addons[$appName]}@");
                $this->app->config->set(['view_path' => $this->path . 'view' . DIRECTORY_SEPARATOR], 'view');
            }
            if ($name) {
                $this->app->request->setRoot('/' . $name);
                $this->app->request->setPathinfo(strpos($pathinfo, '/') ? ltrim(strstr($pathinfo, '/'), '/') : '');
            }
        }
        return $this->setMultiApp($appName ?? $defaultApp, $this->app->http->isBind());
    }

    /**
     * 获取当前运行入口名称
     * @codeCoverageIgnore
     * @return string
     */
    private function scriptName(): string
    {
        $file = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['argv'][0] ?? '');
        return empty($file) ? '' : pathinfo($file, PATHINFO_FILENAME);
    }

    /**
     * 设置应用参数
     * @param string $appName 应用名称
     * @param boolean $appBind 应用绑定
     * @return boolean
     */
    private function setMultiApp(string $appName, bool $appBind): bool
    {
        if (empty($this->path)) {
            $this->path = $this->app->getBasePath() . $appName . DIRECTORY_SEPARATOR;
        }
        if (empty($this->space)) {
            $this->space = ($this->app->config->get('app.app_namespace') ?: 'app') . "\\{$appName}";
        }
        if (is_dir($this->path)) {
            $this->app->setNamespace($this->space)->setAppPath($this->path);
            $this->app->http->setBind($appBind)->name($appName)->path($this->path)->setRoutePath($this->path . 'route' . DIRECTORY_SEPARATOR);
            return $this->loadMultiApp($this->path);
        } else {
            return false;
        }
    }

    /**
     * 加载应用文件
     * @param string $appPath 应用路径
     * @codeCoverageIgnore
     * @return boolean
     */
    private function loadMultiApp(string $appPath): bool
    {
        [$ext, $fmaps] = [$this->app->getConfigExt(), []];
        if (is_file($file = $appPath . 'common' . $ext)) include_once $file;
        foreach (glob($appPath . 'config' . DIRECTORY_SEPARATOR . '*' . $ext) as $file) {
            $this->app->config->load($file, $fmaps[] = pathinfo($file, PATHINFO_FILENAME));
        }
        if (in_array('route', $fmaps) && method_exists($this->app->route, 'reload')) {
            $this->app->route->reload();
        }
        if (is_file($file = $appPath . 'event' . $ext)) {
            $this->app->loadEvent(include $file);
        }
        if (is_file($file = $appPath . 'middleware' . $ext)) {
            $this->app->middleware->import(include $file);
        }
        if (is_file($file = $appPath . 'provider' . $ext)) {
            $this->app->bind(include $file);
        }
        $this->app->lang->switchLangSet($this->app->lang->getLangSet());
        return true;
    }
}