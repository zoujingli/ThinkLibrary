<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2022 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\service;

use think\admin\extend\DataExtend;
use think\admin\Library;
use think\admin\model\SystemAuth;
use think\admin\model\SystemNode;
use think\admin\model\SystemUser;
use think\admin\Service;

/**
 * 系统权限管理服务
 * Class AdminService
 * @package think\admin\service
 */
class AdminService extends Service
{
    /**
     * 是否已经登录
     * @return boolean
     */
    public static function isLogin(): bool
    {
        return static::getUserId() > 0;
    }

    /**
     * 是否为超级用户
     * @return boolean
     */
    public static function isSuper(): bool
    {
        return static::getUserName() === static::getSuperName();
    }

    /**
     * 获取超级用户账号
     * @return string
     */
    public static function getSuperName(): string
    {
        return Library::$sapp->config->get('app.super_user', 'admin');
    }

    /**
     * 获取后台用户ID
     * @return integer
     */
    public static function getUserId(): int
    {
        return intval(Library::$sapp->session->get('user.id', 0));
    }

    /**
     * 获取后台用户名称
     * @return string
     */
    public static function getUserName(): string
    {
        return Library::$sapp->session->get('user.username', '');
    }

    /**
     * 获取用户扩展数据
     * @param null|string $field
     * @param null|mixed $default
     * @return array|mixed
     */
    public static function getUserData(?string $field = null, $default = null)
    {
        $data = SystemService::getData('UserData_' . static::getUserId(), []);
        return is_null($field) ? $data : ($data[$field] ?? $default);
    }

    /**
     * 设置用户扩展数据
     * @param array $data
     * @param boolean $replace
     * @return boolean|integer
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function setUserData(array $data, bool $replace = false)
    {
        $data = $replace ? $data : array_merge(static::getUserData(), $data);
        return SystemService::setData('UserData_' . static::getUserId(), $data);
    }

    /**
     * 获取用户主题名称
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getUserTheme(): string
    {
        $default = sysconf('base.site_theme') ?: 'default';
        return static::getUserData('site_theme', $default);
    }

    /**
     * 设置用户主题名称
     * @param string $theme 主题名称
     * @return boolean|integer
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function setUserTheme(string $theme)
    {
        return static::setUserData(['site_theme' => $theme]);
    }

    /**
     * 检查指定节点授权
     * --- 需要读取缓存或扫描所有节点
     * @param null|string $node
     * @return boolean
     * @throws \ReflectionException
     */
    public static function check(?string $node = ''): bool
    {
        $methods = NodeService::getMethods();
        // 兼容 windows 控制器不区分大小写的验证问题
        foreach ($methods as $key => $rule) {
            if (preg_match('#.*?/.*?_.*?#', $key)) {
                $attr = explode('/', $key);
                $attr[1] = strtr($attr[1], ['_' => '']);
                $methods[join('/', $attr)] = $rule;
            }
        }
        $current = NodeService::fullNode($node);
        if (function_exists('admin_check_filter')) {
            $nodes = Library::$sapp->session->get('user.nodes', []);
            return admin_check_filter($current, $methods, $nodes);
        } elseif (static::isSuper()) {
            return true;
        } elseif (empty($methods[$current]['isauth'])) {
            return !(!empty($methods[$current]['islogin']) && !static::isLogin());
        } else {
            return in_array($current, Library::$sapp->session->get('user.nodes', []));
        }
    }

    /**
     * 获取授权节点列表
     * @param array $checkeds
     * @return array
     * @throws \ReflectionException
     */
    public static function getTree(array $checkeds = []): array
    {
        [$nodes, $pnodes, $methods] = [[], [], array_reverse(NodeService::getMethods())];
        foreach ($methods as $node => $method) {
            [$count, $pnode] = [substr_count($node, '/'), substr($node, 0, strripos($node, '/'))];
            if ($count === 2 && !empty($method['isauth'])) {
                in_array($pnode, $pnodes) or array_push($pnodes, $pnode);
                $nodes[$node] = ['node' => $node, 'title' => $method['title'], 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            } elseif ($count === 1 && in_array($pnode, $pnodes)) {
                $nodes[$node] = ['node' => $node, 'title' => $method['title'], 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            }
        }
        foreach (array_keys($nodes) as $key) foreach ($methods as $node => $method) if (stripos($key, $node . '/') !== false) {
            $pnode = substr($node, 0, strripos($node, '/'));
            $nodes[$node] = ['node' => $node, 'title' => $method['title'], 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            $nodes[$pnode] = ['node' => $pnode, 'title' => ucfirst($pnode), 'pnode' => '', 'checked' => in_array($pnode, $checkeds)];
        }
        return DataExtend::arr2tree(array_reverse($nodes), 'node', 'pnode', '_sub_');
    }

    /**
     * 初始化用户权限
     * @param boolean $force 强刷权限
     * @return array
     */
    public static function apply(bool $force = false): array
    {
        if ($force) static::clear();
        if (($uuid = static::getUserId()) <= 0) return [];
        $user = SystemUser::mk()->where(['id' => $uuid])->findOrEmpty()->toArray();
        if (!static::isSuper() && count($aids = str2arr($user['authorize'])) > 0) {
            $aids = SystemAuth::mk()->where(['status' => 1])->whereIn('id', $aids)->column('id');
            if (!empty($aids)) $nodes = SystemNode::mk()->distinct()->whereIn('auth', $aids)->column('node');
        }
        $user['nodes'] = $nodes ?? [];
        Library::$sapp->session->set('user', $user);
        return $user;
    }

    /**
     * 清理节点缓存
     * @return bool
     */
    public static function clear(): bool
    {
        Library::$sapp->cache->delete('SystemAuthNode');
        return true;
    }

    /**
     * 静态方法兼容(停时)
     * @param string $name
     * @param array $arguments
     * @return bool|void
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if ($name === 'clearCache') {
            return static::clear();
        }
    }
}