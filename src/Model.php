<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2021 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkLibrary
// | github 代码仓库：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin;

use think\helper\Str;

/**
 * 动态模型基础名称
 * Class Model
 * @package think\admin
 */
class Model extends \think\Model
{
    /**
     * 动态模型名称
     * @var string
     */
    private static $mk;

    /**
     * Model constructor.
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->name = self::$mk;
        parent::__construct($data);
        self::$mk = null;
    }

    /**
     * 动态创建模型对象
     * @param string $name 模型名称
     * @param array $data 模型数据
     * @return static
     */
    public static function mk(string $name, array $data = []): \think\Model
    {
        if (strpos($name, '\\') !== false) {
            return new $name($data);
        }
        self::$mk = Str::studly($name);
        return new static($data);
    }
}