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

namespace think\admin\support\middleware;

use Closure;
use think\admin\service\AdminService;
use think\App;
use think\Request;
use think\Response;

/**
 * 后台权限中间键
 * @class RbacAccess
 * @package think\admin\support\middleware
 */
class RbacAccess
{
    /**
     * 当前 App 对象
     * @var \think\App
     */
    protected $app;

    /**
     * Construct
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 中间键处理
     * @param \think\Request $request
     * @param \Closure $next
     * @return \think\Response
     * @throws \ReflectionException
     */
    public function handle(Request $request, Closure $next): Response
    {

        // HTTP.LANG 语言包处理
        $langSet = $this->app->lang->getLangSet();
        if (is_file($file = dirname(__DIR__, 2) . "/lang/{$langSet}.php")) {
            $this->app->lang->load($file, $langSet);
        }
        // 动态加载全局语言包
        if (is_file($file = syspath("lang/{$langSet}.php"))) {
            $this->app->lang->load($file, $langSet);
        }
        $header = [];
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
            return response()->code(204)->header($header);
        }

        // 跳忽略配置忽略的应用
        $ignore = $this->app->config->get('app.rbac_ignore', []);
        if (in_array($this->app->http->getName(), $ignore)) {
            return $next($request)->header($header);
        }

        // 有权限访问，进入下一步
        if (AdminService::check()) {
            $header['X-Frame-Options'] = 'sameorigin';
            return $next($request)->header($header);
        }

        // 无权限已登录，提示异常
        if (AdminService::isLogin()) {
            return json(['code' => 0, 'info' => lang('未授权禁用访问！')])->header($header);
        }

        // 无权限未登录，跳转登录
        $loginUrl = $this->app->config->get('app.rbac_login') ?: 'admin/login/index';
        $loginPage = preg_match('#^(/|https?://)#', $loginUrl) ? $loginUrl : sysuri($loginUrl);
        return json(['code' => 0, 'info' => lang('登录授权无效，请重新登录！'), 'url' => $loginPage])->header($header);
    }
}