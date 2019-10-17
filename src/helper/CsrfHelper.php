<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin\helper;

use think\admin\Controller;
use think\exception\HttpResponseException;

/**
 * 表单令牌验证器
 * Class CsrfHelper
 * @package library\helper
 */
class CsrfHelper extends Helper
{

    /**
     * 获取当前节点
     * @var string
     */
    protected $node;

    /**
     * 获取当前令牌值
     * @var string
     */
    protected $token;

    /**
     * CsrfHelper constructor.
     * @param Controller $controller
     */
    public function __construct(Controller $controller)
    {
        $this->controller = $controller;
        $this->node = $this->controller->request->controller(true) . '/' . $this->controller->request->action(true);
        $this->token = $this->controller->request->header('User-Token-Csrf', input('_csrf_', ''));
    }

    /**
     * 初始化验证码器
     * @param bool $return
     * @return bool
     */
    public function init($return = false)
    {
        $this->controller->csrf_state = true;
        if ($this->controller->request->isPost() && $this->checkFormToken()) {
            if ($return) return false;
            $this->controller->error($this->controller->csrf_message);
        } else {
            return true;
        }
    }

    /**
     * 清理表单令牌
     */
    public function clear()
    {
        $this->clearFormToken($this->token);
    }

    /**
     * 检查表单CSRF验证
     * @return boolean
     */
    private function checkFormToken()
    {
        $cache = $this->controller->app->session->get($this->token);
        if (empty($cache['node']) || empty($cache['time']) || empty($cache['token'])) return false;
        if ($cache['token'] <> $this->token || $cache['time'] + 600 < time() || $cache['node'] <> $this->node) return false;
        return true;
    }

    /**
     * 清理表单CSRF信息
     * @param string $name
     */
    private function clearFormToken($name = null)
    {
        $this->controller->app->session->delete($name);
    }

    /**
     * 生成表单CSRF信息
     * @param null|string $node
     * @return array
     */
    private function buildFormToken($node = null)
    {
        list($token, $time) = [uniqid('csrf'), time()];
        // if (is_null($node)) $node = Node::current();
        $this->controller->app->session->set($token, ['node' => $node, 'token' => $token, 'time' => $time]);
        foreach ($this->controller->app->session->all() as $key => $item) if (stripos($key, 'csrf') === 0 && isset($item['time'])) {
            if ($item['time'] + 600 < $time) $this->clearFormToken($key);
        }
        return ['token' => $token, 'node' => $node, 'time' => $time];
    }

    /**
     * 返回视图内容
     * @param string $tpl 模板名称
     * @param array $vars 模板变量
     * @param string $node CSRF授权节点
     */
    public function fetchTemplate($tpl = '', $vars = [], $node = null)
    {
        throw new HttpResponseException(view($tpl, $vars, 200, function ($html) use ($node) {
            return preg_replace_callback('/<\/form>/i', function () use ($node) {
                $csrf = $this->buildFormToken($node);
                return "<input type='hidden' name='_csrf_' value='{$csrf['token']}'></form>";
            }, $html);
        }));
    }

}