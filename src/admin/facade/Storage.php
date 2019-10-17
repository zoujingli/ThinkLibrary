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

namespace think\admin\facade;

use think\Facade;

/**
 * Class Storage
 * @package think\admin\facade
 * @see \think\admin\Storage
 * @mixin \think\admin\Storage
 * @method array info($name, $safe = false) static 文件存储信息
 * @method string get($name, $safe = false) static 读取文件内容
 * @method string url($name, $safe = false) static 获取文件地址
 * @method string set($name, $content, $safe = false) static 文件储存
 * @method string path($name, $safe = false) static 文件存储路径
 * @method boolean del($name, $safe = false) static 删除存储
 * @method boolean has($name, $safe = false) static 检查文件是否存在
 */
class Storage extends Facade
{
    protected static function getFacadeClass()
    {
        return 'think\admin\Storage';
    }
}