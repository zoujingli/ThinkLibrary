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

namespace think\admin\support\command;

use think\admin\extend\ToolsExtend;
use think\admin\service\ModuleService;
use think\admin\service\RuntimeService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 组件安装指令
 * @class Publish
 * @package think\admin\support\command
 */
class Publish extends Command
{

    /**
     * 任务参数配置
     * @return void
     */
    public function configure()
    {
        $this->setName('xadmin:publish');
        $this->addOption('force', 'f', Option::VALUE_NONE, 'Overwrite any existing files');
        $this->addOption('migrate', 'm', Option::VALUE_NONE, 'Execute phinx database script');
        $this->setDescription('Publish Plugs and Config Assets for ThinkAdmin');
    }

    /**
     * 任务合并执行
     * @param \think\console\Input $input
     * @param \think\console\Output $output
     * @return null|void
     */
    public function execute(Input $input, Output $output)
    {
        RuntimeService::clear(false);
        $this->parse()->plugin()->output->writeln('<info>Succeed!</info>');
    }

    /**
     * 安装数据库
     * @return $this
     */
    private function plugin(): Publish
    {
        // 执行子应用安装
        $force = boolval($this->input->getOption('force'));
        foreach (ModuleService::getModules() as $appName) {
            $appPath = $this->app->getBasePath() . $appName;
            is_dir($appPath) && $this->copy($appPath, $force);
        }
        // 执行数据库脚本
        if ($this->input->getOption('migrate')) {
            $this->app->console->call('migrate:run', [], 'console');
        }
        return $this;
    }

    /**
     * 初始化组件文件
     * @param string $copy 应用资源目录
     * @param boolean $force 是否强制替换
     * @return void
     */
    private function copy(string $copy, bool $force = false)
    {
        // 复制系统配置文件
        $frdir = rtrim($copy, '\\/') . DIRECTORY_SEPARATOR . 'config';
        ToolsExtend::copy($frdir, syspath('config'), [], $force, false);

        // 复制静态资料文件
        $frdir = rtrim($copy, '\\/') . DIRECTORY_SEPARATOR . 'public';
        ToolsExtend::copy($frdir, syspath('public'), [], true, false);

        // 复制数据库脚本
        $frdir = rtrim($copy, '\\/') . DIRECTORY_SEPARATOR . 'database';
        ToolsExtend::copy($frdir, syspath('database/migrations'), [], $force, false);
    }

    /**
     * 解析 json 包
     * @return $this
     */
    private function parse(): Publish
    {
        [$services, $versions] = [[], []];
        if (is_file($file = syspath('vendor/composer/installed.json'))) {
            $packages = json_decode(@file_get_contents($file), true);
            foreach ($packages['packages'] ?? $packages as $package) {
                // 生成组件版本
                $type = $package['type'] ?? '';
                $config = $package['extra']['config'] ?? [];
                $versions[$package['name']] = [
                    'type'        => $config['type'] ?? ($type === 'think-admin-plugin' ? 'plugin' : 'library'),
                    'name'        => $config['name'] ?? ($package['name'] ?? ''),
                    'icon'        => $config['icon'] ?? '',
                    'cover'       => $config['cover'] ?? '',
                    'super'       => $config['super'] ?? false,
                    'license'     => (array)($config['license'] ?? ($package['license'] ?? [])),
                    'version'     => $config['version'] ?? ($package['version'] ?? ''),
                    'homepage'    => $config['homepage'] ?? ($package['homepage'] ?? ''),
                    'document'    => $config['document'] ?? ($package['document'] ?? ''),
                    'platforms'   => $config['platforms'] ?? [],
                    'description' => $config['description'] ?? ($package['description'] ?? ''),
                ];
                // 生成服务配置
                if (!empty($package['extra']['think']['services'])) {
                    $services = array_merge($services, (array)$package['extra']['think']['services']);
                }
                // 复制配置文件
                if (!empty($package['extra']['think']['config'])) {
                    $configPath = $this->app->getConfigPath();
                    $installPath = syspath("vendor/{$package['name']}/");
                    foreach ((array)$package['extra']['think']['config'] as $name => $file) {
                        if (is_file($target = $configPath . $name . '.php')) {
                            $this->output->info("File {$target} exist!");
                            continue;
                        }
                        if (!is_file($source = $installPath . $file)) {
                            $this->output->info("File {$source} not exist!");
                            continue;
                        }
                        copy($source, $target);
                    }
                }
            }
        }

        // 写入服务配置
        $header = "// Automatically Generated At: " . date('Y-m-d H:i:s') . PHP_EOL . 'declare(strict_types=1);';
        $content = '<?php' . PHP_EOL . $header . PHP_EOL . 'return ' . var_export($services, true) . ';';
        @file_put_contents(syspath('vendor/services.php'), $content);

        // 写入组件版本
        $content = '<?php' . PHP_EOL . $header . PHP_EOL . 'return ' . var_export($versions, true) . ';';
        @file_put_contents(syspath('vendor/versions.php'), preg_replace('#\s+=>\s+array\s+\(#m', ' => array (', $content));

        return $this;
    }
}