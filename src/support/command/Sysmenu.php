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

use think\admin\Command;
use think\admin\extend\DataExtend;
use think\admin\model\SystemMenu;

/**
 * 重置并清理系统菜单
 * @class Sysmenu
 * @package think\admin\support\command
 */
class Sysmenu extends Command
{
    /**
     * 指令任务配置
     */
    public function configure()
    {
        $this->setName('xadmin:sysmenu');
        $this->setDescription('Clean and Reset System Menu Data for ThinkAdmin');
    }

    /**
     * 任务执行入口
     * @return void
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function handle()
    {
        $query = SystemMenu::mQuery()->where(['status' => 1]);
        $menus = $query->db()->order('sort desc,id asc')->select()->toArray();
        [$total, $count] = [count($menus), 0, $query->empty()];
        $this->setQueueMessage($total, 0, '开始重置系统菜单编号...');
        foreach (DataExtend::arr2tree($menus) as $sub1) {
            $pid1 = $this->write($sub1);
            $this->setQueueMessage($total, ++$count, "重写1级菜单：{$sub1['title']}");
            if (!empty($sub1['sub'])) foreach ($sub1['sub'] as $sub2) {
                $pid2 = $this->write($sub2, $pid1);
                $this->setQueueMessage($total, ++$count, "重写2级菜单：-> {$sub2['title']}");
                if (!empty($sub2['sub'])) foreach ($sub2['sub'] as $sub3) {
                    $this->write($sub3, $pid2);
                    $this->setQueueMessage($total, ++$count, "重写3级菜单：-> -> {$sub3['title']}");
                }
            }
        }
        $this->setQueueMessage($total, $count, "完成重置系统菜单编号！");
    }

    /**
     * 写入单项菜单数据
     * @param array $arr 单项菜单数据
     * @param mixed $pid 上级菜单编号
     * @return int|string
     */
    private function write(array $arr, $pid = 0)
    {
        return SystemMenu::mk()->insertGetId([
            'pid'    => $pid,
            'url'    => $arr['url'],
            'icon'   => $arr['icon'],
            'node'   => $arr['node'],
            'title'  => $arr['title'],
            'params' => $arr['params'],
            'target' => $arr['target'],
        ]);
    }
}