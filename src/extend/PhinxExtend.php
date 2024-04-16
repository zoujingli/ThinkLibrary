<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2024 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// | 免费声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\extend;

use Exception;
use think\admin\Library;
use think\admin\model\SystemMenu;
use think\admin\service\ProcessService;
use think\helper\Str;

/**
 * 数据库迁移扩展
 * @class PhinxExtend
 * @package think\admin\extend
 */
class PhinxExtend
{
    /**
     * 批量写入菜单
     * @param array $zdata 菜单数据
     * @param mixed $exists 检测条件
     * @return boolean
     */
    public static function write2menu(array $zdata, $exists = []): bool
    {
        // 检查是否需要写入菜单
        try {
            if (!empty($exists) && SystemMenu::mk()->where($exists)->findOrEmpty()->isExists()) {
                return false;
            }
        } catch (Exception $exception) {
            return false;
        }
        // 循环写入系统菜单数据
        foreach ($zdata as $one) {
            $pid1 = static::write1menu($one);
            if (!empty($one['subs'])) foreach ($one['subs'] as $two) {
                $pid2 = static::write1menu($two, $pid1);
                if (!empty($two['subs'])) foreach ($two['subs'] as $thr) {
                    static::write1menu($thr, $pid2);
                }
            }
        }
        return true;
    }

    /**
     * 单个写入菜单
     * @param array $menu 菜单数据
     * @param integer $ppid 上级菜单
     * @return integer
     */
    private static function write1menu(array $menu, int $ppid = 0): int
    {
        return (int)SystemMenu::mk()->insertGetId([
            'pid'    => $ppid,
            'url'    => empty($menu['url']) ? (empty($menu['node']) ? '#' : $menu['node']) : $menu['url'],
            'sort'   => $menu['sort'] ?? 0,
            'icon'   => $menu['icon'] ?? '',
            'node'   => empty($menu['node']) ? (empty($menu['url']) ? '' : $menu['url']) : $menu['node'],
            'title'  => $menu['name'] ?? ($menu['title'] ?? ''),
            'params' => $menu['params'] ?? '',
            'target' => $menu['target'] ?? '_self',
        ]);
    }

    /**
     * 创建数据库安装脚本
     * @param array $tables
     * @param string $class
     * @return string[]
     * @throws \Exception
     */
    public static function create2table(array $tables = [], string $class = 'InstallTable'): array
    {
        $br = "\r\n";
        $content = static::_build2table($tables, true);
        $content = substr($content, strpos($content, "\n") + 1);
        $content = '<?php' . "{$br}{$br}use think\migration\Migrator;{$br}{$br}@set_time_limit(0);{$br}@ini_set('memory_limit', -1);{$br}{$br}class {$class} extends Migrator {{$br}{$content}}{$br}";
        return ['file' => static::nextFile($class), 'text' => $content];
    }

    /**
     * 创建数据库备份脚本
     * @param array $tables
     * @param string $class
     * @param boolean $progress
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function create2backup(array $tables = [], string $class = 'InstallPackage', bool $progress = true): array
    {
        // 处理菜单数据
        [$menuData, $menuList] = [[], SystemMenu::mk()->where(['status' => 1])->order('sort desc,id asc')->select()->toArray()];
        foreach (DataExtend::arr2tree($menuList) as $sub1) {
            $one = ['name' => $sub1['title'], 'icon' => $sub1['icon'], 'url' => $sub1['url'], 'node' => $sub1['node'], 'params' => $sub1['params'], 'subs' => []];
            if (!empty($sub1['sub'])) foreach ($sub1['sub'] as $sub2) {
                $two = ['name' => $sub2['title'], 'icon' => $sub2['icon'], 'url' => $sub2['url'], 'node' => $sub2['node'], 'params' => $sub2['params'], 'subs' => []];
                if (!empty($sub2['sub'])) foreach ($sub2['sub'] as $sub3) {
                    $two['subs'][] = ['name' => $sub3['title'], 'url' => $sub3['url'], 'node' => $sub3['node'], 'icon' => $sub3['icon'], 'params' => $sub3['params']];
                }
                if (empty($two['subs'])) unset($two['subs']);
                $one['subs'][] = $two;
            }
            if (empty($one['subs'])) unset($one['subs']);
            $menuData[] = $one;
        }

        // 备份数据表
        [$extra, $version] = [[], strstr($filename = static::nextFile($class), '_', true)];
        if (count($tables) > 0) foreach ($tables as $table) {
            if (($count = ($db = Library::$sapp->db->table($table))->count()) > 0) {
                $dataFileName = "{$version}/{$table}.data";
                $dataFilePath = syspath("database/migrations/{$dataFileName}");
                is_dir($dataDirectory = dirname($dataFilePath)) || mkdir($dataDirectory, 0777, true);
                $progress && ProcessService::message(" -- Starting write {$table}.data ..." . PHP_EOL);
                [$used, $fp] = [0, fopen($dataFilePath, 'w+')];
                foreach ($db->cursor() as $item) {
                    fwrite($fp, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\r\n");
                    if ($progress && ($number = sprintf("%.4f", (++$used / $count) * 100) . '%')) {
                        ProcessService::message(" -- -- write {$table}.data: {$used}/{$count} {$number}", 1);
                    }
                }
                fclose($fp);
                $extra[$table] = $dataFileName;
                $progress && ProcessService::message(" -- Finished write {$table}.data, Total {$used} rows.", 2);
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
     * 数组转代码
     * @param array $data
     * @return string
     */
    private static function _arr2str(array $data): string
    {
        return preg_replace(['#\s+#', '#, \)$#', '#^array \( #'], [' ', ']', '[',], var_export($data, true));
    }

