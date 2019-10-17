<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace think\admin;

use think\facade\Request;
use \think\Service as BaseService;

/**
 * 应用注册服务
 * Class Service
 * @package library
 */
class InitService extends BaseService
{
    public function register()
    {
        // 注册访问跨域中间键
        $this->app->middleware->add(function (Request $request, \Closure $next, $header = []) {
            if (($origin = $request->header('origin', '*')) !== '*') {
                $header['Access-Control-Allow-Origin'] = $origin;
                $header['Access-Control-Allow-Methods'] = 'GET,POST,PATCH,PUT,DELETE';
                $header['Access-Control-Allow-Headers'] = 'Authorization,Content-Type,If-Match,If-Modified-Since,If-None-Match,If-Unmodified-Since,X-Requested-With';
                $header['Access-Control-Expose-Headers'] = 'User-Token-Csrf';
            }
            if ($request->isOptions()) {
                return response()->code(204)->header($header);
            } else {
                return $next($request)->header($header);
            }
        });
        // 注册系统任务指令
        $this->app->console->addCommands([
            'think\admin\queue\Work',
            'think\admin\queue\Stop',
            'think\admin\queue\State',
            'think\admin\queue\Start',
            'think\admin\queue\Query',
            'think\admin\queue\Listen',
        ]);
//        // 动态加载模块配置
////        if (function_exists('Composer\Autoload\includeFile')) {
////            $root = rtrim(app()->getAppPath(), '\\/');
////            foreach (glob("{$root}/*/sys.php") as $file) {
////                \Composer\Autoload\includeFile($file);
////            }
////        }
    }

}