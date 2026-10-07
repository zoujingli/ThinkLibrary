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
use think\admin\Library;
use think\admin\model\SystemMenu;
use think\admin\service\ProcessService;
use think\helper\Str;

/**
 * 数据库迁移扩展.
 * @class PhinxExtend
 */
class PhinxExtend
{
    /**
     * 批量写入菜单.
     * @param array $zdata 菜单数据
     * @param mixed $exists 检测条件
     * @param null|Table $table 迁移目标表，指定时 $exists 必须为空
     */
    public static function write2menu(array $zdata, $exists = [], ?Table $table = null): bool
    {
        if ($table !== null && !empty($exists)) {
            throw new \InvalidArgumentException('指定迁移目标表时不支持模型查询条件');
        }
        // 检查是否需要写入菜单
        try {
            if (!empty($exists) && SystemMenu::mk()->where($exists)->findOrEmpty()->isExists()) {
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }
        // 循环写入系统菜单数据
        foreach ($zdata as $one) {
            $pid1 = static::write1menu($one, 0, $table);
            if (!empty($one['subs'])) {
                foreach ($one['subs'] as $two) {
                    $pid2 = static::write1menu($two, $pid1, $table);
                    if (!empty($two['subs'])) {
                        foreach ($two['subs'] as $thr) {
                            static::write1menu($thr, $pid2, $table);
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * 升级更新数据表.
     * @param array $fields 字段配置
     * @param array $indexs 索引配置
     * @param bool $force 强制更新
     */
    public static function upgrade(Table $table, array $fields, array $indexs = [], bool $force = false): Table
    {
        $table->setAdapter(PhinxSchema::compatibleAdapter($table->getAdapter(), $fields));
        $isExists = $table->exists();
        if ($isExists && !$force) {
            return $table;
        }
        $sourceFields = $fields;
        $fields = PhinxSchema::prepareFields($table->getAdapter(), $fields);
        [$adapter, $name] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        $driver = PhinxSchema::driver($adapter);
        // 先检查所有索引，避免因不支持的定义留下半张表。
        self::prepareIndexes($table, $indexs);
        self::validatePrimaryKey($table, $isExists, $fields);
        $existing = [];
        if ($isExists && $fields) {
            if ($driver === 'mysql') {
                $existing = array_column($adapter->fetchAll('SHOW FULL COLUMNS FROM ' . PhinxSchema::quote($name)), 'Field');
            } elseif ($driver === 'sqlite') {
                $existing = array_column($adapter->fetchAll('PRAGMA table_info(' . PhinxSchema::quote($name) . ')'), 'name');
                PhinxSchema::validateSqliteReferences($adapter, $name);
            } else {
                $existing = array_map(function ($column) { return $column->getName(); }, $table->getColumns());
            }
        }
        $sqlite = $driver === 'sqlite' && !PhinxSchema::isDryRun($adapter);
        $sequence = $sqlite && $isExists && $adapter->hasTable('sqlite_sequence')
            ? ($adapter->fetchRow('SELECT seq FROM sqlite_sequence WHERE name = ' . $adapter->getConnection()->quote($name))['seq'] ?? null) : null;
        if ($sqlite) {
            $adapter->execute('SAVEPOINT phinx_table_upgrade');
        }
        try {
            if ($isExists && $fields && $adapter instanceof PhinxSqliteColumns) {
                $adapter->replaceFields($name, $sourceFields);
            } else {
                foreach ($fields as $field) {
                    if (in_array($field[0], $existing, true)) {
                        $table->changeColumn($field[0], $field[1], $field[2]);
                    } else {
                        $table->addColumn($field[0], $field[1], $field[2]);
                    }
                }
            }
            $temporaryIndex = null;
            if ($isExists) {
                $table->update();
                self::syncTableOptions($table);
            } else {
                // MySQL 自增列在建表时就必须有索引；非主键自增列使用临时索引过渡。
                if ($driver === 'mysql') {
                    foreach ($fields as $field) {
                        if (!empty($field[2]['identity']) && $field[0] !== (((array)($table->getOptions()['primary_key'] ?? []))[0] ?? null)) {
                            $temporaryIndex = 'phinx_identity_' . substr(sha1($table->getName()), 0, 12);
                            $table->addIndex([str_replace('`', '``', $field[0])], ['name' => $temporaryIndex]);
                        }
                    }
                }
                $table->create();
            }
            self::syncTableIndexes($table, $indexs, $temporaryIndex);
            if ($sequence !== null) {
                // SQLite 重建表后不能复用曾经发放、后来删除的自增 ID。
                $statement = $adapter->getConnection()->prepare('UPDATE sqlite_sequence SET seq = MAX(seq, CAST(? AS INTEGER)) WHERE name = ?');
                $statement->execute([$sequence, $name]);
            }
            if ($sqlite) {
                $adapter->execute('RELEASE SAVEPOINT phinx_table_upgrade');
            }
        } catch (\Throwable $exception) {
            if ($sqlite) {
                $adapter->execute('ROLLBACK TO SAVEPOINT phinx_table_upgrade');
                $adapter->execute('RELEASE SAVEPOINT phinx_table_upgrade');
            }
            throw $exception;
        }
        return $table;
    }

    /**
     * 创建数据库安装脚本.
     * @return string[]
     * @throws \Exception
     */
    public static function create2table(array $tables = [], string $class = 'InstallTable', bool $force = false): array
    {
        self::validateClass($class);
        $br = "\r\n";
        $content = static::_build2table($tables, true, $force);
        $content = substr($content, strpos($content, "\n") + 1);
        $content = '<?php' . "{$br}{$br}use think\\admin\\extend\\PhinxExtend;{$br}use think\\migration\\Migrator;{$br}{$br}@set_time_limit(0);{$br}@ini_set('memory_limit', '-1');{$br}{$br}class {$class} extends Migrator{$br}{{$br}{$content}}{$br}";
        return ['file' => static::nextFile($class), 'text' => $content];
    }

    /**
     * 创建数据库备份脚本.
     * @throws \Exception
     */
    public static function create2backup(array $tables = [], string $class = 'InstallPackage', bool $progress = true): array
    {
        self::validateClass($class);
        $connect = Library::$sapp->db->connect();
        $tables = PhinxSchema::exportTables($connect, $tables);
        // 处理菜单数据
        [$menuData, $menuList] = [[], SystemMenu::mk()->where(['status' => 1])->order('sort desc,id asc')->select()->toArray()];
        foreach (DataExtend::arr2tree($menuList) as $sub1) {
            $one = ['name' => $sub1['title'], 'icon' => $sub1['icon'], 'url' => $sub1['url'], 'node' => $sub1['node'], 'params' => $sub1['params'], 'subs' => []];
            if (!empty($sub1['sub'])) {
                foreach ($sub1['sub'] as $sub2) {
                    $two = ['name' => $sub2['title'], 'icon' => $sub2['icon'], 'url' => $sub2['url'], 'node' => $sub2['node'], 'params' => $sub2['params'], 'subs' => []];
                    if (!empty($sub2['sub'])) {
                        foreach ($sub2['sub'] as $sub3) {
                            $two['subs'][] = ['name' => $sub3['title'], 'url' => $sub3['url'], 'node' => $sub3['node'], 'icon' => $sub3['icon'], 'params' => $sub3['params']];
                        }
                    }
                    if (empty($two['subs'])) {
                        unset($two['subs']);
                    }
                    $one['subs'][] = $two;
                }
            }
            if (empty($one['subs'])) {
                unset($one['subs']);
            }
            $menuData[] = $one;
        }

        // 备份数据表
        [$extra, $version] = [[], strstr($filename = static::nextFile($class), '_', true)];
        if (count($tables) > 0) {
            foreach ($tables as $table) {
                if (($count = ($db = Library::$sapp->db->table($table))->count()) > 0) {
                    $dataFileName = $version . '/' . sha1($table) . '.data';
                    $dataFilePath = syspath("database/migrations/{$dataFileName}");
                    is_dir($dataDirectory = dirname($dataFilePath)) || mkdir($dataDirectory, 0777, true);
                    $progress && ProcessService::message(" -- Starting write {$table}.data ..." . PHP_EOL);
                    $used = PhinxBackup::writeTable($connect, $table, $dataFilePath, function ($used) use ($progress, $table, $count) {
                        if ($progress && ($number = sprintf('%.4f', ($used / $count) * 100) . '%')) {
                            ProcessService::message(" -- -- write {$table}.data: {$used}/{$count} {$number}", 1);
                        }
                    });
                    $extra[PhinxSchema::logicalName($connect, $table)] = $dataFileName;
                    $progress && ProcessService::message(" -- Finished write {$table}.data, Total {$used} rows.", 2);
                }
            }
        }

        // 生成迁移脚本
        return ['file' => $filename, 'text' => self::renderBackup($class, $extra, $menuData)];
    }

    /**
     * 新脚本写入成功后才清理同类旧脚本和数据，生成失败时保留原备份。
     */
    public static function saveMigration(array $migration): bool
    {
        $filename = $migration['file'];
        if (!preg_match('/^\d{14}_[a-z0-9_]+\.php$/iD', $filename)) {
            throw new \InvalidArgumentException('无效的迁移文件名');
        }
        token_get_all($migration['text'], TOKEN_PARSE);
        $directory = syspath('database/migrations');
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('无法创建迁移目录');
        }
        $temporary = tempnam($directory, '.migration-');
        if ($temporary === false) {
            throw new \RuntimeException('无法创建迁移临时文件');
        }
        try {
            if (file_put_contents($temporary, $migration['text']) !== strlen($migration['text']) || !rename($temporary, $directory . DIRECTORY_SEPARATOR . $filename)) {
                throw new \RuntimeException('迁移脚本写入失败，旧脚本已保留');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        $suffix = substr($filename, 14);
        foreach (new \DirectoryIterator($directory) as $info) {
            $previous = $info->getFilename();
            if ($info->isFile() && !$info->isLink() && $previous !== $filename && preg_match('/^\d{14}_/', $previous) && substr($previous, 14) === $suffix) {
                if (!unlink($info->getPathname())) {
                    throw new \RuntimeException("旧迁移脚本清理失败 {$previous}");
                }
                $data = $directory . DIRECTORY_SEPARATOR . substr($previous, 0, 14);
                if (is_dir($data) && !is_link($data)) {
                    ToolsExtend::remove($data);
                }
            }
        }
        return true;
    }

    private static function renderBackup(string $class, array $tables, array $menus): string
    {
        self::validateClass($class);
        $template = file_get_contents(dirname(__DIR__) . '/service/bin/package.stub');
        return strtr($template, ['__CLASS__' => $class, '__MENU__' => self::_arr2str($menus), '__DATA__' => self::_arr2str($tables)]);
    }

    /**
     * 单个写入菜单.
     * @param array $menu 菜单数据
     * @param int $ppid 上级菜单
     */
    private static function write1menu(array $menu, int $ppid = 0, ?Table $table = null): int
    {
        $row = [
            'pid' => $ppid,
            'url' => empty($menu['url']) ? (empty($menu['node']) ? '#' : $menu['node']) : $menu['url'],
            'sort' => $menu['sort'] ?? 0,
            'icon' => $menu['icon'] ?? '',
            'node' => empty($menu['node']) ? (empty($menu['url']) ? '' : $menu['url']) : $menu['node'],
            'title' => $menu['name'] ?? ($menu['title'] ?? ''),
            'params' => $menu['params'] ?? '',
            'target' => $menu['target'] ?? '_self',
        ];
        if ($table !== null) {
            [$adapter, $name] = PhinxSchema::connection($table->getAdapter(), $table->getName());
            if (PhinxSchema::isDryRun($adapter)) {
                return 0;
            }
            // 旧版 SQLite 适配器批量插入不转义单引号，菜单内容始终通过参数绑定。
            $columns = array_map([$adapter, 'quoteColumnName'], array_keys($row));
            $connection = $adapter->getConnection();
            $statement = $connection->prepare('INSERT INTO ' . $adapter->quoteTableName($name) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')');
            $statement->execute(array_values($row));
            return (int)$connection->lastInsertId();
        }
        return (int)SystemMenu::mk()->insertGetId($row);
    }

    /**
     * 生成索引名称.
     *
     * 生成规则: idx_[表名hash后4位]_[表名缩写]_[字段缩写]
     * 缩写规则: 取每个下划线分隔部分的第一个字母
     *
     * @param string $table 表名
     * @param array<int, string>|string $name 字段名
     * @return string 生成的索引名称
     */
    private static function genIndexName(string $table, $name, bool $unique = false): string
    {
        return IndexNameService::generate($table, $name, $unique);
    }

    /**
     * @param mixed $spec
     * @return array{0:array<int, string>,1:array<string, mixed>}
     */
    private static function parseIndexSpec(string $table, $spec): array
    {
        if (is_string($spec)) {
            $columns = [$spec];
            return [$columns, ['name' => self::genIndexName($table, $columns)]];
        }

        if (is_array($spec) && array_values($spec) === $spec) {
            $columns = array_values(array_filter($spec, 'is_string'));
            return [$columns, ['name' => self::genIndexName($table, $columns)]];
        }

        if (is_array($spec)) {
            $columns = array_values(array_filter((array)($spec['columns'] ?? []), 'is_string'));
            $unique = !empty($spec['unique']);
            $options = array_diff_key($spec, ['columns' => true]);
            $options['name'] = $options['name'] ?? self::genIndexName($table, $columns, $unique);
            return [$columns, $options];
        }

        return [[], []];
    }

    /**
     * 在执行 DDL 前解析并检查索引。
     */
    private static function prepareIndexes(Table $table, array $specs): array
    {
        [$adapter] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        $driver = PhinxSchema::driver($adapter);
        $indexes = [];
        foreach ($specs as $spec) {
            [$columns, $options] = self::parseIndexSpec($table->getName(), $spec);
            if (!$columns) {
                continue;
            }
            $index = self::normalizeIndex($table->getName(), $columns, $options);
            if (!in_array($index['type'], ['btree', 'fulltext', 'spatial', 'hash'], true)) {
                throw new \RuntimeException("不支持的索引类型 {$index['type']}");
            }
            if ($driver !== 'mysql' && ($index['type'] !== 'btree' || $index['limits'] || !$index['visible'] || $index['comment'] !== '')) {
                throw new \RuntimeException("{$driver} 不支持索引 {$index['name']} 的 {$index['type']} / MySQL 专有选项");
            }
            if (isset($indexes[$index['name']]) && $indexes[$index['name']] !== $index) {
                throw new \RuntimeException("索引名称重复：{$index['name']}");
            }
            if ($driver === 'sqlite') {
                [, $physicalName] = PhinxSchema::connection($table->getAdapter(), $table->getName());
                $other = $adapter->fetchRow("SELECT tbl_name FROM sqlite_master WHERE type = 'index' AND name = " . $adapter->getConnection()->quote($index['name']));
                if ($other && $other['tbl_name'] !== $physicalName) {
                    throw new \RuntimeException("sqlite 索引名称 {$index['name']} 已被表 {$other['tbl_name']} 使用");
                }
            }
            $indexes[$index['name']] = $index;
        }
        return $indexes;
    }

    private static function validatePrimaryKey(Table $table, bool $exists, array $fields): void
    {
        $options = $table->getOptions();
        if (!array_key_exists('id', $options) || $options['id'] !== false) {
            return;
        }
        [$adapter, $name] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        $primary = (array)($options['primary_key'] ?? []);
        $driver = PhinxSchema::driver($adapter);
        if ($driver === 'sqlite') {
            foreach ($fields as $field) {
                if (!empty($field[2]['identity']) && $primary !== [$field[0]]) {
                    throw new \RuntimeException("sqlite 不支持 {$name} 的非单列主键自增字段");
                }
            }
        }
        if (!$exists) {
            return;
        }
        $current = [];
        if ($driver === 'mysql') {
            foreach ($adapter->fetchAll('SHOW INDEX FROM ' . PhinxSchema::quote($name)) as $row) {
                if ($row['Key_name'] === 'PRIMARY') {
                    $current[(int)$row['Seq_in_index']] = $row['Column_name'];
                }
            }
        } elseif ($driver === 'sqlite') {
            foreach ($adapter->fetchAll('PRAGMA table_info(' . PhinxSchema::quote($name) . ')') as $row) {
                if ($row['pk']) {
                    $current[(int)$row['pk']] = $row['name'];
                }
            }
        } else {
            return;
        }
        ksort($current);
        if (array_values($current) !== $primary) {
            throw new \RuntimeException("数据表 {$name} 主键不同，请通过专用迁移变更主键；未执行强制更新");
        }
    }

    private static function syncTableOptions(Table $table): void
    {
        [$adapter, $name] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        $options = $table->getOptions();
        if (PhinxSchema::driver($adapter) !== 'mysql' || !array_intersect_key($options, array_flip(['engine', 'collation', 'comment']))) {
            return;
        }
        $current = $adapter->fetchRow('SHOW TABLE STATUS WHERE Name = ' . $adapter->getConnection()->quote($name));
        $clauses = [];
        foreach (['engine' => 'Engine', 'collation' => 'Collation', 'comment' => 'Comment'] as $option => $key) {
            if (isset($options[$option]) && $options[$option] !== ($current[$key] ?? null)) {
                if ($option === 'comment') {
                    $clauses[] = 'COMMENT = ' . $adapter->getConnection()->quote($options[$option]);
                } elseif ($option === 'engine') {
                    $clauses[] = 'ENGINE = ' . PhinxSchema::quote($options[$option]);
                } else {
                    $clauses[] = 'DEFAULT CHARACTER SET ' . PhinxSchema::quote(explode('_', $options[$option])[0]) . ' COLLATE ' . PhinxSchema::quote($options[$option]);
                }
            }
        }
        if ($clauses) {
            $adapter->execute('ALTER TABLE ' . PhinxSchema::quote($name) . ' ' . implode(', ', $clauses));
        }
    }

    /**
     * 仅替换同名且结构有变化的索引，保留同列的其他索引。
     */
    private static function syncTableIndexes(Table $table, array $specs, ?string $temporaryIndex = null): void
    {
        $desired = self::prepareIndexes($table, $specs);
        if (!$desired && $temporaryIndex === null) {
            return;
        }
        [$adapter, $name] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        $driver = PhinxSchema::driver($adapter);
        if ($driver === 'mysql') {
            $rows = PhinxSchema::isDryRun($adapter) && !$adapter->hasTable($name) ? [] : PhinxSchema::mysqlIndexes($adapter->fetchAll('SHOW INDEX FROM ' . PhinxSchema::quote($name)));
            unset($rows['PRIMARY']);
        } elseif ($driver === 'sqlite') {
            $rows = PhinxSchema::sqliteIndexes([$adapter, 'fetchAll'], $name);
        } else {
            // 其他适配器保持使用 Phinx 的标准索引接口。
            foreach ($specs as $spec) {
                [$columns, $options] = self::parseIndexSpec($table->getName(), $spec);
                if ($columns && !$table->hasIndexByName($options['name'])) {
                    $table->addIndex($columns, $options);
                }
            }
            $table->update();
            return;
        }
        $existing = [];
        foreach ($rows as $row) {
            $existing[$row['name']] = self::normalizeIndex($name, $row['columns'], $row);
        }
        $drops = $adds = [];
        foreach ($desired as $indexName => $index) {
            if (isset($existing[$indexName])) {
                if ($existing[$indexName] === $index) {
                    continue;
                }
                $drops[] = $indexName;
            }
            $adds[] = $index;
        }
        if ($temporaryIndex !== null) {
            $drops[] = $temporaryIndex;
        }
        if ($driver === 'mysql') {
            $clauses = $fulltext = [];
            foreach ($drops as $indexName) {
                $clauses[$indexName] = 'DROP INDEX ' . PhinxSchema::quote($indexName);
            }
            foreach ($adds as $index) {
                $prefix = $index['unique'] ? 'UNIQUE ' : '';
                if (in_array($index['type'], ['fulltext', 'spatial'], true)) {
                    $prefix = strtoupper($index['type']) . ' ';
                }
                $clause = 'ADD ' . $prefix . 'INDEX ' . PhinxSchema::quote($index['name']) . ' (' . self::indexColumnsSql($index) . ')';
                if ($index['type'] === 'hash') {
                    $clause .= ' USING HASH';
                }
                if ($index['comment'] !== '') {
                    $clause .= ' COMMENT ' . $adapter->getConnection()->quote($index['comment']);
                }
                if (!$index['visible']) {
                    $clause .= ' INVISIBLE';
                }
                if ($index['type'] === 'fulltext') {
                    // MySQL 每条 ALTER 只允许创建一个 FULLTEXT；同名替换仍在同一条语句中。
                    $fulltext[] = (isset($clauses[$index['name']]) ? $clauses[$index['name']] . ', ' : '') . $clause;
                    unset($clauses[$index['name']]);
                } else {
                    $clauses[] = $clause;
                }
            }
            if ($clauses) {
                // 同一条 ALTER 同时删除和创建，避免中途失败后只剩删除结果。
                $adapter->execute('ALTER TABLE ' . PhinxSchema::quote($name) . ' ' . implode(', ', $clauses));
            }
            foreach ($fulltext as $clause) {
                $adapter->execute('ALTER TABLE ' . PhinxSchema::quote($name) . ' ' . $clause);
            }
        } elseif ($drops || $adds) {
            $adapter->execute('SAVEPOINT phinx_index_sync');
            try {
                foreach ($drops as $indexName) {
                    $adapter->execute('DROP INDEX ' . PhinxSchema::quote($indexName));
                }
                foreach ($adds as $index) {
                    $adapter->execute('CREATE ' . ($index['unique'] ? 'UNIQUE ' : '') . 'INDEX ' . PhinxSchema::quote($index['name']) . ' ON ' . PhinxSchema::quote($name) . ' (' . self::indexColumnsSql($index) . ')');
                }
                $adapter->execute('RELEASE SAVEPOINT phinx_index_sync');
            } catch (\Throwable $exception) {
                $adapter->execute('ROLLBACK TO SAVEPOINT phinx_index_sync');
                $adapter->execute('RELEASE SAVEPOINT phinx_index_sync');
                throw $exception;
            }
        }
    }

    private static function normalizeIndex(string $table, array $columns, array $options): array
    {
        $type = strtolower($options['type'] ?? 'btree');
        $unique = !empty($options['unique']) || $type === 'unique';
        $order = [];
        foreach ($columns as $column) {
            $direction = strtoupper($options['order'][$column] ?? 'ASC');
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new \RuntimeException("无效的索引排序 {$direction}");
            }
            if ($direction === 'DESC') {
                $order[$column] = $direction;
            }
        }
        return [
            'name' => $options['name'] ?? self::genIndexName($table, $columns, $unique),
            'columns' => array_values($columns),
            'unique' => $unique,
            'type' => $type === 'unique' ? 'btree' : $type,
            'limits' => self::normalizeIndexLimits($columns, $options['limit'] ?? []),
            'order' => $order,
            'comment' => $options['comment'] ?? '',
            'visible' => $options['visible'] ?? true,
        ];
    }

    private static function normalizeIndexLimits(array $columns, $limits): array
    {
        $result = [];
        foreach ($columns as $column) {
            $limit = is_array($limits) ? ($limits[$column] ?? null) : $limits;
            if (is_numeric($limit) && (int)$limit > 0) {
                $result[$column] = (int)$limit;
            }
        }
        return $result;
    }

    private static function indexColumnsSql(array $index): string
    {
        $columns = [];
        foreach ($index['columns'] as $column) {
            $sql = PhinxSchema::quote($column);
            if (isset($index['limits'][$column])) {
                $sql .= '(' . $index['limits'][$column] . ')';
            }
            if (isset($index['order'][$column])) {
                $sql .= ' ' . $index['order'][$column];
            }
            $columns[] = $sql;
        }
        return implode(', ', $columns);
    }

    /**
     * 按值导出 PHP，不能对导出结果中的空白或占位符再做替换。
     */
    private static function _arr2str(array $data): string
    {
        $items = [];
        $isList = array_values($data) === $data;
        foreach ($data as $key => $value) {
            $export = is_array($value) ? self::_arr2str($value) : var_export($value, true);
            $items[] = ($isList ? '' : var_export($key, true) . ' => ') . $export;
        }
        return '[' . implode(', ', $items) . ']';
    }

    private static function _build2table(array $tables = [], bool $rehtml = false, bool $force = false): string
    {
        $connect = Library::$sapp->db->connect();
        $calls = $methods = [];
        foreach (PhinxSchema::exportTables($connect, $tables) as $table) {
            [$options, $fields, $indexes] = PhinxSchema::read($connect, $table);
            $method = '_create_' . sha1($table);
            $calls[] = "        \$this->{$method}();";
            $fieldSource = [];
            foreach ($fields as $field) {
                $fieldSource[] = '            ' . self::_arr2str($field) . ',';
            }
            $methods[] = "    private function {$method}()\n    {\n"
                . '        $table = $this->table(' . var_export(PhinxSchema::logicalName($connect, $table), true) . ', ' . self::_arr2str($options) . ");\n"
                . "        PhinxExtend::upgrade(\$table, [\n" . implode("\n", $fieldSource) . "\n        ], "
                . self::_arr2str($indexes) . ', ' . ($force ? 'true' : 'false') . ");\n    }";
        }
        $content = "<?php\n\n    public function change()\n    {\n" . implode("\n", $calls) . "\n    }\n\n" . implode("\n\n", $methods) . "\n";
        return $rehtml ? $content : highlight_string($content, true);
    }

    private static function validateClass(string $class): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/iD', $class)) {
            throw new \InvalidArgumentException("无效的迁移类名 {$class}");
        }
        // 同时拒绝 PHP 保留字，避免写出不可解析的脚本。
        token_get_all('<?php class ' . $class . ' {}', TOKEN_PARSE);
    }

    /**
     * 生成下一个脚本名称.
     * @param string $class 脚本类名
     */
    private static function nextFile(string $class): string
    {
        [$snake, $items] = [Str::snake($class), [20010000000000]];
        $directory = syspath('database/migrations');
        if (is_dir($directory)) {
            foreach (new \DirectoryIterator($directory) as $info) {
                if (preg_match('/^(\d{14})(?:_|$)/D', $info->getFilename(), $matches)) {
                    $items[] = (int)$matches[1];
                }
            }
        }

        // 计算下一个版本号
        return sprintf("%s_{$snake}.php", min($items) - 1);
    }
}
