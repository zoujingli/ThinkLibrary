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
use think\admin\extend\PhinxExtend;
use think\admin\Library;

/**
 * @internal
 * @coversNothing
 */
class PhinxExtendTest extends TestCase
{
    public function testGeneratedMigrationsHaveValidSyntax(): void
    {
        $primary = ['Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Seq_in_index' => 1, 'Non_unique' => 0, 'Sub_part' => null];
        $index = ['Key_name' => 'idx_uuid', 'Column_name' => 'uuid', 'Seq_in_index' => 1, 'Non_unique' => 1, 'Sub_part' => null];
        $cases = [
            'no indexes' => [],
            'primary only' => [$primary],
            'ordinary index' => [$primary, $index],
            'compound index' => [$primary, $index, array_merge($index, ['Column_name' => 'uid', 'Seq_in_index' => 2])],
            'unique index' => [$primary, array_merge($index, ['Non_unique' => 0])],
            'prefix index' => [$primary, array_merge($index, ['Sub_part' => 10])],
        ];

        foreach ($cases as $name => $indexes) {
            foreach ([false, true] as $force) {
                $source = $this->buildMigration($indexes, $force);
                self::assertStringContainsString('PhinxExtend::upgrade(', $source, $name);
                self::assertNotEmpty(token_get_all($source, TOKEN_PARSE), $name);
            }
        }
    }

    private function buildMigration(array $indexes, bool $force): string
    {
        $database = new class($indexes) {
            private $indexes;

            public function __construct(array $indexes)
            {
                $this->indexes = $indexes;
            }

            public function connect(): self
            {
                return $this;
            }

            public function getConfig(string $name): string
            {
                return $name === 'type' ? 'mysql' : 'migration_test';
            }

            public function table(string $name): self
            {
                return $this;
            }

            public function where(array $conditions): self
            {
                return $this;
            }

            public function value(string $name, string $default): string
            {
                return '迁移测试';
            }

            public function getFields(string $table): array
            {
                return [
                    ['name' => 'id', 'type' => 'int(11)', 'default' => null, 'notnull' => true],
                    ['name' => 'uuid', 'type' => 'varchar(32)', 'default' => null, 'notnull' => true],
                    ['name' => 'uid', 'type' => 'varchar(32)', 'default' => null, 'notnull' => false],
                ];
            }

            public function query(string $sql): array
            {
                return $this->indexes;
            }
        };

        $app = Library::$sapp;
        try {
            Library::$sapp = (object)['db' => $database];
            $method = new \ReflectionMethod(PhinxExtend::class, '_build2table');
            $method->setAccessible(true);
            $fragment = $method->invoke(null, ['sample_table'], true, $force);
            return '<?php class GeneratedMigration {' . substr($fragment, 5) . '}';
        } finally {
            Library::$sapp = $app;
        }
    }
}
