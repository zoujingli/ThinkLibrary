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

namespace think\admin\extend;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\AdapterWrapper;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Adapter\SQLiteAdapter;
use Phinx\Db\Adapter\TablePrefixAdapter;
use Phinx\Db\Table\Column;
use Phinx\Util\Literal;

/**
 * 读取原始结构，以少量通用类型生成迁移；数据库专有定义只在对应适配器上使用。
 */
class PhinxSchema
{
    public static function exportTables($connect, array $tables, array $ignore = []): array
    {
        return array_values(array_filter(array_unique($tables), function ($table) use ($connect, $ignore) {
            return !in_array($table, $ignore, true)
                && !in_array(self::logicalName($connect, $table), $ignore, true)
                && ($connect->getConfig('type') !== 'sqlite' || strpos($table, 'sqlite_') !== 0);
        }));
    }

    public static function logicalName($connect, string $table): string
    {
        $prefix = (string)$connect->getConfig('prefix');
        return $prefix !== '' && strpos($table, $prefix) === 0 ? substr($table, strlen($prefix)) : $table;
    }

    /**
     * Phinx 3.0 没有 Literal / 列排序规则接口，兼容适配器在同一 PDO 上编译列定义。
     */
    public static function compatibleAdapter(AdapterInterface $adapter, array $fields): AdapterInterface
    {
        if (self::supportsLiteral()) {
            return $adapter;
        }
        if ($adapter instanceof AdapterWrapper) {
            $copy = clone $adapter;
            $copy->setAdapter(self::compatibleAdapter($adapter->getAdapter(), $fields));
            return $copy;
        }
        if ($adapter instanceof MysqlAdapter) {
            return new PhinxMysqlColumns($adapter, $fields);
        }
        if ($adapter instanceof SQLiteAdapter) {
            return new PhinxSqliteColumns($adapter, $fields);
        }
        return $adapter;
    }

    public static function isDryRun(AdapterInterface $adapter): bool
    {
        return method_exists($adapter, 'isDryRunEnabled') && $adapter->isDryRunEnabled();
    }

    public static function validateSqliteReferences(AdapterInterface $adapter, string $name): void
    {
        // 事务中不能临时关闭外键，重建被引用表可能触发级联删除。
        foreach ($adapter->fetchAll("SELECT name FROM sqlite_master WHERE type = 'table'") as $table) {
            foreach ($adapter->fetchAll('PRAGMA foreign_key_list(' . self::quote($table['name']) . ')') as $foreign) {
                if (strcasecmp($foreign['table'], $name) === 0) {
                    throw new \RuntimeException("sqlite 表 {$name} 被外键引用，请通过专用迁移变更字段");
                }
            }
        }
    }

    /**
     * 解析迁移实际使用的连接和物理表名，兼容多层前后缀适配器。
     */
    public static function connection(AdapterInterface $adapter, string $table): array
    {
        while ($adapter instanceof AdapterWrapper) {
            if ($adapter instanceof TablePrefixAdapter) {
                $table = $adapter->getAdapterTableName($table);
            }
            $adapter = $adapter->getAdapter();
        }
        return [$adapter, $table];
    }

    public static function driver(AdapterInterface $adapter): string
    {
        [$adapter] = self::connection($adapter, '');
        // 工厂和直接构造适配器时都允许省略 adapter 选项。
        if ($adapter instanceof MysqlAdapter) {
            return 'mysql';
        }
        if ($adapter instanceof SQLiteAdapter) {
            return 'sqlite';
        }
        return $adapter->getAdapterType();
    }

    public static function read($connect, string $table): array
    {
        $driver = strtolower($connect->getConfig('type'));
        if ($driver === 'sqlite') {
            return self::readSqlite($connect, $table);
        }
        if ($driver !== 'mysql') {
            throw new \RuntimeException("暂不支持从 {$driver} 生成迁移脚本");
        }
        $metadata = $connect->table('information_schema.TABLES')->where([
            'TABLE_SCHEMA' => $connect->getConfig('database'), 'TABLE_NAME' => $table,
        ])->find();
        if (!$metadata) {
            throw new \RuntimeException("无法读取数据表 {$table}");
        }
        $options = ['id' => false, 'engine' => $metadata['ENGINE'], 'collation' => $metadata['TABLE_COLLATION'], 'comment' => $metadata['TABLE_COMMENT']];
        $fields = [];
        foreach ($connect->query('SHOW FULL COLUMNS FROM ' . self::quote($table)) as $field) {
            $fields[] = self::mysqlField($field, $connect, $table);
        }
        $indexes = self::mysqlIndexes($connect->query('SHOW INDEX FROM ' . self::quote($table)));
        if (isset($indexes['PRIMARY'])) {
            if (!empty($indexes['PRIMARY']['limit']) || !empty($indexes['PRIMARY']['order'])) {
                throw new \RuntimeException("暂不支持 {$table} 的前缀或降序主键");
            }
            $options['primary_key'] = $indexes['PRIMARY']['columns'];
            unset($indexes['PRIMARY']);
        }
        return [$options, $fields, array_values($indexes)];
    }

