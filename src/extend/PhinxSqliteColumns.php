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

use Phinx\Db\Adapter\SQLiteAdapter;
use Phinx\Db\Table;

/**
 * 旧版 Phinx 的 SQLite 列定义与表重建兼容层.
 * 由 PhinxSchema 为缺少 Literal 或列排序规则接口的版本创建.
 * @class PhinxSqliteColumns
 * @internal
 */
class PhinxSqliteColumns extends SQLiteAdapter
{
    use PhinxLegacyColumns;

    /**
     * 创建数据表，避免自增列内联主键与同名单列表级主键重复声明.
     * @param Table $table 携带待创建字段及表选项的迁移表对象
     */
    public function createTable(Table $table): void
    {
        $options = $table->getOptions();
        $columns = $table->getPendingColumns();
        foreach ($columns as $column) {
            if ($column->isIdentity() && (array)($options['primary_key'] ?? []) === [$column->getName()]) {
                unset($options['primary_key']);
            }
        }
        $copy = new Table($table->getName(), $options, $this);
        foreach ($columns as $column) {
            $copy->addColumn($column);
        }
        parent::createTable($copy);
    }

    /**
     * 按完整元数据重建旧版适配器无法可靠修改的字段，保留其他列、索引及触发器.
     * 使用保存点保证复制或替换失败时回滚；外键引用检查及历史自增序号恢复由 upgrade 负责.
     * @param string $name 已解析前后缀的物理表名
     * @param array<int, array> $fields 要新增或替换的字段，每项为 [名称, 类型, 选项（可选）]
     * @throws \RuntimeException 表结构或字段定义无法由兼容层支持
     */
    public function replaceFields(string $name, array $fields): void
    {
        // 为结构读取器提供查询接口，继续使用当前迁移连接。
        $source = new class($this) {
            /**
             * 当前 SQLite 迁移适配器.
             * @var PhinxSqliteColumns
             */
            private $adapter;

            /**
             * 包装迁移适配器供结构读取器查询.
             * @param PhinxSqliteColumns $adapter 当前迁移适配器
             */
            public function __construct($adapter)
            {
                $this->adapter = $adapter;
            }

            /**
             * 提供结构读取所需的数据库类型配置.
             * @param string $key 配置项名称
             * @return string type 返回 sqlite，其他配置返回空字符串
             */
            public function getConfig($key)
            {
                return $key === 'type' ? 'sqlite' : '';
            }

            /**
             * 在迁移连接上读取元数据.
             * @param string $sql 元数据查询语句
             * @return array<int, array<string, mixed>> 查询结果行
             */
            public function query($sql)
            {
                return $this->adapter->fetchAll($sql);
            }
        };
        [$options, $current, $indexes] = PhinxSchema::read($source, $name);
        $merged = array_column($current, null, 0);
        foreach ($fields as $field) {
            $merged[$field[0]] = $field;
        }
        $adapter = new self($this, array_values($merged));
        $temporary = 'phinx_rebuild_' . bin2hex(random_bytes(8));
        $table = new Table($temporary, $options, $adapter);
        foreach (PhinxSchema::prepareFields($adapter, array_values($merged)) as $field) {
            $table->addColumn($field[0], $field[1], $field[2]);
        }
        $quotedName = $this->connection->quote($name);
        $triggers = $this->fetchAll("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . $quotedName);
        $columns = array_column($current, 0);
        // 保留没有显式 INTEGER 主键时的 rowid。
        foreach (['_rowid_', 'rowid', 'oid'] as $rowid) {
            if (!in_array($rowid, array_map('strtolower', array_keys($merged)), true)) {
                $columns[] = $rowid;
                break;
            }
        }
        $columns = implode(', ', array_map([PhinxSchema::class, 'quote'], $columns));
        $this->execute('SAVEPOINT phinx_legacy_rebuild');
        try {
            $table->create();
            $this->execute('INSERT INTO ' . PhinxSchema::quote($temporary) . ' (' . $columns . ') SELECT ' . $columns . ' FROM ' . PhinxSchema::quote($name));
            $this->execute('DROP TABLE ' . PhinxSchema::quote($name));
            $this->execute('ALTER TABLE ' . PhinxSchema::quote($temporary) . ' RENAME TO ' . PhinxSchema::quote($name));
            PhinxExtend::upgrade(new Table($name, [], $adapter), [], $indexes, true);
            foreach ($triggers as $trigger) {
                $this->execute($trigger['sql']);
            }
            $this->execute('RELEASE SAVEPOINT phinx_legacy_rebuild');
        } catch (\Throwable $exception) {
            $this->execute('ROLLBACK TO SAVEPOINT phinx_legacy_rebuild');
            $this->execute('RELEASE SAVEPOINT phinx_legacy_rebuild');
            throw $exception;
        }
    }
}
