<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2022 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\extend;

use think\admin\Library;
use think\helper\Str;

/**
 * 扩展工具包
 * Class DataExtend
 * @package think\admin\extend
 */
class ToolsExtend
{

    /**
     * 内容转换为UTF8编码
     * @param string $text
     * @return string
     */
    public static function text2utf8(string $text): string
    {
        return mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text, [
            "ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5',
        ]));
    }

    /**
     * 生成 Phinx 的 SQL 脚本
     * @return string
     */
    public static function db2phinx(): string
    {
        $content = "<?php\n\n";
        foreach (Library::$sapp->db->getTables() as $table) {
            $class = Str::studly($table);
            $content .= <<<CODE

    /**
     * 创建数据对应 {$class}
     * @return void
     */
    public function change() {
        
        // 当前操作
        \$table = "{$table}";
    
        // 存在则跳过
        if (\$this->hasTable(\$table)) return;
        
         // 创建数据表
        \$this->table(\$table, [
            'engine' => 'InnoDB', 'collation' => 'utf8mb4_general_ci', 'comment' => '',
        ])
CODE;
            $fields = Library::$sapp->db->getFields($table);
            unset($fields['id']);
            foreach ($fields as $field) {
                $type = $field['type'];
                $data = ['default' => $field['default'], 'comment' => $field['comment'] ?? ''];
                if (preg_match('/(longtext)/', $field['type'])) {
                    $type = 'text';
                } elseif (preg_match('/varchar\((\d+)\)/', $field['type'], $attr)) {
                    $type = 'string';
                    $data = array_merge(['limit' => intval($attr[1])], $data);
                } elseif (preg_match('/(bigint|tinyint|int)\((\d+)\)/', $field['type'], $attr)) {
                    $type = 'integer';
                    $data = array_merge(['limit' => intval($attr[2])], $data);
                    $data['default'] = intval($data['default']);
                } elseif (preg_match('/decimal\((\d+),(\d+)\)/', $field['type'], $attr)) {
                    $type = 'decimal';
                    $data = array_merge(['precision' => intval($attr[1]), 'scale' => intval($attr[2])], $data);
                }
                $params = preg_replace(['#\s+#', '#, \)$#'], [' ', ' )'], var_export($data, true));
                $content .= "\n\t\t->addColumn('{$field["name"]}', '{$type}', {$params})";
            }
            $content .= "\n\t\t->save();\n\n\t}";
        }
        return highlight_string($content, true);
    }
}