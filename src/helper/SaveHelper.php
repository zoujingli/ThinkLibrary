<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | Payment Plugin for ThinkAdmin
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

namespace think\admin\helper;

use think\admin\Helper;
use think\db\BaseQuery;
use think\db\exception\DbException;
use think\Model;

/**
 * 数据更新管理器.
 * @class SaveHelper
 */
class SaveHelper extends Helper
{
    /**
     * 逻辑器初始化.
     * @param BaseQuery|Model|string $dbQuery
     * @param array $edata 表单扩展数据
     * @param string $field 数据对象主键
     * @param mixed $where 额外更新条件
     * @return bool|void
     * @throws DbException
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
            if (isset($edata)) {
                unset($edata[$field]);
            }
        }

        // 前置回调处理
        if ($this->class->callback('_save_filter', $query, $edata) === false) {
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
        if ($this->class->callback('_save_result', $result, $model) === false) {
            return $result;
        }

        // 回复前端结果
        $this->class->success('数据保存成功！', '');
    }
}
