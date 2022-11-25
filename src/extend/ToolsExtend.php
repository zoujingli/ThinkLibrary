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

/**
 * 通用工具扩展
 * Class ToolsExtend
 * @package think\admin\extend
 */
class ToolsExtend
{
    /**
     * 深度拷贝目录到指定目录
     * @param string $frdir 来源目录
     * @param string $todir 目标目录
     * @param array $files 指定文件
     * @param boolean $force 强制替换
     * @param boolean $remove 删除文件
     * @return boolean
     */
    public static function copyfile(string $frdir, string $todir, array $files = [], bool $force = true, bool $remove = true): bool
    {
        $frdir = trim($frdir, '\\/') . DIRECTORY_SEPARATOR;
        $todir = trim($todir, '\\/') . DIRECTORY_SEPARATOR;
        // 目录检查创建
        file_exists($todir) || mkdir($todir, 0755, true);
        // 扫描目录文件
        if (empty($files) && file_exists($frdir) && is_dir($frdir)) {
            $files = static::findSimpleFiles($frdir, function (\SplFileInfo $item) {
                return !in_array(substr($item->getBasename(), 0, 1), ['.', '_']);
            }, function (\SplFileInfo $item) {
                return !in_array(substr($item->getBasename(), 0, 1), ['.', '_']);
            });
        }
        // 复制指定文件
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
     * 扫描指定目录
     * @param string $root
     * @param null|\Closure $filterFile
     * @param null|\Closure $filterDir
     * @return array
     */
    public static function findSimpleFiles(string $root, ?Closure $filterFile = null, ?Closure $filterDir = null): array
    {
        $files = static::findYieldFiles($root, $filterDir, $filterFile);
        [$pos, $items] = [strlen(realpath($root)) + 1, []];
        foreach ($files as $file) $items[] = substr($file->getRealPath(), $pos);
        unset($root, $files, $filterDir, $filterFile);
        return $items;
    }

    /**
     * 扫描指定目录
     * @param string $root
     * @param \Closure|null $filterDir
     * @param \Closure|null $filterFile
     * @return \Generator|\SplFileInfo[]
     */
    public static function findYieldFiles(string $root, ?Closure $filterFile = null, ?Closure $filterDir = null): Generator
    {
        $items = new FilesystemIterator($root);
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                if (!$filterDir || $filterDir($item)) {
                    yield from static::findYieldFiles($item->getPathname(), $filterDir, $filterFile);
                }
            } else {
                if (!$filterFile || $filterFile($item)) yield $item;
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