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
use Phinx\Db\Adapter\MysqlAdapter;
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
     */
    public static function write2menu(array $zdata, $exists = []): bool
    {
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
            $pid1 = static::write1menu($one);
            if (!empty($one['subs'])) {
                foreach ($one['subs'] as $two) {
                    $pid2 = static::write1menu($two, $pid1);
                    if (!empty($two['subs'])) {
                        foreach ($two['subs'] as $thr) {
                            static::write1menu($thr, $pid2);
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
        [$_exists, $_fields] = [[], array_column($fields, 0)];
        if ($isExists = $table->exists()) {
            // 数据表存在且不强制时退出操作
            if (empty($force)) {
                return $table;
            }
            foreach ($table->getColumns() as $column) {
                $_exists[] = $name = $column->getName();
                if (!in_array($name, $_fields)) {
                    // @todo 为保证数据安全暂不删字段
                    // $table->removeColumn($name);
                    // $table->hasIndex($name) || $table->removeIndex($name);
                }
            }
        }
        foreach ($fields as $field) {
            if (in_array($field[0], $_exists)) {
                $table->changeColumn($field[0], ...array_slice($field, 1));
            } else {
                $table->addColumn($field[0], ...array_slice($field, 1));
            }
        }
        if ($isExists) {
            $table->update();
            self::syncTableIndexes($table->getName(), $indexs);
        } else {
            // 新表创建时直接挂载索引定义
            foreach ($indexs as $spec) {
                [$columns, $options] = self::parseIndexSpec($table->getName(), $spec);
                if (empty($columns)) {
                    continue;
                }
                $table->addIndex($columns, $options);
            }
            $table->create();
        }
        if ($table->hasColumn('id')) {
            $table->changeColumn('id', 'integer', ['limit' => 11, 'identity' => true]);
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
        if (Library::$sapp->db->connect()->getConfig('type') !== 'mysql') {
            throw new \Exception(' ** Notify: 只支持 MySql 数据库生成数据库脚本');
        }
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
        if (Library::$sapp->db->connect()->getConfig('type') !== 'mysql') {
            throw new \Exception(' ** Notify: 只支持 MySql 数据库生成数据库脚本');
        }
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
                    $dataFileName = "{$version}/{$table}.data";
                    $dataFilePath = syspath("database/migrations/{$dataFileName}");
                    is_dir($dataDirectory = dirname($dataFilePath)) || mkdir($dataDirectory, 0777, true);
                    $progress && ProcessService::message(" -- Starting write {$table}.data ..." . PHP_EOL);
                    [$used, $fp] = [0, fopen($dataFilePath, 'w+')];
                    foreach ($db->cursor() as $item) {
                        fwrite($fp, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\r\n");
                        if ($progress && ($number = sprintf('%.4f', (++$used / $count) * 100) . '%')) {
                            ProcessService::message(" -- -- write {$table}.data: {$used}/{$count} {$number}", 1);
                        }
                    }
                    fclose($fp);
                    $extra[$table] = $dataFileName;
                    $progress && ProcessService::message(" -- Finished write {$table}.data, Total {$used} rows.", 2);
                }
            }
        }

        // 生成迁移脚本
        $template = file_get_contents(dirname(__DIR__) . '/service/bin/package.stub');
        $dataJson = json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $menuJson = json_encode($menuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $replaces = ['__CLASS__' => $class, '__MENU_JSON__' => $menuJson, '__DATA_JSON__' => $dataJson];
        return ['file' => $filename, 'text' => str_replace(array_keys($replaces), array_values($replaces), $template)];
    }

    /**
     * 单个写入菜单.
     * @param array $menu 菜单数据
     * @param int $ppid 上级菜单
     */
    private static function write1menu(array $menu, int $ppid = 0): int
    {
        return (int)SystemMenu::mk()->insertGetId([
            'pid' => $ppid,
            'url' => empty($menu['url']) ? (empty($menu['node']) ? '#' : $menu['node']) : $menu['url'],
            'sort' => $menu['sort'] ?? 0,
            'icon' => $menu['icon'] ?? '',
            'node' => empty($menu['node']) ? (empty($menu['url']) ? '' : $menu['url']) : $menu['node'],
            'title' => $menu['name'] ?? ($menu['title'] ?? ''),
            'params' => $menu['params'] ?? '',
            'target' => $menu['target'] ?? '_self',
        ]);
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
    private static function genIndexName(string $table, array|string $name, bool $unique = false): string
    {
        return IndexNameService::generate($table, $name, $unique);
    }

    /**
     * @return array{0:array<int, string>,1:array<string, mixed>}
     */
    private static function parseIndexSpec(string $table, mixed $spec): array
    {
        if (is_string($spec)) {
            $columns = [$spec];
            return [$columns, ['name' => self::genIndexName($table, $columns)]];
        }

        if (is_array($spec) && array_is_list($spec)) {
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
     * 同步已存在数据表的索引结构，避免同列索引配置变更时被跳过。
     */
    private static function syncTableIndexes(string $table, array $indexs): void
    {
        $desired = [];
        foreach ($indexs as $spec) {
            [$columns, $options] = self::parseIndexSpec($table, $spec);
            if (empty($columns)) {
                continue;
            }
            $index = self::normalizeIndex($table, $columns, $options);
            $desired[self::indexCacheKey($index)] = $index;
        }
        if (empty($desired)) {
            return;
        }

        $existing = array_values(self::loadTableIndexes($table));
        $used = [];
        $drops = [];
        $creates = [];

        foreach ($desired as $expect) {
            [$exact, $conflicts] = [[], []];
            foreach ($existing as $idx => $current) {
                if (isset($used[$idx])) {
                    continue;
                }
                $sameDefinition = self::indexDefinitionKey($current) === self::indexDefinitionKey($expect);
                $sameColumns = $current['columns'] === $expect['columns'];
                $sameName = $current['name'] === $expect['name'];
                if ($sameDefinition) {
                    $exact[] = $idx;
                    continue;
                }
                if ($sameColumns || $sameName) {
                    $conflicts[] = $idx;
                }
            }

            if (!empty($exact)) {
                $primary = array_shift($exact);
                $used[$primary] = true;

                foreach ($conflicts as $idx) {
                    $drops[$existing[$idx]['name']] = $existing[$idx]['name'];
                    $used[$idx] = true;
                }
                foreach ($exact as $idx) {
                    $drops[$existing[$idx]['name']] = $existing[$idx]['name'];
                    $used[$idx] = true;
                }

                if ($existing[$primary]['name'] !== $expect['name']) {
                    $drops[$existing[$primary]['name']] = $existing[$primary]['name'];
                    $creates[$expect['name']] = $expect;
                }
                continue;
            }

            foreach ($conflicts as $idx) {
                $drops[$existing[$idx]['name']] = $existing[$idx]['name'];
                $used[$idx] = true;
            }
            $creates[$expect['name']] = $expect;
        }

        $connect = Library::$sapp->db->connect();
        foreach ($drops as $name) {
            $connect->execute(self::buildDropIndexSql($table, $name));
        }
        foreach ($creates as $index) {
            $connect->execute(self::buildAddIndexSql($table, $index));
        }
    }

    /**
     * 读取当前数据表索引定义.
     * @return array<string, array<string, mixed>>
     */
    private static function loadTableIndexes(string $table): array
    {
        $indexes = [];
        foreach (Library::$sapp->db->connect()->query('SHOW INDEX FROM ' . self::quoteIdentifier($table)) as $index) {
            $keyName = strval($index['Key_name'] ?? '');
            if ($keyName === '' || $keyName === 'PRIMARY') {
                continue;
            }
            $column = strval($index['Column_name'] ?? '');
            $indexes[$keyName]['name'] = $keyName;
            $indexes[$keyName]['unique'] = intval($index['Non_unique'] ?? 1) === 0;
            $indexes[$keyName]['columns'][intval($index['Seq_in_index'] ?? 0)] = $column;
            if (is_numeric($index['Sub_part'] ?? null) && intval($index['Sub_part']) > 0) {
                $indexes[$keyName]['limits'][$column] = intval($index['Sub_part']);
            }
        }
        foreach ($indexes as $name => $index) {
            $columns = $index['columns'] ?? [];
            ksort($columns);
            $columns = array_values(array_filter($columns, 'strlen'));
            $indexes[$name] = [
                'name' => $name,
                'unique' => !empty($index['unique']),
                'columns' => $columns,
                'limits' => self::normalizeIndexLimits($columns, $index['limits'] ?? []),
            ];
        }
        return $indexes;
    }

    /**
     * 规范化索引配置.
     * @param array<int, string> $columns
     * @param array<string, mixed> $options
     * @return array{name:string,unique:bool,columns:array<int, string>,limits:array<string, int>}
     */
    private static function normalizeIndex(string $table, array $columns, array $options): array
    {
        $columns = array_values(array_filter(array_map('strval', $columns), 'strlen'));
        $unique = !empty($options['unique']);
        $name = strval($options['name'] ?? self::genIndexName($table, $columns, $unique));
        return [
            'name' => $name,
            'unique' => $unique,
            'columns' => $columns,
            'limits' => self::normalizeIndexLimits($columns, $options['limit'] ?? []),
        ];
    }

    /**
     * 规范化索引前缀长度配置.
     * @param array<int, string> $columns
     * @return array<string, int>
     */
    private static function normalizeIndexLimits(array $columns, mixed $limits): array
    {
        $result = [];
        if (is_numeric($limits) && count($columns) === 1) {
            $result[$columns[0]] = intval($limits);
            return $result;
        }
        if (!is_array($limits)) {
            return $result;
        }
        foreach ($columns as $column) {
            if (isset($limits[$column]) && is_numeric($limits[$column])) {
                $result[$column] = intval($limits[$column]);
            }
        }
        return $result;
    }

    /**
     * 生成索引缓存键，避免重复定义.
     * @param array<string, mixed> $index
     */
    private static function indexCacheKey(array $index): string
    {
        return $index['name'] . '|' . self::indexDefinitionKey($index);
    }

    /**
     * 生成索引定义键，用于比较结构是否一致.
     * @param array<string, mixed> $index
     */
    private static function indexDefinitionKey(array $index): string
    {
        return json_encode([
            'columns' => array_values($index['columns'] ?? []),
            'unique' => !empty($index['unique']),
            'limits' => $index['limits'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 生成删除索引 SQL.
     */
    private static function buildDropIndexSql(string $table, string $name): string
    {
        return sprintf('ALTER TABLE %s DROP INDEX %s', self::quoteIdentifier($table), self::quoteIdentifier($name));
    }

    /**
     * 生成新增索引 SQL.
     * @param array{name:string,unique:bool,columns:array<int, string>,limits:array<string, int>} $index
     */
    private static function buildAddIndexSql(string $table, array $index): string
    {
        $segments = [];
        foreach ($index['columns'] as $column) {
            $segment = self::quoteIdentifier($column);
            if (!empty($index['limits'][$column])) {
                $segment .= '(' . intval($index['limits'][$column]) . ')';
            }
            $segments[] = $segment;
        }
        $prefix = !empty($index['unique']) ? 'ADD UNIQUE INDEX' : 'ADD INDEX';
        return sprintf(
            'ALTER TABLE %s %s %s (%s)',
            self::quoteIdentifier($table),
            $prefix,
            self::quoteIdentifier($index['name']),
            implode(', ', $segments)
        );
    }

    /**
     * 简单的 MySQL 标识符转义.
     */
    private static function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * 数组转代码
     */
    private static function _arr2str(array $data): string
    {
        return preg_replace(['#\s+#', '#, \)$#', '#^array \( #'], [' ', ']', '['], var_export($data, true));
    }

    /**
     * 生成数据库表格创建模板
     * @param array $tables 指定数据表
     * @param bool $rehtml 是否返回内容
     * @param bool $force 强制更新结构
     * @throws \Exception
     */
    private static function _build2table(array $tables = [], bool $rehtml = false, bool $force = false): string
    {
        $br = "\r\n";
        $connect = Library::$sapp->db->connect();
        if ($connect->getConfig('type') !== 'mysql') {
            throw new \Exception(' ** Notify: 只支持 MySql 数据库生成数据库脚本');
        }
        $schema = $connect->getConfig('database');
        $content = '<?php' . "{$br}{$br}\t/**{$br}\t * 创建数据库{$br}\t */{$br}\tpublic function change()\n\t{";
        foreach ($tables as $table) {
            $content .= "{$br}\t\t\$this->_create_{$table}();";
        }
        $content .= "{$br}\t}{$br}{$br}";

        // 字段默认长度
        $sizes = ['tinyint' => 4, 'smallint' => 6, 'mediumint' => 9, 'int' => 11, 'bigint' => 20];

        // 字段类型转换 ( 仅需定义与MySQL不同的配置 )
        $types = [
            // 整形数字
            'tinyint' => AdapterInterface::PHINX_TYPE_TINY_INTEGER,
            'smallint' => AdapterInterface::PHINX_TYPE_SMALL_INTEGER,
            'int' => AdapterInterface::PHINX_TYPE_INTEGER,
            'bigint' => AdapterInterface::PHINX_TYPE_BIG_INTEGER,
            // 字符类型
            'varchar' => AdapterInterface::PHINX_TYPE_STRING,
            'tinytext' => AdapterInterface::PHINX_TYPE_TEXT,
            'mediumtext' => AdapterInterface::PHINX_TYPE_TEXT,
            'longtext' => AdapterInterface::PHINX_TYPE_TEXT,
            // 仅 mysql 有的字段需要单独处理
            'set' => AdapterInterface::PHINX_TYPE_STRING,
            'enum' => AdapterInterface::PHINX_TYPE_STRING,
            'year' => AdapterInterface::PHINX_TYPE_INTEGER,
            'mediumint' => AdapterInterface::PHINX_TYPE_INTEGER,
            'tinyblob' => AdapterInterface::PHINX_TYPE_BLOB,
            'longblob' => AdapterInterface::PHINX_TYPE_BLOB,
            'mediumblob' => AdapterInterface::PHINX_TYPE_BLOB,
        ];

        foreach ($tables as $table) {
            // 读取数据表 - 备注参数
            $comment = Library::$sapp->db->table('information_schema.TABLES')->where([
                'TABLE_SCHEMA' => $schema, 'TABLE_NAME' => $table,
            ])->value('TABLE_COMMENT', '');

            // 读取数据表 - 自动生成结构
            $class = Str::studly($table);
            $content .= <<<CODE
    /**
     * 创建数据对象
     * @class {$class}
     * @table {$table}
     * @return void
     */
    private function _create_{$table}() 
    {
        // 创建数据表对象
        \$table = \$this->table('{$table}', [
            'engine' => 'InnoDB', 'collation' => 'utf8mb4_general_ci', 'comment' => '{$comment}',
        ]);
        // 创建或更新数据表
        PhinxExtend::upgrade(\$table, _FIELDS_, _INDEXS_, __FORCE__);
    }
CODE;
            // 生成字段内容
            $_fieldString = '[' . PHP_EOL;
            foreach (Library::$sapp->db->getFields($table) as $field) {
                if ($field['name'] === 'id') {
                    continue;
                }
                $type = $types[$field['type']] ?? $field['type'];
                $data = ['default' => $field['default'], 'null' => empty($field['notnull']), 'comment' => $field['comment'] ?? ''];
                if ($field['type'] === 'longtext') {
                    $data = array_merge(['limit' => MysqlAdapter::TEXT_LONG], $data);
                } elseif ($field['type'] === 'enum') {
                    $type = $types[$field['type']] ?? 'string';
                    $data = array_merge(['limit' => 10], $data);
                } elseif (preg_match('/(tinyblob|blob|mediumblob|longblob|varbinary|bit|binary|varchar|char)\((\d+)\)/', $field['type'], $attr)) {
                    $type = $types[$attr[1]] ?? 'string';
                    $data = array_merge(['limit' => intval($attr[2])], $data);
                } elseif (preg_match('/(tinyint|smallint|mediumint|int|bigint)\((\d+)\)/', $field['type'], $attr)) {
                    $type = $types[$attr[1]] ?? 'integer';
                    $data = array_merge(['limit' => intval($attr[2])], $data, ['default' => intval($data['default'])]);
                } elseif (preg_match('/(tinyint|smallint|mediumint|int|bigint)\s+unsigned/i', $field['type'], $attr)) {
                    $type = $types[$attr[1]] ?? 'integer';
                    if (isset($sizes[$attr[1]])) {
                        $data = array_merge(['limit' => $sizes[$attr[1]]], $data);
                    }
                    $data['default'] = intval($data['default']);
                } elseif (preg_match('/(float|decimal)\((\d+),(\d+)\)/', $field['type'], $attr)) {
                    $type = $types[$attr[1]] ?? 'decimal';
                    $data = array_merge(['precision' => intval($attr[2]), 'scale' => intval($attr[3])], $data);
                }
                $_fieldString .= "\t\t\t['{$field['name']}', '{$type}', " . self::_arr2str($data) . '],' . PHP_EOL;
            }
            $_fieldString .= "\t\t]";
            // 生成索引内容
            $_indexs = [];
            foreach (Library::$sapp->db->connect()->query("show index from {$table}") as $index) {
                $keyName = strval($index['Key_name'] ?? '');
                if ($keyName === '' || $keyName === 'PRIMARY') {
                    continue;
                }
                $_indexs[$keyName]['unique'] = intval($index['Non_unique'] ?? 1) === 0;
                $column = strval($index['Column_name'] ?? '');
                $_indexs[$keyName]['columns'][intval($index['Seq_in_index'] ?? 0)] = $column;
                if (is_numeric($index['Sub_part'] ?? null) && intval($index['Sub_part']) > 0) {
                    $_indexs[$keyName]['limits'][$column] = intval($index['Sub_part']);
                }
            }
            ksort($_indexs);
            $_indexSpecs = [];
            foreach ($_indexs as $index) {
                $columns = $index['columns'] ?? [];
                ksort($columns);
                $columns = array_values(array_filter($columns, 'strlen'));
                if (empty($columns)) {
                    continue;
                }
                $options = [];
                if (!empty($index['limits'])) {
                    $limits = [];
                    foreach ($columns as $column) {
                        if (isset($index['limits'][$column])) {
                            $limits[$column] = intval($index['limits'][$column]);
                        }
                    }
                    if (!empty($limits)) {
                        $options['limit'] = $limits;
                    }
                }
                if (!empty($index['unique'])) {
                    $options['unique'] = true;
                }
                if (count($columns) === 1 && empty($options)) {
                    $_indexSpecs[] = $columns[0];
                } elseif (count($columns) > 1 && empty($options)) {
                    $_indexSpecs[] = $columns;
                } else {
                    $_indexSpecs[] = array_merge(['columns' => $columns], $options);
                }
            }
            $_indexString = self::_arr2str($_indexSpecs);
            $content = str_replace(['_FIELDS_', '_INDEXS_', '__FORCE__'], [$_fieldString, $_indexString, $force ? 'true' : 'false'], $content) . PHP_EOL . PHP_EOL;
        }
        return $rehtml ? $content : highlight_string($content, true);
    }

    /**
     * 生成下一个脚本名称.
     * @param string $class 脚本类名
     */
    private static function nextFile(string $class): string
    {
        [$snake, $items] = [Str::snake($class), [20010000000000]];
        ToolsExtend::find(syspath('database/migrations'), 1, function (\SplFileInfo $info) use ($snake, &$items) {
            if ($info->isFile()) {
                $bname = pathinfo($info->getBasename(), PATHINFO_FILENAME);
                $items[] = $version = intval(substr($bname, 0, 14));
                if ($snake === substr($bname, 15) && unlink($info->getRealPath())) {
                    if (is_dir($dataPath = $info->getPath() . DIRECTORY_SEPARATOR . $version)) {
                        ToolsExtend::remove($dataPath);
                    }
                }
            }
        });

        // 计算下一个版本号
        return sprintf("%s_{$snake}.php", min($items) - 1);
    }
}
