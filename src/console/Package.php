<?php

namespace think\admin\console;

use think\admin\Command;
use think\admin\extend\ToolsExtend;
use think\console\input\Argument;

/**
 * 生成数据安装包
 * Class Package
 * @package think\admin\command
 */
class Package extends Command
{
    /**
     * 系统指定配置
     * @return void
     */
    public function configure()
    {
        $this->setName('xadmin:package');
        $this->addArgument('table', Argument::OPTIONAL, 'Packaging Tables', '');
        $this->setDescription('Generate System Install Package for ThinkAdmin');
    }

    /**
     * 生成系统安装数据包
     * @return void
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function handle()
    {
        // 创建数据库迁移脚本目录
        $dirname = with_path('database/migrations');
        file_exists($dirname) or mkdir($dirname, 0755, true);
        // 开始创建数据库迁移脚本
        $this->output->writeln('--- 开始创建数据库迁移脚本 ---');
        if ($this->createScheme() && $this->createPackage()) {
            $this->setQueueSuccess('--- 数据库迁移脚本创建成功 ---');
        } else {
            $this->setQueueError('--- 数据库迁移脚本创建失败 ---');
        }
    }

    /**
     * 创建数据表
     * @return boolean
     * @throws \think\admin\Exception
     */
    private function createScheme(): bool
    {
        $this->setQueueMessage(2, 1, '开始创建数据表创建脚本！');
        $result = ToolsExtend::create2phinx();
        $filename = with_path("database/migrations/{$result['file']}");
        file_put_contents($filename, $result['text']) !== false;
        $this->setQueueMessage(2, 1, '成功创建数据表创建脚本！', 1);
        return true;
    }

    /**
     * 创建数据包
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function createPackage(): bool
    {
        $this->setQueueMessage(2, 2, '开始创建系统初始化脚本！');
        $result = ToolsExtend::create2package(str2arr($this->input->getArgument('tables')));
        $filename = with_path("database/migrations/{$result['file']}");
        file_put_contents($filename, $result['text']) !== false;
        $this->setQueueMessage(2, 2, '成功创建系统初始化脚本！', 1);
        return true;
    }
}