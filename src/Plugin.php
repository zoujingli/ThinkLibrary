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
use think\App;
use think\Service;

/**
 * 插件注册服务
 * Class Plugin
 * @package think\admin\service
 */
abstract class Plugin extends Service
{
    /**
     * 插件包名
     * @var string
     */
    protected $package = '';

    /**
     * 插件服务
     * @var string
     */
    protected $service = '';

    /**
     * 插件名称
     * @var string
     */
    protected $appName = '';

    /**
     * 插件目录
     * @var string
     */
    protected $appPath = '';

    /**
     * 拷贝目录
     * @var string
     */
    protected $appCopy = '';

    /**
     * 插件别名
     * @var string
     */
    protected $appAlias = '';

    /**
     * 命名空间
     * @var string
     */
    protected $appSpace = '';

    /**
     * 插件配置
     * @var array
     */
    private static $addons = [];

    /**
     * 当前静态对应
     * @var static
     */
    protected static $static;

    /**
     * 自动注册插件
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        static::$static = $this;

        // 获取基础服务类
        $ref = new \ReflectionClass(static::class);

        // 应用命名空间名
        if (empty($this->appSpace)) {
            $this->appSpace = $ref->getNamespaceName();
        }

        // 应用服务注册类
        if (empty($this->service)) {
            $this->service = static::class;
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
        $attr = explode('\\', $ref->getNamespaceName());
        if ($attr[0] === NodeService::space()) array_shift($attr);

        $this->appName = $this->appName ?: join('-', $attr);
        $this->appAlias = $this->appAlias ?: join('-', $attr);
        if ($this->appName === $this->appAlias) $this->appAlias = '';

        // 注册应用插件信息
        self::add($this->appName, $this->appPath, $this->appCopy, $this->appAlias, $this->appSpace, $this->package, $this->service);
    }

    /**
     * 注册应用启动
     * @return void
     */
    public function boot(): void
    {
    }

    /**
     * 注册应用插件
     * @param string $name 插件名称
     * @param string $path 插件目录
     * @param string $copy 插件资源
     * @param string $alias 插件别名
     * @param string $space 插件空间
     * @param string $package 插件包名
     * @param string $service 服务名称
     * @return void
     */
    private static function add(string $name, string $path, string $copy = '', string $alias = '', string $space = '', string $package = '', string $service = ''): void
    {
        if (file_exists($path) && is_dir($path)) {
            [$path, $space] = [rtrim($path, '\\/') . DIRECTORY_SEPARATOR, $space ?: NodeService::space($name)];
            $copy = rtrim($copy ?: dirname($path) . DIRECTORY_SEPARATOR . 'stc', '\\/') . DIRECTORY_SEPARATOR;
            if (strlen($alias) > 0 && $alias !== $name) Library::$sapp->config->set([
                'app_map' => array_merge(Library::$sapp->config->get('app.app_map', []), [$alias => $name]),
            ], 'app');
            self::$addons[$name] = ['path' => $path, 'copy' => $copy, 'alias' => $alias, 'space' => $space, 'package' => $package, 'service' => $service];
        }
    }

    /**
     * 获取所有插件
     * @param string $code 指定编号
     * @return ?array
     */
    public static function all(string $code = ''): ?array
    {
        return empty($code) ? self::$addons : (self::$addons[$code] ?? null);
    }

    /**
     * 获取内部参数
     * @param string $name
     * @return null
     */
    public function __get(string $name)
    {
        return $this->$name ?? '';
    }

    /**
     * 定义插件菜单
     * @return array 一级或二级菜单
     */
    abstract public static function menu(): array;
}