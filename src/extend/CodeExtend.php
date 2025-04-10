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
 * 随机数码管理扩展
 * @class CodeExtend
 * @package think\admin\extend
 */
class CodeExtend
{

    /**
     * 生成 UUID 编码
     * @return string
     */
    public static function uuid(): string
    {
        $chars = md5(uniqid(strval(mt_rand(0, 9999)), true));
        $value = substr($chars, 0, 8) . '-' . substr($chars, 8, 4) . '-';
        $value .= substr($chars, 12, 4) . '-' . substr($chars, 16, 4) . '-';
        return strtoupper($value . substr($chars, 20, 12));
    }

    /**
     * 生成随机编码
     * @param integer $size 编码长度
     * @param integer $type 编码类型(1纯数字,2纯字母,3数字字母)
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function random(int $size = 10, int $type = 1, string $prefix = ''): string
    {
        $numbs = '0123456789';
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        if ($type === 1) $chars = $numbs;
        if ($type === 3) $chars = "{$numbs}{$chars}";
        $code = $prefix . $chars[rand(1, strlen($chars) - 1)];
        while (strlen($code) < $size) $code .= $chars[rand(0, strlen($chars) - 1)];
        return $code;
    }

    /**
     * 生成日期编码
     * @param integer $size 编码长度
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function uniqidDate(int $size = 16, string $prefix = ''): string
    {
        if ($size < 14) $size = 14;
        $code = $prefix . date('Ymd') . (date('H') + date('i')) . date('s');
        while (strlen($code) < $size) $code .= rand(0, 9);
        return $code;
    }

    /**
     * 生成数字编码
     * @param integer $size 编码长度
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function uniqidNumber(int $size = 12, string $prefix = ''): string
    {
        $time = strval(time());
        if ($size < 10) $size = 10;
        $code = $prefix . (intval($time[0]) + intval($time[1])) . substr($time, 2) . rand(0, 9);
        while (strlen($code) < $size) $code .= rand(0, 9);
        return $code;
    }

    /**
     * 文本转码
     * @param string $text 文本内容
     * @param string $target 目标编码
     * @return string
     */
    public static function text2utf8(string $text, string $target = 'UTF-8'): string
    {
        [$first2, $first4] = [substr($text, 0, 2), substr($text, 0, 4)];
        if ($first4 === chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF)) $ft = 'UTF-32BE';
        elseif ($first4 === chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00)) $ft = 'UTF-32LE';
        elseif ($first2 === chr(0xFE) . chr(0xFF)) $ft = 'UTF-16BE';
        elseif ($first2 === chr(0xFF) . chr(0xFE)) $ft = 'UTF-16LE';
        return mb_convert_encoding($text, $target, $ft ?? mb_detect_encoding($text));
    }

    /**
     * 数据加密处理
     * @param mixed $data 加密数据
     * @param string $skey 安全密钥
     * @return string
     */
    public static function encrypt($data, string $skey): string
    {
        $iv = static::random(16, 3);
        $value = openssl_encrypt(serialize($data), 'AES-256-CBC', $skey, 0, $iv);
        return static::enSafe64(json_encode(['iv' => $iv, 'value' => $value]));
    }

    /**
     * 数据解密处理
     * @param string $data 解密数据
     * @param string $skey 安全密钥
     * @return mixed
     */
    public static function decrypt(string $data, string $skey)
    {
        $attr = json_decode(static::deSafe64($data), true);
        return unserialize(openssl_decrypt($attr['value'], 'AES-256-CBC', $skey, 0, $attr['iv']));
    }

    /**
     * Base64Url 安全编码
     * @param string $text 待加密文本
     * @return string
     */
    public static function enSafe64(string $text): string
    {
        return rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
    }

    /**
     * Base64Url 安全解码
     * @param string $text 待解密文本
     * @return string
     */
    public static function deSafe64(string $text): string
    {
        return base64_decode(str_pad(strtr($text, '-_', '+/'), (int) (ceil(strlen($text) / 4) * 4), '='));
    }

    /**
     * 压缩数据对象
     * @param mixed $data
     * @return string
     */
    public static function enzip($data): string
    {
        return static::enSafe64(gzcompress(serialize($data)));
    }

    /**
     * 解压数据对象
     * @param string $string
     * @return mixed
     */
    public static function dezip(string $string)
    {
        return unserialize(gzuncompress(static::deSafe64($string)));
    }
}