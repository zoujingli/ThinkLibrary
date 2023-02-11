<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2023 Anyon <zoujingli@qq.com>
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// | 免费声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin\support\command;

use think\admin\Command;
use think\admin\Plugin;
use think\admin\service\ModuleService;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 组件安装指令
 * Class Publish
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
        $this->parse()->plugin()->output->writeln('<info>Succeed!</info>');
    }

    /**
     * 安装数据库
     * @return $this
     */
    private function plugin(): Publish
    {
        $force = boolval($this->input->getOption('force'));
        foreach (Plugin::all() as $plugin) {
            ModuleService::copy($plugin['copy'], $force);
        }
        // 执行数据库脚本
        $this->app->console->call('migrate:run', [], 'console');
        return $this;
    }

    /**
     * 解析 json 包
     * @return $this
     */
    private function parse(): Publish
    {
        [$services, $versions] = [[], []];
        if (file_exists($file = syspath('vendor/composer/installed.json'))) {
            $packages = json_decode(@file_get_contents($file), true);
            foreach ($packages['packages'] ?? $packages as $package) {
                $versions[$package['name']] = $package['version'];
                if (!empty($package['extra']['think']['services'])) {
                    $services = array_merge($services, (array)$package['extra']['think']['services']);
                }
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
        file_put_contents(syspath('vendor/services.php'), $content);

        // 写入版本配置
        $content = '<?php' . PHP_EOL . $header . PHP_EOL . 'return ' . var_export($versions, true) . ';';
        file_put_contents(syspath('vendor/versions.php'), $content);

        return $this;
    }
}