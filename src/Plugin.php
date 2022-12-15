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

namespace think\admin;

use think\admin\service\NodeService;
use think\Service;

/**
 * 应用插件注册服务
 * Class Plugin
 * @package think\admin\service
 */
abstract class Plugin extends Service
{
    /**
     * 应用插件包名
     * @var string
     */
    protected $package = '';

    /**
     * 应用插件名称
     * @var string
     */
    protected $appName = '';

    /**
     * 应用插件目录
     * @var string
     */
    protected $appPath = '';

    /**
     * 应用插件别名
     * @var string
     */
    protected $appAlias = '';

    /**
     * 应用命名空间
     * @var string
     */
    protected $appSpace = '';

    /**
     * 文件拷贝目录
     * @var string
     */
    protected $copyPath = '';

    /**
     * 当前插件配置
     * @var array
     */
    private static $addons = [];

    /**
     * 自动注册应用
     * @return void
     */
    public function boot(): void
    {
        // 初始化服务
        $this->initialize();

        $ref = new \ReflectionClass(static::class);
        $attr = explode('\\', $ref->getNamespaceName());

        // 应用命名空间名
        if (empty($this->appSpace)) {
            $this->appSpace = $ref->getNamespaceName();
        }

        // 应用插件路径计算
        if (empty($this->appPath) || !file_exists($this->appPath)) {
            $this->appPath = dirname($ref->getFileName());
        }

        // 应用插件包名计算
        if (empty($this->package) && ($path = $ref->getFileName())) {
            for ($level = 1; $level <= 3; $level++) {
                if (file_exists($file = dirname($path, $level) . '/composer.json')) {
                    $this->package = json_decode(file_get_contents($file), true)['name'] ?? '';
                    break;
                }
            }
        }

        // 应用插件计算名称及别名
        if ($attr[0] === NodeService::space()) array_shift($attr);
        $this->appName = $this->appName ?: join('-', $attr);
        $this->appAlias = $this->appAlias ?: join('-', $attr);
        if ($this->appName === $this->appAlias) $this->appAlias = '';

        // 注册应用插件信息
        static::add($this->appName, $this->appPath, $this->copyPath, $this->appAlias, $this->appSpace, $this->package);
    }

    /**
     * 注册应用插件
     * @param string $name 应用名称
     * @param string $path 应用目录
     * @param string $copy 应用资源
     * @param string $alias 应用别名
     * @param string $space 应用空间
     * @param string $package 应用包名
     * @return boolean
     */
    public static function add(string $name, string $path, string $copy = '', string $alias = '', string $space = '', string $package = ''): bool
    {
        if (file_exists($path) && is_dir($path)) {
            $path = rtrim($path, '\\/') . DIRECTORY_SEPARATOR;
            $space = $space ?: NodeService::space($name);
            $copy = rtrim($copy ?: dirname($path) . DIRECTORY_SEPARATOR . 'stc', '\\/') . DIRECTORY_SEPARATOR;
            if (strlen($alias) > 0 && $alias !== $name) Library::$sapp->config->set([
                'app_map' => array_merge(Library::$sapp->config->get('app.app_map', []), [$alias => $name])
            ], 'app');
            self::$addons[$name] = ['path' => $path, 'copy' => $copy, 'alias' => $alias, 'space' => $space, 'package' => $package];
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取所有插件
     * @return array [string][所在路径,应用空间,资源目录,应用别名]
     */
    public static function all(): array
    {
        return self::$addons;
    }

    /**
     * 服务初始化
     */
    protected function initialize()
    {
    }
}