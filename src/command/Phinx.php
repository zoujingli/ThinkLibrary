<?php

namespace think\admin\command;

use think\admin\Command;
use think\admin\extend\ToolsExtend;

/**
 * 生成安装数据包
 * Class Phinx
 * @package think\admin\command
 */
class Phinx extends Command
{
    /**
     * 系统指定配置
     * @return void
     */
    public function configure()
    {
        $this->setName('xadmin:sysphinx');
        $this->setDescription('Generate system install package for ThinkAdmin');
    }

    /**
     * 生成系统安装数据包
     * @return void
     * @throws \think\admin\Exception
     */
    public function handle()
    {
        if ($this->_createScheme() && $this->_createPackage()) {
            $this->output->writeln('数据迁移脚本生成成功！');
        } else {
            $this->output->error('数据迁移脚本生成失败！');
        }
    }

    /**
     * 创建数据表
     * @return boolean
     * @throws \think\admin\Exception
     */
    private function _createScheme(): bool
    {
        $result = ToolsExtend::create2phinx();
        $filename = with_path("database/migrations/{$result['file']}");
        return file_put_contents($filename, $result['text']) !== false;
    }

    /**
     * 创建数据包
     * @return boolean
     */
    private function _createPackage(): bool
    {
        return true;
    }
}