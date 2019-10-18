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

namespace think\admin;

use think\console\Input;
use think\console\Output;
use think\Db;

/**
 * 基础任务基类
 * Class Queue
 * @package library
 */
abstract class Queue
{
    /**
     * 当前任务ID
     * @var integer
     */
    public $jobid = 0;

    /**
     * 当前任务标题
     * @var string
     */
    public $title = '';

    /**
     * 判断是否WIN环境
     * @return boolean
     */
    protected function isWin()
    {
        return PATH_SEPARATOR === ';';
    }

    /**
     * 重发异步任务
     * @param integer $wait 等待时间
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function reset($wait = 0)
    {
        if (empty($this->jobid)) return false;
        $queue = Db::name('SystemQueue')->where(['id' => $this->jobid])->find();
        if (empty($queue)) return false;
        $queue['time'] = time() + $wait;
        $queue['times'] = $queue['times'] + 1;
        $queue['title'] .= " - 来自任务{$this->jobid}重发任务";
        unset($queue['id'], $queue['create_at'], $queue['desc']);
        return Db::name('SystemQueue')->insert($queue) !== false;
    }

    /**
     * 创建异步处理任务
     * @param string $title 任务名称
     * @param string $command 执行内容
     * @param integer $later 延时执行时间
     * @param array $data 任务附加数据
     * @param integer $double 任务多开
     * @return boolean
     * @throws \think\Exception
     */
    public static function add($title, $command, $later = 0, $data = [], $double = 1)
    {
        $map = [['title', 'eq', $title], ['status', 'in', ['1', '2']]];
        if (empty($double) && Db::name('SystemQueue')->where($map)->count() > 0) {
            throw new \think\Exception('该任务已经创建，请耐心等待处理完成！');
        }
        $result = Db::name('SystemQueue')->insert([
            'title'      => $title,
            'command'    => $command,
            'attempts'   => '0',
            'exec_data'  => json_encode($data, JSON_UNESCAPED_UNICODE),
            'exec_time'  => $later > 0 ? time() + $later : time(),
            'start_time' => '0',
            'done_time'  => '0',
            'double'     => intval($double),
        ]);
        return $result !== false;
    }

    /**
     * 执行任务处理
     * @param Input $input 输入对象
     * @param Output $output 输出对象
     * @param array $data 任务参数
     * @return mixed
     */
    abstract function execute(Input $input, Output $output, array $data = []);

}