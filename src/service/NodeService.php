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

namespace think\admin\service;

use ReflectionClass;
use ReflectionMethod;
use think\admin\Exception;
use think\admin\extend\ToolsExtend;
use think\admin\Library;
use think\admin\Plugin;
use think\admin\Service;

/**
 * 应用节点服务管理
 * @class NodeService
 * @method static array getModules() 获取应用列表
 * @method static array scanDirectory() 扫描目录列表
 * @package think\admin\service
 */
class NodeService extends Service
{

    /**
     * 获取默认应用空间名
     * @param string $suffix 后缀路径
     * @return string
     */
    public static function space(string $suffix = ''): string
    {
        $default = Library::$sapp->config->get('app.app_namespace') ?: 'app';
        return empty($suffix) ? $default : trim($default . '\\' . trim($suffix, '\\/'), '\\');
    }

    /**
     * 驼峰转下划线规则
     * @param string $name
     * @return string
     */
    public static function nameTolower(string $name): string
    {
        $dots = [];
        foreach (explode('.', strtr($name, '/', '.')) as $dot) {
            $dots[] = trim(preg_replace("/[A-Z]/", "_\\0", $dot), '_');
        }
        return strtolower(join('.', $dots));
    }

    /**
     * 获取当前节点内容
     * @param string $type app|module|controller|action
     * @return string
     */
    public static function getCurrent(string $type = ''): string
    {
        // 获取应用节点
        $appname = strtolower(Library::$sapp->http->getName());
        if (in_array($type, ['app', 'module'])) return $appname;

        // 获取控制器节点
        $controller = static::nameTolower(Library::$sapp->request->controller());
        if ($type === 'controller') return "{$appname}/{$controller}";

        // 获取方法权限节点
        $method = strtolower(Library::$sapp->request->action());
        return "{$appname}/{$controller}/{$method}";
    }

    /**
     * 检查并完整节点内容
     * @param ?string $node
     * @return string
     */
    public static function fullNode(?string $node = ''): string
    {
        if (empty($node)) return static::getCurrent();
        switch (count($attrs = explode('/', $node))) {
            case 1: # 方法名
                return static::getCurrent('controller') . '/' . strtolower($node);
            case 2: # 控制器/方法名
                $suffix = static::nameTolower($attrs[0]) . '/' . $attrs[1];
                return static::getCurrent('module') . '/' . strtolower($suffix);
            default: # 应用名/控制器/方法名?[其他参数]
                $attrs[1] = static::nameTolower($attrs[1]);
                return strtolower(join('/', $attrs));
        }
    }

    /**
     * 获取所有控制器入口
     * @param boolean $force 强制更新
     * @return array
     */
    public static function getMethods(bool $force = false): array
    {
        $skey = 'think.admin.methods';
        if (empty($force)) {
            $data = sysvar($skey) ?: Library::$sapp->cache->get('SystemAuthNode', []);
            if (count($data) > 0) return sysvar($skey, $data);
        } else {
            $data = [];
        }
        // 排除内置方法，禁止访问内置方法及忽略的应用模块配置
        $ignoreMethods = get_class_methods('\think\admin\Controller');
        $ignoreAppNames = Library::$sapp->config->get('app.rbac_ignore', []);
        // 扫描所有代码控制器节点，更新节点缓存
        foreach (ToolsExtend::scan(Library::$sapp->getBasePath(), null, 'php') as $name) {
            if (preg_match("|^(\w+)/controller/(.+)\.php$|i", strtr($name, '\\', '/'), $matches)) {
                [, $appName, $className] = $matches;
                if (in_array($appName, $ignoreAppNames)) continue;
                static::_parseClass($appName, self::space($appName), $className, $ignoreMethods, $data);
            }
        }
        // 扫描所有插件代码
        foreach (Plugin::get() as $appName => $plugin) {
            if (in_array($appName, $ignoreAppNames)) continue;
            [$appPath, $appSpace] = [$plugin['path'], $plugin['space']];
            foreach (ToolsExtend::scan($appPath, null, 'php') as $name) {
                if (preg_match("|^.*?controller/(.+)\.php$|i", strtr($name, '\\', '/'), $matches)) {
                    static::_parseClass($appName, $appSpace, $matches[1], $ignoreMethods, $data);
                }
            }
        }
        // 节点数据回调处理
        if (function_exists('admin_node_filter')) {
            $data = call_user_func('admin_node_filter', $data);
        }
        // 缓存系统节点数据
        Library::$sapp->cache->set('SystemAuthNode', $data);
        return sysvar($skey, $data);
    }

    /**
     * 解析节点数据
     * @param string $appName 应用名称
     * @param string $appSpace 应用空间
     * @param string $className 应用类型
     * @param array $ignoreNode 忽略节点
     * @param array $data 绑定节点的数据
     * @return void
     */
    private static function _parseClass(string $appName, string $appSpace, string $className, array $ignoreNode, array &$data)
    {
        $classfull = strtr("{$appSpace}/controller/{$className}", '/', '\\');
        if (class_exists($classfull) && ($class = new ReflectionClass($classfull))) {
            $prefix = strtolower(strtr("{$appName}/" . static::nameTolower($className), '\\', '/'));
            $data[$prefix] = static::_parseComment($class->getDocComment() ?: '', $className);
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (in_array($metname = $method->getName(), $ignoreNode)) continue;
                $data[strtolower("{$prefix}/{$metname}")] = static::_parseComment($method->getDocComment() ?: '', $metname);
            }
        }
    }

    /**
     * 解析硬节点属性
     * @param string $comment 备注内容
     * @param string $default 默认标题
     * @return array
     */
    private static function _parseComment(string $comment, string $default = ''): array
    {
        $text = strtr($comment, "\n", ' ');
        $title = preg_replace('/^\/\*\s*\*\s*\*\s*(.*?)\s*\*.*?$/', '$1', $text);
        if (in_array(substr($title, 0, 5), ['@auth', '@menu', '@logi'])) $title = $default;
        return [
            'title'   => $title ?: $default,
            'isauth'  => intval(preg_match('/@auth\s*true/i', $text)),
            'ismenu'  => intval(preg_match('/@menu\s*true/i', $text)),
            'islogin' => intval(preg_match('/@login\s*true/i', $text)),
        ];
    }

    /**
     * 重构兼容处理
     * @param string $name
     * @param array $arguments
     * @return array
     * @throws \think\admin\Exception
     */
    public function __call(string $name, array $arguments)
    {
        return static::__callStatic($name, $arguments);
    }

    /**
     * 重构兼容处理
     * @param string $name
     * @param array $arguments
     * @return array
     * @throws \think\admin\Exception
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if ($name === 'scanDirectory') {
            return ToolsExtend::scan(...$arguments);
        } elseif ($name === 'getModules') {
            return ModuleService::getModules(...$arguments);
        } else {
            throw new Exception("method not exists: NodeService::{$name}()");
        }
    }
}