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

namespace think\admin\helper;

use think\admin\Exception;
use think\admin\Helper;
use think\admin\service\SystemService;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * 表单视图管理器.
 * @class FormHelper
 */
class FormHelper extends Helper
{
    /**
     * 逻辑器初始化.
     * @param BaseQuery|Model|string $dbQuery
     * @param string $template 视图模板名称
     * @param string $field 指定数据主键
     * @param mixed $where 限定更新条件
     * @param array $edata 表单扩展数据
     * @return array|bool|void
     * @throws Exception
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function init($dbQuery, string $template = '', string $field = '', $where = [], array $edata = [])
    {
        $query = static::buildQuery($dbQuery);
        $field = $field ?: ($query->getPk() ?: 'id');
        $value = $edata[$field] ?? input($field);
        if ($this->app->request->isGet()) {
            if ($value !== null) {
                $exist = $query->where([$field => $value])->where($where)->find();
                if ($exist instanceof Model) {
                    $exist = $exist->toArray();
                }
                $edata = array_merge($edata, $exist ?: []);
            }
            if ($this->class->callback('_form_filter', $edata) !== false) {
                $this->class->fetch($template, ['vo' => $edata]);
            } else {
                return $edata;
            }
        }
        if ($this->app->request->isPost()) {
            $edata = array_merge($this->app->request->post(), $edata);
            if ($this->class->callback('_form_filter', $edata, $where) !== false) {
                $result = SystemService::save($query, $edata, $field, $where) !== false;
                if ($this->class->callback('_form_result', $result, $edata) !== false) {
                    if ($result !== false) {
                        $this->class->success('数据保存成功！');
                    } else {
                        $this->class->error('数据保存失败！');
                    }
                }
                return $result;
            }
        }
    }
}
