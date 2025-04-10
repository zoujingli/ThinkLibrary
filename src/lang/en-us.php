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

use think\admin\Library;
use think\admin\model\SystemBase;

// 动态读取英文数据字典
if (count($langs = Library::$sapp->cache->get('lang-en-us', [])) < 1) {
    $langs = array_column(SystemBase::items('英文字典'), 'name', 'code');
    $menus = array_column(SystemBase::items('英文菜单'), 'name', 'code');
    foreach ($menus as $key => $name) $langs["menus_{$key}"] = $name;
    Library::$sapp->cache->set('lang-en-us', $langs, 360);
}

// 定义菜单专用语言包，使用固定前缀 `menus_` 开头
// 数据字典菜单语言包类型为：英文菜单，配置与 英文字典 相同
// PS. 使用前缀是方便后缀追加配置，另外历史版本未开启语言分组
// PS. 该文件仅在英文模式下才会加载，系统默认使用 `中文` 模式
$menus = [
//    // 系统管理菜单
//    'menus_系统管理'     => 'System',
//    'menus_系统配置'     => 'Configuration',
//    'menus_系统参数配置' => 'Parameter',
//    'menus_系统任务管理' => 'Tasks',
//    'menus_系统日志管理' => 'Oplog',
//    'menus_数据字典管理' => 'Dictionary',
//    'menus_系统文件管理' => 'File',
//    'menus_系统菜单管理' => 'Menu',
//    'menus_权限管理'     => 'Permission',
//    'menus_访问权限管理' => 'Role',
//    'menus_系统用户管理' => 'User',
//    // 微信管理菜单
//    'menus_微信管理'     => 'WeChat',
//    'menus_微信接口配置' => 'Configuration',
//    'menus_微信支付配置' => 'Pay parameters',
//    'menus_微信粉丝管理' => 'Fan User',
//    'menus_微信定制'     => 'Custom ',
//    'menus_微信图文管理' => 'News',
//    'menus_微信菜单配置' => 'Menu',
//    'menus_回复规则管理' => 'Reply Rule',
//    'menus_关注自动回复' => 'Auto Reply',
//    'menus_微信支付'     => 'Payment',
//    'menus_支付行为管理' => 'Action Record',
//    'menus_支付退款管理' => 'Refund Record',
//    // 插件中心菜单
//    'menus_插件中心'     => 'Plugins'
];

$extra = [];
$extra['Y年m月d日 H:i:s'] = 'Y/m/d H:i:s';
$extra['请重新登录！'] = 'Invalid login authorization, Please login again.';
$extra['共 %s 条记录，每页显示 %s 条，共 %s 页当前显示第 %s 页。'] = 'Total %s records, display %s per page, total %s page current display %s page.';

return array_merge([
    // 常规操作翻译
//    '全部'                  => 'All',
//    '添 加'                 => 'Add',
//    '编 辑'                 => 'Edit',
//    '删 除'                 => 'Delete',
//    '搜 索'                 => 'Search',
//    '导 出'                 => 'Export',
//    '已禁用'                => 'Disabled',
//    '已激活'                => 'Activated',
//    '排序权重'              => 'Sort',
//    '回 收 站'              => 'Recycle',
//    '保存数据'              => 'Submit',
//    '取消编辑'              => 'Cancel',
//    '操作面板'              => 'Panel',
//    '使用状态'              => 'Status',
//    '条件搜索'              => 'Search',
//    '清空数据'              => 'Clears Data',
//    '创建时间'              => 'Create Time',
//    '批量删除'              => 'Remove Selected',
//    '批量禁用'              => 'Forbid Selected',
//    '批量恢复'              => 'Resume Selected',
//    '已禁用记录'            => 'Disabled Records',
//    '已激活记录'            => 'Activated Records',
    // 接口提示内容
    '数据删除成功！'         => 'Successfully deleted.',
    '数据删除失败！'         => 'Sorry, Delete failed.',
    '数据保存成功！'         => 'Successfully saved.',
    '数据保存失败！'         => 'Sorry, Save failed.',
    '数据排序成功！'         => 'Successfully Sorted.',
    '列表排序失败！'         => 'Sorry, Sorting failed.',
    '请求响应异常！'         => 'Sorry, Request response exception.',
    '请求响应成功！'         => 'Sorry, Request response successful.',
    '未授权禁止访问！'       => 'Sorry, Unauthorized access prohibited.',
    '会话无效或已失效！'     => 'The session is invalid or has expired.',
    '表单令牌验证失败！'     => 'The Form token is validation failed.',
    '接口账号验证失败！'     => 'Interface account verification failed.',
    '接口请求时差过大！'     => 'Interface request time difference too large.',
    '接口签名验证失败！'     => 'Interface signature verification failed.',
    '非JWT访问！'            => 'Please use JWT to access.',
    '请求参数 %s 不能为空！' => 'Request parameter %s cannot be empty.',
    '接口请求响应格式异常！' => 'Abnormal format of interface request response.',
    '耗时 %.4f 秒'          => 'Time taken %.4f seconds',
    '创建任务失败，%s'       => 'Failed to create task, %s',
    '已创建请等待处理完成！' => 'Task has been created, please wait for processing to complete.',
    '删除%s[%s]及授权配置'  => 'Delete %s[%s] and authorization configuration',
    '暂无轨迹信息~'         => 'No trajectory information currently available',
    // 存储引擎翻译
    '本地服务器存储'        => 'Local server storage',
    '自建Alist存储'         => 'Self built Alist storage',
    '又拍云USS存储'         => 'Upyun Cloud USS storage',
    '阿里云OSS存储'         => 'Aliyun Cloud OSS storage',
    '腾讯云COS存储'         => 'Tencent Cloud COS Storage',
    '七牛云对象存储'        => 'Qiniu Cloud Object storage',
    '未配置又拍云域名'      => 'Unconfigured Upyun Cloud domain',
    '未配置阿里云域名'      => 'Unconfigured Aliyun Cloud domain',
    '未配置七牛云域名'      => 'Unconfigured Qiniu Cloud domain',
    '未配置腾讯云域名'      => 'Unconfigured Tencent Cloud domain',
    '未配置Alist域名'       => 'Unconfigured Alist Server domain',
    // 默认日志翻译
    '增加%s[%s]成功'        => 'Added: %s [ %s ]',
    '修改%s[%s]状态'        => 'Modify: %s [ %s ]',
    '更新%s[%s]记录'        => 'Update: %s [ %s ]',
    '删除%s[%s]成功'        => 'Remove: %s [ %s ]',
    // 模块管理翻译
//    '系统任务管理'          => 'System Task Management',
//    '系统菜单管理'          => 'System Menu Management',
//    '系统文件管理'          => 'System File Management',
//    '系统用户管理'          => 'System User Management',
//    '系统日志管理'          => 'System Oplog Management',
//    '系统参数配置'          => 'System Parameter Management',
//    '系统权限管理'          => 'System Permission Management',
//    '数据字典管理'          => 'System Dictionary Management',
//    '系统运维管理'          => 'System Maintenance Management',
], $extra, $menus, $langs);