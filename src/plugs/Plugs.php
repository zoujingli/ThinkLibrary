<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin\plugs;

use think\admin\extend\PlugsExtend;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 插件基础指令类
 * Class Plugs
 * @package think\admin\plugs
 */
class Plugs extends Command
{
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
     * @param Input $input
     * @param Output $output
     */
    protected function execute(Input $input, Output $output)
    {
        $extend = PlugsExtend::instance($this->app);
        $output->comment("=== 准备从代码仓库下载更新{$extend->getVersion()}版本文件 ===");
        $data = $extend->grenerateDifference($this->rules, $this->ignore);
        if (empty($data)) $output->info('--- 本地文件与线上文件一致，无需更新文件');
        else foreach ($data as $file) {
            list($state, $mode, $name) = $extend->fileSynchronization($file);
            if ($state) {
                if ($mode === 'add') $this->output->info("--- 下载 {$name} 添加成功");
                if ($mode === 'mod') $this->output->info("--- 下载 {$name} 更新成功");
                if ($mode === 'del') $this->output->info("--- 删除 {$name} 文件成功");
            } else {
                if ($mode === 'add') $this->output->error("--- 下载 {$name} 添加失败");
                if ($mode === 'mod') $this->output->error("--- 下载 {$name} 更新失败");
                if ($mode === 'del') $this->output->error("--- 删除 {$name} 文件失败");
            }
        }
        $output->comment("=== 从代码仓库下载{$extend->getVersion()}版本同步更新成功 ===");
        $this->install();
    }

    protected function install()
    {

    }

}