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

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Table\Column;
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

    public function testGeneratedTemporalDefaultsRemainSqlExpressions(): void
    {
        foreach (['timestamp', 'datetime'] as $type) {
            foreach (['current_timestamp()', 'current_timestamp', 'Current_TimeStamp()', 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()'] as $default) {
                $column = $this->buildColumn($type, $default);
                self::assertSame('CURRENT_TIMESTAMP', $column->getDefault());
                self::assertSame(strtoupper($type) . ' NULL DEFAULT CURRENT_TIMESTAMP', $this->getColumnSql($column));
            }
        }
    }

    public function testGeneratedTemporalPrecisionIsPreserved(): void
    {
        foreach (['timestamp', 'datetime'] as $type) {
            foreach (range(0, 6) as $precision) {
                $column = $this->buildColumn("{$type}({$precision})", "current_timestamp({$precision})");
                self::assertSame($type, $column->getType());
                self::assertSame($precision, $column->getLimit());
                self::assertSame("CURRENT_TIMESTAMP({$precision})", $column->getDefault());
                self::assertSame(strtoupper($type) . "({$precision}) NULL DEFAULT CURRENT_TIMESTAMP({$precision})", $this->getColumnSql($column));
            }
        }
    }

    public function testGeneratedLiteralDefaultsArePreserved(): void
    {
        $cases = [
            ['timestamp', null, 'TIMESTAMP NULL'],
            ['datetime', null, 'DATETIME NULL'],
            ['timestamp', '2026-10-07 12:34:56', "TIMESTAMP NULL DEFAULT '2026-10-07 12:34:56'"],
            ['datetime', '2026-10-07 12:34:56', "DATETIME NULL DEFAULT '2026-10-07 12:34:56'"],
            ['timestamp(3)', '2026-10-07 12:34:56.123', "TIMESTAMP(3) NULL DEFAULT '2026-10-07 12:34:56.123'"],
            ['varchar(64)', 'current_timestamp()', "VARCHAR(64) NULL DEFAULT 'current_timestamp()'"],
            ['varchar(64)', 'current_timestamp(3)', "VARCHAR(64) NULL DEFAULT 'current_timestamp(3)'"],
            ['varchar(64)', '', "VARCHAR(64) NULL DEFAULT ''"],
        ];
        foreach ($cases as [$type, $default, $expectedSql]) {
            $column = $this->buildColumn($type, $default);
            self::assertSame($default, $column->getDefault());
            self::assertSame($expectedSql, $this->getColumnSql($column));
        }
    }

    private function buildColumn(string $type, ?string $default): Column
    {
        $source = $this->buildMigration([], false, [
            ['name' => 'create_at', 'type' => $type, 'default' => $default, 'notnull' => false],
        ]);
        self::assertNotEmpty(token_get_all($source, TOKEN_PARSE));
        self::assertSame(1, preg_match('/^\s*(\[\x27create_at\x27.*\]),$/m', $source, $matches));
        // 仅解析测试元数据生成的字段数组，不执行迁移脚本。
        $field = eval('return ' . $matches[1] . ';');
        return (new Column())->setName($field[0])->setType($field[1])->setOptions($field[2]);
    }

    private function getColumnSql(Column $column): string
    {
        $adapter = new class([]) extends MysqlAdapter {
            public function columnSql(Column $column): string
            {
                // 内存连接仅用于字符串转义，SQL 由 MySQL 适配器生成。
                $this->connection = new \PDO('sqlite::memory:');
                return $this->getColumnSqlDefinition($column);
            }
        };
        return $adapter->columnSql($column);
    }

    private function buildMigration(array $indexes, bool $force, ?array $fields = null): string
    {
        $fields = $fields ?? [
            ['name' => 'id', 'type' => 'int(11)', 'default' => null, 'notnull' => true],
            ['name' => 'uuid', 'type' => 'varchar(32)', 'default' => null, 'notnull' => true],
            ['name' => 'uid', 'type' => 'varchar(32)', 'default' => null, 'notnull' => false],
        ];
        $database = new class($indexes, $fields) {
            private $indexes;

            private $fields;

            public function __construct(array $indexes, array $fields)
            {
                $this->indexes = $indexes;
                $this->fields = $fields;
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
                return $this->fields;
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
