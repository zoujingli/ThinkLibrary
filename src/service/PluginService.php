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

use think\admin\Library;

/**
 * 应用插件数据服务
 */
class PluginService
{
    /**
     * 当前插件配置
     * @var array
     */
    private static $addons = [];

    /**
     * 注册插件
     * @param string $path 应用目录
     * @param string $name 应用名称
     * @param string $root 命名空间
     * @param string $copy 应用资源
     * @return boolean
     */
    public static function set(string $path, string $name, string $root = '', string $copy = ''): bool
    {
        if (file_exists($path) && is_dir($path)) {
            $path = rtrim($path, '\\/') . DIRECTORY_SEPARATOR;
            $copy = rtrim($copy ?: $path, '\\/') . DIRECTORY_SEPARATOR;
            $root = $root ?: Library::$sapp->config->get('app.app_namespace') ?: 'app';
            static::$addons[$name] = [$path, $root, $copy];
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取所有插件
     * @return array [[所在路径,主空间名,资源目录]]
     */
    public static function all(): array
    {
        return static::$addons;
    }
}