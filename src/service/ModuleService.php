<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2023 Anyon <zoujingli@qq.com>
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

use think\admin\Library;
use think\admin\Service;

/**
 * 系统模块管理
 * Class ModuleService
 * @package think\admin\service
 */
class ModuleService extends Service
{
    /**
     * 获取版本号信息
     * @return string
     */
    public static function getVersion(): string
    {
        return trim(Library::VERSION, 'v');
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
     * 获取本地组件
     * @param string $package
     * @param boolean $force
     * @return array|string|null
     */
    public static function getLibrarys(string $package = '', bool $force = false)
    {
        static $plugs;
        if (empty($plugs) || $force) {
            $plugs = include syspath('vendor/versions.php');
        }
        return empty($package) ? $plugs : ($plugs[$package] ?? null);
    }
}