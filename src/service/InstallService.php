<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2020 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin\service;

use think\admin\Service;

/**
 * 模块安装服务管理
 * Class InstallService
 * @package think\admin\service
 */
class InstallService extends Service
{

    /**
     * 获取文件信息列表
     * @param array $rules 文件规则
     * @param array $ignore 忽略规则
     * @param array $data 扫描结果列表
     * @return array
     */
    public function getList(array $rules, array $ignore = [], array $data = []): array
    {
        return ModuleService::instance()->getChangeRuleList($rules, $ignore, $data);
    }

    /**
     * 获取文件差异数据
     * @param array $rules 文件规则
     * @param array $ignore 忽略规则
     * @return array
     */
    public function grenerateDifference(array $rules = [], array $ignore = []): array
    {
        return ModuleService::instance()->grenerateDifference($rules, $ignore);
    }

    /**
     * 下载并更新文件
     * @param array $file 文件信息
     * @return array
     */
    public function updateFileByDownload(array $file): array
    {
        return ModuleService::instance()->updateFileByDownload($file);
    }

}