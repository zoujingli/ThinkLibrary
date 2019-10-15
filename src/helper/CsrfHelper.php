<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://library.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace library\helper;

use library\Controller;
use think\exception\HttpResponseException;

/**
 * 表单令牌验证器
 * Class CsrfHelper
 * @package library\helper
 */
class CsrfHelper
{
    /**
     * 当前控制器
     * @var Controller
     */
    protected $controller;

    /**
     * 获取当前令牌值
     * @var string
     */
    protected $token;

    /**
     * 获取当前节点
     * @var string
     */
    protected $node;


    /**
     * CsrfHelper constructor.
     */
    public function __construct()
    {
        $this->token = app()->request->header('User-Token-Csrf', input('_csrf_', ''));
        $this->node = app()->request->controller(true) . '/' . app()->request->action(true);
    }

    /**
     * 初始化验证码器
     * @param Controller $controller
     * @param bool $return
     * @return bool
     */
    public function init(Controller $controller, $return = false)
    {
        $this->controller = $controller;
        $this->controller->csrf_state = true;
        if ($this->controller->request->isPost() && $this->checkFormToken()) {
            if ($return) return false;
            $this->controller->error($this->controller->csrf_message);
        } else {
            return true;
        }
    }

    /**
     * 检查表单CSRF验证
     * @return boolean
     */
    private function checkFormToken()
    {
        app()->session->get($this->token);
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
        app()->session->delete($name);
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
        app()->session->set($token, ['node' => $node, 'token' => $token, 'time' => $time]);
        foreach (app()->session->all() as $key => $item) if (stripos($key, 'csrf') === 0 && isset($item['time'])) {
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