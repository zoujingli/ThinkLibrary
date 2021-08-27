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