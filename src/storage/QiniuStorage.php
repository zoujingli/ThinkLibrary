<?php

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
        $domain = sysconf('storage_qiniu_domain');
        if (strtolower(sysconf('storage_qiniu_is_https')) === 'https') {
            $this->root = "https://{$domain}/";
        } elseif (strtolower(sysconf('storage_qiniu_is_https')) === 'http') {
            $this->root = "http://{$domain}/";
        } elseif (strtolower(sysconf('storage_qiniu_is_https')) === 'auto') {
            $this->root = "//{$domain}/";
        } else {
            throw new \think\Exception('未配置七牛云URL前缀');
        }
    }

    public function set($name, $content, $safe = false)
    {
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