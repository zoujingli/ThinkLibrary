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

namespace think\admin\extend;

use Closure;
use FilesystemIterator;
use Generator;
use SplFileInfo;

/**
 * 通用工具扩展
 * Class ToolsExtend
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
        // 检查创建目录
        file_exists($todir) || mkdir($todir, 0755, true);
        // 扫描目录文件
        if (empty($files) && file_exists($frdir) && is_dir($frdir)) {
            $files = static::findFilesArray($frdir, function (SplFileInfo $info) {
                return substr($info->getBasename(), 0, 1) !== '.';
            }, function (SplFileInfo $info) {
                return substr($info->getBasename(), 0, 1) !== '.';
            });
        }
        // 复制文件列表
        foreach ($files as $target) {
            if ($force || !file_exists($todir . $target)) {
                copy($frdir . $target, $todir . $target);
            }
            $remove && unlink($frdir . $target);
        }
        // 删除源目录
        $remove && static::removeEmptyDirectory($frdir);
        return true;
    }

    /**
     * 扫描目录列表
     * @param string $dir 扫描目录
     * @param string $ext 筛选后缀
     * @param boolean $sub 相对路径
     * @return array
     */
    public static function scanDirectory(string $dir, string $ext = '', bool $sub = true): array
    {
        return static::findFilesArray($dir, function (SplFileInfo $info) use ($ext) {
            return empty($ext) || $info->getExtension() === $ext;
        }, function (SplFileInfo $info) {
            return substr($info->getBasename(), 0, 1) !== '.';
        }, $sub);
    }

    /**
     * 扫描指定目录
     * @param string $dir
     * @param ?Closure $filterFile
     * @param ?Closure $filterPath
     * @param boolean $subPath
     * @return array
     */
    public static function findFilesArray(string $dir, ?Closure $filterFile = null, ?Closure $filterPath = null, bool $subPath = true): array
    {
        $items = [];
        if ($dir = realpath($dir)) {
            $files = static::findFilesYield($dir, $filterFile, $filterPath);
            foreach ($files as $file) $items[] = $file->getRealPath();
            if ($subPath) {
                $posi = strlen($dir) + 1;
                foreach ($items as &$item) {
                    $item = substr($item, $posi);
                }
            }
        }
        return $items;
    }

    /**
     * 扫描指定目录
     * @param string $dir
     * @param \Closure|null $filterFile
     * @param \Closure|null $filterPath
     * @return \Generator|\SplFileInfo[]
     */
    public static function findFilesYield(string $dir, ?Closure $filterFile = null, ?Closure $filterPath = null): Generator
    {
        if (file_exists($dir)) {
            $items = is_file($dir) ? [new SplFileInfo($dir)] : new FilesystemIterator($dir);
            foreach ($items as $item) if ($item->isDir() && !$item->isLink()) {
                if (is_null($filterPath) || $filterPath($item)) {
                    yield from static::findFilesYield($item->getPathname(), $filterFile, $filterPath);
                }
            } else {
                if (is_null($filterFile) || $filterFile($item)) yield $item;
            }
        }
    }

    /**
     * 移除空目录
     * @param string $path 目录位置
     * @return void
     */
    public static function removeEmptyDirectory(string $path)
    {
        if (file_exists($path) && is_dir($path)) {
            if (count(scandir($path)) === 2 && rmdir($path)) {
                static::removeEmptyDirectory(dirname($path));
            }
        }
    }
}