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

use think\admin\Controller;
use think\admin\Exception;
use think\admin\Library;

/**
 * 接口 JWT 接口扩展
 * @class JwtExtend
 * @package think\admin\extend
 * @method static bool isRejwt() 是否输出令牌
 * @method static array getInData() 获取输入数据
 */
class JwtExtend
{
    // 头部参数
    private const header = ['typ' => 'JWT', 'alg' => 'HS256'];

    // 签名配置
    private const signTypes = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512'
    ];

    /**
     * 是否返回令牌
     * @var boolean
     */
    private static $rejwt = false;

    /**
     * 当前请求数据
     * @var array
     */
    private static $input = [];

    /**
     * 获取原会话标签
     * @var string
     */
    public static $sessionId = '';

    /**
     * 生成 jwt token
     * @param array $data jwt 载荷 格式如下非必须
     * {
     * "iss": "http://example.org", // 签发者（Issuer），JWT的签发者
     * "sub": "1234567890", // 主题（Subject），JWT所面向的用户
     * "aud": "http://example.com", // 受众（Audience），接收JWT的一方
     * "exp": 1625174400, // 过期时间（Expiration time），JWT的过期时间戳
     * "iat": 1625138400, // 签发时间（Issued at），JWT的签发时间戳
     * "nbf": 1625138400, // 生效时间（Not Before），JWT的生效时间戳
     * "...": ... // 其他扩展内容
     * }
     * @param ?string $jwtkey 签名密钥
     * @param ?boolean $rejwt 输出令牌
     * @return string
     */
    public static function token(array $data = [], ?string $jwtkey = null, ?bool $rejwt = null): string
    {
        $jwtkey = self::jwtkey($jwtkey);
        if (is_bool($rejwt)) self::$rejwt = $rejwt;

        // JWT 载荷数据组装
        [$fields, $payload] = [['iss', 'sub', 'aud', 'exp', 'iat', 'nbf'], ['iat' => time()]];
        foreach ($data as $k => $v) if (in_array($k, $fields)) {
            $payload[$k] = $v;
            unset($data[$k]);
        }

        // 自定义需要的数据
        $data['.ssid'] = self::withSess();
        if (empty($data['.ssid'])) unset($data['.ssid']);
        $payload['enc'] = CodeExtend::encrypt(json_encode($data, JSON_UNESCAPED_UNICODE), $jwtkey);

        // 组装 JWT 内容格式
        $two = CodeExtend::enSafe64(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $one = CodeExtend::enSafe64(json_encode(self::header, JSON_UNESCAPED_UNICODE));
        return "{$one}.{$two}." . self::withSign("{$one}.{$two}", self::header['alg'], $jwtkey);
    }

    /**
     * 验证 token 是否有效, 默认验证 exp,nbf,iat 时间
     * @param string $token 加密数据
     * @param ?string $jwtkey 签名密钥
     * @return array
     * @throws \think\admin\Exception
     */
    public static function verify(string $token, ?string $jwtkey = null): array
    {
        $tokens = explode('.', $token);
        if (count($tokens) != 3) throw new Exception('数据解密失败！', 0, []);

        [$base64header, $base64payload, $signature] = $tokens;

        // 加密算法
        $header = json_decode(CodeExtend::deSafe64($base64header), true);
        if (empty($header['alg'])) throw new Exception('数据解密失败！', 0, []);

        // 签名验证
        $jwtkey = self::jwtkey($jwtkey);
        if (self::withSign("{$base64header}.{$base64payload}", $header['alg'], $jwtkey) !== $signature) {
            throw new Exception('验证签名失败！', 0, []);
        }

        // 获取 Playload 数据
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

        // 返回自定义数据字段
        if (isset($payload['enc'])) {
            $extra = json_decode(CodeExtend::decrypt($payload['enc'], $jwtkey), true);
            if (!empty($extra['.ssid'])) self::$sessionId = $extra['.ssid'];
            unset($payload['enc'], $extra['.ssid']);
        }

        return self::$input = array_merge($payload, $extra ?? []);
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
            $jwtkey = sysconf('data.jwtkey|raw');
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
     * 获取原会话标识
     * @return string
     */
    private static function withSess(): string
    {
        if (!isset(Library::$sapp->session)) return self::$sessionId = '';
        return self::$sessionId = Library::$sapp->session->getId();
    }


    /**
     * 生成数据签名
     * @param string $input 为 base64UrlEncode(header).".".base64UrlEncode(payload)
     * @param string $alg 算法方式
     * @param ?string $key 签名密钥
     * @return string
     */
    private static function withSign(string $input, string $alg = 'HS256', ?string $key = null): string
    {
        return CodeExtend::enSafe64(hash_hmac(self::signTypes[$alg], $input, self::jwtkey($key), true));
    }

    /**
     * 兼容历史方法
     * @param string $method
     * @param array $arguments
     * @return array|string
     * @throws \think\admin\Exception
     */
    public static function __callStatic(string $method, array $arguments)
    {
        switch ($method) {
            case 'isRejwt': // 是否返回令牌
                return self::$rejwt;
            case 'getInData':  // 获取请求数据
                return self::$input;
            case 'getToken': // 生成接口令牌
                return self::token(...$arguments);
            case 'verifyToken': // 验证接口令牌
                return self::verify(...$arguments);
            default:
                throw new Exception("method not exists: JwtExtend::{$method}()");
        }
    }
}