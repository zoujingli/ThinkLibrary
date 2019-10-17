<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin;

use think\Db;
use think\db\Query;

/**
 * 扩展工具包
 * Class Tools
 * @package library
 */
class Tools
{
    /**
     * 通过百度快递100应用查询物流信息
     * @param string $code 快递公司编辑
     * @param string $number 快递物流编号
     * @return array
     */
    public static function express($code, $number)
    {
        if (in_array($code, ['debangkuaidi'])) $code = 'debangwuliu';
        list($microtime, $clientIp, $list) = [time(), request()->ip(), []];
        $options = ['header' => ['Host' => 'www.kuaidi100.com', 'CLIENT-IP' => $clientIp, 'X-FORWARDED-FOR' => $clientIp], 'cookie_file' => env('runtime_path') . 'temp/cookie'];
        $location = "https://sp0.baidu.com/9_Q4sjW91Qh3otqbppnN2DJv/pae/channel/data/asyncqury?cb=callback&appid=4001&com={$code}&nu={$number}&vcode=&token=&_={$microtime}";
        $result = json_decode(str_replace('/**/callback(', '', trim(http_get($location, [], $options), ')')), true);
        if (empty($result['data']['info']['context'])) { // 第一次可能失败，这里尝试第二次查询
            $result = json_decode(str_replace('/**/callback(', '', trim(http_get($location, [], $options), ')')), true);
            if (empty($result['data']['info']['context'])) {
                return ['message' => 'ok', 'com' => $code, 'nu' => $number, 'data' => $list];
            }
        }
        foreach ($result['data']['info']['context'] as $vo) $list[] = [
            'time' => date('Y-m-d H:i:s', $vo['time']), 'ftime' => date('Y-m-d H:i:s', $vo['time']), 'context' => $vo['desc'],
        ];
        return ['message' => 'ok', 'com' => $code, 'nu' => $number, 'data' => $list];
    }

    /**
     * 设置写入CSV文件头部
     * @param string $filename 导出文件
     * @param array $headers CSV 头部(一级数组)
     */
    public static function setCsvHeader($filename, array $headers)
    {
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=" . iconv('utf-8', 'gbk//TRANSLIT', $filename));
        $handle = fopen('php://output', 'w');
        foreach ($headers as $key => $value) $headers[$key] = iconv("utf-8", "gbk//TRANSLIT", $value);
        fputcsv($handle, $headers);
        if (is_resource($handle)) fclose($handle);
    }

    /**
     * 设置写入CSV文件内容
     * @param array $list 数据列表(二维数组或多维数组)
     * @param array $rules 数据规则(一维数组)
     */
    public static function setCsvBody(array $list, array $rules)
    {
        $handle = fopen('php://output', 'w');
        foreach ($list as $data) {
            $rows = [];
            foreach ($rules as $rule) {
                $rows[] = self::parseKeyDotValue($data, $rule);
            }
            fputcsv($handle, $rows);
        }
        if (is_resource($handle)) fclose($handle);
    }

    /**
     * 根据数组key查询(可带点规则)
     * @param array $data 数据
     * @param string $rule 规则，如: order.order_no
     * @return mixed
     */
    public static function parseKeyDotValue(array $data, $rule)
    {
        list($temp, $attr) = [$data, explode('.', trim($rule, '.'))];
        while ($key = array_shift($attr)) $temp = isset($temp[$key]) ? $temp[$key] : $temp;
        return (is_string($temp) || is_numeric($temp)) ? @iconv('utf-8', 'gbk//TRANSLIT', "{$temp}") : '';
    }

    /**
     * 数据增量保存
     * @param Query|string $dbQuery 数据查询对象
     * @param array $data 需要保存或更新的数据
     * @param string $key 条件主键限制
     * @param array $where 其它的where条件
     * @return boolean|integer
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function dataSave($dbQuery, $data, $key = 'id', $where = [])
    {
        $db = is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery;
        list($table, $value) = [$db->getTable(), isset($data[$key]) ? $data[$key] : null];
        $map = isset($where[$key]) ? [] : (is_string($value) ? [[$key, 'in', explode(',', $value)]] : [$key => $value]);
        if (is_array($info = Db::table($table)->master()->where($where)->where($map)->find()) && !empty($info)) {
            if (Db::table($table)->strict(false)->where($where)->where($map)->update($data) !== false) {
                return isset($info[$key]) ? $info[$key] : true;
            } else {
                return false;
            }
        } else {
            return Db::table($table)->strict(false)->insertGetId($data);
        }
    }

    /**
     * 批量更新数据
     * @param Query|string $dbQuery 数据查询对象
     * @param array $data 需要更新的数据(二维数组)
     * @param string $key 条件主键限制
     * @param array $where 其它的where条件
     * @return boolean
     * @throws \think\db\exception\DbException
     */
    public static function batchDataSave($dbQuery, $data, $key = 'id', $where = [])
    {
        list($case, $input) = [[], []];
        foreach ($data as $row) foreach ($row as $key => $value) {
            $case[$key][] = "WHEN '{$row[$key]}' THEN '{$value}'";
        }
        if (isset($case[$key])) unset($case[$key]);
        $db = is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery;
        foreach ($case as $key => $value) $input[$key] = $db->raw("CASE `{$key}` " . join(' ', $value) . ' END');
        return $db->whereIn($key, array_unique(array_column($data, $key)))->where($where)->update($input) !== false;
    }

