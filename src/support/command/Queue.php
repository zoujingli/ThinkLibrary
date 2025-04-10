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

use Error;
use Exception;
use Psr\Log\NullLogger;
use think\admin\Command;
use think\admin\model\SystemQueue;
use think\admin\service\QueueService;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

/**
 * 异步任务管理指令
 * @class Queue
 * @package think\admin\support\command
 */
class Queue extends Command
{

    /**
     * 任务等待处理
     * @var integer
     */
    const STATE_WAIT = 1;

    /**
     * 任务正在处理
     * @var integer
     */
    const STATE_LOCK = 2;

    /**
     * 任务处理完成
     * @var integer
     */
    const STATE_DONE = 3;

    /**
     * 任务处理失败
     * @var integer
     */
    const STATE_ERROR = 4;

    /**
     * 监听进程指令
     * @var string
     */
    const QUEUE_LISTEN = 'xadmin:queue listen';

    /**
     * 当前任务编号
     * @var string
     */
    protected $code;

    /**
     * 指令任务配置
     */
    public function configure()
    {
        $this->setName('xadmin:queue');
        $this->addOption('host', '-H', Option::VALUE_OPTIONAL, 'The host of WebServer.');
        $this->addOption('port', '-p', Option::VALUE_OPTIONAL, 'The port of WebServer.');
        $this->addOption('daemon', 'd', Option::VALUE_NONE, 'The queue listen in daemon mode');
        $this->addArgument('action', Argument::OPTIONAL, 'stop|start|status|query|listen|clean|dorun|webstop|webstart|webstatus', 'listen');
        $this->addArgument('code', Argument::OPTIONAL, 'Taskcode');
        $this->addArgument('spts', Argument::OPTIONAL, 'Separator');
        $this->setDescription('Asynchronous Command Queue Task for ThinkAdmin');
    }

    /**
     * 任务执行入口
     * @param Input $input
     * @param Output $output
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $action = $input->hasOption('daemon') ? 'start' : $input->getArgument('action');
        if (method_exists($this, $method = "{$action}Action")) return $this->$method();
        $this->output->error("># Wrong operation, Allow stop|start|status|query|listen|clean|dorun|webstop|webstart|webstatus");
    }

    /**
     * 停止 WebServer 调试进程
     */
    protected function webStopAction()
    {
        $root = syspath('public/');
        if (count($result = $this->process->query("{$root} {$root}router.php")) < 1) {
            $this->output->writeln("># There are no WebServer processes to stop");
        } else foreach ($result as $item) {
            $this->process->close(intval($item['pid']));
            $this->output->writeln("># Successfully sent end signal to process {$item['pid']}");
        }
    }

    /**
     * 启动 WebServer 调试进程
     */
    protected function webStartAction()
    {
        $prot = 'http';
        $port = $this->input->getOption('port') ?: '80';
        $host = $this->input->getOption('host') ?: '127.0.0.1';
        $root = syspath('public' . DIRECTORY_SEPARATOR);
        $command = "php -S {$host}:{$port} -t {$root} {$root}router.php";
        $this->output->comment(">$ {$command}");
        if (count($result = $this->process->query($command)) > 0) {
            if ($this->process->isWin()) $this->process->exec("start {$prot}://{$host}:{$port}");
            $this->output->writeln("># WebServer process already exist for pid {$result[0]['pid']}");
        } else {
            $this->process->create($command, 2000);
            if (count($result = $this->process->query($command)) > 0) {
                $this->output->writeln("># WebServer process started successfully for pid {$result[0]['pid']}");
                if ($this->process->isWin()) $this->process->exec("start {$prot}://{$host}:{$port}");
            } else {
                $this->output->writeln('># WebServer process failed to start');
            }
        }
    }

    /**
     * 查看 WebServer 调试进程
     */
    protected function webStatusAction()
    {
        $root = syspath('public' . DIRECTORY_SEPARATOR);
        if (count($result = $this->process->query("{$root} {$root}router.php")) > 0) {
            $this->output->comment(">$ {$result[0]['cmd']}");
            $this->output->writeln("># WebServer process {$result[0]['pid']} running");
        } else {
            $this->output->writeln("># The WebServer process is not running");
        }
    }

    /**
     * 停止所有任务
     */
    protected function stopAction()
    {
        if (count($result = $this->process->thinkQuery('xadmin:queue')) < 1) {
            $this->output->writeln("># There are no task processes to stop");
        } else foreach ($result as $item) {
            $this->process->close(intval($item['pid']));
            $this->output->writeln("># Successfully sent end signal to process {$item['pid']}");
        }
    }

