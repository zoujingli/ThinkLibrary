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

/**
 * 本地存储支持
 * @class LocalStorage
 * @package think\admin\storage
 */
class LocalStorage implements StorageInterface
{
    use StorageUsageTrait;

    /**
     * 初始化入口
     * @throws \think\admin\Exception
     */
    protected function init()
    {
        $type = sysconf('storage.local_http_protocol|raw') ?: 'follow';
        if ($type === 'follow') $type = $this->app->request->scheme();
        $this->domain = trim(dirname($this->app->request->baseFile()), '\\/');
        if ($type !== 'path') {
            $domain = sysconf('storage.local_http_domain|raw') ?: $this->app->request->host();
            if ($type === 'auto') {
                $this->domain = "//{$domain}";
            } elseif (in_array($type, ['http', 'https'])) {
                $this->domain = "{$type}://{$domain}";
            }
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
        try {
            $path = $this->path($name, $safe);
            is_dir($dir = dirname($path)) || mkdir($dir, 0777, true);
            if (file_put_contents($path, $file)) {
                return $this->info($name, $safe, $attname);
            }
        } catch (\Exception $exception) {
        }
        return [];
    }

    /**
     * 读取文件内容
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function get(string $name, bool $safe = false): string
    {
        if (!$this->has($name, $safe)) return '';
        return file_get_contents($this->path($name, $safe));
    }

    /**
     * 删除存储文件
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function del(string $name, bool $safe = false): bool
    {
        if ($this->has($name, $safe)) {
            return @unlink($this->path($name, $safe));
        } else {
            return false;
        }
    }

    /**
     * 判断是否存在
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function has(string $name, bool $safe = false): bool
    {
        return is_file($this->path($name, $safe));
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
        return $safe ? $name : "{$this->domain}/upload/{$this->delSuffix($name)}{$this->getSuffix($attname,$name)}";
    }

    /**
     * 获取存储路径
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function path(string $name, bool $safe = false): string
    {
        $path = $safe ? 'safefile' : 'public/upload';
        return strtr(syspath("{$path}/{$this->delSuffix($name)}"), '\\', '/');
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
            'key' => "upload/{$name}", 'file' => $this->path($name, $safe),
        ] : [];
    }

    /**
     * 获取上传地址
     * @return string
     */
    public function upload(): string
    {
        return url('admin/api.upload/file', [], false, true)->build();
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