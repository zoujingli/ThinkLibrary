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

use think\admin\Exception;

/**
 * JsonRpc 客户端
 * @class JsonRpcClient
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
                'method'  => 'POST', "timeout" => 60,
                'header'  => join("\r\n", array_merge(['Content-Type:application/json'], $this->header, ['User-Agent:think-admin-jsonrpc', ''])),
                'content' => json_encode(['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => $this->id], JSON_UNESCAPED_UNICODE),
            ],
        ];
        try {
            // Performs the HTTP POST
            if ($fp = fopen($this->proxy, 'r', false, stream_context_create($options))) {
                $response = '';
                while ($line = fgets($fp)) $response .= trim($line) . "\n";
                [, $response] = [fclose($fp), json_decode($response, true)];
            } else {
                throw new Exception(lang("Unable connect: %s", [$this->proxy]));
            }
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        }
        // Compatible with normal
        if (isset($response['code']) && isset($response['info'])) {
            throw new Exception($response['info'], intval($response['code']), $response['data'] ?? []);
        }
        // Final checks and return
        if (empty($response['id']) || $response['id'] != $this->id) {
            throw new Exception(lang("Error flag ( Request tag: %s, Response tag: %s )", [$this->id, $response['id'] ?? '-']), 0, $response);
        }
        if (is_null($response['error'])) return $response['result'];
        throw new Exception($response['error']['message'], intval($response['error']['code']), $response['result'] ?? []);
    }
}