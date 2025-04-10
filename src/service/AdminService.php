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

namespace think\admin\service;

use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\admin\extend\DataExtend;
use think\admin\Library;
use think\admin\model\SystemAuth;
use think\admin\model\SystemNode;
use think\admin\model\SystemUser;
use think\admin\Service;
use think\helper\Str;
use think\Session;

/**
 * 系统权限管理服务
 * @class AdminService
 * @package think\admin\service
 */
class AdminService extends Service
{
    /**
     * 自定义回调处理
     * @var array
     */
    private static $checkCallables = [];

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
        $data = SystemService::getData('UserData_' . static::getUserId());
        return is_null($field) ? $data : ($data[$field] ?? $default);
    }

    /**
     * 设置用户扩展数据
     * @param array $data
     * @param boolean $replace
     * @return boolean
     * @throws \think\admin\Exception
     */
    public static function setUserData(array $data, bool $replace = false): bool
    {
        $data = $replace ? $data : array_merge(static::getUserData(), $data);
        return SystemService::setData('UserData_' . static::getUserId(), $data);
    }

    /**
     * 获取用户主题名称
     * @return string
     * @throws \think\admin\Exception
     */
    public static function getUserTheme(): string
    {
        $default = sysconf('base.site_theme|raw') ?: 'default';
        return static::getUserData('site_theme', $default);
    }

    /**
     * 设置用户主题名称
     * @param string $theme 主题名称
     * @return boolean
     * @throws \think\admin\Exception
     */
    public static function setUserTheme(string $theme): bool
    {
        return static::setUserData(['site_theme' => $theme]);
    }

    /**
     * 注册权限检查函数
     * @param callable $callable
     * @return integer
     */
    public static function registerCheckCallable(callable $callable): int
    {
        self::$checkCallables[] = $callable;
        return count(self::$checkCallables) - 1;
    }

    /**
     * 移除权限检查函数
     * @param ?integer $index
     * @return boolean
     */
    public static function removeCheckCallable(?int $index): bool
    {
        if (is_null($index)) {
            self::$checkCallables = [];
            return true;
        } elseif (isset(self::$checkCallables[$index])) {
            unset(self::$checkCallables[$index]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检查指定节点授权
     * --- 需要读取缓存或扫描所有节点
     * @param null|string $node
     * @return boolean
     */
    public static function check(?string $node = ''): bool
    {
        $skey1 = 'think.admin.methods';
        $current = NodeService::fullNode($node);
        $methods = sysvar($skey1) ?: sysvar($skey1, NodeService::getMethods());
        $userNodes = Library::$sapp->session->get('user.nodes', []);
        // 自定义权限检查回调
        if (count(self::$checkCallables) > 0) {
            foreach (self::$checkCallables as $callable) {
                if ($callable($current, $methods, $userNodes) === false) {
                    return false;
                }
            }
            return true;
        }
        // 自定义权限检查方法
        if (function_exists('admin_check_filter')) {
            return call_user_func('admin_check_filter', $current, $methods, $userNodes);
        }
        // 超级用户不需要检查权限
        if (static::isSuper()) return true;
        // 节点权限检查，需要兼容 windows 控制器不区分大小写，统一去除节点下划线再检查权限
        if (empty($simples = sysvar($skey2 = 'think.admin.fulls') ?: [])) {
            foreach ($methods as $k => $v) $simples[strtr($k, ['_' => ''])] = $v;
            sysvar($skey2, $simples);
        }
        if (empty($simples[$simple = strtr($current, ['_' => ''])]['isauth'])) {
            return !(!empty($simples[$simple]['islogin']) && !static::isLogin());
        } else {
            return in_array($current, $userNodes);
        }
    }

    /**
     * 获取授权节点列表
     * @param array $checkeds
     * @return array
     */
    public static function getTree(array $checkeds = []): array
    {
        [$nodes, $pnodes, $methods] = [[], [], array_reverse(NodeService::getMethods())];
        foreach ($methods as $node => $method) {
            [$count, $pnode] = [substr_count($node, '/'), substr($node, 0, strripos($node, '/'))];
            if ($count === 2 && !empty($method['isauth'])) {
                in_array($pnode, $pnodes) or array_push($pnodes, $pnode);
                $nodes[$node] = ['node' => $node, 'title' => lang($method['title']), 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            } elseif ($count === 1 && in_array($pnode, $pnodes)) {
                $nodes[$node] = ['node' => $node, 'title' => lang($method['title']), 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            }
        }
        foreach (array_keys($nodes) as $key) foreach ($methods as $node => $method) if (stripos($key, $node . '/') !== false) {
            $pnode = substr($node, 0, strripos($node, '/'));
            $nodes[$node] = ['node' => $node, 'title' => lang($method['title']), 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            $nodes[$pnode] = ['node' => $pnode, 'title' => Str::studly($pnode), 'pnode' => '', 'checked' => in_array($pnode, $checkeds)];
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
     * 获取会员上传配置
     * @param ?string $uptoken
     * @return array [unid,exts]
     */
    public static function withUploadUnid(?string $uptoken = null): array
    {
        try {
            if ($uptoken === '') return [0, []];
            $session = Library::$sapp->session;
            if (is_null($uptoken)) {
                $sesskey = $session->get('UploadSessionKey');
                if (empty($sesskey)) return [0, []];
                if ($session->getId() !== $sesskey) {
                    $session = Library::$sapp->invokeClass(Session::class);
                    $session->setId($sesskey);
                    $session->init();
                }
                $unid = intval($session->get('AdminUploadUnid', 0));
            } else {
                $sesskey = CodeExtend::decrypt($uptoken, sysconf('data.jwtkey'));
                if (empty($sesskey)) return [0, []];
                if ($session->getId() !== $sesskey) {
                    $session = Library::$sapp->invokeClass(Session::class);
                    $session->setId($sesskey);
                    $session->init();
                }
                if ($unid = intval($session->get('AdminUploadUnid', 0))) {
                    $session->set('UploadSessionKey', $session->getId());
                }
            }
            return [$unid, $session->get('AdminUploadExts', [])];
        } catch (\Error|\Exception $exception) {
            return [0, []];
        }
    }

    /**
     * 生成上传入口令牌
     * @param integer $unid 会员编号
     * @param string $exts 允许后缀(多个以英文逗号隔开)
     * @return string
     * @throws \think\admin\Exception
     */
    public static function withUploadToken(int $unid, string $exts = ''): string
    {
        Library::$sapp->session->set('AdminUploadUnid', $unid);
        Library::$sapp->session->set('AdminUploadExts', str2arr(strtolower($exts)));
        return CodeExtend::encrypt(Library::$sapp->session->getId(), sysconf('data.jwtkey'));
    }

    /**
     * 静态方法兼容(临时)
     * @param string $method
     * @param array $arguments
     * @return bool
     * @throws \think\admin\Exception
     */
    public static function __callStatic(string $method, array $arguments)
    {
        if (strtolower($method) === 'clearcache') return static::clear();
        throw new Exception("method not exists: AdminService::{$method}()");
    }

    /**
     * 对象方法兼容(临时)
     * @param string $method
     * @param array $arguments
     * @return bool
     * @throws \think\admin\Exception
     */
    public function __call(string $method, array $arguments)
    {
        return static::__callStatic($method, $arguments);
    }
}