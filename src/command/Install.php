<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2022 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\command;

use think\admin\Command;
use think\admin\service\ModuleService;
use think\admin\service\SystemService;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

/**
 * 插件更新安装指令
 * Class Install
 * @package think\admin\command
 */
class Install extends Command
{

    /**
     * 指定模块名称
     * @var string
     */
    protected $name;

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
     * 规则配置
     * @var array
     */
    protected $bind = [
        'admin'  => [
            'rules'  => ['app/admin'],
            'ignore' => [],
        ],
        'wechat' => [
            'rules'  => ['app/wechat'],
            'ignore' => [],
        ],
        'config' => [
            'rules'  => [
                'think',
                'config/app.php',
                'config/log.php',
                'config/route.php',
                'config/trace.php',
                'config/view.php',
                'public/index.php',
                'public/router.php',
            ],
            'ignore' => [],
        ],
        'static' => [
            'rules'  => [
                'public/static/plugs',
                'public/static/theme',
                'public/static/admin.js',
                'public/static/login.js',
            ],
            'ignore' => [],
        ],
    ];

    /**
     * 指令任务配置
     */
    protected function configure()
    {
        $this->setName('xadmin:install');
        $this->addArgument('name', Argument::OPTIONAL, 'ModuleName', '');
        $this->setDescription("Source code Install and Update for ThinkAdmin");
    }

    /**
     * 任务执行入口
     * @param \think\console\Input $input
     * @param \think\console\Output $output
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $this->name = trim($input->getArgument('name'));
        if (empty($this->name)) {
            $this->output->writeln('Module name of online install cannot be empty');
        } elseif ($this->name === 'all') {
            foreach ($this->bind as $bind) {
                $this->rules = array_merge($this->rules, $bind['rules']);
                $this->ignore = array_merge($this->ignore, $bind['ignore']);
            }
            $this->copyFileAndDatabase('static') && $this->installFile();
        } elseif (isset($this->bind[$this->name])) {
            $this->rules = $this->bind[$this->name]['rules'] ?? [];
            $this->ignore = $this->bind[$this->name]['ignore'] ?? [];
            $this->copyFileAndDatabase($this->name) && $this->installFile();
        } else {
            $this->output->writeln("The specified module {$this->name} is not configured with install rules");
        }
    }

    /**
     * 安装本地文件
     * @return boolean
     */
    private function installFile(): bool
    {
        $module = ModuleService::instance();
        $data = $module->grenDifference($this->rules, $this->ignore);
        if (empty($data)) {
            $this->output->writeln('No need to update the file if the file comparison is consistent');
            return false;
        }
        [$total, $count] = [count($data), 0];
        foreach ($data as $file) {
            [$state, $mode, $name] = $module->updateFileByDownload($file);
            if ($state) {
                if ($mode === 'add') $this->queue->message($total, ++$count, "--- {$name} add successfully");
                if ($mode === 'mod') $this->queue->message($total, ++$count, "--- {$name} update successfully");
                if ($mode === 'del') $this->queue->message($total, ++$count, "--- {$name} delete successfully");
            } else {
                if ($mode === 'add') $this->queue->message($total, ++$count, "--- {$name} add failed");
                if ($mode === 'mod') $this->queue->message($total, ++$count, "--- {$name} update failed");
                if ($mode === 'del') $this->queue->message($total, ++$count, "--- {$name} delete failed");
            }
        }
        return true;
    }

    /**
     * 初始化安装文件
     * @param string $type
     * @return boolean
     */
    private function copyFileAndDatabase(string $type): bool
    {
        if ($type === 'static') {
            $todir = with_path('public/static/extra/');
            $frdir = dirname(__DIR__) . "/service/bin/{$type}/";
            foreach (['script.js', 'style.css'] as $file) {
                if (!file_exists($todir . $file)) {
                    file_exists($todir) || mkdir($todir, 0755, true);
                    copy($frdir . $file, $todir . $file);
                }
            }
        }
        // 创建系统文件数据表
        if ($type === 'admin') {
            $this->createSystemFileTable();
        }
        return true;
    }

    /**
     * 创建系统文件表
     * @return void
     */
    private function createSystemFileTable()
    {
        [$tables] = SystemService::getTables();
        $config = $this->app->db->connect()->getConfig();
        [$type, $prefix] = [$config['type'] ?? '', $config['prefix'] ?? ''];
        if ($type === 'mysql' && !in_array($table = "{$prefix}system_file", $tables)) {
            $this->app->db->connect()->query(<<<SQL
CREATE TABLE {$table} (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NULL DEFAULT '' COMMENT '上传类型',
  `hash` varchar(32) NULL DEFAULT '' COMMENT '文件哈希',
  `name` varchar(200) NULL DEFAULT '' COMMENT '文件名称',
  `xext` varchar(100) NULL DEFAULT '' COMMENT '文件后缀',
  `xurl` varchar(500) NULL DEFAULT '' COMMENT '访问链接',
  `xkey` varchar(500) NULL DEFAULT '' COMMENT '文件路径',
  `mime` varchar(100) NULL DEFAULT '' COMMENT '文件类型',
  `size` bigint(20) NULL DEFAULT 0 COMMENT '文件大小',
  `uuid` bigint(20) NULL DEFAULT 0 COMMENT '用户编号',
  `isfast` tinyint(1) NULL DEFAULT 0 COMMENT '是否秒传',
  `issafe` tinyint(1) NULL DEFAULT 0 COMMENT '安全模式',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '上传状态(1悬空,2落地)',
  `create_at` datetime NULL DEFAULT NULL COMMENT '创建时间',
  `update_at` datetime NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_system_file_type`(`type`) USING BTREE,
  INDEX `idx_system_file_hash`(`hash`) USING BTREE,
  INDEX `idx_system_file_uuid`(`uuid`) USING BTREE,
  INDEX `idx_system_file_xext`(`xext`) USING BTREE,
  INDEX `idx_system_file_status`(`status`) USING BTREE,
  INDEX `idx_system_file_issafe`(`issafe`) USING BTREE,
  INDEX `idx_system_file_isfast`(`isfast`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 COMMENT='系统-文件' ROW_FORMAT=COMPACT;
SQL
            );
        }
    }
}