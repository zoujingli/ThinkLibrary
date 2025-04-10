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

namespace think\admin\model;

use think\admin\Model;

/**
 * 系统任务模型
 *
 * @property int $attempts 执行次数
 * @property int $exec_pid 执行进程
 * @property int $id
 * @property int $loops_time 循环时间
 * @property int $rscript 任务类型(0单例,1多例)
 * @property int $status 任务状态(1新任务,2处理中,3成功,4失败)
 * @property string $code 任务编号
 * @property string $command 执行指令
 * @property string $create_at 创建时间
 * @property string $enter_time 开始时间
 * @property string $exec_data 执行参数
 * @property string $exec_desc 执行描述
 * @property string $exec_time 执行时间
 * @property string $message 最新消息
 * @property string $outer_time 结束时间
 * @property string $title 任务名称
 * @class SystemQueue
 * @package think\admin\model
 */
class SystemQueue extends Model
{
    protected $createTime = 'create_at';
    protected $updateTime = false;

    /**
     * 格式化计划时间
     * @param mixed $value
     * @return string
     */
    public function getExecTimeAttr($value): string
    {
        return format_datetime($value);
    }

    /**
     * 执行开始时间处理
     * @param mixed $value
     * @return string
     */
    public function getEnterTimeAttr($value): string
    {
        return floatval($value) > 0 ? format_datetime(intval($value)) : '';
    }

    /**
     * 执行结束时间处理
     * @param mixed $value
     * @param array $data
     * @return string
     */
    public function getOuterTimeAttr($value, array $data): string
    {
        if ($value > 0 && $value > $data['enter_time']) {
            return lang("耗时 %.4f 秒", [$data['outer_time'] - $data['enter_time']]);
        } else {
            return ' - ';
        }
    }

    /**
     * 格式化创建时间
     * @param mixed $value
     * @return string
     */
    public function getCreateAtAttr($value): string
    {
        return format_datetime($value);
    }
}