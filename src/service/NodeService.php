<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2022 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\service;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use think\admin\Exception;
use think\admin\extend\ToolsExtend;
use think\admin\Library;
use think\admin\Service;

/**
 * 应用节点服务管理
 * Class NodeService
 * @package think\admin\service
 */
class NodeService extends Service
{
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
     * @param string $type
     * @return string
     */
    public static function getCurrent(string $type = ''): string
    {
        $prefix = strtolower(Library::$sapp->http->getName());
        // 获取应用节点
        if (in_array($type, ['app', 'module'])) return $prefix;
        // 获取控制器节点
        $middle = static::nameTolower(Library::$sapp->request->controller());
        if ($type === 'controller') return "{$prefix}/{$middle}";
        // 获取方法权限节点
        $method = strtolower(Library::$sapp->request->action());
        return "{$prefix}/{$middle}/{$method}";
    }

    /**
     * 检查并完整节点内容
     * @param null|string $node
     * @return string
     */
    public static function fullNode(?string $node = ''): string
    {
        if (empty($node)) {
            return static::getCurrent();
        }
        switch (count($attrs = explode('/', $node))) {
            case 2:
                $suffix = static::nameTolower($attrs[0]) . '/' . $attrs[1];
                return static::getCurrent('module') . '/' . strtolower($suffix);
            case 1:
                return static::getCurrent('controller') . '/' . strtolower($node);
            default:
                $attrs[1] = static::nameTolower($attrs[1]);
                return strtolower(join('/', $attrs));
        }
    }

    /**
     * 获取应用列表
     * @param array $data
     * @return array
     */
    public static function getModules(array $data = []): array
    {
        $path = Library::$sapp->getBasePath();
        foreach (scandir($path) as $item) if ($item[0] !== '.') {
            if (is_dir(realpath($path . $item))) $data[] = $item;
        }
        return $data;
    }

    /**
     * 获取所有控制器入口
     * @param boolean $force
     * @return array
     * @throws ReflectionException
     */
    public static function getMethods(bool $force = false): array
    {
        static $data = [];
        if (empty($force)) {
            if (count($data) > 0) return $data;
            $data = Library::$sapp->cache->get('SystemAuthNode', []);
            if (count($data) > 0) return $data;
        } else {
            $data = [];
        }
        /*! 排除内置方法，禁止访问内置方法 */
        $ignores = get_class_methods('\think\admin\Controller');
        /*! 扫描所有代码控制器节点，更新节点缓存 */
        $defSpace = Library::$sapp->config->get('app.app_namespace') ?: 'app';
        foreach (ToolsExtend::scanDirectory(Library::$sapp->getBasePath(), 'php') as $name) {
            if (preg_match("|^(\w+)/controller/(.+)\.php$|i", strtr($name, '\\', '/'), $matches)) {
                [, $appName, $className] = $matches;
                static::_parseClass($defSpace, $appName, $className, $ignores, $data);
            }
        }
        // 扫描所有插件代码
        foreach (PluginService::all() as $appName => $plugInfo) {
            [$appPath, $appSpace] = $plugInfo;
            foreach (ToolsExtend::scanDirectory($appPath, 'php') as $name) {
                if (preg_match("|^.*?controller/(.+)\.php$|i", strtr($name, '\\', '/'), $matches)) {
                    static::_parseClass($appSpace ?: $defSpace, $appName, $matches[1], $ignores, $data);
                }
            }
        }
        if (function_exists('admin_node_filter')) admin_node_filter($data);
        Library::$sapp->cache->set('SystemAuthNode', $data);
        return $data;
    }

    /**
     * 解析节点数据
     * @param string $space 应用空间
     * @param string $appname 应用名称
     * @param string $classname 应用类型
     * @param array $ignores 忽略节点
     * @param array $data 绑定节点
     * @return void
     */
    private static function _parseClass(string $space, string $appname, string $classname, array $ignores, array &$data)
    {
        $classfull = strtr("{$space}/{$appname}/controller/{$classname}", '/', '\\');
        if (class_exists($classfull) && ($class = new ReflectionClass($classfull))) {
            $prefix = strtolower(strtr("{$appname}/" . static::nameTolower($classname), '\\', '/'));
            $data[$prefix] = static::_parseComment($class->getDocComment() ?: '', $classname);
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (in_array($metname = $method->getName(), $ignores)) continue;
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
    public static function __callStatic(string $name, array $arguments)
    {
        if ($name === 'scanDirectory') {
            return ToolsExtend::scanDirectory(...$arguments);
        } else {
            throw new Exception("method not exists: NodeService::{$name}()");
        }
    }
}