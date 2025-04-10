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

use think\admin\Command;
use think\admin\service\SystemService;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

/**
 * 数据库修复优化指令
 * @class Database
 * @package think\admin\support\command
 */
class Database extends Command
{
    /**
     * 指令任务配置
     */
    public function configure()
    {
        $this->setName('xadmin:database');
        $this->addArgument('action', Argument::OPTIONAL, 'repair|optimize', 'optimize');
        $this->setDescription('Database Optimize and Repair for ThinkAdmin');
    }

    /**
     * 任务执行入口
     * @param \think\console\Input $input
     * @param \think\console\Output $output
     * @return void
     * @throws \think\admin\Exception
     */
    protected function execute(Input $input, Output $output): void
    {
        if ($this->app->db->connect()->getConfig('type') === 'sqlite') {
            $this->setQueueError("Sqlite 数据库不支持 REPAIR 和 OPTIMIZE 操作！");
        }
        $action = $input->getArgument('action');
        if (method_exists($this, $method = "_{$action}")) $this->$method();
        else $this->output->error('Wrong operation, currently allow repair|optimize');
    }

    /**
     * 修复所有数据表
     * @return void
     * @throws \think\admin\Exception
     */
    protected function _repair(): void
    {
        $this->setQueueProgress("正在获取需要修复的数据表", '0');
        [$tables, $total, $count] = SystemService::getTables();
        $this->setQueueProgress("总共需要修复 {$total} 张数据表", '0');
        foreach ($tables as $table) {
            $this->setQueueMessage($total, ++$count, "正在修复数据表 {$table}");
            $this->app->db->connect()->query("REPAIR TABLE `{$table}`");
            $this->setQueueMessage($total, $count, "完成修复数据表 {$table}", 1);
        }
        $this->setQueueSuccess("已完成对 {$total} 张数据表修复操作");
    }

    /**
     * 优化所有数据表
     * @return void
     * @throws \think\admin\Exception
     */
    protected function _optimize(): void
    {
        $this->setQueueProgress("正在获取需要优化的数据表", '0');
        [$tables, $total, $count] = SystemService::getTables();
        $this->setQueueProgress("总共需要优化 {$total} 张数据表", '0');
        foreach ($tables as $table) {
            $this->setQueueMessage($total, ++$count, "正在优化数据表 {$table}");
            $this->app->db->connect()->query("OPTIMIZE TABLE `{$table}`");
            $this->setQueueMessage($total, $count, "完成优化数据表 {$table}", 1);
        }
        $this->setQueueSuccess("已完成对 {$total} 张数据表优化操作");
    }
}