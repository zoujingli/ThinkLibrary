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

namespace think\admin\helper;

use think\admin\Helper;
use think\admin\Library;
use think\exception\HttpResponseException;

/**
 * 表单令牌验证器
 * @class TokenHelper
 * @package think\admin\helper
 */
class TokenHelper extends Helper
{
    /**
     * 初始化验证码器
     * @param boolean $return
     * @return boolean|void
     */
    public function init(bool $return = false)
    {
        $this->class->csrf_state = true;
        if (!$this->app->request->isPost()) return true;
        $token = $this->app->request->post('_token_');
        $extra = ['_token_' => $token ?: $this->app->request->header('User-Form-Token')];
        if ($this->app->request->checkToken('_token_', $extra)) return true; elseif ($return) return false;
        $this->class->error($this->class->csrf_message ?: '表单令牌验证失败！');
    }

    /**
     * 返回视图内容
     * @param string $tpl 模板名称
     * @param array $vars 模板变量
     * @param string|null $node 授权节点
     */
    public static function fetch(string $tpl = '', array $vars = [], ?string $node = null)
    {
        throw new HttpResponseException(view($tpl, $vars, 200, static function ($html) use ($node) {
            return preg_replace_callback('/<\/form>/i', static function () use ($node) {
                return sprintf("<input type='hidden' name='_token_' value='%s'></form>", static::token());
            }, $html);
        }));
    }

    /**
     * 返回表单令牌数据
     * 为了兼容JWT模式使用表单令牌
     * @return string
     */
    public static function token(): string
    {
        return Library::$sapp->request->buildToken('_token_');
    }
}