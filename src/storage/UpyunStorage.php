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

namespace think\admin\storage;

use think\admin\Exception;
use think\admin\extend\HttpExtend;
use think\admin\Storage;

/**
 * 又拍云存储支持
 * Class UpyunStorage
 * @package think\admin\storage
 */
class UpyunStorage extends Storage
{
    /**
     * 存储空间名称
     * @var string
     */
    private $bucket;

    /**
     * AccessId
     * @var string
     */
    private $accessKey;

    /**
     * AccessSecret
     * @var string
     */
    private $secretKey;

    /**
     * 初始化入口
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function initialize()
    {
        // 读取配置文件
        $this->bucket = sysconf('storage.upyun_bucket');
        $this->accessKey = sysconf('storage.upyun_access_key');
        $this->secretKey = sysconf('storage.upyun_secret_key');
        // 计算链接前缀
        $host = strtolower(sysconf('storage.upyun_http_domain'));
        $type = strtolower(sysconf('storage.upyun_http_protocol'));
        if ($type === 'auto') {
            $this->domain = "//{$host}";
        } elseif (in_array($type, ['http', 'https'])) {
            $this->domain = "{$type}://{$host}";
        } else {
            throw new Exception(lang('未配置又拍云URL域名哦'));
        }
    }

    /**
     * 上传文件内容
     * @param string $name 文件名称
     * @param string $file 文件内容
     * @param boolean $safe 安全模式
     * @param null|string $attname 下载名称
     * @return array
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null): array
    {
        $data = [];
        $token = $this->buildUploadToken($name, 3600, $attname, md5($file));
        $data['policy'] = $token['policy'];
        $data['authorization'] = $token['authorization'];
        $file = ['field' => 'file', 'name' => $name, 'content' => $file];
        if (is_numeric(stripos(HttpExtend::submit($this->upload(), $data, $file), '200 OK'))) {
            return ['file' => $this->path($name, $safe), 'url' => $this->url($name, $safe, $attname), 'key' => $name];
        } else {
            return [];
        }
    }

    /**
     * 根据文件名读取文件内容
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function get(string $name, bool $safe = false): string
    {
        return static::curlGet($this->url($name, $safe));
    }

    /**
     * 删除存储的文件
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function del(string $name, bool $safe = false): bool
    {
        [$file] = explode('?', $name);
        $result = HttpExtend::request('DELETE', "{$this->upload()}/{$file}", [
            'returnHeader' => true, 'headers' => $this->headerSign('DELETE', $file),
        ]);
        return is_numeric(stripos($result, 'HTTP/1.1 200 OK'));
    }

    /**
     * 判断文件是否存在
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function has(string $name, bool $safe = false): bool
    {
        $file = $this->delSuffix($name);
        $result = HttpExtend::request('HEAD', "{$this->upload()}/{$file}", [
            'returnHeader' => true, 'headers' => $this->headerSign('HEAD', $file),
        ]);
        return is_numeric(stripos($result, 'HTTP/1.1 200 OK'));
    }

    /**
     * 获取文件当前URL地址
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param null|string $attname 下载名称
     * @return string
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        return "{$this->domain}/{$this->delSuffix($name)}{$this->getSuffix($attname,$name)}";
    }

    /**
     * 获取文件存储路径
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function path(string $name, bool $safe = false): string
    {
        return $this->url($name, $safe);
    }

    /**
     * 获取文件存储信息
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param null|string $attname 下载名称
     * @return array
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        return $this->has($name, $safe) ? [
            'url' => $this->url($name, $safe, $attname),
            'key' => $name, 'file' => $this->path($name, $safe),
        ] : [];
    }

    /**
     * 获取文件上传地址
     * @return string
     */
    public function upload(): string
    {
        $protocol = $this->app->request->isSsl() ? 'https' : 'http';
        return "{$protocol}://v0.api.upyun.com/{$this->bucket}";
    }

    /**
     * 获取文件上传令牌
     * @param string $name 文件名称
     * @param integer $expires 有效时间
     * @param string|null $attname 下载名称
     * @param string|null $fileHash 文件哈希
     * @return array
     */
    public function buildUploadToken(string $name, int $expires = 3600, ?string $attname = null, ?string $fileHash = ''): array
    {
        $policy = ['save-key' => $name];
        $policy['date'] = gmdate('D, d M Y H:i:s \G\M\T');
        $policy['bucket'] = $this->bucket;
        $policy['expiration'] = time() + $expires;
        if ($fileHash) $policy['content-md5'] = $fileHash;
        $data = ['keyid' => $this->accessKey, 'siteurl' => $this->url($name, false, $attname)];
        $data['policy'] = base64_encode(json_encode($policy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $content = "POST&/{$this->bucket}&{$policy['date']}&{$data['policy']}";
        if ($fileHash) $content .= "&{$fileHash}";
        $data['signature'] = base64_encode(hash_hmac('sha1', $content, md5($this->secretKey), true));
        $data['authorization'] = "UPYUN {$this->accessKey}:{$data['signature']}";
        return $data;
    }

    /**
     * 操作请求头信息签名
     * @param string $method 请求方式
     * @param string $name 资源名称
     * @return array
     */
    private function headerSign(string $method, string $name): array
    {
        $data = [$method, "/{$this->bucket}/{$name}", $date = gmdate('D, d M Y H:i:s \G\M\T')];
        $signature = base64_encode(hash_hmac('sha1', join('&', $data), md5($this->secretKey), true));
        return ["Authorization:UPYUN {$this->accessKey}:{$signature}", "Date:{$date}"];
    }

    /**
     * 又拍云存储区域
     * @return array
     */
    public static function region(): array
    {
        return [];
    }
}