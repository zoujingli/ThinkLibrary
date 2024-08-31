<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2024 ThinkAdmin [ thinkadmin.top ]
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

namespace think\admin\extend;

use Closure;
use FilesystemIterator;
use Generator;
use SplFileInfo;

/**
 * 通用工具扩展
 * @class ToolsExtend
 * @package think\admin\extend
 */
class ToolsExtend
{
    /**
     * 深度拷贝到指定目录
     * @param string $frdir 来源目录
     * @param string $todir 目标目录
     * @param array $files 指定文件
     * @param boolean $force 强制替换
     * @param boolean $remove 删除文件
     * @return boolean
     */
    public static function copyfile(string $frdir, string $todir, array $files = [], bool $force = true, bool $remove = true): bool
    {
        $frdir = rtrim($frdir, '\\/') . DIRECTORY_SEPARATOR;
        $todir = rtrim($todir, '\\/') . DIRECTORY_SEPARATOR;
        // 扫描目录文件
        if (empty($files) && is_dir($frdir)) {
            $filter = function (SplFileInfo $info) {
                return $info->getBasename()[0] !== '.';
            };
            $files = static::findFilesArray($frdir, $filter, $filter);
        }
        // 复制文件列表
        foreach ($files as $target) {
            [$fromPath, $destPath] = [$frdir . $target, $todir . $target];
            if ($force || !is_file($destPath)) {
                is_dir($dir = dirname($destPath)) || mkdir($dir, 0777, true);
                copy($fromPath, $destPath);
            }
            // 删除来源文件
            $remove && unlink($fromPath);
        }
        // 删除来源目录
        $remove && static::removeEmptyDirectory($frdir);
        return true;
    }

    /**
     * 扫描目录列表
     * @param string $path 扫描目录
     * @param string $filterExt 筛选后缀
     * @param boolean $shortPath 相对路径
     * @return array
     */
    public static function scanDirectory(string $path, string $filterExt = '', bool $shortPath = true): array
    {
        return static::findFilesArray($path, static function (SplFileInfo $info) use ($filterExt) {
            return !$filterExt || $info->getExtension() === $filterExt;
        }, static function (SplFileInfo $info) {
            return $info->getBasename()[0] !== '.';
        }, $shortPath);
    }

    /**
     * 扫描指定目录并返回文件路径数组
     * @param string $path 要扫描的目录路径
     * @param ?Closure $filterFile 用于过滤文件的闭包
     * @param ?Closure $filterPath 用于过滤目录的闭包
     * @param boolean $shortPath 是否返回相对于给定路径的短路径
     * @return array 包含文件路径的数组
     */
    public static function findFilesArray(string $path, ?Closure $filterFile = null, ?Closure $filterPath = null, bool $shortPath = true): array
    {
        $pathLength = $shortPath ? strlen(realpath($path)) + 1 : 0;
        return file_exists($path) ? array_map(function ($file) use ($shortPath, $pathLength) {
            return $shortPath ? substr($file->getRealPath(), $pathLength) : $file->getRealPath();
        }, iterator_to_array(static::findFilesYield($path, $filterFile, $filterPath))) : [];
    }

    /**
     * 递归扫描指定目录，返回文件或目录的 SplFileInfo 对象。
     * @param string $path 目录路径。
     * @param \Closure|null $filterFile 文件过滤器闭包，返回 true 表示文件被接受。
     * @param \Closure|null $filterPath 目录过滤器闭包，返回 true 表示目录被接受。
     * @param boolean $fullDirectory 是否包含目录本身在结果中。
     * @return \Generator 返回 SplFileInfo 对象的生成器。
     */
    public static function findFilesYield(string $path, ?Closure $filterFile = null, ?Closure $filterPath = null, bool $fullDirectory = false): Generator
    {
        if (!file_exists($path)) return;
        foreach (is_file($path) ? [new SplFileInfo($path)] : new FilesystemIterator($path) as $item) {
            $isDir = $item->isDir() && !$item->isLink();
            if ($isDir && ($filterPath === null || $filterPath($item))) {
                yield from static::findFilesYield($item->getPathname(), $filterFile, $filterPath, $fullDirectory);
                if ($fullDirectory) yield $item;
            } elseif (!$isDir && ($filterFile === null || $filterFile($item))) {
                yield $item;
            }
        }
    }

    /**
     * 移除清空目录
     * @param string $path
     * @return boolean
     */
    public static function removeEmptyDirectory(string $path): bool
    {
        foreach (self::findFilesYield($path, null, null, true) as $item) {
            ($item->isFile() || $item->isLink()) ? unlink($item->getRealPath()) : rmdir($item->getRealPath());
        }
        return is_file($path) ? unlink($path) : (!is_dir($path) || rmdir($path));
    }
}