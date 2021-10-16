<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2021 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkLibrary
// | github 代码仓库：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\service;

use think\admin\Exception;
use think\admin\extend\HttpExtend;
use think\admin\helper\ValidateHelper;
use think\admin\Service;
use think\App;
use think\exception\HttpResponseException;

/**
 * 通用接口基础服务
 * Class InterfaceService
 * @package think\admin\service
 */
class InterfaceService extends Service
{
    /**
     * 接口认证账号
     * @var string
     */
    private $appid;

    /**
     * 接口认证密钥
     * @var string
     */
    private $appkey;

    /**
     * 接口请求地址
     * @var string
     */
    private $getway;

    /**
     * 接口服务初始化
     * InterfaceService constructor.
     * @param App $app
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->appid = sysconf('data.interface_appid') ?: '';
        $this->appkey = sysconf('data.interface_appkey') ?: '';
        $this->getway = sysconf('data.interface_getway') ?: '';
    }

    /**
     * 设置接口网关
     * @param string $getway 接口网关
     * @return $this
     */
    public function getway(string $getway): InterfaceService
    {
        $this->getway = $getway;
        return $this;
    }

    /**
     * 设置授权账号
     * @param string $appid 接口账号
     * @param string $appkey 接口密钥
     * @return $this
     */
    public function setAuth(string $appid, string $appkey): InterfaceService
    {
        $this->appid = $appid;
        $this->appkey = $appkey;
        return $this;
    }

    /**
     * 获取请求参数
     * @return array
     */
    public function getData(): array
    {
        // 基础参数获取
        $input = ValidateHelper::instance()->init([
            'time.require'  => lang('think_library_params_failed_empty', ['time']),
            'sign.require'  => lang('think_library_params_failed_empty', ['sign']),
            'data.require'  => lang('think_library_params_failed_empty', ['data']),
            'appid.require' => lang('think_library_params_failed_empty', ['appid']),
            'nostr.require' => lang('think_library_params_failed_empty', ['nostr']),
        ], 'post', [$this, 'baseError']);

        // 检查请求签名，使用通用签名方式
        $build = $this->signString($input['data'], $input['time'], $input['nostr']);
        if ($build['sign'] !== $input['sign']) {
            $this->baseError(lang('think_library_params_failed_sign'));
        }

        // 检查请求时间，与服务差不能超过 30 秒
        if (abs($input['time'] / 1000 - time()) > 30) {
            $this->baseError(lang('think_library_params_failed_time'));
        }

        // 返回并解析数据内容，如果解析失败返回空数组
        return json_decode($input['data'], true) ?: [];
    }

    /**
     * 获取对象参数
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->$name ?? null;
    }

    /**
     * 回复业务处理失败的消息
     * @param mixed $info 消息内容
     * @param mixed $data 返回数据
     * @param mixed $code 返回状态码
     */
    public function error($info, $data = '{-null-}', $code = 0): void
    {
        if ($data === '{-null-}') $data = new \stdClass();
        $this->baseResponse(lang('think_library_response_failed'), [
            'code' => $code, 'info' => $info, 'data' => $data,
        ]);
    }

    /**
     * 回复业务处理成功的消息
     * @param mixed $info 消息内容
     * @param mixed $data 返回数据
     * @param mixed $code 返回状态码
     */
    public function success($info, $data = '{-null-}', $code = 1): void
    {
        if ($data === '{-null-}') $data = new \stdClass();
        $this->baseResponse(lang('think_library_response_success'), [
            'code' => $code, 'info' => $info, 'data' => $data,
        ]);
    }

    /**
     * 回复根失败消息
     * @param mixed $info 消息内容
     * @param mixed $data 返回数据
     * @param mixed $code 根状态码
     */
    public function baseError($info, $data = [], $code = 0): void
    {
        $this->baseResponse($info, $data, $code);
    }

    /**
     * 回复根成功消息
     * @param mixed $info 消息内容
     * @param mixed $data 返回数据
     * @param mixed $code 根状态码
     */
    public function baseSuccess($info, $data = [], $code = 1): void
    {
        $this->baseResponse($info, $data, $code);
    }

    /**
     * 回复根签名消息
     * @param mixed $info 消息内容
     * @param mixed $data 返回数据
     * @param mixed $code 根状态码
     */
    public function baseResponse($info, $data = [], $code = 1): void
    {
        $array = $this->signData($data);
        throw new HttpResponseException(json([
            'code'  => $code,
            'info'  => $info,
            'time'  => $array['time'],
            'sign'  => $array['sign'],
            'appid' => $array['appid'],
            'nostr' => $array['nostr'],
            'data'  => $array['data'],
        ]));
    }

    /**
     * 接口数据模拟请求
     * @param string $uri 接口地址
     * @param array $data 请求数据
     * @param boolean $check 验证结果
     * @return array
     * @throws Exception
     */
    public function doRequest(string $uri, array $data = [], bool $check = true): array
    {
        $url = rtrim($this->getway, '/') . '/' . ltrim($uri, '/');
        $content = HttpExtend::post($url, $this->signData($data)) ?: '';
        // 返回结果内容验证
        if (!($result = json_decode($content, true)) || empty($result)) {
            throw new Exception("请求返回异常，原因：{$content}");
        }
        // 返回结果错误验证
        if (empty($result['code'])) {
            throw new Exception("接口请求错误，原因：{$result['info']}");
        }
        // 无需进行数据签名
        if (empty($check)) return json_decode($result['data'] ?? '{}', true);
        // 返回结果签名验证
        $build = $this->signString($result['data'], $result['time'], $result['nostr']);
        if ($build['sign'] === $result['sign']) {
            return json_decode($result['data'] ?? '{}', true);
        } else {
            throw new Exception('返回结果签名验证失败！');
        }
    }

    /**
     * 接口响应数据签名
     * @param array $data ['appid','nostr','time','sign','data']
     * @return array
     */
    private function signData(array $data): array
    {
        return $this->signString(json_encode($data, 256));
    }

    /**
     * 数据字符串数据签名
     * @param string $json
     * @param mixed $time
     * @param mixed $rand
     * @return array
     */
    private function signString(string $json, $time = null, $rand = null): array
    {
        $rand = $rand ?: md5(uniqid('', true));
        $time = $time ?: intval(microtime(true) * 1000) . '';
        $params = join('#', [$this->appid, md5($json), $time, $rand, $this->appkey]);
        return ['appid' => $this->appid, 'nostr' => $rand, 'time' => $time, 'sign' => md5($params), 'data' => $json];
    }
}