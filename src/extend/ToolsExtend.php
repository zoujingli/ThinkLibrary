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
            $files = static::findFilesArray($frdir, static function (SplFileInfo $info) {
                return substr($info->getBasename(), 0, 1) !== '.';
            }, static function (SplFileInfo $info) {
                return substr($info->getBasename(), 0, 1) !== '.';
            });
        }
        // 复制文件列表
        foreach ($files as $target) {
            if ($force || !is_file($todir . $target)) {
                $dir = dirname($todir . $target);
                is_dir($dir) or mkdir($dir, 0777, true);
                copy($frdir . $target, $todir . $target);
            }
            // 删除来源文件
            $remove && unlink($frdir . $target);
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
            return empty($filterExt) || $info->getExtension() === $filterExt;
        }, static function (SplFileInfo $info) {
            return substr($info->getBasename(), 0, 1) !== '.';
        }, $shortPath);
    }

    /**
     * 扫描指定目录
     * @param string $path
     * @param ?Closure $filterFile
     * @param ?Closure $filterPath
     * @param boolean $shortPath
     * @return array
     */
    public static function findFilesArray(string $path, ?Closure $filterFile = null, ?Closure $filterPath = null, bool $shortPath = true): array
    {
        $items = [];
        if (file_exists($path)) {
            $files = static::findFilesYield($path, $filterFile, $filterPath);
            foreach ($files as $file) $items[] = $file->getRealPath();
            if ($shortPath && ($offset = strlen(realpath($path)) + 1)) {
                foreach ($items as &$item) $item = substr($item, $offset);
            }
        }
        return $items;
    }

    /**
     * 扫描指定目录
     * @param string $path
     * @param \Closure|null $filterFile
     * @param \Closure|null $filterPath
     * @param boolean $fullDirectory
     * @return \Generator|\SplFileInfo[]
     */
    public static function findFilesYield(string $path, ?Closure $filterFile = null, ?Closure $filterPath = null, bool $fullDirectory = false): Generator
    {
        if (file_exists($path)) {
            $items = is_file($path) ? [new SplFileInfo($path)] : new FilesystemIterator($path);
            foreach ($items as $item) if ($item->isDir() && !$item->isLink()) {
                if (is_null($filterPath) || $filterPath($item)) {
                    yield from static::findFilesYield($item->getPathname(), $filterFile, $filterPath, $fullDirectory);
                }
                $fullDirectory && yield $item;
            } elseif (is_null($filterFile) || $filterFile($item)) {
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