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

declare(strict_types=1);

namespace think\admin\extend;

use think\admin\Exception;
use think\admin\Library;

/**
 * 接口 JWT 接口扩展
 * Class JwtExtend
 * @package think\admin\extend
 */
class JwtExtend
{
    /**
     * 头部参数
     * @var string[]
     */
    private static $header = [
        'typ' => 'JWT', // 类型
        'alg' => 'HS256', // 算法
    ];

    /**
     * 签名配置
     * @var string[]
     */
    private static $signTypes = [
        'HS256' => 'sha256',
    ];

    /**
     * 当前请求数据
     * @var array
     */
    public static $jwtPayload = [];

    /**
     * 当前请求状态
     * @var bool
     */
    public static $isJwtRequest = false;

    /**
     * 获取 jwt token
     * @param array $payload jwt 载荷 格式如下非必须
     * [
     *     'iss' => 'jwt_admin',               // 该JWT的签发者
     *     'iat' => time(),                    // 签发时间
     *     'exp' => time() + 7200,             // 过期时间
     *     'nbf' => time() + 60,               // 该时间之前不接收处理该Token
     *     'sub' => '',                        // 面向的用户
     *     'jti' => md5(uniqid('JWT').time())  // 该 Token 唯一标识
     * ]
     * @param null|string $jwtkey
     * @return string
     */
    public static function getToken(array $payload, ?string $jwtkey = null): string
    {
        $payload['sub'] = CodeExtend::encrypt(Library::$sapp->session->getId(), static::jwtkey());
        $base64header = CodeExtend::enSafe64(json_encode(static::$header, JSON_UNESCAPED_UNICODE));
        $base64payload = CodeExtend::enSafe64(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = static::_sign($base64header . '.' . $base64payload, $jwtkey, static::$header['alg']);
        return $base64header . '.' . $base64payload . '.' . $signature;
    }

    /**
     * 验证 token 是否有效, 默认验证 exp,nbf,iat 时间
     * @param string $token 加密数据
     * @param ?string $jwtkey 签名密钥
     * @return array
     * @throws \think\admin\Exception
     */
    public static function verifyToken(string $token, ?string $jwtkey = null): array
    {
        $tokens = explode('.', $token);
        if (count($tokens) != 3) throw new Exception('数据解密失败！', []);

        [$base64header, $base64payload, $signature] = $tokens;

        // 获取 jwt 算法
        $header = json_decode(CodeExtend::deSafe64($base64header), true);
        if (empty($header['alg'])) throw new Exception('数据解密失败！', []);

        // 签名验证
        if (self::_sign("{$base64header}.{$base64payload}", static::jwtkey($jwtkey), $header['alg']) !== $signature) {
            throw new Exception('验证签名失败！', []);
        }

        $payload = json_decode(CodeExtend::deSafe64($base64payload), true);

        // 签发时间大于当前服务器时间验证失败
        if (isset($payload['iat']) && $payload['iat'] > time()) {
            throw new Exception('服务器时间验证失败！', $payload);
        }

        // 过期时间小宇当前服务器时间验证失败
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('服务器时间验证失败！', $payload);
        }

        // 该 nbf 时间之前不接收处理该 TOKEN
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            throw new Exception('不接收处理该TOKEN', $payload);
        }

        static::$isJwtRequest = true;
        return static::$jwtPayload = $payload;
    }

    /**
     * 获取 JWT 密钥
     * @param ?string $jwtkey
     * @return string
     */
    public static function jwtkey(?string $jwtkey = null): string
    {
        try {
            if (!empty($jwtkey)) return $jwtkey;

            // 优先读取配置文件
            $jwtkey = config('app.jwtkey');
            if (!empty($jwtkey)) return $jwtkey;

            // 再次读取数据配置
            $jwtkey = sysconf('data.jwtkey');
            if (!empty($jwtkey)) return $jwtkey;

            // 自动生成新的密钥
            $jwtkey = md5(uniqid(strval(rand(1000, 9999)), true));
            sysconf('data.jwtkey', $jwtkey);
            return $jwtkey;

        } catch (\Exception $exception) {
            trace_file($exception);
            return 'thinkadmin';
        }
    }

    /**
     * 生成 JWT 签名
     * @param string $input 为 base64UrlEncode(header).".".base64UrlEncode(payload)
     * @param ?string $key 签名密钥
     * @param string $alg 算法方式
     * @return string
     */
    private static function _sign(string $input, ?string $key = null, string $alg = 'HS256'): string
    {
        return CodeExtend::enSafe64(hash_hmac(static::$signTypes[$alg], $input, static::jwtkey($key), true));
    }
}