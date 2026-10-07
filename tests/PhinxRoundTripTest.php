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

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Adapter\SQLiteAdapter;
use Phinx\Db\Adapter\TablePrefixAdapter;
use Phinx\Db\Table;
use PHPUnit\Framework\TestCase;
use think\admin\extend\PhinxBackup;
use think\admin\extend\PhinxExtend;
use think\admin\extend\ToolsExtend;
use think\admin\Library;
use think\DbManager;

/**
 * @internal
 * @coversNothing
 */
class PhinxRoundTripTest extends TestCase
{
    public function testSourceValuesAndIdentifiersSurviveGeneration(): void
    {
        $adapter = new RecordingMysqlAdapter();
        $value = "a  b\nc\td '_INDEXS_' __FORCE__ _FIELDS_ \\path";
        $tables = ['order-detail' => [
            'fields' => [$this->field("customer'name", 'varchar(200)', $value)],
            'options' => ['TABLE_COMMENT' => "Customer's data */\n", 'ENGINE' => 'MyISAM', 'TABLE_COLLATION' => 'utf8mb4_bin'],
        ], 'second' => ['fields' => [$this->field('name', 'varchar(200)', $value)]]];
        $source = $this->generate($tables);
        $this->runMigration($source, $adapter);
        $sql = implode("\n", $adapter->statements);
        self::assertStringContainsString('`order-detail`', $sql);
        self::assertStringContainsString("`customer'name`", $sql);
        self::assertSame(2, substr_count($sql, $adapter->getConnection()->quote($value)));
        self::assertStringContainsString("COMMENT='Customer''s data */\n'", $sql);
        self::assertStringContainsString('ENGINE = MyISAM', $sql);
        self::assertStringContainsString('COLLATE utf8mb4_bin', $sql);
    }

