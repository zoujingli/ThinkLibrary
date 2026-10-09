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

use Phinx\Db\Table;

/**
 * 带版本的逐行备份：字符串按字节保存，仍可读取原来的 JSONL 文件。
 */
class PhinxBackup
{
    private const PREFIX = 'ThinkAdminBackup:1:';

    private const TYPED_PREFIX = 'ThinkAdminBackup:2:';

    private const SEQUENCE_PREFIX = 'ThinkAdminBackup:sequence:';

    public static function readSqliteSequence(\PDO $connection, string $table): ?string
    {
        if ($connection->query("SELECT name FROM sqlite_master WHERE name = 'sqlite_sequence'")->fetchColumn() === false) {
            return null;
        }
        $sequence = $connection->query('SELECT seq FROM sqlite_sequence WHERE name = ' . $connection->quote($table))->fetchColumn();
        return $sequence === false ? null : (string)$sequence;
    }

    public static function restoreSqliteSequence(\PDO $connection, string $table, ?string $sequence): void
    {
        if ($sequence === null) {
            return;
        }
        self::validateType('integer', $sequence);
        $definition = $connection->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $connection->quote($table))->fetchColumn();
        $structure = preg_replace('/\x27(?:\x27\x27|[^\x27])*\x27|"(?:""|[^"])*"|`(?:``|[^`])*`|\[[^\]]*\]|--[^\n]*|\/\*.*?\*\//s', '', (string)$definition);
        if (!preg_match('/\bAUTOINCREMENT\b/i', $structure)) {
            return;
        }
        $statement = $connection->prepare('UPDATE sqlite_sequence SET seq = MAX(seq, CAST(? AS INTEGER)) WHERE name = ?');
        $statement->execute([$sequence, $table]);
        if ($statement->rowCount() === 0) {
            // 空表尚未发放编号时 sqlite_sequence 没有对应记录。
            $statement = $connection->prepare('INSERT INTO sqlite_sequence(name, seq) VALUES (?, CAST(? AS INTEGER))');
            $statement->execute([$table, $sequence]);
        }
    }

    public static function isEmpty(Table $table): bool
    {
        [$adapter, $name] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        return !PhinxSchema::isDryRun($adapter) && (int)$adapter->fetchRow('SELECT COUNT(*) AS total FROM ' . $adapter->quoteTableName($name))['total'] === 0;
    }

    public static function encodeRow(array $row, array $types = []): string
    {
        $values = [];
        foreach ($row as $name => $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new \RuntimeException("备份字段 {$name} 不是标量值");
            }
            $entry = [base64_encode((string)$name), is_string($value) ? [base64_encode($value)] : $value];
            if ($types) {
                self::validateType($types[$name] ?? '', $value);
                $entry[] = $types[$name];
            }
            $values[] = $entry;
        }
        $json = json_encode($values, JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new \RuntimeException('备份编码失败：' . json_last_error_msg());
        }
        return ($types ? self::TYPED_PREFIX : self::PREFIX) . $json;
    }

    public static function decodeRow(string $line, ?array &$types = null): array
    {
        $types = [];
        $typed = strpos($line, self::TYPED_PREFIX) === 0;
        $versioned = $typed || strpos($line, self::PREFIX) === 0;
        $json = $versioned ? substr($line, strlen($typed ? self::TYPED_PREFIX : self::PREFIX)) : $line;
        $values = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($values) || !$values) {
            throw new \RuntimeException('备份记录损坏或版本不受支持：' . json_last_error_msg());
        }
        if (!$versioned) {
            foreach ($values as $value) {
                if (!is_scalar($value) && $value !== null) {
                    throw new \RuntimeException('旧版备份记录包含非法字段值');
                }
            }
            return $values;
        }
        $row = [];
        foreach ($values as $entry) {
            if (!is_array($entry) || count($entry) !== ($typed ? 3 : 2) || !isset($entry[0]) || !is_string($entry[0])) {
                throw new \RuntimeException('备份字段结构损坏');
            }
            $name = base64_decode($entry[0], true);
            $value = $entry[1];
            if (is_array($value)) {
                if (count($value) !== 1 || !isset($value[0]) || !is_string($value[0]) || ($value = base64_decode($value[0], true)) === false) {
                    throw new \RuntimeException('备份字段编码损坏');
                }
            }
            if ($name === false || array_key_exists($name, $row)) {
                throw new \RuntimeException('备份字段名称损坏或重复');
            }
            if ($typed) {
                self::validateType($entry[2] ?? '', $value);
                $types[$name] = $entry[2];
            }
            $row[$name] = $value;
        }
        return $row;
    }

    public static function write(iterable $rows, string $path, ?callable $progress = null): int
    {
        $records = (function () use ($rows) {
            foreach ($rows as $row) {
                yield [$row, []];
            }
        })();
        return self::writeRecords($records, $path, $progress);
    }

    /**
     * 在读取边界保留 SQLite 每值类型及旧 mysqlnd 会截断的小数秒，不改变应用连接配置。
     * @param mixed $connect
     */
    public static function writeTable($connect, string $table, string $path, ?callable $progress = null): int
    {
        $sequence = $connect->getConfig('type') === 'sqlite' ? self::readSqliteSequence($connect->connect(), $table) : null;
        return self::writeRecords(self::readTable($connect, $table), $path, $progress, $sequence);
    }

    /**
     * 使用迁移连接恢复到空表，坏行直接报错；支持事务的目标表会回滚。
     */
    public static function restore(Table $table, string $path, ?callable $progress = null): int
    {
        [$adapter, $name] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        if (PhinxSchema::isDryRun($adapter)) {
            return 0;
        }
        $row = $adapter->fetchRow('SELECT COUNT(*) AS total FROM ' . $adapter->quoteTableName($name));
        if ((int)$row['total'] > 0) {
            return 0;
        }
        $connection = $adapter->getConnection();
        $bitColumns = $binaryColumns = $integerColumns = [];
        $driver = PhinxSchema::driver($adapter);
        if ($driver === 'mysql') {
            foreach ($adapter->fetchAll('SHOW FULL COLUMNS FROM ' . PhinxSchema::quote($name)) as $column) {
                if (stripos($column['Type'], 'bit(') === 0) {
                    $bitColumns[] = $column['Field'];
                }
                if (preg_match('/blob|binary/i', $column['Type'])) {
                    $binaryColumns[] = $column['Field'];
                }
            }
        } elseif ($driver === 'sqlite') {
            foreach ($adapter->fetchAll('PRAGMA table_info(' . PhinxSchema::quote($name) . ')') as $column) {
                if (stripos($column['type'], 'int') !== false) {
                    $integerColumns[] = $column['name'];
                }
                if (preg_match('/blob|binary/i', $column['type'])) {
                    $binaryColumns[] = $column['name'];
                }
            }
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException("无法读取备份文件 {$path}");
        }
        $ownsTransaction = $driver !== 'sqlite' && !$connection->inTransaction();
        $count = $lineNumber = 0;
        $sequence = null;
        try {
            if ($ownsTransaction) {
                $connection->beginTransaction();
            }
            $adapter->execute('SAVEPOINT phinx_backup_restore');
            try {
                $batch = [];
                while (($line = fgets($stream)) !== false) {
                    ++$lineNumber;
                    if (trim($line) === '') {
                        continue;
                    }
                    if ($lineNumber === 1 && strpos($line, self::SEQUENCE_PREFIX) === 0) {
                        $sequence = substr(rtrim($line, "\r\n"), strlen(self::SEQUENCE_PREFIX));
                        self::validateType('integer', $sequence);
                        continue;
                    }
                    $row = self::decodeRow(rtrim($line, "\r\n"), $types);
                    foreach ($integerColumns as $column) {
                        if (!isset($types[$column]) && isset($row[$column]) && is_string($row[$column]) && self::integerOverflows($row[$column])) {
                            throw new \RuntimeException("sqlite 无法精确保存字段 {$column} 的整数：超出 64 位有符号整数范围");
                        }
                    }
                    $batch[] = [$row, $driver === 'sqlite' ? $types : []];
                    ++$count;
                    if (count($batch) >= 100) {
                        self::insertRows($adapter, $name, $batch, $bitColumns, $binaryColumns);
                        $batch = [];
                    }
                    if ($progress !== null) {
                        $progress($count);
                    }
                }
                if (!feof($stream)) {
                    throw new \RuntimeException('读取备份文件失败');
                }
                if ($batch) {
                    self::insertRows($adapter, $name, $batch, $bitColumns, $binaryColumns);
                }
                if ($driver === 'sqlite') {
                    self::restoreSqliteSequence($connection, $name, $sequence);
                }
                $adapter->execute('RELEASE SAVEPOINT phinx_backup_restore');
                if ($ownsTransaction) {
                    $connection->commit();
                }
            } catch (\Throwable $exception) {
                if ($ownsTransaction) {
                    $connection->rollBack();
                } else {
                    $adapter->execute('ROLLBACK TO SAVEPOINT phinx_backup_restore');
                    $adapter->execute('RELEASE SAVEPOINT phinx_backup_restore');
                }
                throw new \RuntimeException("恢复 {$path} 第 {$lineNumber} 行失败：" . $exception->getMessage(), 0, $exception);
            }
        } finally {
            fclose($stream);
        }
        return $count;
    }

    private static function readTable($connect, string $table): \Generator
    {
        $driver = $connect->getConfig('type');
        $name = PhinxSchema::quote($table);
        if (!in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new \RuntimeException("暂不支持从 {$driver} 备份数据");
        }
        $columns = $connect->query($driver === 'sqlite' ? 'PRAGMA table_info(' . $name . ')' : 'SHOW FULL COLUMNS FROM ' . $name);
        $names = $fields = $types = [];
        foreach ($columns as $column) {
            $names[] = $column[$driver === 'sqlite' ? 'name' : 'Field'];
            $field = PhinxSchema::quote(end($names));
            if ($driver === 'mysql' && preg_match('/^(?:timestamp|datetime|time)(?:\(|$)/i', $column['Type'])) {
                $fields[] = 'CAST(' . $field . ' AS CHAR)';
            } elseif ($driver === 'sqlite') {
                // PHP 7 的 pdo_sqlite 把 REAL 转成仅 15 位有效数字的字符串。
                $fields[] = "CASE WHEN typeof({$field}) = 'real' THEN printf('%!.26g', {$field}) ELSE {$field} END";
            } else {
                $fields[] = $field;
            }
            if ($driver === 'sqlite') {
                $types[] = 'typeof(' . $field . ')';
            }
        }
        if (!$names) {
            throw new \RuntimeException("无法读取数据表 {$table}");
        }
        // 使用数字下标分开字段与类型，不引入可能和业务字段重名的别名。
        $statement = $connect->connect()->query('SELECT ' . implode(', ', array_merge($fields, $types)) . ' FROM ' . $name);
        try {
            while (($values = $statement->fetch(\PDO::FETCH_NUM)) !== false) {
                yield [array_combine($names, array_slice($values, 0, count($names))), $types ? array_combine($names, array_slice($values, count($names))) : []];
            }
        } finally {
            $statement->closeCursor();
        }
    }

    private static function writeRecords(iterable $records, string $path, ?callable $progress, ?string $sequence = null): int
    {
        $stream = fopen($path, 'xb');
        if ($stream === false) {
            throw new \RuntimeException("无法创建备份文件 {$path}");
        }
        $count = 0;
        try {
            if ($sequence !== null) {
                self::validateType('integer', $sequence);
                $header = self::SEQUENCE_PREFIX . $sequence . "\n";
                if (fwrite($stream, $header) !== strlen($header)) {
                    throw new \RuntimeException("备份写入失败 {$path}");
                }
            }
            foreach ($records as [$row, $types]) {
                $line = self::encodeRow($row, $types) . "\n";
                $offset = 0;
                while ($offset < strlen($line)) {
                    $written = fwrite($stream, substr($line, $offset));
                    if ($written === false || $written === 0) {
                        throw new \RuntimeException("备份写入失败 {$path}");
                    }
                    $offset += $written;
                }
                ++$count;
                if ($progress !== null) {
                    $progress($count);
                }
            }
            if (!fflush($stream)) {
                throw new \RuntimeException("备份写入失败 {$path}");
            }
        } catch (\Throwable $exception) {
            fclose($stream);
            unlink($path);
            throw $exception;
        }
        fclose($stream);
        return $count;
    }

    private static function validateType($type, $value): void
    {
        if (($type === 'null' && $value === null)
            || (in_array($type, ['text', 'blob'], true) && is_string($value))
            || ($type === 'real' && (is_float($value) || is_int($value) || (is_string($value) && preg_match('/^[+-]?\d+(?:\.\d*)?(?:[eE][+-]?\d+)?$/D', $value) && is_finite((float)$value))))
            || ($type === 'integer' && (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/D', $value) && !self::integerOverflows($value))))) {
            return;
        }
        throw new \RuntimeException('备份字段存储类型损坏');
    }

    private static function integerOverflows(string $value): bool
    {
        $value = trim($value);
        if (!preg_match('/^[+-]?\d+$/D', $value)) {
            return false;
        }
        // 先转成 float 或 int 会在范围检查前丢失精度。
        $limit = $value[0] === '-' ? '9223372036854775808' : '9223372036854775807';
        $digits = ltrim($value, '+-0');
        return strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, $limit) > 0);
    }

    private static function insertRows($adapter, string $table, array $rows, array $bitColumns, array $binaryColumns): void
    {
        $statements = [];
        foreach ($rows as [$row, $types]) {
            $columns = $values = [];
            foreach ($row as $column => $value) {
                $columns[] = $adapter->quoteColumnName((string)$column);
                // PDO 将 BIT 查询结果返回为数值；直接按字符串绑定会被 MySQL 当成字节串。
                if (in_array($column, $bitColumns, true)) {
                    $values[] = 'CAST(? AS UNSIGNED)';
                } elseif (in_array($types[$column] ?? '', ['real', 'integer'], true)) {
                    $values[] = 'CAST(? AS ' . strtoupper($types[$column]) . ')';
                } else {
                    $values[] = '?';
                }
            }
            $sql = 'INSERT INTO ' . $adapter->quoteTableName($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
            $statement = $statements[$sql] ?? ($statements[$sql] = $adapter->getConnection()->prepare($sql));
            $position = 0;
            foreach ($row as $column => $value) {
                $type = $value === null ? \PDO::PARAM_NULL : (is_int($value) || is_bool($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
                if (is_string($value) && (($types[$column] ?? '') === 'blob' || (!isset($types[$column]) && in_array($column, $binaryColumns, true)))) {
                    $type = \PDO::PARAM_LOB;
                } elseif (is_float($value)) {
                    $value = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);
                }
                $statement->bindValue(++$position, $value, $type);
            }
            $statement->execute();
        }
    }
}
