<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://library.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace library\storage;

use library\Storage;
use library\tools\Http;

/**
 * Class QiniuStorage
 * @package library\storage
 */
class QiniuStorage extends Storage
{
    private $bucket;
    private $domain;
    private $accessKey;
    private $secretKey;

    /**
     * QiniuStorage constructor.
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function __construct()
    {
        // 读取配置文件
        $this->bucket = sysconf('storage_qiniu_bucket');
        $this->accessKey = sysconf('storage_qiniu_access_key');
        $this->secretKey = sysconf('storage_qiniu_secret_key');
        // 计算文件前置链接
        $type = strtolower(sysconf('storage_qiniu_is_https'));
        $this->domain = strtolower(sysconf('storage_qiniu_domain'));
        if ($type === 'auto') $this->root = "//{$this->domain}/";
        elseif ($type === 'http') $this->root = "http://{$this->domain}/";
        elseif ($type === 'https') $this->root = "https://{$this->domain}/";
        else throw new \think\Exception('未配置七牛云URL域名哦');
    }

    /**
     * 上传文件内容
     * @param string $name
     * @param string $content
     * @param boolean $safe
     * @return array
     */
    public function set($name, $content, $safe = false)
    {
        $policy = $this->safeBase64(json_encode([
            "deadline"   => time() + 3600, "scope" => "{$this->bucket}:{$name}",
            'returnBody' => json_encode(['filename' => '$(key)', 'url' => "{$this->root}/$(key)"], JSON_UNESCAPED_UNICODE),
        ]));
        $token = "{$this->accessKey}:{$this->safeBase64(hash_hmac('sha1', $policy, $this->secretKey, true))}:{$policy}";
        list($attrs, $frontier) = [[], uniqid()];
        foreach (['key' => $name, 'token' => $token, 'fileName' => $name] as $k => $v) {
            $attrs[] = "--{$frontier}";
            $attrs[] = "Content-Disposition:form-data; name=\"{$k}\"";
            $attrs[] = "";
            $attrs[] = $v;
        }
        $attrs[] = "--{$frontier}";
        $attrs[] = "Content-Disposition:form-data; name=\"file\"; filename=\"{$name}\"";
        $attrs[] = "";
        $attrs[] = $content;
        $attrs[] = "--{$frontier}--";
        $result = Http::post('http://up-z2.qiniup.com/', join("\r\n", $attrs), ['headers' => ["Content-type:multipart/form-data;boundary={$frontier}"]]);
        return json_decode($result, true);
    }

    /**
     * URL安全的Base64编码
     * @param string $content
     * @return string
     */
    private function safeBase64($content)
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($content));
    }

    public function get($name, $safe = false)
    {
        // TODO: Implement get() method.
    }

    public function del($name, $safe = false)
    {
        // TODO: Implement del() method.
    }

    public function has($name, $safe = false)
    {
        // TODO: Implement has() method.
    }

    public function url($name, $safe = false)
    {
        // TODO: Implement url() method.
    }

    public function path($name, $safe = false)
    {
        // TODO: Implement path() method.
    }

    public function info($name, $safe = false)
    {
        // TODO: Implement info() method.
    }
}