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

/**
 * Class QiniuStorage
 * @package library\storage
 */
class QiniuStorage extends Storage
{
    /**
     * QiniuStorage constructor.
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function __construct()
    {
        $type = strtolower(sysconf('storage_qiniu_is_https'));
        $domain = strtolower(sysconf('storage_qiniu_domain'));
        if ($type === 'auto') $this->root = "//{$domain}/";
        elseif ($type === 'http') $this->root = "http://{$domain}/";
        elseif ($type === 'https') $this->root = "https://{$domain}/";
        else throw new \think\Exception('未配置七牛云URL域名哦');
    }

    /**
     * @param string $name
     * @param string $content
     * @param boolean $safe
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function set($name, $content, $safe = false)
    {
        $bucket = sysconf('storage_qiniu_bucket');
        $policy = [
            'returnBody' => '{"filename":"$(key)","url":"' . $this->root . '$(key)"}',
        ];

        // TODO: Implement put() method.
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