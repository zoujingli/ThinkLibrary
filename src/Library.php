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

namespace think\admin;

use Closure;
use think\admin\service\AdminService;
use think\admin\service\RuntimeService;
use think\admin\support\command\Database;
use think\admin\support\command\Install;
use think\admin\support\command\Package;
use think\admin\support\command\Publish;
use think\admin\support\command\Queue;
use think\admin\support\command\Replace;
use think\admin\support\command\Sysmenu;
use think\admin\support\middleware\JwtInit;
use think\admin\support\middleware\Multiple;
use think\App;
use think\middleware\LoadLangPack;
use think\Request;
use think\Service;
use function Composer\Autoload\includeFile;

/**
 * 模块注册服务
 * Class Library
 * @package think\admin
 */
class Library extends Service
{
    /**
     * 组件版本号
     */
    const VERSION = '6.1.0';

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
            Install::class,
            Replace::class,
            Database::class,
        ]);

        // 动态应用运行参数
        RuntimeService::apply();

        // 服务初始化处理
        $this->app->event->listen('HttpRun', function (Request $request) {
            // 配置默认输入过滤
            $request->filter([function ($value) {
                return is_string($value) ? xss_safe($value) : $value;
            }]);
            // 判断访问模式兼容处理
            if ($request->isCli()) {
                // 兼容 CLI 访问控制器
                if (empty($_SERVER['REQUEST_URI']) && isset($_SERVER['argv'][1])) {
                    $request->setPathinfo($_SERVER['argv'][1]);
                }
            } else {
                // 兼容 HTTP 调用 Console 后 URL 问题
                $request->setHost($request->host());
            }
            // 注册多应用中间键
            $this->app->middleware->add(Multiple::class);
        });
    }

    /**
     * 初始化服务
     */
    public function register()
    {
        // 动态加载应用初始化系统函数
        $this->app->lang->load(__DIR__ . '/lang/zh-cn.php', 'zh-cn');
        foreach (glob("{$this->app->getBasePath()}*/sys.php") as $file) {
            includeFile($file);
        }

        // 终端 HTTP 访问时特殊处理
        if (!$this->app->request->isCli()) {
            // YAR 接口或指定情况下不需要初始化会话和语言包，否则有可能会报错
            $isApiRpc = $this->app->request->header('api-token', '') !== '';
            $isNotRpc = intval($this->app->request->get('not_init_session', 0)) > 0;
            $isYarRpc = is_numeric(stripos($this->app->request->header('user_agent', ''), 'PHP Yar RPC-'));
            if (!($isApiRpc || $isNotRpc || $isYarRpc)) {
                // 注册会话初始化中间键
                $this->app->middleware->add(JwtInit::class);
                // 注册语言包处理中间键
                $this->app->middleware->add(LoadLangPack::class);
            }
            // 注册访问处理中间键
            $this->app->middleware->add(function (Request $request, Closure $next) {
                $header = [];

                // 加载对应组件的语言包
                $langSet = $this->app->lang->getLangSet();
                if (file_exists($file = __DIR__ . "/lang/{$langSet}.php")) {
                    $this->app->lang->load($file, $langSet);
                }

                // HTTP.CORS 跨域规则配置
                if (($origin = $request->header('origin', '*')) !== '*') {
                    if (is_string($hosts = $this->app->config->get('app.cors_host', []))) $hosts = str2arr($hosts);
                    if ($this->app->config->get('app.cors_auto', 1) || in_array(parse_url(strtolower($origin), PHP_URL_HOST), $hosts)) {
                        $headers = $this->app->config->get('app.cors_headers', 'Api-Name,Api-Type,Api-Token,User-Form-Token,User-Token,Token');
                        $header['Access-Control-Allow-Origin'] = $origin;
                        $header['Access-Control-Allow-Methods'] = $this->app->config->get('app.cors_methods', 'GET,PUT,POST,PATCH,DELETE');
                        $header['Access-Control-Allow-Headers'] = "Authorization,Content-Type,If-Match,If-Modified-Since,If-None-Match,If-Unmodified-Since,X-Requested-With,{$headers}";
                        $header['Access-Control-Expose-Headers'] = $headers;
                        $header['Access-Control-Allow-Credentials'] = 'true';
                    }
                }

                // 访问模式及访问权限检查
                if ($request->isOptions()) {
                    return response()->code(204)->header($header);
                } elseif (AdminService::check()) {
                    $header['X-Frame-Options'] = 'sameorigin';
                    return $next($request)->header($header);
                } elseif (AdminService::isLogin()) {
                    return json(['code' => 0, 'info' => lang('think_library_not_auth')])->header($header);
                } else {
                    return json(['code' => 0, 'info' => lang('think_library_not_login'), 'url' => sysuri('admin/login/index')])->header($header);
                }
            }, 'route');
        }
    }
}