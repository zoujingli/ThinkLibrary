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
            $files = static::findFilesArray($frdir, null, function (SplFileInfo $info) {
                return $info->getBasename()[0] !== '.';
            });
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
        foreach (self::findFilesYield($path, null, null, true) as $item) {
            ($item->isFile() || $item->isLink()) ? unlink($item->getPathname()) : rmdir($item->getPathname());
        }
        return is_file($path) ? unlink($path) : (!is_dir($path) || rmdir($path));
    }

    /**
     * 扫描目录列表
     * @param string $path 扫描目录
     * @param ?integer $depth 扫描深度
     * @param string $filterExt 筛选后缀
     * @param boolean $shortPath 相对路径
     * @return array
     */
    public static function scanDirectory(string $path, ?int $depth = null, string $filterExt = '', bool $shortPath = true): array
    {
        return static::findFilesArray($path, $depth, function (SplFileInfo $info) use ($filterExt, &$files) {
            return $info->isDir() || $filterExt === '' || strtolower($info->getExtension()) === strtolower($filterExt);
        }, $shortPath);
    }

    /**
     * 扫描指定目录并返回文件路径数组
     * @param string $path 扫描目录
     * @param ?integer $depth 扫描深度
     * @param ?Closure $filter 文件过滤，返回 false 表示放弃
     * @param boolean $short 是否返回相对于给定路径的短路径
     * @return array 包含文件路径的数组
     */
    public static function findFilesArray(string $path, ?int $depth = null, ?Closure $filter = null, bool $short = true): array
    {
        [$info, $files] = [new SplFileInfo($path), []];
        if ($info->isDir() || $info->isFile()) {
            if ($info->isFile() && ($filter === null || $filter($info) !== false)) {
                $files[] = $short ? $info->getBasename() : $info->getPathname();
            }
            if ($info->isDir()) foreach (static::findFilesYield($info->getPathname(), $depth, $filter) as $file) {
                $files[] = $short ? substr($file->getPathname(), strlen($info->getPathname()) + 1) : $file->getPathname();
            }
        }
        return $files;
    }

    /**
     * 递归扫描指定目录，返回文件或目录的 SplFileInfo 对象。
     * @param string $path 目录路径。
     * @param ?integer $depth 扫描深度
     * @param \Closure|null $filter 文件过滤，返回 false 表示放弃
     * @param boolean $appendPath 是否包含目录本身在结果中。
     * @param integer $currDepth 当前深度，临时变量递归时使用。
     * @return \Generator 返回 SplFileInfo 对象的生成器。
     */
    private static function findFilesYield(string $path, ?int $depth = null, ?Closure $filter = null, bool $appendPath = false, int $currDepth = 1): Generator
    {
        if (file_exists($path) && is_dir($path) && (!is_numeric($depth) || $currDepth <= $depth)) {
            foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
                if ($filter === null || $filter($item) !== false) {
                    if ($item->isDir() && !$item->isLink()) {
                        $appendPath && yield $item;
                        yield from static::findFilesYield($item->getPathname(), $depth, $filter, $appendPath, $currDepth + 1);
                    } else {
                        yield $item;
                    }
                }
            }
        }
    }
}