<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | ThinkAdmin Plugin for ThinkAdmin
 * +----------------------------------------------------------------------
 * | 版权所有 2014~2026 ThinkAdmin [ thinkadmin.top ]
 * +----------------------------------------------------------------------
 * | 官方网站: https://thinkadmin.top
 * +----------------------------------------------------------------------
 * | 开源协议 ( https://mit-license.org )
 * | 免责声明 ( https://thinkadmin.top/disclaimer )
 * | 会员特权 ( https://thinkadmin.top/vip-introduce )
 * +----------------------------------------------------------------------
 * | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
 * | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
 * +----------------------------------------------------------------------
 */

namespace think\admin;

use think\admin\service\ProcessService;
use think\admin\service\QueueService;
use think\App;

/**
 * 任务基础类.
 * @class Queue
 */
abstract class Queue
{
    /**
     * 应用实例.
     * @var App
     */
    protected $app;

    /**
     * 任务控制服务
     * @var QueueService
     */
    protected $queue;

    /**
     * 进程控制服务
     * @var ProcessService
     */
    protected $process;

    /**
     * Constructor.
     */
    public function __construct(App $app, ProcessService $process)
    {
        $this->app = $app;
        $this->process = $process;
    }

    /**
     * 初始化任务数据.
     * @return $this
     */
    public function initialize(QueueService $queue): Queue
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * 执行任务处理内容.
     * @return string|void
     */
    abstract public function execute(array $data = []);

    /**
     * 设置失败的消息.
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueError(string $message)
    {
        $this->queue->error($message);
    }

    /**
     * 设置成功的消息.
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueSuccess(string $message)
    {
        $this->queue->success($message);
    }

    /**
     * 更新任务进度.
     * @param int $total 记录总和
     * @param int $count 当前记录
     * @param string $message 文字描述
     * @param int $backline 回退行数
     * @return static
     * @throws Exception
     */
    protected function setQueueMessage(int $total, int $count, string $message = '', int $backline = 0): Queue
    {
        $this->queue->message($total, $count, $message, $backline);
        return $this;
    }

    /**
     * 设置任务的进度.
     * @param ?string $message 进度消息
     * @param ?string $progress 进度数值
     * @param int $backline 回退行数
     * @throws Exception
     */
    protected function setQueueProgress(?string $message = null, ?string $progress = null, int $backline = 0): Queue
    {
        $this->queue->progress(2, $message, $progress, $backline);
        return $this;
    }
}
