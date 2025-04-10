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

namespace think\admin\extend;

/**
 * 数据处理扩展
 * @class DataExtend
 * @package think\admin\extend
 */
class DataExtend
{
    /**
     * 二维数组转多维数据树
     * @param array $list 待处理数据
     * @param string $ckey 自己的主键
     * @param string $pkey 上级的主键
     * @param string $chil 子数组名称
     * @return array
     */
    public static function arr2tree(array $list, string $ckey = 'id', string $pkey = 'pid', string $chil = 'sub'): array
    {
        [$tree, $list] = [[], array_column($list, null, $ckey)];
        foreach ($list as $it) isset($list[$it[$pkey]]) ? $list[$it[$pkey]][$chil][] = &$list[$it[$ckey]] : $tree[] = &$list[$it[$ckey]];
        return $tree;
    }

    /**
     * 二维数组转数据树表
     * @param array $list 待处理数据
     * @param string $ckey 自己的主键
     * @param string $pkey 上级的主键
     * @param string $path 当前 PATH
     * @return array
     */
    public static function arr2table(array $list, string $ckey = 'id', string $pkey = 'pid', string $path = 'path'): array
    {
        $build = static function (array $nodes, callable $build, array &$data = [], string $parent = '') use ($ckey, $pkey, $path) {
            foreach ($nodes as $node) {
                $subs = $node['sub'] ?? [];
                unset($node['sub']);
                $node[$path] = "{$parent}-{$node[$ckey]}";
                $node['spc'] = count($subs);
                $node['spt'] = substr_count($parent, '-');
                $node['spl'] = str_repeat('ㅤ├ㅤ', $node['spt']);
                $node['sps'] = ",{$node[$ckey]},";
                array_walk_recursive($subs, static function ($val, $key) use ($ckey, &$node) {
                    if ($key === $ckey) $node['sps'] .= "{$val},";
                });
                $node['spp'] = arr2str(str2arr(strtr($parent . $node['sps'], '-', ',')));
                $data[] = $node;
                if (empty($subs)) continue;
                $build($subs, $build, $data, $node[$path]);
            }
            return $data;
        };
        return $build(static::arr2tree($list, $ckey, $pkey), $build);
    }

    /**
     * 获取数据树子ID集合
     * @param array $list 数据列表
     * @param mixed $value 起始有效ID值
     * @param string $ckey 当前主键ID名称
     * @param string $pkey 上级主键ID名称
     * @return array
     */
    public static function getArrSubIds(array $list, $value = 0, string $ckey = 'id', string $pkey = 'pid'): array
    {
        $ids = [intval($value)];
        foreach ($list as $vo) if (intval($vo[$pkey]) > 0 && intval($vo[$pkey]) === intval($value)) {
            $ids = array_merge($ids, static::getArrSubIds($list, intval($vo[$ckey]), $ckey, $pkey));
        }
        return $ids;
    }
}