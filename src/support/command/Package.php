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
use think\admin\Exception;
use think\admin\extend\PhinxExtend;
use think\admin\Library;
use think\admin\service\SystemService;
use think\console\input\Option;

/**
 * 生成数据安装包
 * @class Package
 * @package think\admin\support\command
 */
class Package extends Command
{
    /**
     * 系统指定配置
     * @return void
     */
    public function configure()
    {
        $this->setName('xadmin:package');
        $this->addOption('all', 'a', Option::VALUE_NONE, 'Backup All Tables');
        $this->addOption('force', 'f', Option::VALUE_NONE, 'Force All Update');
        $this->addOption('table', 't', Option::VALUE_OPTIONAL, 'Package Tables Scheme', '');
        $this->addOption('backup', 'b', Option::VALUE_OPTIONAL, 'Package Tables Backup', '');
        $this->setDescription('Generate System Install Package for ThinkAdmin');
    }

    /**
     * 生成系统安装数据包
     * @return void
     * @throws \think\admin\Exception
     */
    public function handle()
    {
        try {
            // 创建数据库迁移脚本目录
            $dirname = syspath('database/migrations');
            is_dir($dirname) or mkdir($dirname, 0777, true);
            // 开始创建数据库迁移脚本
            $this->output->writeln('--- 开始创建数据库迁移脚本 ---');
            if ($this->createBackup() && $this->createScheme()) {
                $this->setQueueSuccess('--- 数据库迁移脚本创建成功 ---');
            } else {
                $this->setQueueError('--- 数据库迁移脚本创建失败 ---');
            }
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            trace_file($exception);
            $this->setQueueError($exception->getMessage());
        }
    }

    /**
     * 创建数据表
     * @return boolean
     * @throws \Exception
     */
    private function createScheme(): bool
    {
        $force = $this->input->hasOption('force');
        // 接收指定打包数据表
        if ($this->input->hasOption('table')) {
            $tables = str2arr(strtr($this->input->getOption('table'), '|', ','));
        } elseif ($this->input->hasOption('all')) {
            [$tables] = SystemService::getTables();
        } else {
            $tables = Library::$sapp->config->get('phinx.tables', []);
            if (empty($tables)) [$tables] = SystemService::getTables();
        }

        // 去除忽略的数据表
        $ignore = Library::$sapp->config->get('phinx.ignore', []);
        $tables = array_unique(array_diff($tables, $ignore, ['migrations']));

        // 创建数据库结构安装脚本
        [$prefix, $groups] = ['', []];
        foreach ($tables as $table) {
            $attr = explode('_', $table);
            if ($attr[0] === 'plugin') array_shift($attr);
            if (empty($prefix) || $prefix !== $attr[0]) {
                $prefix = $attr[0];
            }
            $groups[$prefix][] = $table;
        }
        [$total, $count] = [count($groups), 0];
        $this->setQueueMessage($total, 0, '开始创建数据表创建脚本！');
        foreach ($groups as $key => $tbs) {
            $name = 'Install' . ucfirst($key) . 'Table';
            $phinx = PhinxExtend::create2table($tbs, $name, $force);
            $target = syspath("database/migrations/{$phinx['file']}");
            if (file_put_contents($target, $phinx['text']) !== false) {
                $this->setQueueMessage($total, ++$count, "创建数据库 {$name} 安装脚本成功！");
            } else {
                $this->setQueueMessage($total, ++$count, "创建数据库 {$name} 安装脚本失败！");
                return false;
            }
        }
        return true;
    }

    /**
     * 创建数据包
     * @return boolean
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function createBackup(): bool
    {
        // 接收指定打包数据表
        if ($this->input->hasOption('backup')) {
            $tables = str2arr(strtr($this->input->getOption('backup'), '|', ','));
        } elseif ($this->input->hasOption('all')) {
            [$tables] = SystemService::getTables();
        } else {
            [$tables] = SystemService::getTables();
            $tables = array_intersect($tables, Library::$sapp->config->get('phinx.backup', []));
        }

        // 去除忽略的数据表
        $ignore = Library::$sapp->config->get('phinx.ignore', []);
        if (empty($ignore)) $ignore = ['system_queue', 'system_oplog'];
        $tables = array_unique(array_diff($tables, $ignore, ['migrations']));

        // 创建数据库记录安装脚本
        $this->setQueueMessage(4, 1, '开始创建数据包安装脚本！');
        $phinx = PhinxExtend::create2backup($tables);
        $target = syspath("database/migrations/{$phinx['file']}");
        if (file_put_contents($target, $phinx['text']) !== false) {
            $this->setQueueMessage(4, 2, '成功创建数据包安装脚本！');
            return true;
        } else {
            $this->setQueueMessage(4, 2, '创建数据包安装脚本失败！');
            return false;
        }
    }
}