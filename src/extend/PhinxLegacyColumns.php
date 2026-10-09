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
 * 补充旧版 Phinx 缺失的列类型、精度和默认值能力.
 * 复用原适配器的连接及执行行为，仅为需要兼容的字段保存完整列定义.
 * @internal
 */
trait PhinxLegacyColumns
{
    /**
     * 需要兼容的字段名到 SQL 列定义的映射，定义不含列名.
     * @var array<string, string>
     */
    private $definitions = [];

    /**
     * 提供连接、查询和 dry-run 行为的原迁移适配器.
     * @var AdapterInterface
     */
    private $wrappedAdapter;

    /**
     * 复用原适配器配置和 PDO，仅预编译旧版本无法完整表达的字段.
     * @param AdapterInterface $adapter 已解包的 MySQL / SQLite 迁移适配器
     * @param array<int, array> $fields 原始字段配置，每项为 [名称, 类型, 选项（可选）]
     * @throws \RuntimeException 目标数据库不支持字段的专有定义
     */
    public function __construct(AdapterInterface $adapter, array $fields)
    {
        $this->wrappedAdapter = $adapter;
        $this->connection = $adapter->getConnection();
        // 旧版父构造器会检查迁移表，并回调 fetchAll / execute。
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

    /**
     * 委托原适配器执行 SQL，保留其日志及 dry-run 行为.
     * {@inheritDoc}
     */
    public function execute($sql, array $params = []): int
    {
        return $this->wrappedAdapter->execute($sql, $params);
    }

    /**
     * 委托原适配器查询，保留原连接和结果格式.
     * {@inheritDoc}
     */
    public function fetchAll($sql): array
    {
        return $this->wrappedAdapter->fetchAll($sql);
    }

    /**
     * 检查物理表是否存在，SQLite 直接查询元数据以准确匹配名称.
     * {@inheritDoc}
     */
    public function hasTable($tableName): bool
    {
        if (PhinxSchema::driver($this) === 'sqlite') {
            return $this->connection->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $this->connection->quote($tableName))->fetchColumn() !== false;
        }
        return $this->wrappedAdapter->hasTable($tableName);
    }

    /**
     * 通过原适配器读取列对象，沿用原有元数据解析规则.
     * {@inheritDoc}
     */
    public function getColumns($tableName): array
    {
        return $this->wrappedAdapter->getColumns($tableName);
    }

    /**
     * 允许已预编译的字段定义通过旧版类型检查，其余列沿用父适配器校验.
     * {@inheritDoc}
     */
    public function isValidColumnType(Column $column): bool
    {
        // 已编译的原始定义不依赖旧版类型名单，例如 SQLite 的 JSON 声明。
        return isset($this->definitions[$column->getName()]) || parent::isValidColumnType($column);
    }

    /**
     * 优先使用已预编译的兼容列定义，普通列仍由父适配器生成.
     * {@inheritDoc}
     */
    protected function getColumnSqlDefinition(Column $column): string
    {
        return $this->definitions[$column->getName()] ?? parent::getColumnSqlDefinition($column);
    }

    /**
     * 编译需要兼容的列定义，区分默认字面量、时间表达式和位字面量.
     * @param array{0:string,1:string,2?:array} $field [字段名, 通用类型, 字段选项（可选）]
     * @return string 不含字段名的 SQL 列定义
     * @throws \RuntimeException SQLite 不支持该 MySQL 专有定义或小数秒时间默认值
     */
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
