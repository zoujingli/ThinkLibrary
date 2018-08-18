<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2018 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://library.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace library\logic;

use think\Db;

/**
 * 列表处理管理器
 * Class ViewList
 * @package library\view
 */
class LogicList extends Logic
{
    /**
     * 集合分页记录数
     * @var integer
     */
    protected $total;

    /**
     * 是否启用分页
     * @var boolean
     */
    protected $isPage;

    /**
     * 是否渲染模板
     * @var boolean
     */
    protected $isDisplay;

    /**
     * ViewList constructor.
     * @param string $dbQuery 数据库查询对象
     * @param boolean $isPage 是否启用分页
     * @param boolean $isDisplay 是否渲染模板
     * @param boolean $total 集合分页记录数
     */
    public function __construct($dbQuery, $isPage = true, $isDisplay = true, $total = false)
    {
        parent::__construct($dbQuery);
        $this->total = $total;
        $this->isPage = $isPage;
        $this->isDisplay = $isDisplay;
    }

    /**
     * 应用初始化
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    protected function init()
    {
        $this->_sort();
        return $this->_list();
    }

    /**
     * 列表集成处理方法
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function _list()
    {
        // 列表数据查询与显示
        if (null === $this->db->getOptions('order')) {
            if (method_exists($this->db, 'getTableFields') && in_array('sort', $this->db->getTableFields())) {
                $this->db->order('sort asc');
            }
        }
        if ($this->isPage) {
            $rows = intval($this->request->get('rows', cookie('page-rows')));
            cookie('page-rows', $rows = $rows >= 10 ? $rows : 20);
            $page = $this->db->paginate($rows, $this->total, ['query' => $this->request->get()]);
            // 分页HTML数据处理
            $attr = ['|href="(.*?)"|' => 'data-open="$1"',];
            $html = "<div class='pagination-trigger nowrap'><span>共 {$page->total()} 条记录，每页显示 {$rows} 条，共 {$page->lastPage()} 页当前显示第 {$page->currentPage()} 页。</span>{$page->render()}</div>";
            $this->class->assign('pagehtml', preg_replace(array_keys($attr), array_values($attr), $html));
            // 组装结果数据
            $result = [
                'page' => [
                    'limit'   => intval($rows),
                    'total'   => intval($page->total()),
                    'pages'   => intval($page->lastPage()),
                    'current' => intval($page->currentPage()),
                ],
                'list' => $page->items(),
            ];
        } else {
            $result = ['list' => $this->db->select()];
        }
        if (false !== $this->class->_callback('_list_filter', $result['list']) && $this->isDisplay) {
            return $this->class->fetch('', $result);
        }
        return $result;
    }

    /**
     * 列表排序操作
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    protected function _sort()
    {
        if ($this->request->isPost() && $this->request->post('action') === 'resort') {
            foreach ($this->request->post() as $key => $value) {
                if (preg_match('/^_\d{1,}$/', $key) && preg_match('/^\d{1,}$/', $value)) {
                    list($where, $update) = [['id' => trim($key, '_')], ['sort' => $value]];
                    if (false === Db::table($this->db->getTable())->where($where)->update($update)) {
                        $this->class->error('排序失败, 请稍候再试！');
                    }
                }
            }
            $this->class->success('排序成功, 正在刷新页面！', '');
        }
    }

}