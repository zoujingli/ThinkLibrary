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

    public static function isEmpty(Table $table): bool
    {
        [$adapter, $name] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        return !PhinxSchema::isDryRun($adapter) && (int)$adapter->fetchRow('SELECT COUNT(*) AS total FROM ' . $adapter->quoteTableName($name))['total'] === 0;
    }

    public static function encodeRow(array $row): string
    {
        $values = [];
        foreach ($row as $name => $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new \RuntimeException("备份字段 {$name} 不是标量值");
            }
            $values[] = [base64_encode((string)$name), is_string($value) ? [base64_encode($value)] : $value];
        }
        $json = json_encode($values, JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new \RuntimeException('备份编码失败：' . json_last_error_msg());
        }
        return self::PREFIX . $json;
    }

    public static function decodeRow(string $line): array
    {
        $versioned = strpos($line, self::PREFIX) === 0;
        $json = $versioned ? substr($line, strlen(self::PREFIX)) : $line;
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
            if (!is_array($entry) || count($entry) !== 2 || !isset($entry[0]) || !is_string($entry[0])) {
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
            $row[$name] = $value;
        }
        return $row;
    }

    public static function write(iterable $rows, string $path, ?callable $progress = null): int
    {
        $stream = fopen($path, 'xb');
        if ($stream === false) {
            throw new \RuntimeException("无法创建备份文件 {$path}");
        }
        $count = 0;
        try {
            foreach ($rows as $row) {
                $line = self::encodeRow($row) . "\n";
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
        $bitColumns = $binaryColumns = [];
        if ($adapter->getAdapterType() === 'mysql') {
            foreach ($adapter->fetchAll('SHOW FULL COLUMNS FROM ' . PhinxSchema::quote($name)) as $column) {
                if (stripos($column['Type'], 'bit(') === 0) {
                    $bitColumns[] = $column['Field'];
                }
                if (preg_match('/blob|binary/i', $column['Type'])) {
                    $binaryColumns[] = $column['Field'];
                }
            }
        } elseif ($adapter->getAdapterType() === 'sqlite') {
            foreach ($adapter->fetchAll('PRAGMA table_info(' . PhinxSchema::quote($name) . ')') as $column) {
                if (preg_match('/blob|binary/i', $column['type'])) {
                    $binaryColumns[] = $column['name'];
                }
            }
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException("无法读取备份文件 {$path}");
        }
        $ownsTransaction = $adapter->getAdapterType() !== 'sqlite' && !$connection->inTransaction();
        $count = $lineNumber = 0;
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
                    $batch[] = self::decodeRow(rtrim($line, "\r\n"));
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

    private static function insertRows($adapter, string $table, array $rows, array $bitColumns, array $binaryColumns): void
    {
        foreach ($rows as $row) {
            $columns = $values = [];
            foreach ($row as $column => $value) {
                $columns[] = $adapter->quoteColumnName((string)$column);
                // PDO 将 BIT 查询结果返回为数值；直接按字符串绑定会被 MySQL 当成字节串。
                $values[] = in_array($column, $bitColumns, true) ? 'CAST(? AS UNSIGNED)' : '?';
            }
            $statement = $adapter->getConnection()->prepare('INSERT INTO ' . $adapter->quoteTableName($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')');
            $position = 0;
            foreach ($row as $column => $value) {
                $type = $value === null ? \PDO::PARAM_NULL : (is_int($value) || is_bool($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
                if (is_string($value) && in_array($column, $binaryColumns, true)) {
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
