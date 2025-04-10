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

namespace think\admin\service;

use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\admin\model\SystemQueue;
use think\admin\Service;

/**
 * 任务基础服务
 * @class QueueService
 * @package think\admin\service
 */
class QueueService extends Service
{

    /**
     * 当前任务编号
     * @var string
     */
    public $code = '';

    /**
     * 当前任务标题
     * @var string
     */
    public $title = '';

    /**
     * 当前任务参数
     * @var array
     */
    public $data = [];

    /**
     * 当前任务数据
     * @var SystemQueue
     */
    public $record;

    /**
     * 运行消息记录
     * @var array
     */
    private $msgs = [];

    /**
     * 运行消息写库
     * @var boolean
     */
    private $msgsWriteDb = false;

    /**
     * 异常尝试次数
     * @var integer
     */
    private $tryTimes = 0;

    /**
     * 数据初始化
     * @param string $code
     * @return static
     * @throws \think\admin\Exception
     */
    public function initialize(string $code = ''): QueueService
    {
        // 重置消息内容
        if (!empty($this->code) && $this->code !== $code) {
            $this->_lazyWrite(true);
            $this->msgs = [];
        }
        // 初始化新任务数据
        if (!empty($code)) {
            $this->record = SystemQueue::mk()->master()->where(['code' => $code])->findOrEmpty();
            if ($this->record->isEmpty()) {
                $message = sprintf("Qeueu initialize failed, Queue %s not found.", $code);
                $this->app->log->error($message);
                throw new Exception($message);
            }
            $this->code = $code;
            $this->data = json_decode($this->record['exec_data'], true) ?: [];
            $this->title = $this->record['title'];
        }
        // 消息写入数据库
        $this->msgsWriteDb = in_array('message', SystemQueue::mk()->getTableFields());
        return $this;
    }

    /**
     * 重发异步任务
     * @param integer $wait 等待时间
     * @return $this
     * @throws \think\admin\Exception
     */
    public function reset(int $wait = 0): QueueService
    {
        if ($this->record->isEmpty()) {
            $message = "Qeueu reset failed, Queue {$this->code} data cannot be empty!";
            $this->app->log->error($message);
            throw new Exception($message);
        }
        $this->record->save(['exec_pid' => 0, 'exec_time' => time() + $wait, 'status' => 1]);
        return $this;
    }

    /**
     * 添加定时清理任务
     * @param integer $loops 循环时间
     * @return $this
     * @throws \think\admin\Exception
     */
    public static function addCleanQueue(int $loops = 3600): QueueService
    {
        return static::register('定时清理系统任务数据', "xadmin:service clean", 0, [], 0, $loops);
    }