    public static function mysqlIndexes(array $rows): array
    {
        $indexes = [];
        foreach ($rows as $row) {
            $name = $row['Key_name'];
            $column = $row['Column_name'];
            if ($column === null || !empty($row['Expression'])) {
                throw new \RuntimeException("暂不支持表达式索引 {$name}");
            }
            $indexes[$name]['name'] = $name;
            $indexes[$name]['columns'][(int)$row['Seq_in_index']] = $column;
            if (empty($row['Non_unique'])) {
                $indexes[$name]['unique'] = true;
            }
            $type = strtolower($row['Index_type'] ?? 'btree');
            if ($type !== 'btree') {
                $indexes[$name]['type'] = $type;
            }
            if (!empty($row['Sub_part'])) {
                $indexes[$name]['limit'][$column] = (int)$row['Sub_part'];
            }
            if (($row['Collation'] ?? '') === 'D') {
                $indexes[$name]['order'][$column] = 'DESC';
            }
            if (!empty($row['Index_comment'])) {
                $indexes[$name]['comment'] = $row['Index_comment'];
            }
            if (($row['Visible'] ?? 'YES') === 'NO') {
                $indexes[$name]['visible'] = false;
            }
        }
        foreach ($indexes as &$index) {
            ksort($index['columns']);
            $index['columns'] = array_values($index['columns']);
        }
        return $indexes;
    }

