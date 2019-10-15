<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace library\process;

use library\Process;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * 启动监听异步任务守护的主进程
 * Class Listen
 * @package library\process
 */
class Listen extends Command
{
    /**
     * 配置指定信息
     */
    protected function configure()
    {
        $this->setName('xtask:listen')->setDescription('[监听]常驻异步任务循环监听主进程');
    }

    /**
     * 执行进程守护监听
     * @param Input $input
     * @param Output $output
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function execute(Input $input, Output $output)
    {
        Db::name('SystemQueue')->count();
        $output->comment('============ 异步任务监听中 ============');
        if (Process::iswin() && function_exists('cli_set_process_title')) {
            cli_set_process_title("ThinkAdmin " . Process::version() . " 异步任务监听主进程");
        }
        while (true) {
            foreach (Db::name('SystemQueue')->where([['status', 'eq', '1'], ['time', '<=', time()]])->order('time asc')->select() as $item) {
                try {
                    Db::name('SystemQueue')->where(['id' => $item['id']])->update(['status' => '2', 'start_at' => date('Y-m-d H:i:s')]);
                    $command = Process::think("xtask:_work {$item['id']} -");
                    if (Process::query($command)) {
                        $output->comment("处理任务的子进程已经存在 --> [{$item['id']}] {$item['title']}");
                    } else {
                        Process::create($command);
                        $output->info("创建处理任务的子进程成功 --> [{$item['id']}] {$item['title']}");
                    }
                } catch (\Exception $e) {
                    Db::name('SystemQueue')->where(['id' => $item['id']])->update(['status' => '4', 'desc' => $e->getMessage()]);
                    $output->error("创建处理任务的子进程失败 --> [{$item['id']}] {$item['title']}，{$e->getMessage()}");
                }
            }
            sleep(2);
        }
    }

}
