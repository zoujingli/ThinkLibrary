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

namespace think\admin\helper;

use think\admin\Helper;
use think\db\BaseQuery;
use think\Model;

/**
 * 数据更新管理器
 * @class SaveHelper
 * @package think\admin\helper
 */
class SaveHelper extends Helper
{

    /**
     * 逻辑器初始化
     * @param BaseQuery|Model|string $dbQuery
     * @param array $edata 表单扩展数据
     * @param string $field 数据对象主键
     * @param mixed $where 额外更新条件
     * @return boolean|void
     * @throws \think\db\exception\DbException
     */
    public function init($dbQuery, array $edata = [], string $field = '', $where = [])
    {
        $query = static::buildQuery($dbQuery);
        $field = $field ?: ($query->getPk() ?: 'id');
        $edata = $edata ?: $this->app->request->post();
        $value = $this->app->request->post($field);

        // 主键限制处理
        if (!isset($where[$field]) && !is_null($value)) {
            $query->whereIn($field, str2arr(strval($value)));
            if (isset($edata)) unset($edata[$field]);
        }

        // 前置回调处理
        if (false === $this->class->callback('_save_filter', $query, $edata)) {
            return false;
        }

        // 检查原始数据
        $query->master()->where($where)->update($edata);

        // 模型自定义事件回调
        $model = $query->getModel();
        if ($model instanceof \think\admin\Model) {
            $model->onAdminSave(strval($value));
        }

        // 结果回调处理
        $result = true;
        if (false === $this->class->callback('_save_result', $result, $model)) {
            return $result;
        }

        // 回复前端结果
        $this->class->success('数据保存成功！', '');
    }
}