    /**
     * 一维数据数组生成数据树
     * @param array $list 数据列表
     * @param string $id 父ID Key
     * @param string $pid ID Key
     * @param string $son 定义子数据Key
     * @return array
     */
    public static function arr2tree($list, $id = 'id', $pid = 'pid', $son = 'sub')
    {
        list($tree, $map) = [[], []];
        foreach ($list as $item) $map[$item[$id]] = $item;
        foreach ($list as $item) if (isset($item[$pid]) && isset($map[$item[$pid]])) {
            $map[$item[$pid]][$son][] = &$map[$item[$id]];
        } else $tree[] = &$map[$item[$id]];
        unset($map);
        return $tree;
    }

    /**
     * 一维数据数组生成数据树
     * @param array $list 数据列表
     * @param string $id ID Key
     * @param string $pid 父ID Key
     * @param string $path
     * @param string $ppath
     * @return array
     */
    public static function arr2table(array $list, $id = 'id', $pid = 'pid', $path = 'path', $ppath = '')
    {
        $tree = [];
        foreach (self::arr2tree($list, $id, $pid) as $attr) {
            $attr[$path] = "{$ppath}-{$attr[$id]}";
            $attr['sub'] = isset($attr['sub']) ? $attr['sub'] : [];
            $attr['spt'] = substr_count($ppath, '-');
            $attr['spl'] = str_repeat("　├　", $attr['spt']);
            $sub = $attr['sub'];
            unset($attr['sub']);
            $tree[] = $attr;
            if (!empty($sub)) $tree = array_merge($tree, self::arr2table($sub, $id, $pid, $path, $attr[$path]));
        }
        return $tree;
    }

    /**
     * 获取数据树子ID
     * @param array $list 数据列表
     * @param int $id 起始ID
     * @param string $key 子Key
     * @param string $pkey 父Key
     * @return array
     */
    public static function getArrSubIds($list, $id = 0, $key = 'id', $pkey = 'pid')
    {
        $ids = [intval($id)];
        foreach ($list as $vo) if (intval($vo[$pkey]) > 0 && intval($vo[$pkey]) === intval($id)) {
            $ids = array_merge($ids, self::getArrSubIds($list, intval($vo[$key]), $key, $pkey));
        }
        return $ids;
    }

    /**
     * 获取随机字符串编码
     * @param integer $length 字符串长度
     * @param integer $type 字符串类型(1纯数字,2纯字母,3数字字母)
     * @return string
     */
    public static function randomCode($length = 10, $type = 1)
    {
        $numbs = '0123456789';
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        if (intval($type) === 1) $chars = $numbs;
        if (intval($type) === 2) $chars = "a{$chars}";
        if (intval($type) === 3) $chars = "{$numbs}{$chars}";
        $string = $chars[rand(1, strlen($chars) - 1)];
        if (isset($chars)) while (strlen($string) < $length) {
            $string .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $string;
    }

    /**
     * 唯一数字编码
     * @param integer $length
     * @return string
     */
    public static function uniqidNumberCode($length = 10)
    {
        $time = time() . '';
        if ($length < 10) $length = 10;
        $string = ($time[0] + $time[1]) . substr($time, 2) . rand(0, 9);
        while (strlen($string) < $length) $string .= rand(0, 9);
        return $string;
    }

    /**
     * 唯一日期编码
     * @param integer $length
     * @return string
     */
    public static function uniqidDateCode($length = 14)
    {
        if ($length < 14) $length = 14;
        $string = date('Ymd') . (date('H') + date('i')) . date('s');
        while (strlen($string) < $length) $string .= rand(0, 9);
        return $string;
    }

}