    /**
     * 在实际运行的数据库上选择类型，不能无损转换的专有结构在执行 DDL 前报错。
     */
    public static function prepareFields(AdapterInterface $adapter, array $fields): array
    {
        [$adapter] = self::connection($adapter, '');
        $driver = self::driver($adapter);
        foreach ($fields as &$field) {
            [$name, $type] = $field;
            $options = $field[2] ?? [];
            $mysqlType = $options['mysql_type'] ?? null;
            $sqliteType = $options['sqlite_type'] ?? null;
            $literalDefault = !empty($options['default_literal']);
            $expressionDefault = !empty($options['default_expression']);
            if ($driver !== 'mysql' && (!empty($options['mysql_only']) || !empty($options['update']))) {
                throw new \RuntimeException("{$driver} 不支持字段 {$name} 的 MySQL 专有定义 " . ($mysqlType ?? $options['update']));
            }
            unset($options['mysql_only'], $options['mysql_type'], $options['sqlite_type'], $options['default_literal'], $options['default_expression']);
            $default = $options['default'] ?? null;
            if ($driver === 'sqlite' && $type === 'integer' && is_string($default) && preg_match('/^\d{19,}$/', $default) && (strlen($default) > 19 || strcmp($default, '9223372036854775807') > 0)) {
                throw new \RuntimeException("sqlite 无法精确保存字段 {$name} 的无符号大整数默认值");
            }
            // 旧适配器已保留完整列 SQL，Column 仅承载其认识的通用选项。
            if (!self::supportsLiteral() && in_array($driver, ['mysql', 'sqlite'], true)) {
                $valid = array_flip(['limit', 'default', 'null', 'identity', 'precision', 'scale', 'after', 'update', 'comment', 'signed', 'timezone', 'properties', 'values']);
                $field = [$name, $type, array_intersect_key($options, $valid)];
                continue;
            }
            if (is_string($default) && strpos($default, 'CURRENT_TIMESTAMP') === 0) {
                if (!$literalDefault && ($expressionDefault || in_array($type, ['timestamp', 'datetime'], true)) && preg_match('/^CURRENT_TIMESTAMP(?:\([0-6]\))?$/', $default)) {
                    if ($driver === 'sqlite' && preg_match('/\(([1-6])\)$/', $default)) {
                        throw new \RuntimeException("sqlite 不支持字段 {$name} 的小数秒时间默认表达式");
                    }
                    $expression = $driver === 'sqlite' ? 'CURRENT_TIMESTAMP' : $default;
                    if ($driver === 'mysql' && !in_array($type, ['timestamp', 'datetime'], true)) {
                        $expression = '(' . $expression . ')';
                    }
                    $options['default'] = Literal::from($expression);
                } else {
                    $literal = $adapter->getConnection()->quote($default);
                    if ($driver === 'mysql' && in_array($type, ['text', 'binary', 'json'], true) && version_compare($adapter->getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION), '8', '>=')) {
                        $literal = '(' . $literal . ')';
                    }
                    $options['default'] = Literal::from($literal);
                }
            }
            if ($driver === 'mysql' && $mysqlType !== null) {
                $type = Literal::from(preg_replace_callback('/^[a-z]+/i', function ($match) { return strtoupper($match[0]); }, $mysqlType));
                unset($options['signed'], $options['limit'], $options['precision'], $options['scale']);
                if (stripos($mysqlType, 'bit(') === 0 && is_string($default) && preg_match("/^b'[01]+'$/i", $default)) {
                    $options['default'] = Literal::from($default);
                }
            } elseif (in_array($type, ['decimal', 'float'], true) && isset($options['precision'], $options['scale']) && $options['scale'] === 0) {
                // 当前 Phinx 的 scale=0 分支会丢弃 precision。
                $type = Literal::from(strtoupper($type) . '(' . (int)$options['precision'] . ',0)' . ($driver === 'mysql' && isset($options['signed']) && !$options['signed'] ? ' unsigned' : ''));
                unset($options['signed'], $options['precision'], $options['scale']);
            }
            if ($driver === 'sqlite') {
                if ($sqliteType !== null) {
                    $type = Literal::from($sqliteType);
                }
                // SQLite 的整数本身为 64 位，identity 必须声明成 INTEGER。
                if (!empty($options['identity'])) {
                    $type = 'integer';
                }
                // SQLite 不存储字段备注；Phinx 会把备注拼成未转义的 SQL 注释。
                unset($options['comment'], $options['collation'], $options['encoding'], $options['signed']);
            }
            $field = [$name, $type, $options];
        }
        return $fields;
    }

    public static function sqliteIndexes(callable $query, string $table): array
    {
        $indexes = [];
        foreach ($query('PRAGMA index_list(' . self::quote($table) . ')') as $row) {
            if (($row['origin'] ?? '') === 'pk') {
                continue;
            }
            if (!empty($row['partial'])) {
                throw new \RuntimeException("暂不支持 SQLite 部分索引 {$row['name']}");
            }
            $index = ['name' => $row['name'], 'columns' => [], 'unique' => !empty($row['unique'])];
            foreach ($query('PRAGMA index_xinfo(' . self::quote($row['name']) . ')') as $column) {
                if (isset($column['key']) && !$column['key']) {
                    continue;
                }
                if ($column['name'] === null || ($column['coll'] ?? 'BINARY') !== 'BINARY') {
                    throw new \RuntimeException("暂不支持 SQLite 表达式或排序规则索引 {$row['name']}");
                }
                $index['columns'][] = $column['name'];
                if (!empty($column['desc'])) {
                    $index['order'][$column['name']] = 'DESC';
                }
            }
            // SQLite 保留 sqlite_ 名称给内部约束，导出时换成合法名称。
            if (strpos($index['name'], 'sqlite_') === 0) {
                $index['name'] = IndexNameService::generate($table, $index['columns'], $index['unique']);
            }
            $indexes[$index['name']] = $index;
        }
        return $indexes;
    }

    public static function quote(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private static function supportsLiteral(): bool
    {
        return method_exists(Column::class, 'getCollation') && class_exists(Literal::class);
    }

    private static function mysqlField(array $field, $connect = null, string $table = ''): array
    {
        $source = $field['Type'];
        $extra = $field['Extra'] ?? '';
        $options = ['default' => $field['Default'], 'null' => $field['Null'] === 'YES', 'comment' => $field['Comment'] ?? ''];
        if (preg_match('/(?:VIRTUAL|STORED|PERSISTENT) GENERATED|INVISIBLE/i', $extra)) {
            throw new \RuntimeException("暂不支持生成列或不可见列 {$field['Field']}");
        }
        if (!preg_match('/^([a-z]+)(?:\((.*)\))?(?:\s+(unsigned)(?:\s+zerofill)?|\s+zerofill)?$/i', $source, $parts)) {
            throw new \RuntimeException("无法识别字段 {$field['Field']} 的类型 {$source}");
        }
        $base = strtolower($parts[1]);
        $size = $parts[2] ?? '';
        $integers = ['tinyint' => 4, 'smallint' => 6, 'mediumint' => 9, 'int' => 11, 'integer' => 11, 'bigint' => 20];
        $texts = ['tinytext', 'text', 'mediumtext', 'longtext'];
        if (isset($integers[$base])) {
            $type = 'integer';
            $options['limit'] = $size !== '' ? (int)$size : $integers[$base];
            if (!in_array($base, ['int', 'integer'], true)) {
                $options['mysql_type'] = $source;
            }
        } elseif (in_array($base, $texts, true)) {
            $type = 'text';
            if ($base !== 'text') {
                $options['mysql_type'] = $source;
            }
        } elseif (in_array($base, ['varchar', 'char'], true)) {
            $type = 'string';
            $options['limit'] = (int)$size;
            if ($base === 'char') {
                $options['mysql_type'] = $source;
            }
        } elseif (in_array($base, ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit'], true)) {
            $type = 'binary';
            $options['mysql_type'] = $source;
            if ($base === 'bit') {
                $options['mysql_only'] = true;
            }
        } elseif (in_array($base, ['enum', 'set', 'year'], true)) {
            $type = $base === 'year' ? 'integer' : 'string';
            $options['mysql_type'] = $source;
            $options['mysql_only'] = true;
        } elseif (in_array($base, ['decimal', 'numeric', 'float', 'double'], true)) {
            $type = in_array($base, ['decimal', 'numeric'], true) ? 'decimal' : 'float';
            if ($size !== '') {
                $dimensions = array_map('intval', explode(',', $size));
                $options['precision'] = $dimensions[0];
                $options['scale'] = $dimensions[1] ?? 0;
            }
            if ($base === 'double') {
                $options['mysql_type'] = $source;
            }
        } elseif (in_array($base, ['timestamp', 'datetime', 'time', 'date', 'json'], true)) {
            $type = $base;
            if ($size !== '') {
                $options['limit'] = (int)$size;
            }
        } else {
            throw new \RuntimeException("暂不支持字段 {$field['Field']} 的类型 {$source}");
        }
        if (preg_match('/\bunsigned\b/i', $source)) {
            $options['signed'] = false;
        }
        if (preg_match('/\bzerofill\b/i', $source)) {
            $options['mysql_type'] = $source;
            $options['mysql_only'] = true;
        }
        if (stripos($extra, 'auto_increment') !== false) {
            $options['identity'] = true;
        }
        if (!empty($field['Collation'])) {
            $options['collation'] = $field['Collation'];
        }
        $temporal = in_array($base, ['timestamp', 'datetime'], true);
        $generatedDefault = stripos($extra, 'DEFAULT_GENERATED') !== false;
        if (($temporal || $generatedDefault) && is_string($options['default']) && preg_match('/^(?:current_timestamp|now)(?:\(([0-6]?)\))?$/i', $options['default'], $match)) {
            $options['default'] = 'CURRENT_TIMESTAMP' . (isset($match[1]) && $match[1] !== '' ? '(' . $match[1] . ')' : '');
            if (!$temporal) {
                $options['default_expression'] = true;
            }
        } elseif ($generatedDefault && $options['default'] !== null) {
            $options['default'] = self::mysqlLiteralDefault($connect, $table, $field['Field']);
            $options['default_literal'] = true;
        }
        if (preg_match('/on update (current_timestamp(?:\([0-6]?\))?)/i', $extra, $match)) {
            $options['update'] = strtoupper($match[1]);
        }
        return [$field['Field'], $type, $options];
    }

    private static function mysqlLiteralDefault($connect, string $table, string $column): string
    {
        // MySQL 8 的 COLUMN_DEFAULT 会额外转义，甚至重编码非 ASCII 字符。
        // 从建表语句读取完整字面量，仅允许一个字符串常量，拒绝任意表达式。
        if ($connect !== null) {
            $definition = $connect->query('SHOW CREATE TABLE ' . self::quote($table));
            $sql = $definition[0]['Create Table'] ?? '';
            $literal = "(?:_(?:utf8mb4|utf8mb3|utf8|ascii|binary)\\s*)?'(?:[^'\\\\]|\\\\.|'')*'";
            $pattern = '/(?:^|\n)[ \t]*' . preg_quote(self::quote($column), '/')
                . '[ \t]+[a-z]+(?:\(\d+(?:,\d+)?\))?'
                . '(?:[ \t]+(?:CHARACTER SET [a-z0-9_]+|COLLATE [a-z0-9_]+|NOT NULL|NULL))*'
                . '[ \t]+DEFAULT[ \t]*\([ \t]*(' . $literal . ')[ \t]*\)/is';
            if (preg_match($pattern, $sql, $match)) {
                $value = substr($match[1], strpos($match[1], "'") + 1, -1);
                // SHOW CREATE 的表达式字面量始终使用反斜线转义，不受读取会话 SQL mode 影响。
                return preg_replace_callback("/''|\\\\(.)/s", function ($part) {
                    if ($part[0] === "''") {
                        return "'";
                    }
                    return ['0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a"][$part[1]] ?? $part[1];
                }, $value);
            }
        }
        throw new \RuntimeException("暂不支持字段 {$column} 的默认表达式");
    }

    private static function readSqlite($connect, string $table): array
    {
        $fields = $primary = [];
        $options = ['id' => false];
        $definition = $connect->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = '" . str_replace("'", "''", $table) . "'");
        $sql = $definition[0]['sql'] ?? '';
        // 忽略字符串、引号标识符和注释后检查不能无损导出的表级约束。
        $structure = preg_replace('/\x27(?:\x27\x27|[^\x27])*\x27|"(?:""|[^"])*"|`(?:``|[^`])*`|\[[^\]]*\]|--[^\n]*|\/\*.*?\*\//s', '', $sql);
        if (preg_match('/\bWITHOUT\s+ROWID\b|\bSTRICT\s*$|\bCHECK\s*\(|\bREFERENCES\b|\bPRIMARY\s+KEY\s+DESC\b|\bCOLLATE\b|\bON\s+CONFLICT\b/i', $structure)) {
            throw new \RuntimeException("暂不支持 SQLite 表 {$table} 的专有表约束");
        }
        $columns = $connect->query('PRAGMA table_xinfo(' . self::quote($table) . ')');
        if (!$columns) {
            throw new \RuntimeException("无法读取数据表 {$table}");
        }
        foreach ($columns as $column) {
            if (!empty($column['hidden'])) {
                throw new \RuntimeException("暂不支持 SQLite 生成列 {$column['name']}");
            }
            $type = strtolower($column['type']);
            $type = strtr($type, ['_text' => '', '_blob' => '', '_integer' => '']);
            $type = preg_replace('/^(?:tinyinteger|smallinteger|biginteger|integer)$/', 'bigint', $type);
            $type = preg_replace('/^boolean$/', 'tinyint', $type);
            $type = preg_replace('/^real$/', 'float', $type);
            $default = $column['dflt_value'];
            $literalDefault = false;
            $expressionDefault = false;
            if ($default !== null) {
                if (preg_match("/^'(.*)'$/s", $default, $match)) {
                    $default = str_replace("''", "'", $match[1]);
                    $literalDefault = true;
                } elseif (strtoupper($default) === 'NULL') {
                    $default = null;
                } elseif (strtoupper($default) === 'CURRENT_TIMESTAMP') {
                    $default = 'CURRENT_TIMESTAMP';
                    $expressionDefault = true;
                } elseif (!is_numeric($default)) {
                    throw new \RuntimeException("暂不支持 SQLite 字段 {$column['name']} 的默认表达式");
                }
            }
            $field = self::mysqlField(['Field' => $column['name'], 'Type' => $type, 'Null' => $column['notnull'] ? 'NO' : 'YES', 'Default' => $default]);
            if ($literalDefault) {
                $field[2]['default'] = $default;
                $field[2]['default_literal'] = true;
            }
            if ($expressionDefault) {
                $field[2]['default_expression'] = true;
            }
            if ($column['pk']) {
                $primary[(int)$column['pk']] = $column['name'];
                // 只有精确的 INTEGER 单列主键才是 rowid 别名；INT/BIGINT 允许空值。
                if ($field[1] === 'integer' && strcasecmp($column['type'], 'integer') !== 0) {
                    $field[2]['sqlite_type'] = strtoupper($column['type']);
                }
            }
            $fields[] = $field;
        }
        if ($primary) {
            ksort($primary);
            $options['primary_key'] = array_values($primary);
            if (count($primary) === 1 && preg_match('/\bAUTOINCREMENT\b/i', $structure)) {
                foreach ($columns as $i => $column) {
                    if ($column['pk'] && strcasecmp($column['type'], 'integer') === 0) {
                        $fields[$i][2]['identity'] = true;
                    }
                }
            }
        }
        $indexes = self::sqliteIndexes(function ($sql) use ($connect) { return $connect->query($sql); }, $table);
        return [$options, $fields, array_values($indexes)];
    }
}