    /**
     * 生成数据库表格创建模板
     * @param array $tables 指定数据表
     * @param boolean $rehtml 是否返回内容
     * @return string
     * @throws \Exception
     */
    private static function _build2table(array $tables = [], bool $rehtml = false): string
    {
        $br = "\r\n";
        $connect = Library::$sapp->db->connect();
        if ($connect->getConfig('type') !== 'mysql') {
            throw new Exception(' ** Notify: 只支持 MySql 数据库生成数据库脚本');
        }
        $schema = $connect->getConfig('database');
        $content = '<?php' . "{$br}{$br}\t/**{$br}\t * 创建数据库{$br}\t */{$br}\t public function change() {";
        foreach ($tables as $table) $content .= "{$br}\t\t\$this->_create_{$table}();";
        $content .= "{$br}{$br}\t}{$br}{$br}";

        // 字段默认长度
        $sizes = ['tinyint' => 4, 'smallint' => 6, 'mediumint' => 9, 'int' => 11, 'bigint' => 20];

        // 字段类型转换
        $types = [
            'varchar'  => 'string', 'enum' => 'string', 'char' => 'char', // 字符
            'tinyint'  => 'integer', 'smallint' => 'integer', 'mediumint' => 'integer', 'int' => 'integer', 'bigint' => 'biginteger', // 整型
            'tinytext' => 'text', 'mediumtext' => 'text', 'longtext' => 'text', // 文本
            'tinyblob' => 'binary', 'blob' => 'binary', 'mediumblob' => 'binary', 'longblob' => 'binary', 'varbinary' => 'binary', 'bit' => 'binary', // 文件
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
    private function _create_{$table}() {

        // 当前数据表
        \$table = '{$table}';

        // 存在则跳过
        if (\$this->hasTable(\$table)) return;

        // 创建数据表
        \$this->table(\$table, [
            'engine' => 'InnoDB', 'collation' => 'utf8mb4_general_ci', 'comment' => '{$comment}',
        ])
CODE;
            foreach (Library::$sapp->db->getFields($table) as $field) {
                if ($field['name'] === 'id') continue;
                $type = $types[$field['type']] ?? $field['type'];
                $data = ['default' => $field['default'], 'null' => empty($field['notnull']), 'comment' => $field['comment'] ?? ''];
                if ($field['type'] === 'enum') {
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
                $params = static::_arr2str($data);
                $content .= "{$br}\t\t->addColumn('{$field["name"]}','{$type}',{$params})";
            }
            // 读取数据表 - 自动生成索引
            $idxs = [];
            $indexs = Library::$sapp->db->connect()->query("show index from {$table}");
            foreach ($indexs as $index) {
                if ($index['Key_name'] === 'PRIMARY') continue;
                $short = substr(md5($index['Table']), 0, 9);
                $params = static::_arr2str(['name' => "i{$short}_{$index['Column_name']}"]);
                $idxs[] = "{$br}\t\t->addIndex('{$index['Column_name']}', {$params})";
            }
            usort($idxs, function ($a, $b) {
                return strlen($a) <=> strlen($b);
            });
            $content .= join('', $idxs);
            $content .= "{$br}\t\t->create();{$br}{$br}\t\t// 修改主键长度";
            $content .= "{$br}\t\t\$this->table(\$table)->changeColumn('id', 'integer', ['limit' => 11, 'identity' => true]);";
            $content .= "{$br}\t}{$br}{$br}";
        }
        return $rehtml ? $content : highlight_string($content, true);
    }

    /**
     * 生成下一个脚本名称
     * @param string $class 脚本类名
     * @return string
     */
    private static function nextFile(string $class): string
    {
        [$filename, $versions, $start] = [Str::snake($class), [], 20009999999999];
        if (count($files = glob(syspath('database/migrations/*.php'))) > 0) {
            foreach ($files as $file) {
                $versions[] = $version = intval(substr($bname = pathinfo($file, 8), 0, 14));
                if ($filename === substr($bname, 15) && unlink($file)) {
                    echo " ** Notify: Class {$class} already exists and has been replaced." . PHP_EOL;
                    if (is_dir($dataPath = dirname($file) . DIRECTORY_SEPARATOR . $version)) {
                        ToolsExtend::removeEmptyDirectory($dataPath);
                    }
                }
            }
            $version = min($versions) - 1;
        }
        if (!isset($version) || $version > $start) $version = $start;
        return "{$version}_{$filename}.php";
    }
}