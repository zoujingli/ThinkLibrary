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
use Phinx\Db\Table\Column;

/**
 * 旧版 Phinx 缺失的类型、精度和默认值能力；不修改依赖或切换连接。
 * @internal
 */
trait PhinxLegacyColumns
{
    private $definitions = [];

    private $wrappedAdapter;

    public function __construct(AdapterInterface $adapter, array $fields)
    {
        $this->wrappedAdapter = $adapter;
        $this->connection = $adapter->getConnection();
        // Phinx 3.0 的父构造器会检查迁移表，并回调 fetchAll / execute。
        parent::__construct($adapter->getOptions(), $adapter->getInput(), $adapter->getOutput());
        $mysql = PhinxSchema::driver($this) === 'mysql';
        foreach ($fields as $field) {
            $options = $field[2] ?? [];
            $custom = array_intersect_key($options, array_flip(['mysql_type', 'sqlite_type', 'collation', 'precision', 'scale', 'default_literal', 'default_expression']));
            $temporal = in_array($field[1], ['timestamp', 'datetime', 'time'], true) && isset($options['limit']);
            $timestamp = is_string($options['default'] ?? null) && strpos($options['default'], 'CURRENT_TIMESTAMP') === 0;
            $textDefault = $mysql && in_array($field[1], ['text', 'binary', 'json'], true) && is_string($options['default'] ?? null) && version_compare($this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION), '8', '>=');
            $notNull = !$mysql && isset($options['null']) && !$options['null'] && !isset($options['default']);
            // 普通列沿用父适配器，保留 values、布尔默认值等原有选项语义。
            if ($custom || $temporal || $timestamp || $textDefault || $notNull) {
                $this->definitions[$field[0]] = $this->compileColumn($field);
            }
        }
    }

    public function execute($sql, array $params = []): int
    {
        return $this->wrappedAdapter->execute($sql, $params);
    }

    public function fetchAll($sql): array
    {
        return $this->wrappedAdapter->fetchAll($sql);
    }

    public function hasTable($tableName): bool
    {
        if (PhinxSchema::driver($this) === 'sqlite') {
            return $this->connection->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $this->connection->quote($tableName))->fetchColumn() !== false;
        }
        return $this->wrappedAdapter->hasTable($tableName);
    }

    public function getColumns($tableName): array
    {
        return $this->wrappedAdapter->getColumns($tableName);
    }

    public function isValidColumnType(Column $column): bool
    {
        // 已编译的原始定义不依赖旧版类型名单，例如 SQLite 的 JSON 声明。
        return isset($this->definitions[$column->getName()]) || parent::isValidColumnType($column);
    }

    protected function getColumnSqlDefinition(Column $column): string
    {
        return $this->definitions[$column->getName()] ?? parent::getColumnSqlDefinition($column);
    }

    private function compileColumn(array $field): string
    {
        [$name, $type] = $field;
        $options = $field[2] ?? [];
        $mysql = PhinxSchema::driver($this) === 'mysql';
        if (!$mysql && (!empty($options['mysql_only']) || !empty($options['update']))) {
            throw new \RuntimeException("sqlite 不支持字段 {$name} 的 MySQL 专有定义");
        }
        if ($mysql && isset($options['mysql_type'])) {
            $sql = preg_replace_callback('/^[a-z]+/i', function ($match) { return strtoupper($match[0]); }, $options['mysql_type']);
        } elseif (!$mysql && isset($options['sqlite_type'])) {
            $sql = $options['sqlite_type'];
        } else {
            $definition = $this->getSqlType($type, $options['limit'] ?? null);
            $sql = strtoupper($definition['name']);
            if (isset($options['precision'], $options['scale'])) {
                $sql .= '(' . (int)$options['precision'] . ',' . (int)$options['scale'] . ')';
            } elseif ($mysql && isset($definition['limit'])) {
                $sql .= '(' . $definition['limit'] . ')';
            } elseif ($mysql && in_array($type, ['timestamp', 'datetime', 'time'], true) && isset($options['limit'])) {
                $sql .= '(' . (int)$options['limit'] . ')';
            } elseif (!$mysql && $type === 'string' && isset($options['limit'])) {
                $sql .= '(' . (int)$options['limit'] . ')';
            }
            if ($mysql && isset($options['signed']) && !$options['signed']) {
                $sql .= ' unsigned';
            }
            if (!empty($options['values'])) {
                $sql .= '(' . implode(', ', array_map(function ($value) { return $value === null ? 'NULL' : $this->connection->quote($value); }, $options['values'])) . ')';
            }
        }
        if ($mysql && !empty($options['collation'])) {
            $sql .= ' COLLATE ' . $options['collation'];
        }
        $sql .= !empty($options['null']) ? ' NULL' : ' NOT NULL';
        if (!empty($options['identity'])) {
            $sql .= $mysql ? ' AUTO_INCREMENT' : ' PRIMARY KEY AUTOINCREMENT';
        }
        $default = $options['default'] ?? null;
        $defaultSql = $this->getDefaultValueDefinition($default, $type);
        if ($default !== null) {
            if (empty($options['default_literal']) && (!empty($options['default_expression']) || in_array($type, ['timestamp', 'datetime'], true)) && is_string($default) && preg_match('/^CURRENT_TIMESTAMP(?:\([0-6]\))?$/', $default)) {
                if (!$mysql && preg_match('/\([1-6]\)/', $default)) {
                    throw new \RuntimeException("sqlite 不支持字段 {$name} 的小数秒时间默认表达式");
                }
                $default = $mysql ? $default : 'CURRENT_TIMESTAMP';
                if ($mysql && !in_array($type, ['timestamp', 'datetime'], true)) {
                    $default = '(' . $default . ')';
                }
                $defaultSql = ' DEFAULT ' . $default;
            } elseif ($mysql && strpos($options['mysql_type'] ?? '', 'bit(') === 0 && is_string($default) && preg_match("/^b'[01]+'$/i", $default)) {
                // MySQL 的位字面量保持原样。
                $defaultSql = ' DEFAULT ' . $default;
            } elseif (is_string($default)) {
                $default = $this->connection->quote($default);
                if ($mysql && in_array($type, ['text', 'binary', 'json'], true) && version_compare($this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION), '8', '>=')) {
                    $default = '(' . $default . ')';
                }
                $defaultSql = ' DEFAULT ' . $default;
            }
        }
        $sql .= $defaultSql;
        if ($mysql && !empty($options['comment'])) {
            $sql .= ' COMMENT ' . $this->connection->quote($options['comment']);
        }
        if ($mysql && !empty($options['update'])) {
            $sql .= ' ON UPDATE ' . $options['update'];
        }
        return $sql;
    }
}
