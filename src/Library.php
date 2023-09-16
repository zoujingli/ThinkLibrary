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

namespace think\admin;

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
use think\App;
use think\middleware\LoadLangPack;
use think\Request;
use think\Service;

/**
 * 模块注册服务
 * @class Library
 * @package think\admin
 */
class Library extends Service
{
    /**
     * 静态应用实例
     * @var App
     */
    public static $sapp;

    /**
     * 启动服务
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

            // 配置默认输入过滤
            $request->filter([function ($value) {
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
        $this->app->event->listen('HttpEnd', function () {
            function_exists('sysvar') && sysvar('', '');
        });
    }

    /**
     * 初始化服务
     */
    public function register()
    {
        // 动态加载全局配置
        [$dir, $ext] = [$this->app->getBasePath(), $this->app->getConfigExt()];
        foreach (glob("{$dir}*/sys{$ext}") as $file) include_once $file;
        if (is_file($file = "{$dir}common{$ext}")) include_once $file;
        if (is_file($file = "{$dir}provider{$ext}")) $this->app->bind(include $file);
        if (is_file($file = "{$dir}event{$ext}")) $this->app->loadEvent(include $file);
        if (is_file($file = "{$dir}middleware{$ext}")) $this->app->middleware->import(include $file, 'app');

        // 终端 HTTP 访问时特殊处理
        if (!$this->app->runningInConsole()) {

            // 初始化会话和语言包
            $isApiRequest = $this->app->request->header('api-token', '') !== '';
            $isYarRequest = is_numeric(stripos($this->app->request->header('user_agent', ''), 'PHP Yar RPC-'));
            if (!($isApiRequest || $isYarRequest || $this->app->request->get('not_init_session', 0) > 0)) {
                // 注册会话中间键
                $this->app->middleware->add(JwtSession::class);
                // 注册语言包中间键
                $this->app->middleware->add(LoadLangPack::class);
            }

            // 注册权限验证中间键
            $this->app->middleware->add(RbacAccess::class, 'route');
        }
    }
}