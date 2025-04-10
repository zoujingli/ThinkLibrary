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
 * 授权节点模型
 *
 * @property int $auth 角色
 * @property int $id
 * @property string $node 节点
 * @class SystemNode
 * @mixin \think\db\Query
 * @package think\admin\model
 */
class SystemNode extends Model
{
    protected $updateTime = false;
    protected $createTime = false;

    /**
     * 绑定模型名称
     * @var string
     */
    protected $name = 'SystemAuthNode';
}