    /**
     * 注册异步处理任务
     * @param string $title 任务名称
     * @param string $command 执行脚本
     * @param integer $later 延时时间
     * @param array $data 任务附加数据
     * @param integer $rscript 任务类型(0单例,1多例)
     * @param integer $loops 循环等待时间
     * @return $this
     * @throws \think\admin\Exception
     */
    public static function register(string $title, string $command, int $later = 0, array $data = [], int $rscript = 0, int $loops = 0): QueueService
    {
        try {
            $map = [['title', '=', $title], ['status', 'in', [1, 2]]];
            if (empty($rscript) && ($queue = SystemQueue::mk()->master()->where($map)->findOrEmpty())->isExists()) {
                throw new Exception(lang('已创建请等待处理完成！'), 0, $queue['code']);
            }
            // 生成唯一编号
            do $map = ['code' => $code = CodeExtend::uniqidDate(16, 'Q')];
            while (($queue = SystemQueue::mk()->master()->where($map)->findOrEmpty())->isExists());
            // 写入任务数据
            $queue->save([
                'code'       => $code,
                'title'      => $title,
                'command'    => $command,
                'attempts'   => 0,
                'rscript'    => intval(boolval($rscript)),
                'exec_data'  => json_encode($data, JSON_UNESCAPED_UNICODE),
                'exec_time'  => $later > 0 ? time() + $later : time(),
                'enter_time' => 0,
                'outer_time' => 0,
                'loops_time' => $loops,
                'create_at'  => date('Y-m-d H:i:s'),
            ]);
            $that = static::instance([], true)->initialize($code);
            $that->progress(1, '>>> 任务创建成功 <<<', '0.00');
            return $that;
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 设置任务进度信息
     * @param ?integer $status 任务状态
     * @param ?string $message 进度消息
     * @param ?string $progress 进度数值
     * @param integer $backline 回退信息行
     * @return array
     * @throws \think\admin\Exception
     */
    public function progress(?int $status = null, ?string $message = null, ?string $progress = null, int $backline = 0): array
    {
        if (is_numeric($status) && intval($status) === 3) {
            if (!is_numeric($progress)) $progress = '100.00';
            if (is_null($message)) $message = '>>> 任务已经完成 <<<';
        }
        if (is_numeric($status) && intval($status) === 4) {
            if (!is_numeric($progress)) $progress = '0.00';
            if (is_null($message)) $message = '>>> 任务执行失败 <<<';
        }
        try {
            if (empty($this->msgs)) $this->msgs = $this->app->cache->get("queue_{$this->code}_progress", [
                'code' => $this->code, 'status' => $status, 'sctime' => 0, 'message' => $message, 'progress' => $progress, 'history' => []
            ]);
            $this->tryTimes = 0;
        } catch (\Exception|\Error $exception) {
            if ($this->tryTimes++ > 10) throw new Exception('读取进程缓存异常！');
            return $this->progress($status, $message, $progress, $backline);
        }
        while (--$backline > -1 && count($this->msgs['history']) > 0) array_pop($this->msgs['history']);
        if (is_numeric($status)) $this->msgs['status'] = intval($status);
        if (is_numeric($progress)) $progress = str_pad(sprintf('%.2f', $progress), 6, '0', STR_PAD_LEFT);
        if (is_string($message) && is_null($progress)) {
            $this->msgs['swrite'] = 0;
            $this->msgs['message'] = $message;
            $this->msgs['history'][] = ['message' => $message, 'progress' => $this->msgs['progress'], 'datetime' => date('Y-m-d H:i:s')];
        } elseif (is_null($message) && is_numeric($progress)) {
            $this->msgs['swrite'] = 0;
            $this->msgs['progress'] = $progress;
            $this->msgs['history'][] = ['message' => $this->msgs['message'], 'progress' => $progress, 'datetime' => date('Y-m-d H:i:s')];
        } elseif (is_string($message) && is_numeric($progress)) {
            $this->msgs['swrite'] = 0;
            $this->msgs['message'] = $message;
            $this->msgs['progress'] = $progress;
            $this->msgs['history'][] = ['message' => $message, 'progress' => $progress, 'datetime' => date('Y-m-d H:i:s')];
        }
        if (is_string($message) || is_numeric($progress)) if (count($this->msgs['history']) > 10) {
            $this->msgs['history'] = array_slice($this->msgs['history'], -10);
        }
        // 延时写入并返回内容
        return $this->_lazyWrite();
    }

    /**
     * 延时写入记录
     * @param boolean $force 强制更新
     * @return array
     */
    private function _lazyWrite(bool $force = false): array
    {
        // 无消息状态
        if (!isset($this->msgs['status'])) return $this->msgs;
        // 消息延时写数据库
        if ($force || empty($this->msgs['sctime']) || in_array($this->msgs['status'], [3, 4]) || microtime(true) - $this->msgs['sctime'] > 1) {
            if (empty($this->msgs['swrite']) && $this->record->isExists()) {
                [$this->msgs['swrite'], $this->msgs['sctime']] = [1, microtime(true)];
                $this->app->cache->set("queue_{$this->code}_progress", $this->msgs, 864000);
                if ($this->msgsWriteDb) {
                    $this->record->save(['message' => json_encode($this->msgs, JSON_UNESCAPED_UNICODE)]);
                }
            }
        }
        return $this->msgs;
    }

    /**
     * 更新任务进度
     * @param integer $total 记录总和
     * @param integer $count 当前记录
     * @param string $message 文字描述
     * @param integer $backline 回退行数
     * @throws \think\admin\Exception
     */
    public function message(int $total, int $count, string $message = '', int $backline = 0): void
    {
        $prefix = str_pad("{$count}", strlen(strval($total)), '0', STR_PAD_LEFT);
        if (defined('WorkQueueCode')) {
            $this->progress(2, "[{$prefix}/{$total}] {$message}", sprintf("%.2f", $count / max($total, 1) * 100), $backline);
        } else {
            ProcessService::message("[{$prefix}/{$total}] {$message}", $backline);
        }
    }

    /**
     * 任务执行成功
     * @param string $message 消息内容
     * @throws Exception
     */
    public function success(string $message): void
    {
        throw new Exception($message, 3, $this->code);
    }

    /**
     * 任务执行失败
     * @param string $message 消息内容
     * @throws Exception
     */
    public function error(string $message): void
    {
        throw new Exception($message, 4, $this->code);
    }

    /**
     * 执行任务处理
     * @param array $data 任务参数
     * @return void
     */
    public function execute(array $data = [])
    {
    }
}