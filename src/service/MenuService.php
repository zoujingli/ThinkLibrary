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

namespace think\admin\service;

use think\admin\extend\DataExtend;
use think\admin\model\SystemMenu;
use think\admin\Service;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 系统菜单管理服务
 * @class MenuService
 */
class MenuService extends Service
{
    /**
     * 获取可选菜单节点.
     * @param bool $force 强制刷新
     */
    public static function getList(bool $force = false): array
    {
        $nodes = sysvar($keys = 'think.admin.menus') ?: [];
        if (empty($force) && count($nodes) > 0) {
            return $nodes;
        }  $nodes = [];
        foreach (NodeService::getMethods($force) as $node => $method) {
            if ($method['ismenu']) {
                $nodes[] = ['node' => $node, 'title' => self::lang($method['title'])];
            }
        }
        return sysvar($keys, $nodes);
    }

    /**
     * 获取系统菜单树数据.
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function getTree(): array
    {
        $menus = SystemMenu::mk()->where(['status' => 1])->order('sort desc,id asc')->select()->toArray();
        if (function_exists('admin_menu_filter')) {
            $menus = call_user_func('admin_menu_filter', $menus);
        }
        foreach ($menus as &$menu) {
            $menu['title'] = self::lang($menu['title']);
        }
        return static::filter(DataExtend::arr2tree($menus));
    }

    /**
     * 菜单分组语言包.
     */
    private static function lang(string $name): string
    {
        $lang = lang("menus_{$name}");
        if (stripos($lang, 'menus_') === 0) {
            return lang(substr($lang, 6));
        }
        return $lang;
    }

    /**
     * 后台主菜单权限过滤.
     * @param array $menus 当前菜单列表
     */
    private static function filter(array $menus): array
    {
        foreach ($menus as $key => &$menu) {
            if (!empty($menu['sub'])) {
                $menu['sub'] = static::filter($menu['sub']);
            }
            if (!empty($menu['sub'])) {
                $menu['url'] = '#';
            } elseif (empty($menu['url']) || $menu['url'] === '#' || !(empty($menu['node']) || AdminService::check($menu['node']))) {
                unset($menus[$key]);
            } elseif (preg_match('#^(https?:)?//\w+#i', $menu['url'])) {
                if ($menu['params']) {
                    $menu['url'] .= (strpos($menu['url'], '?') === false ? '?' : '&') . $menu['params'];
                }
            } else {
                $node = join('/', array_slice(str2arr($menu['url'], '/'), 0, 3));
                $menu['url'] = admuri($menu['url']) . ($menu['params'] ? '?' . $menu['params'] : '');
                if (!AdminService::check($node)) {
                    unset($menus[$key]);
                }
            }
        }
        return $menus;
    }
}
