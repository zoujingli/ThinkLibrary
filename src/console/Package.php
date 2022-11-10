<?php

namespace think\admin\console;

use think\admin\Command;
use think\admin\extend\ToolsExtend;

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
        $this->queue->message(2, 0, '开初始创建数据库迁移脚本！');
        if ($this->createScheme() && $this->createPackage()) {
            $this->setQueueSuccess('数据迁移脚本生成成功！');
        } else {
            $this->setQueueError('数据迁移脚本生成失败！');
        }
    }

    /**
     * 创建数据表
     * @return boolean
     * @throws \think\admin\Exception
     */
    private function createScheme(): bool
    {
        $this->queue->message(2, 1, '开始创建数据表创建脚本！');
        $result = ToolsExtend::create2phinx();
        $filename = with_path("database/migrations/{$result['file']}");
        file_put_contents($filename, $result['text']) !== false;
        $this->queue->message(2, 1, '成功创建数据表创建脚本！', 1);
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
        $this->queue->message(2, 2, '开始创建系统初始化脚本！');
        $result = ToolsExtend::create2package();
        $filename = with_path("database/migrations/{$result['file']}");
        file_put_contents($filename, $result['text']) !== false;
        $this->queue->message(2, 2, '成功创建系统初始化脚本！', 1);
        return true;
    }
}