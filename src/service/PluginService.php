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

namespace think\admin\service;

use think\admin\Library;
use think\Service;

/**
 * 应用插件注册服务
 * Class PluginService
 * @package think\admin\service
 */
class PluginService extends Service
{
    /**
     * 当前插件配置
     * @var array
     */
    private static $addons = [];

    /**
     * 插件应用名称
     * @var string
     */
    protected $appName = '';

    /**
     * 插件应用目录
     * @var string
     */
    protected $appPath = '';

    /**
     * 插件空间名称
     * @var string
     */
    protected $rootSpace = '';

    /**
     * 文件拷贝目录
     * @var string
     */
    protected $copyPath = '';

    /**
     * 启动时注册应用
     * @return void
     */
    public function boot(): void
    {
        $ref = new \ReflectionClass(static::class);
        $attr = explode('\\', $ref->getNamespaceName());

        // 插件应用路径计算
        if (empty($this->appPath) || !file_exists($this->appPath)) {
            $this->appPath = dirname($ref->getFileName());
        }

        // 插件应用名称计算
        $appName = array_pop($attr);
        if (empty($this->appName)) $this->appName = $appName;
        if (empty($this->rootSpace)) $this->rootSpace = join('\\', $attr);
        static::add($this->appPath, $this->appName, $this->rootSpace, $this->copyPath);
    }

    /**
     * 注册插件
     * @param string $path 应用目录
     * @param string $name 应用名称
     * @param string $root 命名空间
     * @param string $copy 应用资源
     * @return boolean
     */
    public static function add(string $path, string $name, string $root = '', string $copy = ''): bool
    {
        if (file_exists($path) && is_dir($path)) {
            $path = rtrim($path, '\\/') . DIRECTORY_SEPARATOR;
            $copy = rtrim($copy ?: $path, '\\/') . DIRECTORY_SEPARATOR;
            $root = $root ?: Library::$sapp->config->get('app.app_namespace') ?: 'app';
            static::$addons[$name] = [$path, $root, $copy];
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取所有插件
     * @return array [[所在路径,主空间名,资源目录]]
     */
    public static function all(): array
    {
        return static::$addons;
    }
}