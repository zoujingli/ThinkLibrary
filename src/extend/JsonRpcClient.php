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

declare (strict_types=1);

namespace think\admin\extend;

use think\admin\Exception;

/**
 * JsonRpc 客户端
 * Class JsonRpcClient
 * @package think\admin\extend
 */
class JsonRpcClient
{
    /**
     * 请求ID
     * @var integer
     */
    private $id;

    /**
     * 服务端地址
     * @var string
     */
    private $proxy;

    /**
     * 请求头部参数
     * @var string
     */
    private $header;

    /**
     * JsonRpcClient constructor.
     * @param string $proxy
     * @param array $header
     */
    public function __construct(string $proxy, array $header = [])
    {
        $this->id = time();
        $this->proxy = $proxy;
        $this->header = $header;
    }

    /**
     * 执行 JsonRpc 请求
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws \think\admin\Exception
     */
    public function __call(string $method, array $params = [])
    {
        $options = [
            'ssl'  => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'method'  => 'POST',
                'header'  => join("\r\n", array_merge(['Content-type:application/json'], $this->header)),
                'content' => json_encode(['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => $this->id], JSON_UNESCAPED_UNICODE),
            ],
        ];
        // Performs the HTTP POST
        if ($fp = fopen($this->proxy, 'r', false, stream_context_create($options))) {
            $response = '';
            while ($line = fgets($fp)) $response .= trim($line) . "\n";
            [, $response] = [fclose($fp), json_decode($response, true)];
        } else {
            throw new Exception(lang("无法连接到 %s", [$this->proxy]));
        }
        // Compatible with normal
        if (isset($response['code']) && isset($response['info'])) {
            throw new Exception($response['info'], $response['code'], $response['data'] ?? []);
        }
        // Final checks and return
        if (empty($response['id']) || $response['id'] != $this->id) {
            throw new Exception(lang("错误标记 ( 请求标记: %s, 响应标记: %s )", [$this->id, $response['id'] ?? '- ']), 0, $response);
        }
        if (is_null($response['error'])) return $response['result'];
        throw new Exception($response['error']['message'], $response['error']['code'], $response['result']);
    }
}