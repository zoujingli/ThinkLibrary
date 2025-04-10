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

namespace think\admin\storage;

use think\admin\contract\StorageInterface;
use think\admin\contract\StorageUsageTrait;
use think\admin\Exception;
use think\admin\extend\HttpExtend;
use think\admin\Storage;

/**
 * 又拍云存储支持
 * @class UpyunStorage
 * @package think\admin\storage
 */
class UpyunStorage implements StorageInterface
{
    use StorageUsageTrait;

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
     */
    protected function init()
    {
        // 读取配置文件
        $this->bucket = sysconf('storage.upyun_bucket|raw');
        $this->accessKey = sysconf('storage.upyun_access_key|raw');
        $this->secretKey = sysconf('storage.upyun_secret_key|raw');
        // 计算链接前缀
        $host = strtolower(sysconf('storage.upyun_http_domain|raw'));
        $type = strtolower(sysconf('storage.upyun_http_protocol|raw'));
        if ($type === 'auto') {
            $this->domain = "//{$host}";
        } elseif (in_array($type, ['http', 'https'])) {
            $this->domain = "{$type}://{$host}";
        } else {
            throw new Exception(lang('未配置又拍云域名'));
        }
    }

    /**
     * 上传文件内容
     * @param string $name 文件名称
     * @param string $file 文件内容
     * @param boolean $safe 安全模式
     * @param ?string $attname 下载名称
     * @return array
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null): array
    {
        $data = [];
        $token = $this->token($name, 3600, $attname, md5($file));
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
     * @return boolean
     */
    public function del(string $name, bool $safe = false): bool
    {
        [$file] = explode('?', $name);
        $result = HttpExtend::request('DELETE', "{$this->upload()}/{$file}", [
            'returnHeader' => true, 'headers' => $this->_sign('DELETE', $file),
        ]);
        return is_numeric(stripos($result, 'HTTP/1.1 200 OK'));
    }

    /**
     * 判断是否存在
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function has(string $name, bool $safe = false): bool
    {
        $file = $this->delSuffix($name);
        $result = HttpExtend::request('HEAD', "{$this->upload()}/{$file}", [
            'returnHeader' => true, 'headers' => $this->_sign('HEAD', $file),
        ]);
        return is_numeric(stripos($result, 'HTTP/1.1 200 OK'));
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
        return "{$this->domain}/{$this->delSuffix($name)}{$this->getSuffix($attname,$name)}";
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
            'key' => $name, 'file' => $this->path($name, $safe),
        ] : [];
    }

    /**
     * 获取上传地址
     * @return string
     */
    public function upload(): string
    {
        $protocol = $this->app->request->isSsl() ? 'https' : 'http';
        return "{$protocol}://v0.api.upyun.com/{$this->bucket}";
    }

    /**
     * 生成上传令牌
     * @param string $name 文件名称
     * @param integer $expires 有效时间
     * @param ?string $attname 下载名称
     * @param ?string $fileHash 文件哈希
     * @return array
     */
    public function token(string $name, int $expires = 3600, ?string $attname = null, ?string $fileHash = ''): array
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
     * 生成请求签名
     * @param string $method 请求方式
     * @param string $name 资源名称
     * @return array
     */
    private function _sign(string $method, string $name): array
    {
        $data = [$method, "/{$this->bucket}/{$name}", $date = gmdate('D, d M Y H:i:s \G\M\T')];
        $signature = base64_encode(hash_hmac('sha1', join('&', $data), md5($this->secretKey), true));
        return ["Authorization:UPYUN {$this->accessKey}:{$signature}", "Date:{$date}"];
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