<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | Payment Plugin for ThinkAdmin
 * +----------------------------------------------------------------------
 * | 版权所有 2014~2026 ThinkAdmin [ thinkadmin.top ]
 * +----------------------------------------------------------------------
 * | 官方网站: https://thinkadmin.top
 * +----------------------------------------------------------------------
 * | 开源协议 ( https://mit-license.org )
 * | 免责声明 ( https://thinkadmin.top/disclaimer )
 * | 会员特权 ( https://thinkadmin.top/vip-introduce )
 * +----------------------------------------------------------------------
 * | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
 * | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
 * +----------------------------------------------------------------------
 */

namespace think\admin\extend;

/**
 * 导出 CSV 文件扩展.
 * @class ExcelExtend
 * @deprecated 改用 JavaScript
 */
class ExcelExtend
{
    /**
     * 设置写入 CSV 文件头部.
     * @param string $name 导出文件名称
     * @param array $headers 表格头部(一维数组)
     */
    public static function header(string $name, array $headers): void
    {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . iconv('utf-8', 'gbk//TRANSLIT', $name));
        $handle = fopen('php://output', 'w');
        foreach ($headers as $key => $value) {
            $headers[$key] = iconv('utf-8', 'gbk//TRANSLIT', $value);
        }
        fputcsv($handle, $headers);
        if (is_resource($handle)) {
            fclose($handle);
        }
    }

    /**
     * 设置写入CSV文件内容.
     * @param array $list 数据列表(二维数组)
     * @param array $rules 数据规则(一维数组)
     */
    public static function body(array $list, array $rules): void
    {
        $handle = fopen('php://output', 'w');
        foreach ($list as $data) {
            $rows = [];
            foreach ($rules as $rule) {
                $rows[] = static::parseKeyDotValue($data, $rule);
            }
            fputcsv($handle, $rows);
        }
        if (is_resource($handle)) {
            fclose($handle);
        }
    }

    /**
     * 根据数组key查询(可带点规则).
     * @param array $data 数据
     * @param string $rule 规则，如: order.order_no
     */
    public static function parseKeyDotValue(array $data, string $rule): string
    {
        [$temp, $attr] = [$data, explode('.', trim($rule, '.'))];
        while ($key = array_shift($attr)) {
            $temp = $temp[$key] ?? $temp;
        }
        return (is_string($temp) || is_numeric($temp)) ? @iconv('utf-8', 'gbk//TRANSLIT', "{$temp}") : '';
    }
}
