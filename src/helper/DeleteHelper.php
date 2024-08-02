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

namespace think\admin\helper;

use think\admin\Helper;
use think\db\BaseQuery;
use think\Model;

/**
 * 通用删除管理器
 * @class DeleteHelper
 * @package think\admin\helper
 */
class DeleteHelper extends Helper
{
    /**
     * 逻辑器初始化
     * @param BaseQuery|Model|string $dbQuery
     * @param string $field 操作数据主键
     * @param mixed $where 额外更新条件
     * @return boolean|void
     * @throws \think\db\exception\DbException
     */
    public function init($dbQuery, string $field = '', $where = [])
    {
        $query = static::buildQuery($dbQuery);
        $field = $field ?: ($query->getPk() ?: 'id');
        $value = $this->app->request->post($field);

        // 查询限制处理
        if (!empty($where)) $query->where($where);
        if (!isset($where[$field]) && is_string($value)) {
            $query->whereIn($field, str2arr($value));
        }

        // 前置回调处理
        if (false === $this->class->callback('_delete_filter', $query, $where)) {
            return false;
        }

        // 阻止危险操作
        if (!$query->getOptions('where')) {
            $this->class->error('数据删除失败！');
        }

        // 组装执行数据
        $data = [];
        if (method_exists($query, 'getTableFields')) {
            $fields = $query->getTableFields();
            if (in_array('deleted', $fields)) $data['deleted'] = 1;
            if (in_array('is_deleted', $fields)) $data['is_deleted'] = 1;
            if (isset($data['deleted']) || isset($data['is_deleted'])) {
                if (in_array('deleted_at', $fields)) $data['deleted_at'] = date('Y-m-d H:i:s');
                if (in_array('deleted_time', $fields)) $data['deleted_time'] = time();
            }
        }

        // 执行删除操作
        if ($result = (empty($data) ? $query->delete() : $query->update($data)) !== false) {
            // 模型自定义事件回调
            $model = $query->getModel();
            if ($model instanceof \think\admin\Model) {
                $model->onAdminDelete(strval($value));
            }
        }

        // 结果回调处理
        if (false === $this->class->callback('_delete_result', $result)) {
            return $result;
        }

        // 回复返回结果
        if ($result !== false) {
            $this->class->success('数据删除成功！', '');
        } else {
            $this->class->error('数据删除失败！');
        }
    }
}
