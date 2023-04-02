<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2023 ThinkAdmin [ thinkadmin.top ]
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
 * Alist自建存储支持
 * Class AlistStorage
 * @package think\admin\storage
 */
class AlistStorage implements StorageInterface
{
    use StorageUsageTrait;

    /**
     * 用户账号
     * @var string
     */
    protected $username;

    /**
     * 用户密码
     * @var string
     */
    protected $password;

    /**
     * 保存路径
     * @var string
     */
    protected $savepath;

    /**
     * 存储引擎初始化
     * @return void
     * @throws \think\admin\Exception
     */
    protected function init()
    {
        $host = strtolower(sysconf('storage.alist_http_domain|raw'));
        $type = strtolower(sysconf('storage.alist_http_protocol|raw'));
        if (!empty($host) && $type === 'auto') {
            $this->domain = "//{$host}";
        } elseif (!empty($host) && in_array($type, ['http', 'https'])) {
            $this->domain = "{$type}://{$host}";
        } else {
            throw new Exception(lang('未配置Alist域名哦'));
        }
        $this->savepath = sysconf('storage.alist_savepath|raw') ?: '/';
        $this->savepath = trim($this->savepath, '\\/') . '/';
        $this->username = sysconf('storage.alist_username|raw') ?: '';
        $this->password = sysconf('storage.alist_password|raw') ?: '';
    }

    /**
     * 上传文件内容
     * @param string $name 文件名称
     * @param string $file 文件内容
     * @param boolean $safe 安全模式
     * @param ?string $attname 下载名称
     * @return array
     * @throws \think\admin\Exception
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null): array
    {
        $file = ['field' => 'file', 'name' => $name, 'content' => $file];
        $header = ["Authorization: {$this->token()}", "file-path:" . urlencode($this->realpath($name))];
        $result = HttpExtend::submit("{$this->domain}/api/fs/form", [], $file, $header, 'PUT', false);
        if (is_array($data = json_decode($result, true))) {
            if ($data['code'] === 200 && $data['message'] === 'success') {
                return $this->info($name, $safe);
            } else {
                throw new Exception($data['message'] ?? '接口请求失败！', intval($data['code'] ?? 0));
            }
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
        try {
            $path = $this->realpath($name);
            $data = ['dir' => dirname($path) ?: '/', 'names' => [basename($path)]];
            $this->post('/api/fs/remove', $data);
            return true;
        } catch (\Exception $exception) {
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
        try {
            $this->post('/api/fs/get', [
                'path' => $this->realpath($name)
            ]);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * 获取文件下载链接
     * @param string $name
     * @param bool $safe
     * @param string|null $attname
     * @return string
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        return $this->domain . '/d/' . trim($this->realpath($name), '\\/');
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
        return "{$this->domain}/api/fs/form";
    }

    /**
     * 获取存储区域
     * @return array
     */
    public static function region(): array
    {
        return [];
    }

    /**
     * 创建目录
     * @param string $path
     * @return boolean
     */
    private function mkdir(string $path): bool
    {
        try {
            $this->post('/api/fs/mkdir', [
                'path' => $this->realpath($path)
            ]);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * 转换为绝对路径
     * @param string $path
     * @return string
     */
    private function realpath(string $path): string
    {
        return $this->savepath . trim($path, '\\/');
    }

    /**
     * POST 提交数据
     * @param string $uri
     * @param array $body
     * @param boolean $auth
     * @return array
     * @throws \think\admin\Exception
     */
    private function post(string $uri, array $body = [], bool $auth = true): array
    {
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        $header = $auth ? ["Authorization: {$this->token()}"] : [];
        $header[] = "Content-Type: application/json;charset=UTF-8";
        $result = HttpExtend::post($this->domain . $uri, $body, ['headers' => $header]);
        if (is_array($data = json_decode($result, true))) {
            if ($data['code'] === 200 && $data['message'] === 'success') return $data;
            throw new Exception($data['message'] ?? '接口请求失败！', intval($data['code'] ?? 0));
        } else {
            throw new Exception('接口请求失败！');
        }
    }

    /**
     * 获取用户 Token 信息
     * @param boolean $force
     * @return string
     * @throws \think\admin\Exception
     */
    private function token(bool $force = false): string
    {
        $skey = 'AlistStorage#' . md5($this->domain . $this->username . $this->password);
        // if (empty($force) && ($token = $this->app->cache->get($skey))) return $token;
        try {
            $params = http_build_query($data = ['Password' => $this->password, 'Username' => $this->username]);
            $body = $this->post("/api/auth/login?$params", $data, false);
            if (!empty($body['data']['token'])) {
                // $this->app->cache->set($skey, $data['data']['token'], 60);
                return $body['data']['token'];
            } else {
                throw new Exception('获取用户 Token 失败！');
            }
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }
}