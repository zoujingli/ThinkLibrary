<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2021 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkLibrary
// | github 代码仓库：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin;

use think\admin\helper\DeleteHelper;
use think\admin\helper\FormHelper;
use think\admin\helper\QueryHelper;
use think\admin\helper\SaveHelper;

/**
 * 基础模型类
 * Class Model
 * @package think\admin
 * @see \think\db\Query
 * @mixin \think\db\Query
 */
abstract class Model extends \think\Model
{

    /**
     * 日志名称
     * @var string
     */
    protected $oplogName;

    /**
     * 日志类型
     * @var string
     */
    protected $oplogType;

    /**
     * 实例返回模型
     * @return static
     */
    public static function mk($data = [])
    {
        return new static($data);
    }

    /**
     * 快捷表单逻辑器
     * @param string $template 模板名称
     * @param string $field 指定数据对象主键
     * @param array $where 额外更新条件
     * @param array $data 表单扩展数据
     * @return array|bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function mForm(string $template = '', string $field = '', array $where = [], array $data = [])
    {
        return FormHelper::instance()->init(static::mk(), $template, $field, $where, $data);
    }

    /**
     * 快捷更新逻辑器
     * @param array $data 表单扩展数据
     * @param string $field 数据对象主键
     * @param array $where 额外更新条件
     * @return bool
     * @throws \think\db\exception\DbException
     */
    public static function mSave(array $data = [], string $field = '', array $where = []): bool
    {
        return SaveHelper::instance()->init(static::mk(), $data, $field, $where);
    }

    /**
     * 快捷查询逻辑器
     * @param array|string|null $input 查询来源
     * @param callable|null $callable 初始回调
     * @return QueryHelper
     * @throws \think\db\exception\DbException
     */
    public static function mQuery($input = null, ?callable $callable = null): QueryHelper
    {
        return QueryHelper::instance()->init(static::mk(), $input, $callable);
    }

    /**
     * 快捷删除逻辑器
     * @param string $field 数据对象主键
     * @param array $where 额外更新条件
     * @return bool|null
     * @throws \think\db\exception\DbException
     */
    public static function mDelete(string $field = '', array $where = []): ?bool
    {
        return DeleteHelper::instance()->init(static::mk(), $field, $where);
    }

    /**
     * 修改状态默认处理
     * @param string $ids
     */
    public function onAdminSave(string $ids)
    {
        if ($this->oplogType && $this->oplogName) {
            sysoplog($this->oplogType, "修改{$this->oplogName}[{$ids}]状态");
        }
    }

    /**
     * 更新事件默认处理
     * @param string $ids
     */
    public function onAdminUpdate(string $ids)
    {
        if ($this->oplogType && $this->oplogName) {
            sysoplog($this->oplogType, "更新{$this->oplogName}[{$ids}]成功");
        }
    }

    /**
     * 新增事件默认处理
     * @param string $ids
     */
    public function onAdminInsert(string $ids)
    {
        if ($this->oplogType && $this->oplogName) {
            sysoplog($this->oplogType, "增加{$this->oplogName}[{$ids}]成功");
        }
    }

    /**
     * 删除事件默认处理
     * @param string $ids
     */
    public function onAdminDelete(string $ids)
    {
        if ($this->oplogType && $this->oplogName) {
            sysoplog($this->oplogType, "删除{$this->oplogName}[{$ids}]成功");
        }
    }
}