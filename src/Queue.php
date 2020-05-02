<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2020 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkLibrary
// | github 代码仓库：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin;

use think\admin\service\ProcessService;
use think\admin\service\QueueService;

/**
 * 任务基础类
 * Class Queue
 * @package think\admin
 */
abstract class Queue
{
    /**
     * 应用实例
     * @var \think\App
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
     * Queue constructor.
     * @param \think\App $app
     */
    public function __construct(\think\App $app)
    {
        $this->app = $app;
    }

    /**
     * 初始化任务数据
     * @param QueueService $queue
     * @return $this
     */
    public function initialize(QueueService $queue)
    {
        $this->queue = $queue;
        $this->process = ProcessService::instance();
        return $this;
    }

    /**
     * 执行任务处理内容
     * @param array $data
     * @return mixed
     */
    abstract public function execute(array $data = []);

    /**
     * 设置成功的消息
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueSuccessMessage($message)
    {
        throw new Exception($message, 3, $this->queue->code);
    }

    /**
     * 设置失败的消息
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueErrorMessage($message)
    {
        throw new Exception($message, 4, $this->queue->code);
    }
}