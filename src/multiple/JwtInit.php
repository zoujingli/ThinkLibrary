<?php
declare (strict_types=1);

namespace think\admin\multiple;

use think\admin\extend\CodeExtend;
use think\admin\extend\JwtExtend;
use think\App;
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
     * Session 初始化
     * @access public
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // 处理 JWT 请求
        $authorization = $request->header('Authorization', '');
        if (preg_match('#^Bearer\s+([\w\-_]+\.[\w\-_]+\.[\w\-_]+)$#', $authorization, $match)) {
            try {
                $payload = JwtExtend::verifyToken($match[1]);
                if (isset($payload['sub']) && !empty($payload['sub'])) {
                    $sessionId = CodeExtend::decrypt($payload['sub'], JwtExtend::jwtkey());
                }
            } catch (\Exception $exception) {
                json(['code' => 0, 'info' => lang($exception->getMessage())])->send();
            }
        }

        $cookieName = $this->session->getName();

        if (!isset($sessionId)) {
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

        $this->app->cookie->set($cookieName, $this->session->getId());

        return $response;
    }

    public function end()
    {
        $this->session->save();
    }
}