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

namespace think\admin\support\middleware;

use Closure;
use think\admin\Exception;
use think\admin\extend\JwtExtend;
use think\App;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;

/**
 * 兼容会话中间键
 * @class JwtSession
 * @package think\admin\support\middleware
 */
class JwtSession
{
    /**
     * 当前 App 对象
     * @var \think\App
     */
    protected $app;

    /**
     * 当前 Session 对象
     * @var \think\Session
     */
    protected $session;

    /**
     * Construct
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->session = $app->session;
    }

    /**
     * 中间键处理
     * @param \think\Request $request
     * @param \Closure $next
     * @return \think\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 处理 Jwt 请求，请求头存在 jwt-token 字段
        if (($token = $request->header('jwt-token', ''))) try {
            if (preg_match('#^\s*([\w\-]+\.[\w\-]+\.[\w\-]+)\s*$#', $token, $match)) {
                JwtExtend::verify($match[1]);
                $sessionId = JwtExtend::$sessionId;
            } else {
                throw new Exception('令牌格式错误！', 401);
            }
        } catch (\Exception $exception) {
            throw new HttpResponseException(json(['code' => $exception->getCode(), 'info' => lang($exception->getMessage())]));
        }

        if (empty($sessionId)) {
            $varSessionId = $this->app->config->get('session.var_session_id');
            if ($varSessionId && $request->request($varSessionId)) {
                $sessionId = $request->request($varSessionId);
            } else {
                $sessionId = $request->cookie($this->session->getName());
            }
        }

        if ($sessionId) {
            $this->session->setId($sessionId);
        }

        // 基础 Session 初始化
        $this->session->init();
        $request->withSession($this->session);

        if (empty(JwtExtend::$sessionId)) {
            // 非 Jwt 会话需写入 Cookie 记录 SessionID
            $this->app->cookie->set($this->session->getName(), $this->session->getId());
        }

        // 执行下一步控制器操作
        return $next($request)->setSession($this->session);
    }

    /**
     * 保存会话数据
     * @return void
     */
    public function end()
    {
        $this->session->save();
        JwtExtend::$sessionId = '';
    }
}