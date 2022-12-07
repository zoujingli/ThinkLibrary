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

use think\admin\Controller;
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
     * 标识字段
     * @var string
     */
    private static $skey = '__ISJWT_SESS__';

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
     * 当前请求状态
     * @var boolean
     */
    public static $isJwt = false;

    /**
     * 当前请求数据
     * @var array
     */
    private static $inData = [];

    /**
     * 当前输出数据
     * @var array
     */
    private static $outData = [];

    /**
     * 是否输出令牌
     * @var boolean
     */
    private static $outToken = false;

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
     * @param null|string $jwtkey 签名密钥
     * @param null|boolean $outToken 输出令牌
     * @return string
     */
    public static function getToken(array $payload, ?string $jwtkey = null, ?bool $outToken = null): string
    {
        is_bool($outToken) && static::$outToken = $outToken;
        $payload['sub'] = CodeExtend::encrypt(static::setJwtSession(), static::jwtkey());
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
        if (count($tokens) != 3) throw new Exception('数据解密失败！', 0, []);

        [$base64header, $base64payload, $signature] = $tokens;

        // 加密算法
        $header = json_decode(CodeExtend::deSafe64($base64header), true);
        if (empty($header['alg'])) throw new Exception('数据解密失败！', 0, []);

        // 签名验证
        if (self::_sign("{$base64header}.{$base64payload}", static::jwtkey($jwtkey), $header['alg']) !== $signature) {
            throw new Exception('验证签名失败！', 0, []);
        }

        // 获取数据
        $payload = json_decode(CodeExtend::deSafe64($base64payload), true);

        // 签发时间大于当前服务器时间验证失败
        if (isset($payload['iat']) && $payload['iat'] > time()) {
            throw new Exception('服务器时间验证失败！', 0, $payload);
        }

        // 过期时间小于当前服务器时间验证失败
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('服务器时间验证失败！', 0, $payload);
        }

        // 该 nbf 时间之前不接收处理该 TOKEN
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            throw new Exception('不接收处理该TOKEN', 0, $payload);
        }

        static::$isJwt = true;
        return static::$inData = $payload;
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
     * 输出模板变量
     * @param \think\admin\Controller $class
     * @param array $vars
     * @return void
     */
    public static function fetch(Controller $class, array $vars = [])
    {
        $ignore = array_keys(get_class_vars(Controller::class));
        foreach ($class as $name => $value) if (!in_array($name, $ignore)) {
            if (is_array($value) || is_numeric($value) || is_string($value) || is_bool($value) || is_null($value)) {
                $vars[$name] = $value;
            }
        }
        $class->success('获取变量成功！', $vars);
    }

    /**
     * 获取当前请求的数据
     * @return array
     */
    public static function getInData(): array
    {
        return static::$inData;
    }

    /**
     * 获取需要输出的数据
     * @return array
     */
    public static function getOutData(): array
    {
        return static::$outData;
    }

    /**
     * 设置需要输出的数据
     * @param array $data
     * @return array
     */
    public static function setOutData(array $data = []): array
    {
        static::$outToken = true;
        return static::$outData = $data;
    }

    /**
     * 获取是否输出令牌
     * @return boolean
     */
    public static function getOutToken(): bool
    {
        return static::$outToken;
    }

    /**
     * 设置是否输出令牌
     * @param boolean $output
     * @return boolean
     */
    public static function setOutToken(bool $output = true): bool
    {
        return static::$outToken = $output;
    }

    /**
     * 升级 Jwt 会话模式
     * @return string
     */
    public static function setJwtSession(): string
    {
        if (!Library::$sapp->session->get(static::$skey)) {
            Library::$sapp->session->save(); // 保存原会话数据
            Library::$sapp->session->regenerate(); // 切换新会话编号
            Library::$sapp->session->set(static::$skey, true);
        }
        return Library::$sapp->session->getId();
    }

    /**
     * 判断 Jwt 会话模式
     * @return bool
     */
    public static function isJwtSession(): bool
    {
        return boolval(Library::$sapp->session->get(static::$skey));
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