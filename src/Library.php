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

use SplFileInfo;
use think\admin\extend\ToolsExtend;
use think\admin\service\RuntimeService;
use think\admin\support\command\Database;
use think\admin\support\command\Package;
use think\admin\support\command\Publish;
use think\admin\support\command\Queue;
use think\admin\support\command\Replace;
use think\admin\support\command\Sysmenu;
use think\admin\support\middleware\JwtSession;
use think\admin\support\middleware\MultAccess;
use think\admin\support\middleware\RbacAccess;
use think\exception\HttpResponseException;
use think\middleware\LoadLangPack;
use think\Request;
use think\Response;
use think\Service;

/**
 * 模块注册服务
 * @class Library
 * @package think\admin
 */
class Library extends Service
{
    /** @var \think\App */
    public static $sapp;

    /**
     * 启动服务
     * @return void
     */
    public function boot()
    {
        // 静态应用赋值
        static::$sapp = $this->app;

        // 注册 ThinkAdmin 指令
        $this->commands([
            Queue::class,
            Package::class,
            Sysmenu::class,
            Publish::class,
            Replace::class,
            Database::class,
        ]);

        // 动态应用运行参数
        RuntimeService::apply();

        // 请求初始化处理
        $this->app->event->listen('HttpRun', function (Request $request) {

            // 运行环境配置同步
            RuntimeService::sync();

            // 配置默认输入过滤
            $request->filter([static function ($value) {
                return is_string($value) ? xss_safe($value) : $value;
            }]);

            // 判断访问模式兼容处理
            if ($this->app->runningInConsole()) {
                // 兼容 CLI 访问控制器
                if (empty($_SERVER['REQUEST_URI']) && isset($_SERVER['argv'][1])) {
                    $request->setPathinfo($_SERVER['argv'][1]);
                }
            } else {
                // 兼容 HTTP 调用 Console 后 URL 问题
                $request->setHost($request->host());
            }

            // 注册多应用中间键
            $this->app->middleware->add(MultAccess::class);
        });

        // 请求结束后处理
        $this->app->event->listen('HttpEnd', static function () {
            function_exists('sysvar') && sysvar('', '');
        });
    }

    /**
     * 初始化服务
     * @return void
     */
    public function register()
    {
        // 动态加载全局配置
        [$dir, $ext] = [$this->app->getBasePath(), $this->app->getConfigExt()];
        ToolsExtend::find($dir, 2, function (SplFileInfo $info) use ($ext) {
            $info->isFile() && $info->getBasename() === "sys{$ext}" && include_once $info->getPathname();
        });
        if (is_file($file = "{$dir}common{$ext}")) include_once $file;
        if (is_file($file = "{$dir}provider{$ext}")) $this->app->bind(include $file);
        if (is_file($file = "{$dir}event{$ext}")) $this->app->loadEvent(include $file);
        if (is_file($file = "{$dir}middleware{$ext}")) $this->app->middleware->import(include $file, 'app');

        // 终端 HTTP 访问时特殊处理
        if (!$this->app->runningInConsole()) {
            // 动态注释 CORS 跨域处理
            $this->app->middleware->add(function (Request $request, \Closure $next): Response {
                $header = ['X-Frame-Options' => $this->app->config->get('app.cors_frame') ?: 'sameorigin'];
                // HTTP.CORS 跨域规则配置
                if ($this->app->config->get('app.cors_on', true) && ($origin = $request->header('origin', '-')) !== '-') {
                    if (is_string($hosts = $this->app->config->get('app.cors_host', []))) $hosts = str2arr($hosts);
                    if (empty($hosts) || in_array(parse_url(strtolower($origin), PHP_URL_HOST), $hosts)) {
                        $headers = $this->app->config->get('app.cors_headers', 'Api-Name,Api-Type,Api-Token,Jwt-Token,User-Form-Token,User-Token,Token');
                        $header['Access-Control-Allow-Origin'] = $origin;
                        $header['Access-Control-Allow-Methods'] = $this->app->config->get('app.cors_methods', 'GET,PUT,POST,PATCH,DELETE');
                        $header['Access-Control-Allow-Headers'] = "Authorization,Content-Type,If-Match,If-Modified-Since,If-None-Match,If-Unmodified-Since,X-Requested-With,{$headers}";
                        $header['Access-Control-Allow-Credentials'] = 'true';
                        $header['Access-Control-Expose-Headers'] = $headers;
                    }
                }
                // 跨域预请求状态处理
                if ($request->isOptions()) {
                    throw new HttpResponseException(response()->code(204)->header($header));
                } else {
                    return $next($request)->header($header);
                }
            });

            // 初始化会话和语言包
            $isapi = $this->app->request->header('api-token') !== null;
            $agent = preg_replace('|\s+|', '', $this->app->request->header('user-agent', ''));
            $isrpc = is_numeric(stripos($agent, 'think-admin-jsonrpc')) || is_numeric(stripos($agent, 'PHPYarRPC'));
            if (empty($isapi) && empty($isrpc) && empty($this->app->request->get('not_init_session'))) {
                // 非接口模式，注册会话中间键
                $this->app->middleware->add(JwtSession::class);
                // 启用会话后，注册语言包中间键
                $this->app->middleware->add(LoadLangPack::class);
            }

            // 注册权限验证中间键
            $this->app->middleware->add(RbacAccess::class, 'route');
        }
    }
}