    /**
     * 启动后台任务
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function startAction()
    {
        SystemQueue::mk()->count();
        $this->output->comment(">$ {$this->process->think(static::QUEUE_LISTEN)}");
        if (count($result = $this->process->thinkQuery(static::QUEUE_LISTEN)) > 0) {
            if (is_file($lock = syspath('runtime/cache/time.queue')) && intval(file_get_contents($lock)) + 60 < time()) {
                $this->output->writeln("># The task monitoring delay has exceeded 60 seconds, and the monitoring will be restarted.");
                $this->process->close(intval($result[0]['pid'])) && $this->process->thinkExec(static::QUEUE_LISTEN, 1000);
            } else {
                $this->output->writeln("># Queue daemons already exist for pid {$result[0]['pid']}");
            }
        } else {
            $this->process->thinkExec(static::QUEUE_LISTEN, 1000);
            if (count($result = $this->process->thinkQuery(static::QUEUE_LISTEN)) > 0) {
                $this->output->writeln("># Queue daemons started successfully for pid {$result[0]['pid']}");
            } else {
                $this->output->writeln("># Queue daemons failed to start");
            }
        }
    }

    /**
     * 查询所有任务
     */
    protected function queryAction()
    {
        $items = $this->process->thinkQuery('xadmin:queue');
        if (count($items) > 0) foreach ($items as $item) {
            $this->output->writeln("># {$item['pid']}\t{$item['cmd']}");
        } else {
            $this->output->writeln('># No related task process found');
        }
    }

    /**
     * 清理所有任务
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DbException
     */
    protected function cleanAction()
    {
        // 清理任务历史记录
        $days = intval(sysconf('base.queue_clean_days|raw') ?: 7);
        $clean = SystemQueue::mk()->where('exec_time', '<', time() - $days * 24 * 3600)->delete();
        // 标记超过 1 小时未完成的任务为失败状态，循环任务失败重置
        $map1 = [['loops_time', '>', 0], ['status', '=', static::STATE_ERROR]]; // 执行失败的循环任务
        $map2 = [['exec_time', '<', time() - 3600], ['status', '=', static::STATE_LOCK]]; // 执行超时的任务
        [$timeout, $loops, $total] = [0, 0, SystemQueue::mk()->whereOr([$map1, $map2])->count()];
        foreach (SystemQueue::mk()->whereOr([$map1, $map2])->cursor() as $queue) {
            $queue['loops_time'] > 0 ? $loops++ : $timeout++;
            if ($queue['loops_time'] > 0) {
                $this->queue->message($total, $timeout + $loops, "正在重置任务 {$queue['code']} 为运行");
                [$status, $message] = [static::STATE_WAIT, $queue['status'] === static::STATE_ERROR ? '任务执行失败，已自动重置任务！' : '任务执行超时，已自动重置任务！'];
            } else {
                $this->queue->message($total, $timeout + $loops, "正在标记任务 {$queue['code']} 为超时");
                [$status, $message] = [static::STATE_ERROR, '任务执行超时，已自动标识为失败！'];
            }
            $queue->save(['status' => $status, 'exec_desc' => $message]);
        }
        $this->setQueueSuccess("清理 {$clean} 条历史任务，关闭 {$timeout} 条超时任务，重置 {$loops} 条循环任务");
    }

    /**
     * 查询兼听状态
     */
    protected function statusAction()
    {
        if (count($result = $this->process->thinkQuery(static::QUEUE_LISTEN)) > 0) {
            $this->output->writeln("Listening for main process {$result[0]['pid']} running");
        } else {
            $this->output->writeln("The Listening main process is not running");
        }
    }

    /**
     * 启动任务监听
     * @return void
     */
    protected function listenAction()
    {
        try {
            set_time_limit(0) && PHP_SAPI !== 'cli' && ignore_user_abort(true);
            $this->app->db->setLog(new NullLogger());
            $this->createListenProcess();
        } catch (Exception $exception) {
            trace_file($exception) && usleep(3000000);
            $this->output->write('=============== EXCEPTION ===============');
            $this->output->write($exception->getMessage());
            $this->output->writeln('=============== TRY-REBOOT ===============');
            $this->createListenProcess();
        }
    }

    /**
     * 执行任务监听
     * @return void
     */
    private function createListenProcess()
    {
        $this->output->writeln("\n\tYou can exit with <info>`CTRL-C`</info>");
        $this->output->writeln('=============== LISTENING ===============');
        while (true) {
            @file_put_contents(syspath('runtime/cache/time.queue'), strval(time()));
            [$map, $start] = [[['status', '=', static::STATE_WAIT], ['exec_time', '<=', time()]], microtime(true)];
            foreach (SystemQueue::mk()->where($map)->order('exec_time asc')->cursor() as $queue) try {
                $args = "xadmin:queue dorun {$queue['code']} -";
                $this->output->comment(">$ {$this->process->think($args)}");
                if (count($this->process->thinkQuery($args)) > 0) {
                    $this->output->writeln("># Already in progress -> [{$queue['code']}] {$queue['title']}");
                } else {
                    $this->process->thinkExec($args);
                    $this->output->writeln("># Created new process -> [{$queue['code']}] {$queue['title']}");
                }
            } catch (Exception $exception) {
                $queue->save(['status' => static::STATE_ERROR, 'outer_time' => time(), 'exec_desc' => $exception->getMessage()]);
                $this->output->error("># Execution failed -> [{$queue['code']}] {$queue['title']}，{$exception->getMessage()}");
            }
            if (microtime(true) < $start + 1) usleep(1000000);
        }
    }

