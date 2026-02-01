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

namespace think\admin\support\command;

use think\admin\Command;
use think\admin\Exception;
use think\admin\service\SystemService;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\db\exception\DbException;
use think\helper\Str;

/**
 * 数据库字符替换.
 * @class Replace
 */
class Replace extends Command
{
    /**
     * 指令任务配置.
     */
    protected function configure()
    {
        $this->setName('xadmin:replace');
        $this->addArgument('search', Argument::OPTIONAL, '查找替换的字符内容', '');
        $this->addArgument('replace', Argument::OPTIONAL, '目标替换的字符内容', '');
        $this->setDescription('Database Character Field Replace for ThinkAdmin');
    }

    /**
     * 任务执行入口.
     * @throws Exception
     * @throws DbException
     */
    protected function execute(Input $input, Output $output)
    {
        $search = $input->getArgument('search');
        $repalce = $input->getArgument('replace');
        if ($search === '') {
            $this->setQueueError('查找替换字符内容不能为空！');
        }
        if ($repalce === '') {
            $this->setQueueError('目标替换字符内容不能为空！');
        }
        [$tables, $total, $count] = SystemService::getTables();
        foreach ($tables as $table) {
            $data = [];
            $this->setQueueMessage($total, ++$count, sprintf('准备替换数据表 %s', Str::studly($table)));
            foreach ($this->app->db->table($table)->getFields() as $field => $attrs) {
                if (preg_match('/char|text/', $attrs['type'])) {
                    $data[$field] = $this->app->db->raw(sprintf('REPLACE(`%s`,"%s","%s")', $field, $search, $repalce));
                }
            }
            if (count($data) > 0) {
                $this->app->db->table($table)->master()->where('1=1')->update($data);
                $this->setQueueMessage($total, $count, sprintf('成功替换数据表 %s', Str::studly($table)), 1);
            } else {
                $this->setQueueMessage($total, $count, sprintf('无需替换数据表 %s', Str::studly($table)), 1);
            }
        }
        $this->setQueueSuccess("批量替换 {$total} 张数据表成功");
    }
}
