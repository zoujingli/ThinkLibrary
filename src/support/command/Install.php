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

namespace think\admin\support\command;

use think\admin\Command;
use think\admin\extend\ToolsExtend;
use think\admin\service\ModuleService;
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
     * 规则配置
     * @var array
     */
    protected $bind = [
        'admin'  => [
            'rules'  => ['app/admin'],
            'ignore' => [],
        ],
        'wechat' => [
            'rules'  => ['app/wechat'],
            'ignore' => [],
        ],
        'config' => [
            'rules'  => [
                'think',
                'config/app.php',
                'config/log.php',
                'config/route.php',
                'config/trace.php',
                'config/view.php',
                'public/index.php',
                'public/router.php',
            ],
            'ignore' => [],
        ],
        'static' => [
            'rules'  => [
                'public/static/plugs',
                'public/static/theme',
                'public/static/admin.js',
                'public/static/login.js',
            ],
            'ignore' => [],
        ],
    ];

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
     */
    protected function execute(Input $input, Output $output)
    {
        $name = trim($input->getArgument('name'));
        if (empty($name)) {
            $output->writeln('待安装或更新的模块名称不能为空！');
        } elseif (isset($this->bind[$name])) {
            [$rules, $ignore] = [$this->bind[$name]['rules'] ?? [], $this->bind[$name]['ignore'] ?? []];
            if (in_array($name, ['admin', 'wechat', 'data']) && !file_exists($this->app->getBasePath() . $name)) {
                $this->install($name, $rules, $ignore);
            } elseif ($output->confirm($input, "安全警告：安装 {$name} 模块，将会替换或删除本地文件！")) {
                $this->install($name, $rules, $ignore);
            } else {
                $output->info("未执行，未同意安装模块！");
            }
        } else {
            $output->error("未执行，待安装或更新的模块[ {$name} ] 不存在！");
        }
    }

    /**
     * 安装本地文件
     * @param string $name 更新模块
     * @param array $rules 检查规则
     * @param array $ignore 忽略规则
     * @return void
     */
    private function install(string $name, array $rules = [], array $ignore = [])
    {
        // 更新模块文件
        $data = ModuleService::grenDifference($rules, $ignore);
        if (empty($data)) {
            $this->output->writeln('未发现有变更的文件，不需要进行更新！');
            return;
        }
        [$total, $count] = [count($data), 0];
        foreach ($data as $file) {
            [$state, $mode, $base] = ModuleService::updateFileByDownload($file);
            if ($state) {
                if ($mode === 'add') $this->queue->message($total, ++$count, "--- {$base} 添加成功");
                if ($mode === 'mod') $this->queue->message($total, ++$count, "--- {$base} 更新成功");
                if ($mode === 'del') $this->queue->message($total, ++$count, "--- {$base} 删除成功");
            } else {
                if ($mode === 'add') $this->queue->message($total, ++$count, "--- {$base} 添加失败");
                if ($mode === 'mod') $this->queue->message($total, ++$count, "--- {$base} 更新失败");
                if ($mode === 'del') $this->queue->message($total, ++$count, "--- {$base} 删除失败");
            }
        }

        // 指定模块初始化
        if ($name === 'static') {
            $todir = with_path('public/static/extra/');
            $frdir = dirname(__DIR__, 2) . "/service/bin/{$name}/";
            $this->queue->message($total, $count, "--- 处理静态自定义目录");
            ToolsExtend::copyfile($frdir, $todir, ['script.js', 'style.css'], false, false);
        }

        // 执行模块数据库操作
        $frdir = with_path("{$name}/database", $this->app->getBasePath());
        $todir = with_path('database/migrations', $this->app->getRootPath());
        $this->queue->message($total, $count, "--- 处理数据库可执行脚本");
        ToolsExtend::copyfile($frdir, $todir) && $this->app->console->call('migrate:run');
    }
}