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
use think\model\relation\HasOne;

/**
 * 系统用户模型
 *
 * @property int $id
 * @property int $is_deleted 删除(1删除,0未删)
 * @property int $login_num 登录次数
 * @property int $sort 排序权重
 * @property int $status 状态(0禁用,1启用)
 * @property string $authorize 权限授权
 * @property string $contact_mail 联系邮箱
 * @property string $contact_phone 联系手机
 * @property string $contact_qq 联系QQ
 * @property string $create_at 创建时间
 * @property string $describe 备注说明
 * @property string $headimg 头像地址
 * @property string $login_at 登录时间
 * @property string $login_ip 登录地址
 * @property string $nickname 用户昵称
 * @property string $password 用户密码
 * @property string $username 用户账号
 * @property string $usertype 用户类型
 * @property-read \think\admin\model\SystemBase $userinfo
 * @class SystemUser
 * @package think\admin\model
 */
class SystemUser extends Model
{
    protected $createTime = 'create_at';
    protected $updateTime = false;

    /**
     * 日志名称
     * @var string
     */
    protected $oplogName = '系统用户';

    /**
     * 日志类型
     * @var string
     */
    protected $oplogType = '系统用户管理';

    /**
     * 获取用户数据
     * @param mixed $map 数据查询规则
     * @param array $data 用户数据集合
     * @param string $field 原外连字段
     * @param string $target 关联目标字段
     * @param string $fields 关联数据字段
     * @return array
     */
    public static function items($map, array &$data = [], string $field = 'uuid', string $target = 'user_info', string $fields = 'username,nickname,headimg,status,is_deleted'): array
    {
        $query = static::mk()->where($map)->order('sort desc,id desc');
        if (count($data) > 0) {
            $users = $query->whereIn('id', array_unique(array_column($data, $field)))->column($fields, 'id');
            foreach ($data as &$vo) $vo[$target] = $users[$vo[$field]] ?? [];
            return $users;
        } else {
            return $query->column($fields, 'id');
        }
    }

    /**
     * 关联身份权限
     * @return HasOne
     */
    public function userinfo(): HasOne
    {
        return $this->hasOne(SystemBase::class, 'code', 'usertype')->where([
            'type' => '身份权限', 'status' => 1, 'deleted' => 0,
        ]);
    }

    /**
     * 默认头像处理
     * @param mixed $value
     * @return string
     */
    public function getHeadimgAttr($value): string
    {
        if (empty($value)) try {
            $host = sysconf('base.site_host|raw') ?: 'https://v6.thinkadmin.top';
            return "{$host}/static/theme/img/headimg.png";
        } catch (\Exception $exception) {
            return "https://v6.thinkadmin.top/static/theme/img/headimg.png";
        } else {
            return $value;
        }
    }

    /**
     * 格式化登录时间
     * @param string $value
     * @return string
     */
    public function getLoginAtAttr(string $value): string
    {
        return format_datetime($value);
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