    /**
     * 执行指定任务
     * @return void
     * @throws \think\admin\Exception
     */
    protected function doRunAction()
    {
        $this->code = trim($this->input->getArgument('code'));
        if (empty($this->code)) {
            $this->output->error('Task number needs to be specified for task execution');
        } else try {
            set_time_limit(0) && PHP_SAPI !== 'cli' && ignore_user_abort(true);
            $this->queue->initialize($this->code);
            if (empty($this->queue->record) || intval($this->queue->record->getAttr('status')) !== static::STATE_WAIT) {
                // 这里不做任何处理（该任务可能在其它地方已经在执行）
                $this->output->warning("The or status of task {$this->code} is abnormal");
            } else {
                // 锁定任务状态，防止任务再次被执行
                SystemQueue::mk()->strict(false)->where(['code' => $this->code])->inc('attempts')->update([
                    'enter_time' => microtime(true), 'outer_time' => 0, 'exec_pid' => getmypid(), 'exec_desc' => '', 'status' => static::STATE_LOCK,
                ]);
                $this->queue->progress(2, '>>> 任务处理开始 <<<', '0');
                // 执行任务内容
                defined('WorkQueueCall') or define('WorkQueueCall', true);
                defined('WorkQueueCode') or define('WorkQueueCode', $this->code);
                if (class_exists($command = $this->queue->record->getAttr('command'))) {
                    // 自定义任务，支持返回消息（支持异常结束，异常码可选择 3|4 设置任务状态）
                    /**@var \think\admin\Queue|QueueService $class */
                    $class = $this->app->make($command, [], true);
                    if ($class instanceof \think\admin\Queue) {
                        $this->updateQueue(static::STATE_DONE, $class->initialize($this->queue)->execute($this->queue->data) ?: '');
                    } elseif ($class instanceof QueueService) {
                        $this->updateQueue(static::STATE_DONE, $class->initialize($this->queue->code)->execute($this->queue->data) ?: '');
                    } else {
                        throw new \think\admin\Exception("自定义 {$command} 未继承 think\admin\Queue 或 think\admin\service\QueueService");
                    }
                } else {
                    // 自定义指令，不支持返回消息（支持异常结束，异常码可选择 3|4 设置任务状态）
                    $attr = explode(' ', trim(preg_replace('|\s+|', ' ', $command)));
                    $this->updateQueue(static::STATE_DONE, $this->app->console->call(array_shift($attr), $attr)->fetch(), false);
                }
            }
        } catch (Exception|Throwable|Error $exception) {
            $isDone = intval($exception->getCode()) === static::STATE_DONE;
            $this->updateQueue($isDone ? static::STATE_DONE : static::STATE_ERROR, $exception->getMessage());
        }
    }

    /**
     * 修改当前任务状态
     * @param integer $status 任务状态
     * @param string $message 消息内容
     * @param boolean $isSplit 是否分隔
     * @throws \think\admin\Exception
     */
    private function updateQueue(int $status, string $message, bool $isSplit = true)
    {
        // 更新当前任务
        $desc = $isSplit ? explode("\n", trim($message)) : [$message];
        SystemQueue::mk()->strict(false)->where(['code' => $this->code])->update([
            'status' => $status, 'outer_time' => microtime(true), 'exec_pid' => getmypid(), 'exec_desc' => $desc[0],
        ]);
        $this->process->message($message);
        // 任务进度标记
        if (!empty($desc[0])) {
            $this->queue->progress($status, ">>> {$desc[0]} <<<");
        }
        // 任务状态标记
        if ($status === static::STATE_DONE) {
            $this->queue->progress($status, '>>> 任务处理完成 <<<', '100.00');
        } elseif ($status === static::STATE_ERROR) {
            $this->queue->progress($status, '>>> 任务处理失败 <<<');
        }
        // 注册循环任务
        if (($time = intval($this->queue->record->getAttr('loops_time'))) > 0) try {
            $this->queue->initialize($this->code)->reset($time);
        } catch (Exception|Throwable|Error $exception) {
            $this->app->log->error("Queue {$this->queue->record->getAttr('code')} Loops Failed. {$exception->getMessage()}");
        }
    }
}