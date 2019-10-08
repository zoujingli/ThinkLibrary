<?php

namespace library\storage;

use library\Storage;

/**
 * Class LocalStorage
 * @package library\storage
 */
class LocalStorage extends Storage
{

    /**
     * LocalStorage constructor.
     */
    public function __construct()
    {
        $this->root = env('root_path');
    }

    public function put($name, $content, $safe = false)
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