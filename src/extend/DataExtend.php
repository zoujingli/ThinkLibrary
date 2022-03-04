<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2022 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkLibrary
// | github 代码仓库：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\extend;

/**
 * 数据处理扩展
 * Class DataExtend
 * @package think\admin\extend
 */
class DataExtend
{

    /**
     * 一维数组生成数据树
     * @param array $its 待处理数据
     * @param string $cid 自己的主键
     * @param string $pid 上级的主键
     * @param string $sub 子数组名称
     * @return array
     */
    public static function arr2tree(array $its, string $cid = 'id', string $pid = 'pid', string $sub = 'sub'): array
    {
        [$tree, $its] = [[], array_column($its, null, $cid)];
        foreach ($its as $it) isset($its[$it[$pid]]) ? $its[$it[$pid]][$sub][] = &$its[$it[$cid]] : $tree[] = &$its[$it[$cid]];
        return $tree;
    }

    /**
     * 一维数组生成数据树
     * @param array $list 待处理数据
     * @param string $cid 自己的主键
     * @param string $pid 上级的主键
     * @param string $path 当前 PATH
     * @return array
     */
    public static function arr2table(array $list, string $cid = 'id', string $pid = 'pid', string $path = 'path'): array
    {
        $call = function (array $list, callable $call, array &$data = [], string $parent = '') use ($cid, $pid, $path) {
            foreach ($list as $item) {
                $list = $item['sub'] ?? [];
                unset($item['sub']);
                $item[$path] = "{$parent}-{$item[$cid]}";
                $item['spc'] = count($list);
                $item['spt'] = substr_count($parent, '-');
                $item['spl'] = str_repeat('ㅤ├ㅤ', $item['spt']);
                $item['sps'] = ",{$item[$cid]},";
                array_walk_recursive($list, function ($val, $key) use ($cid, &$item) {
                    if ($key === $cid) $item['sps'] .= "{$val},";
                });
                $data[] = $item;
                if (empty($list)) continue;
                $call($list, $call, $data, $item[$path]);
            }
            return $data;
        };
        return $call(static::arr2tree($list, $cid, $pid), $call);
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