    public function testIntegerPrimaryKeysDefaultsAndNullsArePreserved(): void
    {
        $adapter = new RecordingMysqlAdapter();
        $fields = [
            $this->field('id', 'bigint unsigned', null, ['Null' => 'NO', 'Extra' => 'auto_increment']),
            $this->field('optional', 'int(11)'),
            $this->field('maximum', 'bigint unsigned', '18446744073709551615'),
        ];
        $this->runMigration($this->generate(['records' => ['fields' => $fields, 'indexes' => [$this->index('PRIMARY', 'id', ['Non_unique' => 0])]]]), $adapter);
        $sql = implode("\n", $adapter->statements);
        self::assertSame(1, preg_match('/`id` BIGINT(?:\(20\))? unsigned NOT NULL AUTO_INCREMENT/i', $sql), $sql);
        self::assertStringContainsString('`optional` INT(11) NULL', $sql);
        self::assertStringNotContainsString('`optional` INT(11) NULL DEFAULT 0', $sql);
        self::assertStringContainsString("DEFAULT '18446744073709551615'", $sql);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $sql);
        self::assertSame(1, substr_count($sql, '`id` BIGINT'));
    }

    public function testMysqlTypesKeepTheirStorageAndPrecision(): void
    {
        $types = ['char(16)', 'binary(16)', 'varbinary(512)', 'bit(1)', "enum('draft','Bob''s')", "set('red','blue')", 'float(10,2)', 'double(10,2)', 'double unsigned', 'decimal(20,0)', 'mediumtext', 'longtext', 'mediumblob', 'longblob', 'year(4)', 'time(3)'];
        foreach ($types as $type) {
            $adapter = new RecordingMysqlAdapter();
            $this->runMigration($this->generate(['sample' => ['fields' => [$this->field('value', $type)]]]), $adapter);
            $sql = strtolower(implode("\n", $adapter->statements));
            self::assertStringContainsString('`value` ' . strtolower($type) . ' null', $sql, $type);
        }
    }

    public function testColumnCollationUpdateAndTextTimestampDefault(): void
    {
        $adapter = new RecordingMysqlAdapter();
        $fields = [
            $this->field('label', 'varchar(64)', 'CURRENT_TIMESTAMP', ['Collation' => 'utf8mb4_bin']),
            $this->field('modified', 'timestamp(3)', 'current_timestamp(3)', ['Extra' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP(3)']),
        ];
        $this->runMigration($this->generate(['sample' => ['fields' => $fields]]), $adapter);
        $sql = implode("\n", $adapter->statements);
        self::assertStringContainsString("COLLATE utf8mb4_bin NULL DEFAULT 'CURRENT_TIMESTAMP'", $sql);
        self::assertStringContainsString('DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)', $sql);
    }

    public function testIndexesPreserveNamesTypesPrefixesAndOrder(): void
    {
        $adapter = new RecordingMysqlAdapter();
        $indexes = [
            $this->index('short', 'name', ['Sub_part' => 10]),
            $this->index('long', 'name', ['Sub_part' => 20]),
            $this->index('search', 'body', ['Index_type' => 'FULLTEXT']),
            $this->index('pair', 'name', ['Sub_part' => 15, 'Collation' => 'D', 'Non_unique' => 0]),
            $this->index('pair', 'code', ['Seq_in_index' => 2, 'Non_unique' => 0]),
        ];
        $fields = [$this->field('name', 'varchar(100)'), $this->field('code', 'varchar(20)'), $this->field('body', 'text')];
        $this->runMigration($this->generate(['sample' => ['fields' => $fields, 'indexes' => $indexes]]), $adapter);
        $sql = implode("\n", $adapter->statements);
        self::assertSame(1, preg_match('/(?:KEY|INDEX) `short`\s*\(`name`\(10\)/', $sql), $sql);
        self::assertSame(1, preg_match('/(?:KEY|INDEX) `long`\s*\(`name`\(20\)/', $sql), $sql);
        self::assertSame(1, preg_match('/FULLTEXT (?:KEY|INDEX) `search`/', $sql), $sql);
        self::assertStringContainsString('`name`(15) DESC, `code`', $sql);
    }

    public function testForcedIndexesUseMigrationConnectionAndPrefixAndAreIdempotent(): void
    {
        $adapter = new RecordingMysqlAdapter();
        $adapter->existing = true;
        $adapter->indexes = [$this->index('short', 'name', ['Sub_part' => 10]), $this->index('long', 'name', ['Sub_part' => 20])];
        $prefix = new TablePrefixAdapter($adapter);
        $prefix->setOptions(['adapter' => 'mysql', 'table_prefix' => 'tenant_']);
        $table = new Table('sample', [], $prefix);
        $specs = [
            ['columns' => ['name'], 'name' => 'short', 'limit' => 10],
            ['columns' => ['name'], 'name' => 'long', 'limit' => 20],
        ];
        PhinxExtend::upgrade($table, [], $specs, true);
        self::assertSame([], $adapter->statements);
        self::assertContains('SHOW INDEX FROM `tenant_sample`', $adapter->queries);
        $specs[0]['limit'] = 5;
        PhinxExtend::upgrade($table, [], $specs, true);
        $sql = implode("\n", $adapter->statements);
        self::assertStringContainsString('ALTER TABLE `tenant_sample`', $sql);
        self::assertStringContainsString('DROP INDEX `short`', $sql);
        self::assertStringNotContainsString('DROP INDEX `long`', $sql);
        self::assertSame(1, count($adapter->statements), 'Replace an index in one ALTER statement');
    }

    public function testCommonGeneratedMigrationRunsAndUpgradesOnSqlite(): void
    {
        $adapter = $this->sqlite();
        $fields = [
            $this->field('id', 'bigint unsigned', null, ['Null' => 'NO', 'Extra' => 'auto_increment']),
            $this->field('name', 'varchar(80)', 'CURRENT_TIMESTAMP'),
            $this->field('optional', 'int(11)'),
            $this->field('created', 'datetime', 'current_timestamp()'),
            $this->field('body', 'mediumtext', null, ['Comment' => 'a */ comment']),
        ];
        $source = $this->generate(['sample' => ['fields' => $fields, 'indexes' => [$this->index('PRIMARY', 'id', ['Non_unique' => 0]), $this->index('name_idx', 'name')]]], true);
        $this->runMigration($source, $adapter);
        $adapter->execute('INSERT INTO sample DEFAULT VALUES');
        $this->runMigration($source, $adapter);
        $row = $adapter->fetchRow('SELECT * FROM sample');
        self::assertSame(1, (int)$row['id']);
        self::assertSame('CURRENT_TIMESTAMP', $row['name']);
        self::assertNull($row['optional']);
        self::assertNotEmpty($row['created']);
        self::assertCount(1, $adapter->fetchAll('PRAGMA index_list(sample)'));
    }

    public function testNaturalAndCompoundPrimaryKeysDoNotAcquireAnId(): void
    {
        foreach ([['code'], ['tenant', 'code']] as $primary) {
            $adapter = $this->sqlite();
            $indexes = [];
            foreach ($primary as $sequence => $column) {
                $indexes[] = $this->index('PRIMARY', $column, ['Seq_in_index' => $sequence + 1, 'Non_unique' => 0]);
            }
            $source = $this->generate(['sample' => ['fields' => [$this->field('tenant', 'int', null, ['Null' => 'NO']), $this->field('code', 'varchar(20)', null, ['Null' => 'NO'])], 'indexes' => $indexes]]);
            $this->runMigration($source, $adapter);
            $columns = $adapter->fetchAll('PRAGMA table_info(sample)');
            self::assertSame(['tenant', 'code'], array_column($columns, 'name'));
            $keys = array_filter($columns, function ($column) { return $column['pk'] > 0; });
            usort($keys, function ($a, $b) { return $a['pk'] <=> $b['pk']; });
            self::assertSame($primary, array_column($keys, 'name'));
        }
    }

    public function testUnsupportedSqliteIndexFailsBeforeCreatingTable(): void
    {
        $adapter = $this->sqlite();
        $source = $this->generate(['sample' => ['fields' => [$this->field('body', 'text')], 'indexes' => [$this->index('search', 'body', ['Index_type' => 'FULLTEXT'])]]]);
        try {
            $this->runMigration($source, $adapter);
            self::fail('FULLTEXT must not silently become an ordinary SQLite index');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('FULLTEXT', strtoupper($exception->getMessage()));
            self::assertFalse($adapter->hasTable('sample'));
        }
    }

    public function testUnsignedBigintDefaultsFailBeforeCreatingSqliteTable(): void
    {
        $adapter = $this->sqlite();
        try {
            PhinxExtend::upgrade(new Table('sample', ['id' => false], $adapter), [['counter', 'integer', ['default' => '18446744073709551615', 'signed' => false]]]);
            self::fail('SQLite cannot preserve unsigned 64-bit integer defaults');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('无符号大整数', $exception->getMessage());
            self::assertSame([], $adapter->fetchAll("SELECT name FROM sqlite_master WHERE name='sample'"));
        }
    }

    public function testSqliteForcedColumnsPreserveRowsIndexesTriggersAndSequence(): void
    {
        $adapter = $this->sqlite();
        $adapter->execute("CREATE TABLE sample(id INTEGER PRIMARY KEY AUTOINCREMENT, amount DECIMAL(20,0), body TEXT DEFAULT 'one,two', extra TEXT, payload BLOB)");
        $adapter->execute('CREATE INDEX untouched ON sample(extra)');
        $adapter->execute('CREATE TABLE audit(message TEXT)');
        $adapter->execute("CREATE TRIGGER sample_changed AFTER UPDATE ON sample BEGIN INSERT INTO audit VALUES ('changed'); END");
        $adapter->execute("INSERT INTO sample(id, amount, extra, payload) VALUES (1, 42, 'kept', X'FF00'), (100, 7, 'removed', NULL)");
        $adapter->execute('DELETE FROM sample WHERE id = 100');
        $fields = [
            ['id', 'integer', ['identity' => true, 'null' => false]],
            ['amount', 'decimal', ['precision' => 22, 'scale' => 0, 'null' => true]],
            ['body', 'string', ['limit' => 200, 'default' => 'three,four', 'null' => true]],
        ];
        PhinxExtend::upgrade(new Table('sample', ['id' => false, 'primary_key' => ['id']], $adapter), $fields, [['columns' => ['amount'], 'name' => 'by_amount']], true);
        $row = $adapter->fetchRow('SELECT * FROM sample');
        self::assertSame('kept', $row['extra']);
        self::assertSame('one,two', $row['body']);
        self::assertSame("\xFF\x00", $row['payload']);
        self::assertSame('DECIMAL(22,0)', $adapter->fetchAll('PRAGMA table_info(sample)')[1]['type']);
        self::assertContains('untouched', array_column($adapter->fetchAll('PRAGMA index_list(sample)'), 'name'));
        $adapter->execute('UPDATE sample SET amount = 43 WHERE id = 1');
        self::assertSame('changed', $adapter->fetchRow('SELECT * FROM audit')['message']);
        $adapter->execute('INSERT INTO sample DEFAULT VALUES');
        $row = $adapter->fetchRow('SELECT * FROM sample ORDER BY id DESC');
        self::assertSame(101, (int)$row['id']);
        self::assertSame('three,four', $row['body']);
    }

    public function testFailedSqliteColumnChangesRollBackTheEntireUpgrade(): void
    {
        $adapter = $this->sqlite();
        $adapter->execute('CREATE TABLE sample(id INTEGER PRIMARY KEY, body TEXT)');
        $adapter->execute('INSERT INTO sample(id) VALUES (1)');
        $before = $adapter->fetchAll("SELECT name, sql FROM sqlite_master WHERE type = 'table' ORDER BY name");
        try {
            PhinxExtend::upgrade(new Table('sample', ['id' => false, 'primary_key' => ['id']], $adapter), [
                ['extra', 'text', ['null' => true]],
                ['body', 'string', ['null' => false]],
            ], [], true);
            self::fail('NULL data must reject a NOT NULL column change');
        } catch (\PDOException $exception) {
            self::assertSame($before, $adapter->fetchAll("SELECT name, sql FROM sqlite_master WHERE type = 'table' ORDER BY name"));
            self::assertNull($adapter->fetchRow('SELECT * FROM sample')['body']);
        }
    }

    public function testSqliteReferencedTablesFailBeforeAnyCascadingDelete(): void
    {
        $adapter = $this->sqlite();
        $adapter->execute('PRAGMA foreign_keys = ON');
        $adapter->execute('CREATE TABLE parent(id INTEGER PRIMARY KEY, name TEXT)');
        $adapter->execute('CREATE TABLE child(parent_id INTEGER REFERENCES parent(id) ON DELETE CASCADE)');
        $adapter->execute('INSERT INTO parent VALUES (1, NULL)');
        $adapter->execute('INSERT INTO child VALUES (1)');
        try {
            PhinxExtend::upgrade(new Table('parent', ['id' => false, 'primary_key' => ['id']], $adapter), [['name', 'string', ['null' => true]]], [], true);
            self::fail('SQLite referenced tables require an explicit migration');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('外键引用', $exception->getMessage());
            self::assertSame(1, (int)$adapter->fetchRow('SELECT COUNT(*) AS total FROM child')['total']);
            self::assertSame('TEXT', $adapter->fetchAll('PRAGMA table_info(parent)')[1]['type']);
        }
    }

    public function testBackupRecordsRoundTripBinaryAndLegacyJson(): void
    {
        $row = ['id' => '18446744073709551615', 'blob' => "\xff\0\r\n", 'name' => "Bob's C:\\path 中文", 'null' => null, 'zero' => 0, 'flag' => false, 'number' => 1.0];
        self::assertSame($row, PhinxBackup::decodeRow(PhinxBackup::encodeRow($row)));
        self::assertSame(['name' => 'old backup', 'id' => '18446744073709551615'], PhinxBackup::decodeRow('{"name":"old backup","id":18446744073709551615}'));
        $this->expectException(\RuntimeException::class);
        PhinxBackup::decodeRow('{corrupt');
    }

    public function testBackupTemplatePreservesQuotesBackslashesAndPlaceholders(): void
    {
        $menus = [['name' => "Bob's C:\\path  中文\n__CLASS__ __DATA__ __MENU__"]];
        $tables = ["a'b" => 'path\data'];
        $method = new \ReflectionMethod(PhinxExtend::class, 'renderBackup');
        $method->setAccessible(true);
        $source = $method->invoke(null, 'BackupFixture', $tables, $menus);
        $strings = [];
        foreach (token_get_all($source, TOKEN_PARSE) as $token) {
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $strings[] = eval('return ' . $token[1] . ';');
            }
        }
        self::assertContains($menus[0]['name'], $strings);
        self::assertContains("a'b", $strings);
        self::assertContains('path\data', $strings);
    }

    public function testBinaryBackupRestoresThroughTheMigrationConnectionAndRollsBackCorruption(): void
    {
        $adapter = $this->sqlite();
        $adapter->execute('CREATE TABLE tenant_data (id INTEGER PRIMARY KEY, value BLOB)');
        $prefix = new TablePrefixAdapter($adapter);
        $prefix->setOptions(['adapter' => 'sqlite', 'table_prefix' => 'tenant_']);
        $table = new Table('data', [], $prefix);
        $directory = sys_get_temp_dir() . '/phinx-backup-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory . '/rows.data';
        try {
            $rows = [];
            foreach (range(1, 105) as $id) {
                $rows[] = ['id' => $id, 'value' => "\xff\0Bob's\\path\n{$id}"];
            }
            self::assertSame(105, PhinxBackup::write($rows, $path));
            self::assertSame(105, PhinxBackup::restore($table, $path));
            self::assertSame($rows, $adapter->fetchAll('SELECT * FROM tenant_data ORDER BY id'));
            self::assertSame('blob', $adapter->fetchRow('SELECT typeof(value) AS kind FROM tenant_data')['kind']);
            self::assertSame(1, (int)$adapter->fetchRow("SELECT COUNT(*) AS total FROM tenant_data WHERE value = X'FF00426F6227735C706174680A31'")['total']);
            self::assertSame(0, PhinxBackup::restore($table, $path));
            $adapter->execute('DELETE FROM tenant_data');
            // 在第一个批次写入之后损坏，验证恢复不会留下部分数据。
            file_put_contents($path, "{broken\n", FILE_APPEND);
            try {
                PhinxBackup::restore($table, $path);
                self::fail('A corrupt backup must fail');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('106', $exception->getMessage());
                self::assertSame(0, (int)$adapter->fetchRow('SELECT COUNT(*) AS total FROM tenant_data')['total']);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    public function testSqliteSourceCanBeGeneratedAndExecuted(): void
    {
        $source = $this->sqlite();
        $source->execute("CREATE TABLE original (id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR(20) NOT NULL UNIQUE, note TEXT DEFAULT 'CURRENT_TIMESTAMP', stamp DATETIME DEFAULT 'CURRENT_TIMESTAMP', amount DECIMAL(20,0), fraction REAL DEFAULT 1.25, payload BLOB)");
        $source->execute('CREATE INDEX by_code ON original(code DESC)');
        $database = new class($source) {
            private $adapter;

            public function __construct($adapter)
            {
                $this->adapter = $adapter;
            }

            public function connect()
            {
                return $this;
            }

            public function getConfig($name)
            {
                return $name === 'type' ? 'sqlite' : '';
            }

            public function query($sql)
            {
                return $this->adapter->fetchAll($sql);
            }
        };
        $migration = $this->generateFrom($database, ['original', 'sqlite_sequence']);
        $target = $this->sqlite();
        $this->runMigration($migration, $target);
        $target->execute("INSERT INTO original(code) VALUES ('one')");
        $row = $target->fetchRow('SELECT * FROM original');
        self::assertSame(1, (int)$row['id']);
        self::assertSame('CURRENT_TIMESTAMP', $row['note']);
        self::assertSame('CURRENT_TIMESTAMP', $row['stamp']);
        self::assertSame(1.25, (float)$row['fraction']);
        self::assertSame(1, (int)$target->fetchAll('PRAGMA index_xinfo(by_code)')[0]['desc']);
        $this->expectException(\PDOException::class);
        $target->execute("INSERT INTO original(code) VALUES ('one')");
    }

    public function testMysqlSchemaAndBackupRoundTripOnAnIsolatedDatabase(): void
    {
        $port = getenv('PHINX_TEST_MYSQL_PORT');
        if (!$port) {
            self::markTestSkipped('Set PHINX_TEST_MYSQL_PORT to run against a disposable phinx_fixture MySQL database.');
        }
        $adapter = new MysqlAdapter(['adapter' => 'mysql', 'host' => '127.0.0.1', 'port' => (int)$port, 'name' => 'phinx_fixture', 'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4']);
        $adapter->connect();
        $database = new DbManager();
        $database->setConfig(['default' => 'fixture', 'connections' => ['fixture' => ['type' => 'mysql', 'hostname' => '127.0.0.1', 'hostport' => $port, 'database' => 'phinx_fixture', 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4']]]);
        $adapter->execute('DROP TABLE IF EXISTS copy_original, original, copy_secondary, secondary');
        $directory = sys_get_temp_dir() . '/phinx-mysql-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory . '/rows.data';
        try {
            $adapter->execute("CREATE TABLE original (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                optional INT DEFAULT NULL, maximum BIGINT UNSIGNED DEFAULT 18446744073709551615,
                code CHAR(16), name VARCHAR(100) COLLATE utf8mb4_bin DEFAULT 'CURRENT_TIMESTAMP',
                status ENUM('draft','Bob''s') DEFAULT 'draft', colors SET('red','blue'), flag BIT(1) DEFAULT b'1',
                payload VARBINARY(512), fixed BINARY(16), body MEDIUMTEXT, large_text LONGTEXT,
                medium_data MEDIUMBLOB, large_data LONGBLOB, amount DECIMAL(20,0) UNSIGNED DEFAULT 0,
                approximate FLOAT(10,2), precise DOUBLE(10,2), year_value YEAR, clock TIME(3),
                created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                INDEX short_name (name(10)), INDEX long_name (name(20)),
                UNIQUE INDEX pair (name(15) DESC, code), FULLTEXT INDEX search_body (body), FULLTEXT INDEX search_large (large_text)
            ) ENGINE=InnoDB COLLATE=utf8mb4_bin COMMENT='Customer''s data'");
            $migration = $this->generateFrom($database, ['original'], true);
            $prefix = new TablePrefixAdapter($adapter);
            $prefix->setOptions(array_merge($adapter->getOptions(), ['table_prefix' => 'copy_']));
            $this->runMigration($migration, $prefix);
            $before = $adapter->fetchAll('SHOW FULL COLUMNS FROM original');
            self::assertSame($before, $adapter->fetchAll('SHOW FULL COLUMNS FROM copy_original'));
            $adapter->execute("INSERT INTO original (id, code, payload, body) VALUES (4294967296, 'one', X'FF000A', 'two  spaces')");
            self::assertSame(1, PhinxBackup::write($database->table('original')->cursor(), $path));
            self::assertSame(1, PhinxBackup::restore(new Table('original', [], $prefix), $path));
            self::assertSame($adapter->fetchAll('SELECT * FROM original'), $adapter->fetchAll('SELECT * FROM copy_original'));
            $adapter->execute("ALTER TABLE copy_original DEFAULT COLLATE utf8mb4_general_ci, COMMENT = 'changed'");
            $this->runMigration($migration, $prefix);
            self::assertSame($before, $adapter->fetchAll('SHOW FULL COLUMNS FROM copy_original'));
            $status = $adapter->fetchRow("SHOW TABLE STATUS WHERE Name = 'copy_original'");
            self::assertSame('utf8mb4_bin', $status['Collation']);
            self::assertSame("Customer's data", $status['Comment']);
            $indexes = function (array $rows): array {
                $result = [];
                foreach ($rows as $row) {
                    $result[] = array_intersect_key($row, array_flip(['Key_name', 'Column_name', 'Non_unique', 'Seq_in_index', 'Sub_part', 'Index_type', 'Collation']));
                }
                usort($result, function ($a, $b) { return [$a['Key_name'], $a['Seq_in_index']] <=> [$b['Key_name'], $b['Seq_in_index']]; });
                return $result;
            };
            self::assertSame($indexes($adapter->fetchAll('SHOW INDEX FROM original')), $indexes($adapter->fetchAll('SHOW INDEX FROM copy_original')));
            $adapter->execute('CREATE TABLE secondary (tenant INT NOT NULL, id INT NOT NULL AUTO_INCREMENT, PRIMARY KEY(tenant,id), INDEX by_id(id))');
            $this->runMigration($this->generateFrom($database, ['secondary']), $prefix);
            $adapter->execute('INSERT INTO copy_secondary(tenant) VALUES (1)');
            self::assertSame(1, (int)$adapter->fetchRow('SELECT id FROM copy_secondary')['id']);
        } finally {
            $adapter->execute('DROP TABLE IF EXISTS copy_original, original, copy_secondary, secondary');
            if (is_file($path)) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    public function testPrimaryKeyDifferencesFailBeforeChangingColumns(): void
    {
        $adapter = $this->sqlite();
        $adapter->execute('CREATE TABLE sample(id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $table = new Table('sample', ['id' => false, 'primary_key' => ['code']], $adapter);
        try {
            PhinxExtend::upgrade($table, [['extra', 'text', ['null' => true]]], [], true);
            self::fail('A primary key mismatch must not be ignored');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('主键不同', $exception->getMessage());
            self::assertSame(['id', 'code'], array_column($adapter->fetchAll('PRAGMA table_info(sample)'), 'name'));
        }
    }

    public function testSqliteGlobalIndexCollisionsFailBeforeCreatingTheSecondTable(): void
    {
        $adapter = $this->sqlite();
        $fields = [['code', 'string', ['null' => true]]];
        $indexes = [['columns' => ['code'], 'name' => 'by_code']];
        PhinxExtend::upgrade(new Table('alpha', ['id' => false], $adapter), $fields, $indexes);
        try {
            PhinxExtend::upgrade(new Table('beta', ['id' => false], $adapter), $fields, $indexes);
            self::fail('SQLite index names are global');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('by_code', $exception->getMessage());
            self::assertSame([], $adapter->fetchAll("SELECT name FROM sqlite_master WHERE name='beta'"));
        }
    }

    public function testSqliteSourcePrefixesAndUnsupportedTableConstraints(): void
    {
        $source = $this->sqlite();
        $source->execute('CREATE TABLE tenant_sample (id INTEGER PRIMARY KEY, name TEXT)');
        $database = new class($source) {
            private $adapter;

            public function __construct($adapter)
            {
                $this->adapter = $adapter;
            }

            public function connect()
            {
                return $this;
            }

            public function getConfig($name)
            {
                return $name === 'type' ? 'sqlite' : ($name === 'prefix' ? 'tenant_' : '');
            }

            public function query($sql)
            {
                return $this->adapter->fetchAll($sql);
            }
        };
        $target = $this->sqlite();
        $prefix = new TablePrefixAdapter($target);
        $prefix->setOptions(['adapter' => 'sqlite', 'table_prefix' => 'tenant_']);
        $this->runMigration($this->generateFrom($database, ['tenant_sample']), $prefix);
        self::assertNotEmpty($target->fetchAll("SELECT name FROM sqlite_master WHERE name='tenant_sample'"));
        self::assertSame([], $target->fetchAll("SELECT name FROM sqlite_master WHERE name='tenant_tenant_sample'"));
        $source->execute('CREATE TABLE tenant_special(id INTEGER PRIMARY KEY) WITHOUT ROWID');
        $this->expectException(\RuntimeException::class);
        $this->generateFrom($database, ['tenant_special']);
    }

    public function testPackageInitializesOnlyTheMigrationConnection(): void
    {
        $adapter = $this->sqlite();
        $adapter->execute('CREATE TABLE tenant_system_config(type TEXT, name TEXT, value TEXT)');
        $adapter->execute('CREATE TABLE tenant_system_user(id INTEGER, username TEXT, nickname TEXT, password TEXT, headimg TEXT)');
        $adapter->execute('CREATE TABLE tenant_system_menu(id INTEGER PRIMARY KEY AUTOINCREMENT, pid INTEGER, url TEXT, sort INTEGER, icon TEXT, node TEXT, title TEXT, params TEXT, target TEXT)');
        $method = new \ReflectionMethod(PhinxExtend::class, 'renderBackup');
        $method->setAccessible(true);
        $source = $method->invoke(null, 'RuntimeBackupFixture', [], [['name' => "Bob's menu", 'subs' => [['name' => 'Child']]]]);
        eval(substr($source, 5));
        $class = new \ReflectionClass(\RuntimeBackupFixture::class);
        $migration = $class->newInstanceArgs($class->getConstructor()->getNumberOfRequiredParameters() > 1 ? ['testing', 20000000000000] : [20000000000000]);
        $prefix = new TablePrefixAdapter($adapter);
        $prefix->setOptions(['adapter' => 'sqlite', 'table_prefix' => 'tenant_']);
        $migration->setAdapter($prefix);
        $migration->change();
        $migration->change();
        self::assertSame(11, (int)$adapter->fetchRow('SELECT COUNT(*) AS total FROM tenant_system_config')['total']);
        self::assertSame(1, (int)$adapter->fetchRow('SELECT COUNT(*) AS total FROM tenant_system_user')['total']);
        $menus = $adapter->fetchAll('SELECT id, pid, title FROM tenant_system_menu ORDER BY id');
        self::assertSame("Bob's menu", $menus[0]['title']);
        self::assertSame((int)$menus[0]['id'], (int)$menus[1]['pid']);
        self::assertCount(2, $menus);
        $this->expectException(\InvalidArgumentException::class);
        PhinxExtend::write2menu([], ['title' => "Bob's menu"], new Table('system_menu', [], $prefix));
    }

    public function testGeneratingAndSavingDoesNotDiscardThePreviousBackupOnFailure(): void
    {
        $directory = sys_get_temp_dir() . '/phinx-generation-' . bin2hex(random_bytes(8));
        mkdir($directory . '/database/migrations', 0777, true);
        $previous = $directory . '/database/migrations/20000000000000_install_fixture.php';
        file_put_contents($previous, '<?php class InstallFixture {}');
        $app = Library::$sapp;
        try {
            Library::$sapp = new class($directory) {
                public $db;

                private $directory;

                public function __construct($directory)
                {
                    $this->directory = $directory;
                    $this->db = new MigrationMetadataDatabase([]);
                }

                public function getRootPath()
                {
                    return $this->directory;
                }
            };
            $migration = PhinxExtend::create2table([], 'InstallFixture');
            self::assertFileExists($previous);
            try {
                PhinxExtend::saveMigration(['file' => $migration['file'], 'text' => '<?php syntax error']);
                self::fail('Invalid scripts must not replace the old backup');
            } catch (\ParseError $exception) {
                self::assertFileExists($previous);
            }
            self::assertTrue(PhinxExtend::saveMigration($migration));
            self::assertFalse(is_file($previous));
            self::assertSame($migration['text'], file_get_contents($directory . '/database/migrations/' . $migration['file']));
        } finally {
            Library::$sapp = $app;
            ToolsExtend::remove($directory);
        }
    }

    private function field(string $name, string $type, $default = null, array $extra = []): array
    {
        return array_merge(['Field' => $name, 'Type' => $type, 'Null' => 'YES', 'Default' => $default, 'Extra' => '', 'Comment' => '', 'Collation' => null], $extra);
    }

    private function index(string $name, string $column, array $extra = []): array
    {
        return array_merge(['Key_name' => $name, 'Column_name' => $column, 'Seq_in_index' => 1, 'Non_unique' => 1, 'Sub_part' => null, 'Index_type' => 'BTREE', 'Collation' => 'A'], $extra);
    }

    private function sqlite(): SQLiteAdapter
    {
        $adapter = new SQLiteAdapter(['adapter' => 'sqlite', 'memory' => true]);
        $adapter->setConnection(new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]));
        return $adapter;
    }

    private function generate(array $tables, bool $force = false): string
    {
        return $this->generateFrom(new MigrationMetadataDatabase($tables), array_keys($tables), $force);
    }

    private function generateFrom($database, array $tables, bool $force = false): string
    {
        $app = Library::$sapp;
        try {
            Library::$sapp = (object)['db' => $database];
            $method = new \ReflectionMethod(PhinxExtend::class, '_build2table');
            $method->setAccessible(true);
            return $method->invoke(null, $tables, true, $force);
        } finally {
            Library::$sapp = $app;
        }
    }

    private function runMigration(string $source, AdapterInterface $adapter): void
    {
        $body = substr($source, 5);
        $migration = eval('use think\admin\extend\PhinxExtend; return new class($adapter) {
            private $adapter;
            public function __construct($adapter) { $this->adapter = $adapter; }
            public function table($name, $options) { return new \Phinx\Db\Table($name, $options, $this->adapter); }
            ' . $body . '};');
        $migration->change();
    }
}

class MigrationMetadataDatabase
{
    private $tables;

    private $selected;

    public function __construct(array $tables)
    {
        $this->tables = $tables;
    }

    public function connect(): self
    {
        return $this;
    }

    public function getConfig(string $key): string
    {
        return $key === 'type' ? 'mysql' : ($key === 'prefix' ? '' : 'fixture');
    }

    public function table(string $name): self
    {
        return $this;
    }

    public function where(array $conditions): self
    {
        $this->selected = $conditions['TABLE_NAME'];
        return $this;
    }

    public function find(): array
    {
        return array_merge(['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb4_general_ci', 'TABLE_COMMENT' => ''], $this->tables[$this->selected]['options'] ?? []);
    }

    public function value(string $name, string $default): string
    {
        return $this->find()[$name] ?? $default;
    }

    public function getFields(string $table): array
    {
        return array_map(function ($field) {
            return ['name' => $field['Field'], 'type' => $field['Type'], 'notnull' => $field['Null'] === 'NO', 'default' => $field['Default'], 'comment' => $field['Comment']];
        }, $this->tables[$table]['fields']);
    }

    public function query(string $sql, array $bindings = []): array
    {
        foreach ($this->tables as $table => $data) {
            if (strpos($sql, '`' . str_replace('`', '``', $table) . '`') !== false || substr($sql, -strlen($table)) === $table) {
                return stripos($sql, 'columns') !== false ? $data['fields'] : ($data['indexes'] ?? []);
            }
        }
        throw new \RuntimeException('Unexpected fixture query: ' . $sql);
    }
}

class RecordingMysqlAdapter extends MysqlAdapter
{
    public $statements = [];

    public $queries = [];

    public $indexes = [];

    public $existing = false;

    public function __construct()
    {
        parent::__construct(['adapter' => 'mysql']);
        $this->connection = new \PDO('sqlite::memory:');
    }

    public function getAttribute(int $attribute)
    {
        return $attribute === \PDO::ATTR_SERVER_VERSION ? '8.4.0' : parent::getAttribute($attribute);
    }

    public function execute($sql, array $params = []): int
    {
        $this->statements[] = $sql;
        return 0;
    }

    public function hasTable($tableName): bool
    {
        return $this->existing;
    }

    public function getColumns($tableName): array
    {
        return [];
    }

    public function fetchAll($sql): array
    {
        $this->queries[] = $sql;
        return $this->indexes;
    }
}
