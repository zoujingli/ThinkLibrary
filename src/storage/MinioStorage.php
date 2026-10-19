<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | ThinkAdmin Plugin for ThinkAdmin
 * +----------------------------------------------------------------------
 * | 版权所有 2014~2026 ThinkAdmin [ thinkadmin.top ]
 * +----------------------------------------------------------------------
 * | 官方网站: https://thinkadmin.top
 * +----------------------------------------------------------------------
 * | 开源协议 ( https://mit-license.org )
 * | 免责声明 ( https://thinkadmin.top/disclaimer )
 * | 会员特权 ( https://thinkadmin.top/vip-introduce )
 * +----------------------------------------------------------------------
 * | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
 * | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
 * +----------------------------------------------------------------------
 */

namespace think\admin\storage;

use think\admin\contract\StorageInterface;
use think\admin\contract\StorageUsageTrait;
use think\admin\Exception;
use think\admin\Storage;

/**
 * MinIO 路径式 S3 存储；普通文件支持公开访问，安全文件委托本地安全目录.
 * 服务端操作使用 SigV4 鉴权，不修改桶策略，不自动重试写入。
 */
class MinioStorage implements StorageInterface
{
    use StorageUsageTrait;

    /** @var string S3 API 端点（含协议和可选端口） */
    private $endpoint;

    /** @var string 签名 Host（含可选端口） */
    private $host;

    /** @var string 存储桶 */
    private $bucket;

    /** @var string 签名区域 */
    private $region;

    /** @var string 访问密钥 */
    private $accessKey;

    /** @var string 签名密钥，仅用于服务端计算 */
    private $secretKey;

    /**
     * 写入完整文件内容；失败返回空数组，不创建本地中间文件.
     * @param string $name 相对存储键
     * @param string $file 原始文件内容
     * @param bool $safe 安全文件写入本地安全目录
     * @param ?string $attname 下载名称
     * @throws \InvalidArgumentException 存储键不合法
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null): array
    {
        if ($safe) {
            return LocalStorage::instance()->set($name, $file, true, $attname);
        }
        $name = $this->objectName($name);
        [$status] = $this->request('PUT', $name, $file, $attname);
        return $status === 200 ? ['file' => $this->path($name), 'url' => $this->url($name, false, $attname), 'key' => $name] : [];
    }

    /**
     * 签名读取文件，私有桶也可读取；不存在或请求失败返回空字符串.
     * @param string $name 相对存储键，可含原有显示后缀
     * @param bool $safe 从本地安全目录读取
     */
    public function get(string $name, bool $safe = false): string
    {
        if ($safe) {
            return LocalStorage::instance()->get($name, true);
        }
        [$status, $body] = $this->request('GET', $this->objectName($name));
        return $status === 200 ? $body : '';
    }

    /**
     * 签名删除文件；S3 删除不存在对象也返回成功，请求失败返回 false.
     * @param string $name 相对存储键，可含原有显示后缀
     * @param bool $safe 仅删除本地安全目录中的文件
     */
    public function del(string $name, bool $safe = false): bool
    {
        if ($safe) {
            return LocalStorage::instance()->del($name, true);
        }
        [$status] = $this->request('DELETE', $this->objectName($name));
        return $status === 204;
    }

    /**
     * 签名检查对象；无权限、网络失败或对象不存在均返回 false.
     * @param string $name 相对存储键，可含原有显示后缀
     * @param bool $safe 检查本地安全目录
     */
    public function has(string $name, bool $safe = false): bool
    {
        if ($safe) {
            return LocalStorage::instance()->has($name, true);
        }
        [$status] = $this->request('HEAD', $this->objectName($name));
        return $status === 200;
    }

    /**
     * 获取普通文件公开地址（需由管理员配置桶的只读策略）；安全文件不生成云地址.
     * @param string $name 相对存储键
     * @param bool $safe 使用本地安全文件地址规则
     * @param ?string $attname 下载名称，沿用现有链接后缀规则
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        if ($safe) {
            return LocalStorage::instance()->url($name, true, $attname);
        }
        return $this->upload() . '/' . $this->objectName($name) . $this->getSuffix($attname, $name);
    }

    /**
     * 获取对象地址或本地安全文件路径，不下载文件.
     * @param string $name 相对存储键
     * @param bool $safe 使用本地安全目录
     */
    public function path(string $name, bool $safe = false): string
    {
        return $safe ? LocalStorage::instance()->path($name, true) : $this->url($name);
    }

    /**
     * 检查并返回既有存储信息结构；对象不存在或无权限返回空数组.
     * @param string $name 相对存储键
     * @param bool $safe 使用本地安全目录
     * @param ?string $attname 下载名称
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        if ($safe) {
            return LocalStorage::instance()->info($name, true, $attname);
        }
        $name = $this->objectName($name);
        return $this->has($name) ? ['url' => $this->url($name, false, $attname), 'key' => $name, 'file' => $this->path($name)] : [];
    }

    /** 获取浏览器 POST 表单上传端点，不包含凭据. */
    public function upload(): string
    {
        return $this->endpoint . '/' . $this->bucket;
    }

    /**
     * 签发限定单一对象的 POST policy；表单字段必须原样提交，file 字段最后提交.
     * @param string $name 相对存储键
     * @param int $expires 有效秒数（1 至 604800）
     * @param ?string $attname 下载名称
     * @return array 包含 fields 和 siteurl，不返回签名密钥
     * @throws \InvalidArgumentException 键或有效时间无效
     */
    public function token(string $name, int $expires = 3600, ?string $attname = null): array
    {
        if ($expires < 1 || $expires > 604800) {
            throw new \InvalidArgumentException('MinIO 上传授权有效期必须在 1 至 604800 秒内');
        }
        $name = $this->objectName($name);
        $date = gmdate('Ymd\THis\Z');
        $scope = substr($date, 0, 8) . '/' . $this->region . '/s3/aws4_request';
        $fields = [
            'key' => $name, 'x-amz-algorithm' => 'AWS4-HMAC-SHA256',
            'x-amz-credential' => $this->accessKey . '/' . $scope,
            'x-amz-date' => $date, 'success_action_status' => '200',
            'Content-Type' => Storage::mime(pathinfo($name, PATHINFO_EXTENSION)),
        ];
        if ($attname !== null && $attname !== '') {
            $fields['Content-Disposition'] = 'inline;filename=' . rawurlencode($attname);
        }
        $conditions = [['bucket' => $this->bucket], ['content-length-range', 0, 1048576000]];
        foreach ($fields as $key => $value) {
            $conditions[] = [$key => $value];
        }
        $fields['policy'] = base64_encode(json_encode([
            'expiration' => gmdate('Y-m-d\TH:i:s\Z', time() + $expires), 'conditions' => $conditions,
        ], JSON_UNESCAPED_SLASHES));
        $fields['x-amz-signature'] = hash_hmac('sha256', $fields['policy'], $this->signingKey(substr($date, 0, 8)));
        return ['fields' => $fields, 'siteurl' => $this->url($name, false, $attname)];
    }

    /** MinIO 区域由部署配置，不限定云厂商区域列表. */
    public static function region(): array
    {
        return [];
    }

    /**
     * 加载现有存储配置；禁止 URL 用户信息、路径和查询参数，避免签名歧义.
     * @throws Exception 端点、桶、区域或密钥未正确配置
     */
    protected function init()
    {
        $protocol = strtolower(sysconf('storage.minio_http_protocol|raw'));
        $this->host = strtolower(trim(sysconf('storage.minio_http_domain|raw')));
        $this->bucket = sysconf('storage.minio_bucket|raw');
        $this->region = sysconf('storage.minio_region|raw') ?: 'us-east-1';
        $this->accessKey = sysconf('storage.minio_access_key|raw');
        $this->secretKey = sysconf('storage.minio_secret_key|raw');
        if (!in_array($protocol, ['http', 'https'], true)
            || !preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/D', $this->host)
            || !preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/D', $this->bucket)
            || !preg_match('/^[a-zA-Z0-9-]+$/D', $this->region)
            || $this->accessKey === '' || $this->secretKey === '') {
            throw new Exception('MinIO 存储配置不完整或格式无效');
        }
        $this->endpoint = $protocol . '://' . $this->host;
    }

    /**
     * 去除原有显示后缀并验证系统存储键，不接受 URL 或目录穿越.
     * @param string $name 输入存储键
     * @throws \InvalidArgumentException 存储键不合法
     */
    private function objectName(string $name): string
    {
        $name = $this->delSuffix($name);
        if (!Storage::isPathSafe($name)) {
            throw new \InvalidArgumentException('MinIO 文件存储键无效');
        }
        return $name;
    }

    /**
     * 派生 SigV4 签名密钥，区域、服务与 credential scope 保持一致.
     * @param string $date UTC 日期（Ymd）
     */
    private function signingKey(string $date): string
    {
        $key = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $key = hash_hmac('sha256', $this->region, $key, true);
        $key = hash_hmac('sha256', 's3', $key, true);
        return hash_hmac('sha256', 'aws4_request', $key, true);
    }

    /**
     * 执行签名请求；验证 TLS，不跟随重定向以免凭据泄漏，所有路径释放句柄.
     * @param string $method S3 请求方式
     * @param string $name 已验证的对象键
     * @param string $body 原始请求体
     * @param ?string $attname 下载名称
     * @return array [HTTP 状态码, 响应体]，传输失败为 [0, '']
     */
    private function request(string $method, string $name, string $body = '', ?string $attname = null): array
    {
        $path = '/' . $this->bucket . '/' . $name;
        $date = gmdate('Ymd\THis\Z');
        $hash = hash('sha256', $body);
        $scope = substr($date, 0, 8) . '/' . $this->region . '/s3/aws4_request';
        $signed = 'host;x-amz-content-sha256;x-amz-date';
        $canonical = $method . "\n" . $path . "\n\n" . 'host:' . $this->host . "\n" . 'x-amz-content-sha256:' . $hash . "\n" . 'x-amz-date:' . $date . "\n\n" . $signed . "\n" . $hash;
        $signature = hash_hmac('sha256', "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . hash('sha256', $canonical), $this->signingKey(substr($date, 0, 8)));
        $headers = ['Host: ' . $this->host, 'x-amz-date: ' . $date, 'x-amz-content-sha256: ' . $hash,
            'Authorization: AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $scope . ', SignedHeaders=' . $signed . ', Signature=' . $signature];
        if ($method === 'PUT') {
            $headers[] = 'Content-Type: ' . Storage::mime(pathinfo($name, PATHINFO_EXTENSION));
            if ($attname !== null && $attname !== '') {
                $headers[] = 'Content-Disposition: inline;filename=' . rawurlencode($attname);
            }
        }
        $curl = curl_init($this->endpoint . $path);
        try {
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60, CURLOPT_NOBODY => $method === 'HEAD']);
            if ($method === 'PUT') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            $response = curl_exec($curl);
            return $response === false ? [0, ''] : [(int)curl_getinfo($curl, CURLINFO_HTTP_CODE), $response];
        } finally {
            if (PHP_VERSION_ID < 80000) {
                curl_close($curl);
            }
        }
    }
}
