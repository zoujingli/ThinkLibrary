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

declare(strict_types=1);

namespace think\admin\storage;

use think\admin\contract\StorageInterface;
use think\admin\contract\StorageUsageTrait;
use think\admin\Exception;
use think\admin\extend\HttpExtend;
use think\admin\Storage;

/**
 * Minio对象存储支持
 * @class MinioStorage
 * @package think\admin\storage
 */
class MinioStorage implements StorageInterface
{
    use StorageUsageTrait;

    /**
     * 存储点域名
     * @var string
     */
    private $endpoint;

    /**
     * 存储空间名称
     * @var string
     */
    private $bucket;

    /**
     * AccessKey
     * @var string
     */
    private $accessKey;

    private $region;

    /**
     * SecretKey
     * @var string
     */
    private $secretKey;

    /**
     * 初始化入口
     * @throws \think\admin\Exception
     */
    protected function init()
    {
        $this->endpoint = sysconf('storage.minio_http_domain|raw');
        $type = strtolower(sysconf('storage.minio_http_protocol|raw'));
        $this->bucket = sysconf('storage.minio_bucket|raw');
        $this->accessKey = sysconf('storage.minio_access_key|raw');
        $this->secretKey = sysconf('storage.minio_secret_key|raw');
        $this->region = sysconf('storage.minio_region|raw');

        if (!empty($this->endpoint) && $type === 'auto') {
            $this->domain = "//{$this->endpoint}";
        } elseif (!empty($this->endpoint) && in_array($type, ['http', 'https'])) {
            $this->domain = "{$type}://{$this->endpoint}";
        } else {
            throw new Exception(lang('未配置Minio域名'));
        }
    }

    /**
     * 上传文件内容
     * @param string $name 文件名称
     * @param string $file 文件内容
     * @param boolean $safe 安全模式
     * @param ?string $attname 下载名称
     * @return array
     * @throws Exception
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null): array
    {
        $token = $this->token($name);
        $data = [
            'key' => $name,
            'policy' => $token['policy'],
            'x-amz-algorithm' => $token['x-amz-algorithm'],
            'x-amz-credential' => $token['x-amz-credential'],
            'x-amz-date' => $token['x-amz-date'],
            'x-amz-signature' => $token['x-amz-signature'],
            'success_action_status' => '200'
        ];

        if (is_string($attname) && strlen($attname) > 0) {
            $data['Content-Disposition'] = 'inline;filename=' . urlencode($attname);
        }

        $uri = "{$this->domain}/{$this->bucket}";
        $file = ['field' => 'file', 'name' => $name, 'content' => $file];
        if (is_numeric(stripos(HttpExtend::submit($uri, $data, $file), '200 OK'))) {
            return ['file' => $this->path($name, $safe), 'url' => $this->url($name, $safe, $attname), 'key' => $name];
        } else {
            return [];
        }
    }

    /**
     * 获取上传令牌
     * @param string $name 文件名称
     * @param integer $expires 有效时间
     * @param ?string $attname 下载名称
     * @return array
     */
    public function token(string $name, int $expires = 3600, ?string $attname = null): array
    {
        $date = gmdate('Ymd\THis\Z');
        $shortDate = substr($date, 0, 8);
        $region = $this->region;
        $service = 's3';
        $algorithm = 'AWS4-HMAC-SHA256';

        $credentialScope = "$shortDate/$region/$service/aws4_request";
        $policy = [
            'expiration' => gmdate('Y-m-d\TH:i:s\Z', time() + $expires),
            'conditions' => [
                ['bucket' => $this->bucket],
                ['key' => $name],
                ['x-amz-algorithm' => $algorithm],
                ['x-amz-credential' => "{$this->accessKey}/$credentialScope"],
                ['x-amz-date' => $date],
                ['content-length-range', 0, 1048576000],
            ],
        ];

        $policyBase64 = base64_encode(json_encode($policy));
        $signingKey = $this->_getSignatureKey($shortDate);
        $signature = hash_hmac('sha256', $policyBase64, $signingKey);

        return [
            'policy' => $policyBase64,
            'x-amz-algorithm' => $algorithm,
            'x-amz-credential' => "{$this->accessKey}/$credentialScope",
            'x-amz-date' => $date,
            'x-amz-signature' => $signature,
            'siteurl' => $this->url($name, false, $attname),
        ];
    }

