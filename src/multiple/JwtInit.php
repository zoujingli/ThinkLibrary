<?php
declare (strict_types=1);

namespace think\admin\multiple;

use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\admin\extend\JwtExtend;
use think\App;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;
use think\Session;

/**
 * 兼容会话中间键
 * @class JwtInit
 */
class JwtInit
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
     * @param \think\Session $session
     */
    public function __construct(App $app, Session $session)
    {
        $this->app = $app;
        $this->session = $session;
    }

    /**
     * Jwt Session 初始化
     * @access public
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // 处理 JWT 请求
        if (($token = $request->header('jwt-token', ''))) try {
            if (preg_match('#^\s*([\w\-_]+\.[\w\-_]+\.[\w\-_]+)\s*$#', $token, $match)) {
                $payload = JwtExtend::verifyToken($match[1]);
                if (isset($payload['sub']) && !empty($payload['sub'])) {
                    $sessionId = CodeExtend::decrypt($payload['sub'], JwtExtend::jwtkey());
                }
            } else {
                throw new Exception('JwtToken 格式错误！');
            }
        } catch (\Exception $exception) {
            throw new HttpResponseException(json([
                'code' => 0, 'info' => lang($exception->getMessage()),
            ]));
        }

        $cookieName = $this->session->getName();

        if (empty($sessionId)) {
            $varSessionId = $this->app->config->get('session.var_session_id');
            if ($varSessionId && $request->request($varSessionId)) {
                $sessionId = $request->request($varSessionId);
            } else {
                $sessionId = $request->cookie($cookieName);
            }
        }

        if ($sessionId) {
            $this->session->setId($sessionId);
        }

        // Session 初始化
        $this->session->init();

        $request->withSession($this->session);

        /** @var Response $response */
        $response = $next($request);

        $response->setSession($this->session);

        if (!JwtExtend::$isJwt) {
            // 已经标识为 Jwt 的 Session 无法在非 Jwt 时访问
            if ($this->session->get('__IS_JWT_SESSION_')) {
                throw new HttpResponseException(json([
                    'code' => 0, 'info' => lang('请使用 JWT 方式访问！'),
                ]));
            }
            // 非 Jwt 接口模式需要写入 Cookie
            $this->app->cookie->set($cookieName, $this->session->getId());
        } else {
            // 再次 标识 Jwt 接口会话
            $this->session->set('__IS_JWT_SESSION_', true);
        }

        return $response;
    }

    public function end()
    {
        $this->session->save();
    }
}