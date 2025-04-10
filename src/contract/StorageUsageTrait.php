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

namespace think\admin\contract;

use think\admin\Exception;
use think\App;
use think\Container;

/**
 * 文件存储公共属性
 * @class StorageUsageTrait
 * @package think\admin\contract
 */
trait StorageUsageTrait
{
    /**
     * @var \think\App $app
     */
    protected $app;

    /**
     * 链接类型
     * @var string
     */
    protected $link;

    /**
     * 链接前缀
     * @var string
     */
    protected $domain;

    /**
     * 存储器构造方法
     * @param \think\App $app
     * @throws \think\admin\Exception
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->link = sysconf('storage.link_type|raw');
        $this->init();
    }

    /**
     * 自定义初始化方法
     * @return void
     */
    protected function init()
    {
    }

    /**
     * 获取对象实例
     * @return static
     */
    public static function instance()
    {
        /** @var \think\admin\contract\StorageInterface */
        return Container::getInstance()->make(static::class);
    }

    /**
     * 获取下载链接后缀
     * @param null|string $attname 下载名称
     * @param null|string $filename 文件名称
     * @return string
     */
    protected function getSuffix(?string $attname = null, ?string $filename = null): string
    {
        [$class, $suffix] = [class_basename(get_class($this)), ''];
        if (is_string($filename) && stripos($this->link, 'compress') !== false) {
            $compress = [
                'LocalStorage'  => '',
                'QiniuStorage'  => '?imageslim',
                'UpyunStorage'  => '!/format/webp',
                'TxcosStorage'  => '?imageMogr2/format/webp',
                'AliossStorage' => '?x-oss-process=image/format,webp',
            ];
            $extens = strtolower(pathinfo($this->delSuffix($filename), PATHINFO_EXTENSION));
            $suffix = in_array($extens, ['png', 'jpg', 'jpeg']) ? ($compress[$class] ?? '') : '';
        }
        if (is_string($attname) && strlen($attname) > 0 && stripos($this->link, 'full') !== false) {
            if ($class === 'UpyunStorage') {
                $suffix .= ($suffix ? '&' : '?') . '_upd=' . urlencode($attname);
            } else {
                $suffix .= ($suffix ? '&' : '?') . 'attname=' . urlencode($attname);
            }
        }
        return $suffix;
    }

    /**
     * 获取文件基础名称
     * @param string $name 文件名称
     * @return string
     */
    protected function delSuffix(string $name): string
    {
        if (strpos($name, '?') !== false) {
            return strstr($name, '?', true);
        }
        if (stripos($name, '!') !== false) {
            return strstr($name, '!', true);
        }
        return $name;
    }

    /**
     * 重构后兼容处理
     * @param string $method
     * @param array $arguments
     * @return array|string
     * @throws \think\admin\Exception
     */
    public function __call(string $method, array $arguments)
    {
        if (strtolower($method) === 'builduploadtoken') {
            if (method_exists($this, 'token')) {
                return $this->token(...$arguments);
            }
        }
        // 调用方法异常处理
        $class = class_basename(static::class);
        throw new Exception("method not exists: {$class}->{$method}()");
    }
}