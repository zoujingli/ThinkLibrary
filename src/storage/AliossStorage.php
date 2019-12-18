<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin\storage;

use think\admin\extend\HttpExtend;
use think\admin\Storage;

/**
 * 阿里云OSS存储支持
 * Class AliossStorage
 * @package think\admin\storage
 */
class AliossStorage extends Storage
{
    /**
     * @var string
     */
    private $domain;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var string
     */
    private $keyid;

    /**
     * @return Storage
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function initialize(): Storage
    {
        $this->keyid = sysconf('storage.alioss_keyid');
        $this->secret = sysconf('storage.alioss_secret');
        $this->domain = sysconf('storage.alioss_domain');
        // 计算链接前缀
        $type = strtolower(sysconf('storage.alioss_http_protocol'));
        if ($type === 'auto') $this->prefix = "//{$this->domain}/";
        elseif ($type === 'http') $this->prefix = "http://{$this->domain}/";
        elseif ($type === 'https') $this->prefix = "https://{$this->domain}/";
        else throw new \think\Exception('未配置七牛云URL域名哦');
        return $this;
    }

    /**
     * 获取当前实例对象
     * @param null $name
     * @return static
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function instance($name = null): Storage
    {
        return parent::instance('alioss');
    }

    /**
     * 上传文件内容
     * @param string $name
     * @param string $file
     * @param bool $safe
     * @return array|mixed
     */
    public function set($name, $file, $safe = false)
    {
        $token = $this->buildUploadToken($name);
        list($attrs, $frontier) = [[], uniqid()];
        foreach (['key' => $name, 'success_action_status' => '200', 'OSSAccessKeyId' => $this->keyid, 'policy' => $token['policy'], 'Signature' => $token['signature']] as $key => $value) {
            $attrs[] = "--{$frontier}";
            $attrs[] = "Content-Disposition:form-data; name=\"{$key}\"";
            $attrs[] = "";
            $attrs[] = $value;
        }
        $attrs[] = "--{$frontier}";
        $attrs[] = "Content-Disposition:form-data; name=\"file\"; filename=\"{$name}\"";
        $attrs[] = "";
        $attrs[] = $file;
        $attrs[] = "--{$frontier}--";
        return json_decode(HttpExtend::post($this->upload(), join("\r\n", $attrs), [
            'headers' => ["Content-type:multipart/form-data;boundary={$frontier}"],
        ]), true);
    }

    public function get($name, $safe = false)
    {

    }

    public function del($name, $safe = false)
    {

    }

    public function has($name, $safe = false)
    {

    }

    public function url($name, $safe = false)
    {
        return $this->prefix . $name;
    }

    public function path($name, $safe = false)
    {
        return $this->url($name, $safe);
    }

    public function info($name, $safe = false)
    {

    }

    /**
     * 获取文件上传地址
     * @return string
     */
    public function upload()
    {
        $protocol = $this->app->request->isSsl() ? 'https' : 'http';
        return "{$protocol}://{$this->domain}";
    }

    /**
     * 获取文件上传令牌
     * @param string $name 文件名称
     * @param integer $expires 有效时间
     * @return array
     */
    public function buildUploadToken($name = null, $expires = 3600)
    {
        $data = [
            'policy'  => base64_encode(json_encode([
                'conditions' => [['content-length-range', 0, 1048576000]],
                'expiration' => date('Y-m-d\TH:i:s.000\Z', time() + $expires),
            ])),
            'siteurl' => $this->url($name), 'alioss_keyid' => $this->keyid,
        ];
        $data['signature'] = base64_encode(hash_hmac('sha1', $data['policy'], $this->secret, true));
        return $data;
    }

}