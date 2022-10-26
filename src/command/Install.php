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

namespace think\admin\command;

use think\admin\Command;
use think\admin\extend\ToolsExtend;
use think\admin\service\ModuleService;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

/**
 * 插件更新安装指令
 * Class Install
 * @package think\admin\command
 */
class Install extends Command
{

    /**
     * 指定模块名称
     * @var string
     */
    protected $name;

    /**
     * 查询规则
     * @var array
     */
    protected $rules = [];

    /**
     * 忽略规则
     * @var array
     */
    protected $ignore = [];

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
        $this->setDescription("Source code Install and Update for ThinkAdmin");
    }

    /**
     * 任务执行入口
     * @param \think\console\Input $input
     * @param \think\console\Output $output
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $this->name = trim($input->getArgument('name'));
        if (empty($this->name)) {
            $this->output->writeln('Module name of online install cannot be empty');
        } elseif ($this->name === 'all') {
            foreach ($this->bind as $bind) {
                $this->rules = array_merge($this->rules, $bind['rules']);
                $this->ignore = array_merge($this->ignore, $bind['ignore']);
            }
            $this->install($this->name);
        } elseif (isset($this->bind[$this->name])) {
            $this->rules = $this->bind[$this->name]['rules'] ?? [];
            $this->ignore = $this->bind[$this->name]['ignore'] ?? [];
            $this->install($this->name);
        } else {
            $this->output->writeln("The specified module {$this->name} is not configured with install rules");
        }
    }

    /**
     * 安装本地文件
     * @param string $name
     * @return boolean
     */
    protected function install(string $name): bool
    {
        // 同步模块文件
        $data = ModuleService::grenDifference($this->rules, $this->ignore);
        if (empty($data)) {
            $this->output->writeln('No need to update the file if the file comparison is consistent');
            return false;
        }
        [$total, $count] = [count($data), 0];
        foreach ($data as $file) {
            [$state, $mode, $base] = ModuleService::updateFileByDownload($file);
            if ($state) {
                if ($mode === 'add') $this->queue->message($total, ++$count, "--- {$base} add successfully");
                if ($mode === 'mod') $this->queue->message($total, ++$count, "--- {$base} update successfully");
                if ($mode === 'del') $this->queue->message($total, ++$count, "--- {$base} delete successfully");
            } else {
                if ($mode === 'add') $this->queue->message($total, ++$count, "--- {$base} add failed");
                if ($mode === 'mod') $this->queue->message($total, ++$count, "--- {$base} update failed");
                if ($mode === 'del') $this->queue->message($total, ++$count, "--- {$base} delete failed");
            }
        }

        // 指定模块初始化
        if ($name === 'static') {
            $todir = with_path('public/static/extra/');
            $frdir = dirname(__DIR__) . "/service/bin/{$name}/";
            $this->queue->message($total, $count, "--- copy static/extra files");
            ToolsExtend::copyfile($frdir, $todir, ['script.js', 'style.css'], false, false);
        }

        // 执行模块数据库操作
        $frdir = with_path("{$name}/database", $this->app->getBasePath());
        $todir = with_path('database/migrations', $this->app->getRootPath());
        $this->queue->message($total, $count, "--- copy database upgrade files");
        ToolsExtend::copyfile($frdir, $todir) && $this->app->console->call('migrate:run');
        return true;
    }
}