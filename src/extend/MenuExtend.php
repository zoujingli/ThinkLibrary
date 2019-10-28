<?php


namespace think\admin\extend;


use think\Db;

class MenuExtend
{
    /**
     * 初始化用户权限
     * @param boolean $force 是否重置系统权限
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function applyUserAuth($force = false)
    {
        if ($force) app()->cache->delete('system_auth_node');
        if (($uid = app()->session->get('admin_user.id'))) {
            session('admin_user', Db::name('SystemUser')->where(['id' => $uid])->find());
        }
        if (($aids = app()->session->get('admin_user.authorize'))) {
            $where = [['status', 'eq', '1'], ['id', 'in', explode(',', $aids)]];
            $subsql = Db::name('SystemAuth')->field('id')->where($where)->buildSql();
            app()->session->set('admin_user.nodes', array_unique(Db::name('SystemAuthNode')->whereRaw("auth in {$subsql}")->column('node')));
        } else {
            app()->session->set('admin_user.nodes', []);
        }
    }

    /**
     * 获取可选菜单节点
     * @return array
     * @throws \ReflectionException
     */
    public static function getNodeList()
    {
        static $nodes = [];
        if (count($nodes) > 0) return $nodes;
        foreach (NodeExtend::getMethods() as $node => $method) if ($method['ismenu']) {
            $nodes[] = ['node' => $node, 'title' => $method['title']];
        }
        return $nodes;
    }

    /**
     * 获取系统菜单树数据
     * @return array
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getNodeTree()
    {
        $list = Db::name('SystemMenu')->where(['status' => '1'])->order('sort desc,id asc')->select();
        return self::buildData(DataExtend::arr2tree((array)$list), NodeExtend::getMethods());
    }

    /**
     * 后台主菜单权限过滤
     * @param array $menus 当前菜单列表
     * @param array $nodes 系统权限节点
     * @return array
     * @throws \ReflectionException
     */
    private static function buildData($menus, $nodes)
    {
        foreach ($menus as $key => &$menu) {
            if (empty($menu['ismenu'])) continue;
            if (!empty($menu['sub'])) $menu['sub'] = self::buildData($menu['sub'], $nodes);
            if (!empty($menu['sub'])) $menu['url'] = '#';
            elseif (preg_match('/^https?\:/i', $menu['url'])) continue;
            elseif ($menu['url'] === '#') unset($menus[$key]);
            else {
                $node = join('/', array_slice(explode('/', preg_replace('/[\W]/', '/', $menu['url'])), 0, 3));
                $menu['url'] = url($menu['url']) . (empty($menu['params']) ? '' : "?{$menu['params']}");
                if (!NodeExtend::checkAuth($node)) unset($menus[$key]);
            }
        }
        return $menus;
    }
}