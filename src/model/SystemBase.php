<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
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

namespace think\admin\model;

use think\admin\Model;

/**
 * 数据字典模型
 *
 * @property int $deleted 删除状态(0正常,1已删)
 * @property int $deleted_by 删除用户
 * @property int $id
 * @property int $sort 排序权重
 * @property int $status 数据状态(0禁用,1启动)
 * @property string $code 数据代码
 * @property string $content 数据内容
 * @property string $create_at 创建时间
 * @property string $deleted_at 删除时间
 * @property string $name 数据名称
 * @property string $type 数据类型
 * @class SystemBase
 * @package think\admin\model
 */
class SystemBase extends Model
{
    protected $createTime = 'create_at';
    protected $updateTime = false;

    /**
     * 日志名称
     * @var string
     */
    protected $oplogName = '数据字典';

    /**
     * 日志类型
     * @var string
     */
    protected $oplogType = '数据字典管理';

    /**
     * 获取指定数据列表
     * @param string $type 数据类型
     * @param array $data 外围数据
     * @param string $field 外链字段
     * @param string $bind 绑定字段
     * @return array
     */
    public static function items(string $type, array &$data = [], string $field = 'base_code', string $bind = 'base_info'): array
    {
        $map = ['type' => $type, 'status' => 1, 'deleted' => 0];
        $bases = static::mk()->where($map)->order('sort desc,id asc')->column('code,name,content', 'code');
        if (count($data) > 0) foreach ($data as &$vo) $vo[$bind] = $bases[$vo[$field]] ?? [];
        return $bases;
    }

    /**
     * 获取所有数据类型
     * @param boolean $simple 加载默认值
     * @return array
     */
    public static function types(bool $simple = false): array
    {
        $types = static::mk()->where(['deleted' => 0])->distinct()->column('type');
        if (empty($types) && empty($simple)) $types = ['身份权限'];
        return $types;
    }

    /**
     * 格式化创建时间
     * @param mixed $value
     * @return string
     */
    public function getCreateAtAttr($value): string
    {
        return format_datetime($value);
    }
}