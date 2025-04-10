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

use think\admin\Library;
use think\admin\Service;

/**
 * 系统模块管理
 * @class ModuleService
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
        $libray = self::getLibrarys('zoujingli/think-library');
        return trim($libray['version'] ?? 'v6.0.0', 'v');
    }

    /**
     * 获取运行参数变量
     * @param string $field 指定字段
     * @return string
     */
    public static function getRunVar(string $field): string
    {
        $file = syspath('vendor/binarys.php');
        if (is_file($file) && is_array($binarys = include $file)) {
            return $binarys[$field] ?? '';
        } else {
            return '';
        }
    }

    /**
     * 获取 PHP 执行路径
     * @return string
     */
    public static function getPhpExec(): string
    {
        if ($phpExec = sysvar($keys = 'phpBinary')) return $phpExec;
        if (ProcessService::isFile($phpExec = self::getRunVar('php'))) {
            return sysvar($keys, $phpExec);
        } else {
            $phpExec = str_replace('/sbin/php-fpm', '/bin/php', PHP_BINARY);
            $phpExec = preg_replace('#-(cgi|fpm)(\.exe)?$#', '$2', $phpExec);
            return sysvar($keys, ProcessService::isFile($phpExec) ? $phpExec : 'php');
        }
    }

    /**
     * 获取应用模块
     * @param array $data
     * @return array
     */
    public static function getModules(array $data = []): array
    {
        $path = Library::$sapp->getBasePath();
        foreach (scandir($path) as $item) if ($item[0] !== '.') {
            if (is_dir($path . $item)) $data[] = $item;
        }
        return $data;
    }

    /**
     * 获取本地组件
     * @param ?string $package 指定包名
     * @param boolean $force 强制刷新
     * @return array|string|null
     */
    public static function getLibrarys(?string $package = null, bool $force = false)
    {
        $plugs = sysvar($keys = 'think.admin.version');
        if ((empty($plugs) || $force) && is_file($file = syspath('vendor/versions.php'))) {
            $plugs = sysvar($keys, include $file);
        }
        return empty($package) ? $plugs : ($plugs[$package] ?? null);
    }
}