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
 * @method void onAdminSave(\think\Model $model) 数据字段操作
 * @method void onAdminUpdate(\think\Model $model) 数据更新操作
 * @method void onAdminInsert(\think\Model $model) 数据插入操作
 * @method void onAdminDelete(\think\Model $model) 数据删除操作
 */
abstract class Model extends \think\Model
{
}