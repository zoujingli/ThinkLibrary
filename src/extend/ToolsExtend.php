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
use think\File;

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
     * 移除清空目录
     * @param string $path
     * @return boolean
     */
    public static function removeEmptyDirectory(string $path): bool
    {
        foreach (self::findFilesYield($path, null, null, null, true) as $item) {
            ($item->isFile() || $item->isLink()) ? unlink($item->getRealPath()) : rmdir($item->getRealPath());
        }
        return is_file($path) ? unlink($path) : (!is_dir($path) || rmdir($path));
    }

    /**
     * 扫描目录列表
     * @param string $path 扫描目录
     * @param string $filterExt 筛选后缀
     * @param boolean $shortPath 相对路径
     * @param ?integer $depth 当前递归深度，null 表示无限制深度
     * @return array
     */
    public static function scanDirectory(string $path, string $filterExt = '', bool $shortPath = true, ?int $depth = null): array
    {
        return static::findFilesArray($path, static function (SplFileInfo $info) use ($filterExt) {
            return !$filterExt || $info->getExtension() === $filterExt;
        }, static function (SplFileInfo $info) {
            return $info->getBasename()[0] !== '.';
        }, $shortPath, $depth);
    }

    /**
     * 扫描指定目录并返回文件路径数组
     * @param string $path 要扫描的目录路径
     * @param ?Closure $filterFile 用于过滤文件的闭包
     * @param ?Closure $filterPath 用于过滤目录的闭包
     * @param boolean $short 是否返回相对于给定路径的短路径
     * @param ?integer $depth 当前递归深度，null 表示无限制深度
     * @return array 包含文件路径的数组
     */
    public static function findFilesArray(string $path, ?Closure $filterFile = null, ?Closure $filterPath = null, bool $short = true, ?int $depth = null): array
    {
        [$info, $files] = [new SplFileInfo($path), []];
        if ($info->isFile()) {
            if ($filterFile === null || $filterFile($info)) {
                $files[] = $short ? $info->getBasename() : $info->getPathname();
            }
        } elseif ($info->isDir()) {
            foreach (static::findFilesYield($info->getPathname(), $filterFile, $filterPath, $depth) as $file) {
                $files[] = $short ? substr($file->getRealPath(), strlen($info->getPathname()) + 1) : $file->getRealPath();
            }
        }
        return $files;
    }

    /**
     * 递归扫描指定目录，返回文件或目录的 SplFileInfo 对象。
     * @param string $path 目录路径。
     * @param \Closure|null $filterFile 文件过滤器闭包，返回 true 表示文件被接受。
     * @param \Closure|null $filterPath 目录过滤器闭包，返回 true 表示目录被接受。
     * @param ?integer $depth 当前递归深度，null 表示无限制深度
     * @param boolean $appendPath 是否包含目录本身在结果中。
     * @return \Generator 返回 SplFileInfo 对象的生成器。
     */
    private static function findFilesYield(string $path, ?Closure $filterFile = null, ?Closure $filterPath = null, ?int $depth = null, bool $appendPath = false): Generator
    {
        if (file_exists($path)) {
            foreach (is_file($path) ? [new SplFileInfo($path)] : new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
                if (($isDir = $item->isDir() && !$item->isLink()) && ($filterPath === null || $filterPath($item))) {
                    if ($depth === null || $depth > 0) {
                        yield from static::findFilesYield($item->getPathname(), $filterFile, $filterPath, $depth !== null ? $depth - 1 : null, $appendPath);
                    }
                    if ($appendPath) yield $item;
                } elseif (!$isDir && ($filterFile === null || $filterFile($item))) {
                    yield $item;
                }
            }
        }
    }
}