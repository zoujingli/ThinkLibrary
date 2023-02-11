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

declare (strict_types=1);

namespace think\admin\support\command;

use think\admin\Command;
use think\admin\service\ProcessService;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

/**
 * 插件更新安装指令
 * Class Install
 * @package think\admin\support\command
 */
class Install extends Command
{

    /**
     * 指令任务配置
     */
    protected function configure()
    {
        $this->setName('xadmin:install');
        $this->addArgument('name', Argument::OPTIONAL, 'ModuleName', '');
        $this->setDescription("Install and Update Source code for ThinkAdmin");
    }

    /**
     * 任务执行入口
     * @param \think\console\Input $input
     * @param \think\console\Output $output
     * @return void
     * @throws \think\admin\Exception
     */
    protected function execute(Input $input, Output $output)
    {
        // 获取待操作插件名称
        $name = trim($input->getArgument('name'));
        if (empty($name)) $output->writeln('待安装或更新的插件不能为空！');

        // 兼容历史安装更新
        if ($name === 'static' || $name === 'config') {
            $this->install($name, 'zoujingli/think-plugs-static');
        } elseif ($name === 'admin') {
            $this->install($name, 'zoujingli/think-plugs-admin');
        } elseif ($name === 'wechat') {
            $this->install($name, 'zoujingli/think-plugs-wechat');
        } else {
            $this->setQueueError("待安装或更新的模块[ {$name} ] 不存在！");
        }
    }

    private function install(string $name, string $package)
    {
        $json = @json_decode(file_get_contents(syspath('composer.json')), true);
        if (empty($json['require'][$package])) {
            if ($this->output->confirm($this->input, "安全警告：安装 {$name} 模块将升级为插件模式，确定要安装吗？")) {
                $this->doInstall($package);
            }
        } else {
            $this->doInstall($package);
        }
    }

    private function doInstall(string $package)
    {
        $this->output->writeln(">$ composer require {$package} -vvv");
        ProcessService::system("composer require {$package} -vvv");
    }
}