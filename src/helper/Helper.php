<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin\helper;

use think\admin\Controller;
use think\Db;

/**
 * 基础视图管理器
 * Class Helper
 * @package think\admin\helper
 */
abstract class Helper
{
    /**
     * 数据库实例
     * @var \think\db\Query
     */
    protected $query;

    /**
     * 当前控制器实例
     * @var Controller
     */
    public $controller;

    /**
     * 逻辑器初始化
     * @return mixed
     */
    abstract public function init();

    /**
     * 获取数据库查询对象
     * @param string|\think\db\Query $dbQuery
     * @return \think\db\Query
     */
    protected function buildQuery($dbQuery)
    {
        return is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery;
    }

}
