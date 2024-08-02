<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2024 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免费声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// 以下代码来自 topthink/think-multi-app，有部分修改以兼容 ThinkAdmin 的需求
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\support;

use InvalidArgumentException;
use think\admin\service\NodeService;
use think\route\Url as ThinkUrl;

/**
 * 多应用 URL 生成与解析
 * @class Url
 * @package think\admin\support
 */
class Url extends ThinkUrl
{
    /**
     * 直接解析 URL 地址
     * @param string $url URL
     * @param string|boolean $domain Domain
     * @return string
     */
    protected function parseUrl(string $url, &$domain): string
    {
        $request = $this->app->request;
        if (0 === strpos($url, '/')) {
            $url = substr($url, 1);
        } elseif (false !== strpos($url, '\\')) {
            $url = ltrim(str_replace('\\', '/', $url), '/');
        } elseif (0 === strpos($url, '@')) {
            $url = substr($url, 1);
        } else {
            $attrs = str2arr($url, '/');
            $action = empty($attrs) ? $request->action() : array_pop($attrs);
            $contrl = empty($attrs) ? $request->controller() : array_pop($attrs);
            $module = empty($attrs) ? $this->app->http->getName() : array_pop($attrs);
            // 拼装新的链接地址
            $url = NodeService::nameTolower($contrl) . '/' . $action;
            $bind = $this->app->config->get('app.domain_bind', []);
            if ($key = array_search($module, $bind)) {
                if (isset($bind[$_SERVER['SERVER_NAME']])) $domain = $_SERVER['SERVER_NAME'];
                $domain = is_bool($domain) ? $key : $domain;
            } elseif ($key = array_search($module, $this->app->config->get('app.app_map', []))) {
                $url = $key . '/' . $url;
            } else {
                $url = $module . '/' . $url;
            }
        }
        return $url;
    }

    /**
     * Build URL
     * @return string
     */
    public function build(): string
    {
        $url = $this->url;
        $vars = $this->vars;
        $domain = $this->domain;
        $suffix = $this->suffix;
        $request = $this->app->request;
        if (0 === strpos($url, '[') && $pos = strpos($url, ']')) {
            // [name] 表示使用路由命名标识生成URL
            $name = substr($url, 1, $pos - 1);
            $url = 'name' . substr($url, $pos + 1);
        }
        if (false === strpos($url, '://') && 0 !== strpos($url, '/')) {
            $info = parse_url($url);
            $url = !empty($info['path']) ? $info['path'] : '';
            if (isset($info['fragment'])) {
                // 解析锚点
                $anchor = $info['fragment'];
                if (false !== strpos($anchor, '?')) {
                    // 解析参数
                    [$anchor, $info['query']] = explode('?', $anchor, 2);
                }
                if (false !== strpos($anchor, '@')) {
                    // 解析域名
                    [$anchor, $domain] = explode('@', $anchor, 2);
                }
            } elseif (strpos($url, '@') && false === strpos($url, '\\')) {
                // 解析域名
                [$url, $domain] = explode('@', $url, 2);
            }
        }
        if ($url) {
            $checkDomain = $domain && is_string($domain) ? $domain : null;
            $checkName = $name ?? $url . (isset($info['query']) ? '?' . $info['query'] : '');
            $rule = $this->route->getName($checkName, $checkDomain);
            if (empty($rule) && isset($info['query'])) {
                $rule = $this->route->getName($url, $checkDomain);
                parse_str($info['query'], $params);
                $vars = array_merge($params, $vars);
                unset($info['query']);
            }
        }
        if (!empty($rule) && $match = $this->getRuleUrl($rule, $vars, $domain)) {
            $url = $match[0];
            if ($domain && !empty($match[1])) $domain = $match[1];
            if (!is_null($match[2])) $suffix = $match[2];
            if (!$this->app->http->isBind()) {
                $url = $this->app->http->getName() . '/' . $url;
            }
        } elseif (!empty($rule) && isset($name)) {
            throw new InvalidArgumentException('route name not exists:' . $name);
        } else {
            // 检测URL绑定
            $bind = $this->route->getDomainBind($domain && is_string($domain) ? $domain : null);
            if ($bind && 0 === strpos($url, $bind)) {
                $url = substr($url, strlen($bind) + 1);
            } else {
                $binds = $this->route->getBind();
                foreach ($binds as $key => $val) {
                    if (is_string($val) && 0 === strpos($url, $val) && substr_count($val, '/') > 1) {
                        $url = substr($url, strlen($val) + 1);
                        $domain = $key;
                        break;
                    }
                }
            }
            // 路由标识不存在 直接解析
            $url = $this->parseUrl($url, $domain);
            if (isset($info['query'])) {
                // 解析地址里面参数 合并到vars
                parse_str($info['query'], $params);
                $vars = array_merge($params, $vars);
            }
        }
        // 还原 URL 分隔符
        $file = $request->baseFile();
        $depr = $this->route->config('pathinfo_depr');
        [$uri, $url] = [$request->url(), str_replace('/', $depr, $url)];
        if ($file && 0 !== strpos($uri, $file)) {
            $file = str_replace('\\', '/', dirname($file));
        }
        /*=====- 多应用绑定 URL 生成处理 -=====*/
        $app = $this->app->http->getName();
        if ($this->app->http->isBind()) {
            if (preg_match("#^{$app}({$depr}|\.|$)#i", $url)) {
                $url = trim(substr($url, strlen($app)), $depr);
            } elseif (substr_count($url, $depr) >= 2) {
                $file = 'index.php';
            }
        }
        $url = rtrim($file, '/') . '/' . ltrim($url, '/');
        // URL后缀
        if ('/' == substr($url, -1) || '' == $url) {
            $suffix = '';
        } else {
            $suffix = $this->parseSuffix($suffix);
        }
        // 锚点
        $anchor = !empty($anchor) ? '#' . $anchor : '';
        // 参数组装
        if (!empty($vars)) {
            // 添加参数
            if ($this->route->config('url_common_param')) {
                $vars = http_build_query($vars);
                $url .= $suffix . '?' . $vars . $anchor;
            } else {
                foreach ($vars as $var => $val) {
                    $val = (string)$val;
                    if ('' !== $val) {
                        $url .= $depr . $var . $depr . urlencode($val);
                    }
                }
                $url .= $suffix . $anchor;
            }
        } else {
            $url .= $suffix . $anchor;
        }
        // 检测域名
        $domain = $this->parseDomain($url, $domain);
        // URL 组装
        return $domain . rtrim($this->root, '/') . '/' . ltrim($url, '/');
    }
}