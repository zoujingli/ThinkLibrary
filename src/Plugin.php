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

namespace think\admin;

use think\admin\service\ModuleService;
use think\admin\service\NodeService;
use think\App;
use think\Service;

/**
 * 插件注册服务
 *
 * @class Plugin
 * @package think\admin\service
 *
 * @method string getAppCode() static 获取插件编号
 * @method string getAppName() static 获取插件名称
 * @method string getAppPath() static 获取插件路径
 * @method string getAppSpace() static 获取插件空间名
 * @method string getAppPackage() static 获取插件安装包
 */
abstract class Plugin extends Service
{
    /**
     * 必填，插件包名
     * @var string
     */
    protected $package = '';

    /**
     * 必填，插件编码
     * @var string
     */
    protected $appCode = '';

    /**
     * 必填，插件名称
     * @var string
     */
    protected $appName = '';

    /**
     * 可选，插件目录
     * @var string
     */
    protected $appPath = '';

    /**
     * 可选，插件别名
     * @var string
     */
    protected $appAlias = '';

    /**
     * 可选，命名空间
     * @var string
     */
    protected $appSpace = '';

    /**
     * 可选，注册服务
     * @var string
     */
    protected $appService = '';

    /**
     * 插件配置
     * @var array
     */
    private static $addons = [];

    /**
     * 自动注册插件
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);

        // 获取基础服务类
        $ref = new \ReflectionClass(static::class);

        // 应用服务注册类
        if (empty($this->appService)) {
            $this->appService = static::class;
        }

        // 应用命名空间名
        if (empty($this->appSpace)) {
            $this->appSpace = $ref->getNamespaceName();
        }

        // 应用插件路径计算
        if (empty($this->appPath) || !is_dir($this->appPath)) {
            $this->appPath = dirname($ref->getFileName());
        }

        // 应用插件包名计算
        if (empty($this->package) && ($path = $ref->getFileName())) {
            for ($level = 1; $level <= 3; $level++) {
                if (is_file($file = dirname($path, $level) . '/composer.json')) {
                    $this->package = json_decode(file_get_contents($file), true)['name'] ?? '';
                    break;
                }
            }
        }

        // 应用插件计算名称及别名
        $attr = explode('\\', $ref->getNamespaceName());
        if ($attr[0] === NodeService::space()) array_shift($attr);

        $this->appCode = $this->appCode ?: join('-', $attr);
        if ($this->appCode === $this->appAlias) $this->appAlias = '';

        if (is_dir($this->appPath)) {
            // 写入插件参数信息
            self::$addons[$this->appCode] = [
                'name'    => $this->appName,
                'path'    => realpath($this->appPath) . DIRECTORY_SEPARATOR,
                'alias'   => $this->appAlias,
                'space'   => $this->appSpace ?: NodeService::space($this->appCode),
                'package' => $this->package,
                'service' => $this->appService
            ];
            // 插件别名动态设置
            if (!empty($this->appAlias) && $this->appCode !== $this->appAlias) {
                Library::$sapp->config->set([
                    'app_map' => array_merge(Library::$sapp->config->get('app.app_map', []), [
                        $this->appAlias => $this->appCode
                    ]),
                ], 'app');
            }
        }
    }

    /**
     * 注册应用启动
     * @return void
     */
    public function boot(): void
    {
    }

    /**
     * 获取插件及安装信息
     * @param ?string $code 指定插件编号
     * @param boolean $append 关联安装数据
     * @return ?array
     */
    public static function get(?string $code = null, bool $append = false): ?array
    {
        // 读取插件原始信息
        $data = empty($code) ? self::$addons : (self::$addons[$code] ?? null);
        if (empty($data) || empty($append)) return $data;
        // 关联插件安装信息
        $versions = ModuleService::getLibrarys();
        return empty($code) ? array_map(static function ($item) use ($versions) {
            $item['install'] = $versions[$item['package']] ?? [];
            if (empty($item['name'])) $item['name'] = $item['install']['name'] ?? '';
            return $item;
        }, $data) : $data + ['install' => $versions[$data['package']] ?? []];
    }

    /**
     * 获取对象内部属性
     * @param string $name
     * @return null
     */
    public function __get(string $name)
    {
        return $this->$name ?? '';
    }

    /**
     * 静态调用方法兼容
     * @param string $method
     * @param array $arguments
     * @return array|string|null
     * @throws \think\admin\Exception
     */
    public static function __callStatic(string $method, array $arguments)
    {
        switch (strtolower($method)) {
            case 'all':
                return self::get(...$arguments);
            case 'getappcode':
                return app(static::class)->appCode;
            case 'getappname':
                return app(static::class)->appName;
            case 'getapppath':
                return app(static::class)->appPath;
            case 'getappspace':
                return app(static::class)->appSpace;
            case 'getapppackage';
                return app(static::class)->package;
            default:
                $class = basename(str_replace('\\', '/', static::class));
                throw new Exception("method not exists: {$class}::{$method}()");
        }
    }

    /**
     * 魔术方法调用兼容处理
     * @param string $method
     * @param array $arguments
     * @return array|null
     * @throws \think\admin\Exception
     */
    public function __call(string $method, array $arguments)
    {
        return self::__callStatic($method, $arguments);
    }

    /**
     * 定义插件菜单
     * @return array 一级或二级菜单
     */
    abstract public static function menu(): array;
}