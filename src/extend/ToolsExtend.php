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
 * 扩展工具
 * Class ToolsExtend
 * @package think\admin\extend
 */
class ToolsExtend
{
    /**
     * 扫描指定目录
     * @param string $root
     * @param null|\Closure $filterFile
     * @param null|\Closure $filterDir
     * @return array
     */
    public static function findSimpleFiles(string $root, ?Closure $filterFile = null, ?Closure $filterDir = null): array
    {
        /** @var \SplFileInfo[] $files */
        $files = static::findYieldFiles($root, $filterDir, $filterFile);
        [$pos, $items] = [strlen(realpath($root)), []];
        foreach ($files as $file) $items[] = substr($file->getRealPath(), $pos);
        unset($root, $files, $filterDir, $filterFile);
        return $items;
    }

    /**
     * 扫描指定目录
     * @param string $root
     * @param \Closure|null $filterDir
     * @param \Closure|null $filterFile
     * @return Generator
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