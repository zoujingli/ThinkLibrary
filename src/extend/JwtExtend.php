<?php
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
     * @throws \think\admin\Exception
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
     * @return boolean|array
     * @throws \think\admin\Exception
     */
    public static function verifyToken(string $token, ?string $jwtkey = null)
    {
        $tokens = explode('.', $token);
        if (count($tokens) != 3) return false;

        [$base64header, $base64payload, $signature] = $tokens;

        // 获取 jwt 算法
        $header = json_decode(CodeExtend::deSafe64($base64header), true);
        if (empty($header['alg'])) return false;

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

        return $payload;
    }

    /**
     * 生成 JWT 签名
     * @param string $input 为 base64UrlEncode(header).".".base64UrlEncode(payload)
     * @param ?string $key 签名密钥
     * @param string $alg 算法方式
     * @return string
     * @throws \think\admin\Exception
     */
    private static function _sign(string $input, ?string $key = null, string $alg = 'HS256'): string
    {
        return CodeExtend::enSafe64(hash_hmac(static::$signTypes[$alg], $input, static::jwtkey($key), true));
    }

    /**
     * 获取 JWT 密钥
     * @param null|string $jwtkey
     * @return string
     * @throws \think\admin\Exception
     */
    public static function jwtkey(?string $jwtkey = null): string
    {
        try {
            return is_null($jwtkey) ? (config('app.jwtkey') ?: (sysconf('data.jwtkey') ?: 'thinkadmin')) : $jwtkey;
        } catch (\Exception $exception) {
            trace_file($exception);
            throw new Exception($exception->getMessage());
        }
    }
}