    /**
     * 获取访问地址
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param ?string $attname 下载名称
     * @return string
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        return "{$this->domain}/{$this->bucket}/{$this->delSuffix($name)}{$this->getSuffix($attname,$name)}";
    }

    /**
     * 获取存储路径
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function path(string $name, bool $safe = false): string
    {
        return $this->url($name, $safe);
    }

    /**
     * 获取上传地址
     * @return string
     */
    public function upload(): string
    {
        return $this->domain . '/' . $this->bucket;
    }

    /**
     * 获取签名密钥
     * @param string $shortDate 短日期
     * @return string
     */
    private function _getSignatureKey(string $shortDate): string
    {
        $dateKey = hash_hmac('sha256', $shortDate, "AWS4{$this->secretKey}", true);
        $regionKey = hash_hmac('sha256', $this->region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    }

    /**
     * 读取文件内容
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function get(string $name, bool $safe = false): string
    {
        return Storage::curlGet($this->url($name, $safe));
    }

    /**
     * 删除存储文件
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return bool
     */
    public function del(string $name, bool $safe = false): bool
    {
        $url = $this->url($name, $safe);
        $headers = HttpExtend::request('DELETE', $url, ['returnHeader' => true]);
        return strpos($headers, '204 No Content') !== false;
    }

    /**
     * 请求数据签名
     * @param string $method 请求方式
     * @param string $source 资源名称
     * @return array
     */
    private function _sign(string $method, string $source): array
    {
        $date = gmdate('Ymd\THis\Z');
        $shortDate = substr($date, 0, 8);

        $canonical_uri = "/{$this->bucket}/{$source}";
        $canonical_query_string = '';
        $host = parse_url($this->domain, PHP_URL_HOST);

        $canonical_headers = [
            'host' => $host,
            'x-amz-content-sha256' => hash('sha256', ''),
            'x-amz-date' => $date
        ];

        ksort($canonical_headers);
        $signed_headers = [];
        $canonical_headers_str = '';

        foreach ($canonical_headers as $key => $value) {
            $signed_headers[] = $key;
            $canonical_headers_str .= "{$key}:{$value}\n";
        }

        $signed_headers_str = implode(';', $signed_headers);

        $canonical_request = implode("\n", [
            $method,
            $canonical_uri,
            $canonical_query_string,
            $canonical_headers_str,
            $signed_headers_str,
            hash('sha256', '')
        ]);

        $scope = implode('/', [
            $shortDate,
            's3',
            'aws4_request'
        ]);

        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $date,
            $scope,
            hash('sha256', $canonical_request)
        ]);

        $signature = $this->_getSignatureKey($shortDate);
        $signature = hash_hmac('sha256', $string_to_sign, $signature);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$scope},SignedHeaders={$signed_headers_str},Signature={$signature}";

        $headers = [];
        foreach ($canonical_headers as $key => $value) {
            $headers[ucwords($key, '-')] = $value;
        }
        $headers['Authorization'] = $authorization;

        return array_map(function ($k, $v) {
            return "{$k}: {$v}";
        }, array_keys($headers), $headers);
    }

    /**
     * 判断文件是否存在
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return bool
     */
    public function has(string $name, bool $safe = false): bool
    {
        $file = $this->delSuffix($name);
        $result = HttpExtend::request('HEAD', "{$this->domain}/{$this->bucket}/{$file}", [
            'returnHeader' => true,
            'headers' => $this->_sign('HEAD', $file)
        ]);

        return is_numeric(stripos($result, '200 OK'));
    }

    /**
     * 获取文件信息
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param ?string $attname 下载名称
     * @return array
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        return $this->has($name, $safe) ? [
            'url' => $this->url($name, $safe, $attname),
            'key' => $name,
            'file' => $this->path($name, $safe),
        ] : [];
    }

    /**
     * 获取存储区域
     * @return array
     */
    public static function region(): array
    {
        return [];
    }
}
