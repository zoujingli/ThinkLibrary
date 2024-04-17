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

namespace think\admin\model;

use think\admin\Model;
use think\model\relation\HasOne;

/**
 * 文件管理系统
 * @class SystemFile
 * @package think\admin\model
 */
class SystemFile extends Model
{
    /**
     * 创建字段
     * @var string
     */
    protected $createTime = 'create_at';

    /**
     * 更新字段
     * @var string
     */
    protected $updateTime = 'update_at';

    /**
     * 关联用户数据
     * @return \think\model\relation\HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(SystemUser::class, 'id', 'uuid')->field('id,username,nickname');
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

    /**
     * 格式化更新时间
     * @param mixed $value
     * @return string
     */
    public function getUpdateAtAttr($value): string
    {
        return format_datetime($value);
    }
}