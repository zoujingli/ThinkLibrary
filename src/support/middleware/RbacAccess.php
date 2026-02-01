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

use think\admin\service\AdminService;
use think\App;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;

/**
 * 后台权限中间键.
 * @class RbacAccess
 */
class RbacAccess
{
    /**
     * 当前 App 对象
     * @var App
     */
    protected $app;

    /**
     * Construct.
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 中间键处理.
     */
    public function handle(Request $request, \Closure $next): Response
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

        // 跳过忽略配置应用 或 有权限访问
        $ignore = $this->app->config->get('app.rbac_ignore', []);
        if (in_array($this->app->http->getName(), $ignore) || AdminService::check()) {
            return $next($request);
        }

        // 无权限已登录，提示异常
        if (AdminService::isLogin()) {
            throw new HttpResponseException(json(['code' => 0, 'info' => lang('禁用访问！')]));
        }

        // 无权限未登录，跳转登录
        $loginUrl = $this->app->config->get('app.rbac_login') ?: 'admin/login/index';
        $loginPage = preg_match('#^(/|https?://)#', $loginUrl) ? $loginUrl : sysuri($loginUrl);
        throw new HttpResponseException(json(['code' => 0, 'info' => lang('请重新登录！'), 'url' => $loginPage]));
    }
}
