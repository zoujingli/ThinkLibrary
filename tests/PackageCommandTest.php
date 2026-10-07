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

namespace think\admin\tests;

use PHPUnit\Framework\TestCase;
use think\admin\Command;
use think\admin\extend\ToolsExtend;
use think\admin\Library;
use think\admin\support\command\Package;
use think\Config;
use think\console\Input;
use think\console\Output;
use think\DbManager;
use think\Model;

/**
 * @internal
 * @coversNothing
 */
class PackageCommandTest extends TestCase
{
    public function testPackageFiltersPhysicalAndLogicalNamesWithPrefixes(): void
    {
        foreach (['', 'tenant_'] as $prefix) {
            foreach ([[], ['skip_logical', $prefix . 'skip_physical']] as $ignore) {
                $database = new DbManager();
                $database->setConfig(['default' => 'fixture', 'connections' => ['fixture' => ['type' => 'sqlite', 'database' => ':memory:', 'prefix' => $prefix]]]);
                $database->execute('CREATE TABLE ' . $prefix . 'system_menu(id INTEGER, status INTEGER, sort INTEGER)');
                foreach (['records', 'migrations', 'system_oplog', 'system_queue', 'skip_logical', 'skip_physical'] as $table) {
                    $database->execute('CREATE TABLE ' . $prefix . $table . '(id INTEGER PRIMARY KEY, value TEXT)');
                    $database->execute('INSERT INTO ' . $prefix . $table . " VALUES (1, 'fixture')");
                }
                $directory = sys_get_temp_dir() . '/phinx-command-' . bin2hex(random_bytes(8));
                $app = Library::$sapp;
                $property = new \ReflectionProperty(Model::class, 'db');
                $property->setAccessible(true);
                $modelDatabase = $property->getValue();
                ob_start();
                try {
                    Model::setDb($database);
                    Library::$sapp = new class($database, $directory, $ignore) {
                        public $db;

                        public $config;

                        private $directory;

                        public function __construct($database, $directory, $ignore)
                        {
                            $this->db = $database;
                            $this->directory = $directory;
                            $this->config = new Config();
                            $this->config->set(['ignore' => $ignore], 'phinx');
                        }

                        public function getRootPath()
                        {
                            return $this->directory;
                        }
                    };
                    (new PackageCommandFixture())->generate();
                    $scripts = glob($directory . '/database/migrations/*.php');
                    $source = implode("\n", array_map('file_get_contents', $scripts));
                    $backups = glob($directory . '/database/migrations/*_install_package.php');
                    self::assertCount(1, $backups);
                    $backup = file_get_contents($backups[0]);
                    self::assertStringNotContainsString("'migrations'", $source);
                    self::assertStringContainsString("'records' =>", $backup);
                    self::assertNotEmpty(glob($directory . '/database/migrations/*_install_records_table.php'));
                    foreach ($ignore ? ['skip_logical', 'skip_physical'] : ['system_oplog', 'system_queue'] as $excluded) {
                        self::assertStringNotContainsString("'{$excluded}' =>", $backup);
                        if ($ignore) {
                            self::assertStringNotContainsString("table('{$excluded}',", $source);
                        }
                    }
                } finally {
                    ob_end_clean();
                    Library::$sapp = $app;
                    $property->setValue(null, $modelDatabase);
                    if (is_dir($directory)) {
                        ToolsExtend::remove($directory);
                    }
                }
            }
        }
    }
}

class PackageCommandFixture extends Package
{
    public function generate(): void
    {
        $this->input = new Input(['--all']);
        $this->input->bind($this->getDefinition());
        $this->output = new Output('buffer');
        $this->handle();
    }

    public function setQueueMessage(int $total, int $count, string $message = '', int $backline = 0): Command
    {
        return $this;
    }

    protected function setQueueSuccess(string $message) {}

    protected function setQueueError(string $message)
    {
        throw new \RuntimeException($message);
    }
}
