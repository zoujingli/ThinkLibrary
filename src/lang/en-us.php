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

declare(strict_types=1);

use think\admin\Library;
use think\admin\model\SystemBase;

/**
 * 动态加载英文数据字典
 * 从系统数据字典中读取英文翻译，并缓存以提高性能
 */
$cacheKey = 'lang-en-us';
$langs = Library::$sapp->cache->get($cacheKey, []);

if (empty($langs)) {
    // 从数据字典读取英文翻译
    $langs = array_column(SystemBase::items('英文字典'), 'name', 'code');
    
    // 读取英文菜单并合并到语言包中（使用 menus_ 前缀）
    $menuItems = array_column(SystemBase::items('英文菜单'), 'name', 'code');
    foreach ($menuItems as $key => $name) {
        $langs["menus_{$key}"] = $name;
    }
    
    // 缓存语言包数据，有效期 360 秒
    Library::$sapp->cache->set($cacheKey, $langs, 360);
}

/**
 * 静态菜单语言包定义
 * 使用固定前缀 `menus_` 开头，便于后续扩展和维护
 * 注意：该文件仅在英文模式下才会加载，系统默认使用中文模式
 */
$menus = [
    // 系统管理菜单
    'menus_系统管理'     => 'System',
    'menus_系统配置'     => 'Config',
    'menus_系统参数配置' => 'Params',
    'menus_系统任务管理' => 'Tasks',
    'menus_系统日志管理' => 'Logs',
    'menus_数据字典管理' => 'Dict',
    'menus_系统文件管理' => 'Files',
    'menus_系统菜单管理' => 'Menus',
    'menus_权限管理'     => 'Perms',
    'menus_访问权限管理' => 'Roles',
    'menus_系统用户管理' => 'Users',
    
    // 微信管理菜单
    'menus_微信管理'     => 'WeChat',
    'menus_微信接口配置' => 'Config',
    'menus_微信支付配置' => 'Pay Config',
    'menus_微信粉丝管理' => 'Fans',
    'menus_微信定制'     => 'Custom',
    'menus_微信图文管理' => 'News',
    'menus_微信菜单配置' => 'Menus',
    'menus_回复规则管理' => 'Rules',
    'menus_关注自动回复' => 'Auto Reply',
    'menus_微信支付'     => 'Payment',
    'menus_支付行为管理' => 'Actions',
    'menus_支付退款管理' => 'Refunds',
    
    // 插件中心菜单
    'menus_插件中心'     => 'Plugins',
];

/**
 * 额外语言包配置
 * 包含日期格式、登录提示、分页信息等特殊翻译
 */
$extra = [
    'Y年m月d日 H:i:s' => 'Y/m/d H:i:s',
    '请重新登录！' => 'Invalid authorization, please login again.',
    '共 %s 条记录，每页显示 %s 条，共 %s 页当前显示第 %s 页。' => 'Total %s records, %s per page, page %s of %s.',
];

/**
 * 基础语言包定义
 * 包含接口提示、存储引擎、日志记录、模块管理等翻译
 */
$base = [
    // 接口提示内容
    '数据删除成功！'         => 'Deleted successfully.',
    '数据删除失败！'         => 'Delete failed.',
    '数据保存成功！'         => 'Saved successfully.',
    '数据保存失败！'         => 'Save failed.',
    '数据排序成功！'         => 'Sorted successfully.',
    '列表排序失败！'         => 'Sort failed.',
    '请求响应异常！'         => 'Request exception.',
    '请求响应成功！'         => 'Request successful.',
    '未授权禁止访问！'       => 'Unauthorized access.',
    '会话无效或已失效！'     => 'Session invalid or expired.',
    '表单令牌验证失败！'     => 'Form token validation failed.',
    '接口账号验证失败！'     => 'Account verification failed.',
    '接口请求时差过大！'     => 'Request time difference too large.',
    '接口签名验证失败！'     => 'Signature verification failed.',
    '非JWT访问！'            => 'JWT access required.',
    '请求参数 %s 不能为空！' => 'Parameter %s cannot be empty.',
    '接口请求响应格式异常！' => 'Invalid response format.',
    '耗时 %.4f 秒'          => 'Time: %.4f s',
    '创建任务失败，%s'       => 'Failed to create task: %s',
    '已创建请等待处理完成！' => 'Task created, please wait.',
    '删除%s[%s]及授权配置'  => 'Delete %s[%s] and authorization',
    '暂无轨迹信息~'         => 'No trajectory info.',
    
    // 存储引擎翻译
    '本地服务器存储'        => 'Local Storage',
    '自建Alist存储'         => 'Alist Storage',
    '又拍云USS存储'         => 'Upyun USS',
    '阿里云OSS存储'         => 'Aliyun OSS',
    '腾讯云COS存储'         => 'Tencent COS',
    '七牛云对象存储'        => 'Qiniu OSS',
    '未配置又拍云域名'      => 'Upyun domain not configured',
    '未配置阿里云域名'      => 'Aliyun domain not configured',
    '未配置七牛云域名'      => 'Qiniu domain not configured',
    '未配置腾讯云域名'      => 'Tencent domain not configured',
    '未配置Alist域名'       => 'Alist domain not configured',
    
    // 默认日志翻译
    '增加%s[%s]成功'        => 'Added: %s[%s]',
    '修改%s[%s]状态'        => 'Modified: %s[%s]',
    '更新%s[%s]记录'        => 'Updated: %s[%s]',
    '删除%s[%s]成功'        => 'Deleted: %s[%s]',
    
    // 模块管理翻译
    '系统任务管理'          => 'Task Management',
    '系统菜单管理'          => 'Menu Management',
    '系统文件管理'          => 'File Management',
    '系统用户管理'          => 'User Management',
    '系统日志管理'          => 'Logs Management',
    '系统参数配置'          => 'Parameter Management',
    '访问权限管理'          => 'Permission Management',
    '数据字典管理'          => 'Dictionary Management',
    '系统运维管理'          => 'Maintenance Management',
];

// 合并所有语言包：基础翻译 -> 额外配置 -> 静态菜单 -> 动态字典
return array_merge($base, $extra, $menus, $langs);