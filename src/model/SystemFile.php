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

namespace think\admin\model;

use think\admin\Model;
use think\model\relation\HasOne;

/**
 * 文件管理系统
 *
 * @property int $id
 * @property int $isfast 是否秒传
 * @property int $issafe 安全模式
 * @property int $size 文件大小
 * @property int $status 上传状态(1悬空,2落地)
 * @property int $unid 会员编号
 * @property int $uuid 用户编号
 * @property string $create_at 创建时间
 * @property string $hash 文件哈希
 * @property string $mime 文件类型
 * @property string $name 文件名称
 * @property string $tags 文件标签
 * @property string $type 上传类型
 * @property string $update_at 更新时间
 * @property string $xext 文件后缀
 * @property string $xkey 文件路径
 * @property string $xurl 访问链接
 * @property SystemUser $user
 * @class SystemFile
 */
class SystemFile extends Model
{
    /**
     * 创建字段.
     * @var string
     */
    protected $createTime = 'create_at';

    /**
     * 更新字段.
     * @var string
     */
    protected $updateTime = 'update_at';

    /**
     * 关联用户数据.
     */
    public function user(): HasOne
    {
        return $this->hasOne(SystemUser::class, 'id', 'uuid')->field('id,username,nickname');
    }

    /**
     * 格式化创建时间.
     * @param mixed $value
     */
    public function getCreateAtAttr($value): string
    {
        return format_datetime($value);
    }

    /**
     * 格式化更新时间.
     * @param mixed $value
     */
    public function getUpdateAtAttr($value): string
    {
        return format_datetime($value);
    }
}
