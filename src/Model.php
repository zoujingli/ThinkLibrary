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

namespace think\admin;

use think\admin\helper\QueryHelper;

/**
 * 基础模型类
 * @class Model
 * @mixin \think\db\Query
 * @package think\admin
 *
 * --- 静态助手调用
 * @method static bool mSave(array $data = [], string $field = '', mixed $where = []) 快捷更新
 * @method static bool mDelete(string $field = '', mixed $where = []) 快捷删除
 * @method static bool|array mForm(string $template = '', string $field = '', mixed $where = [], array $data = []) 快捷表单
 * @method static bool|integer mUpdate(array $data = [], string $field = '', mixed $where = []) 快捷保存
 * @method static QueryHelper mQuery($input = null, callable $callable = null) 快捷查询
 */
abstract class Model extends \think\Model
{
    /**
     * 日志类型
     * @var string
     */
    protected $oplogType;

    /**
     * 日志名称
     * @var string
     */
    protected $oplogName;

    /**
     * 日志过滤
     * @var callable
     */
    public static $oplogCall;

    /**
     * 创建模型实例
     * @template t of static
     * @param mixed $data
     * @return t|static
     */
    public static function mk($data = [])
    {
        return new static($data);
    }

    /**
     * 创建查询实例
     * @param array $data
     * @return \think\db\Query|\think\db\Mongo
     */
    public static function mq(array $data = [])
    {
        return Helper::buildQuery(static::mk($data)->newQuery());
    }

    /**
     * 调用魔术方法
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return $this|false|mixed
     */
    public function __call($method, $args)
    {
        $oplogs = [
            'onAdminSave'   => "修改%s[%s]状态",
            'onAdminUpdate' => "更新%s[%s]记录",
            'onAdminInsert' => "增加%s[%s]成功",
            "onAdminDelete" => "删除%s[%s]成功",
        ];
        if (isset($oplogs[$method])) {
            if ($this->oplogType && $this->oplogName) {
                $changeIds = $args[0] ?? '';
                if (is_callable(static::$oplogCall)) {
                    $changeIds = call_user_func(static::$oplogCall, $method, $changeIds, $this);
                }
                sysoplog($this->oplogType, lang($oplogs[$method], [lang($this->oplogName), $changeIds]));
            }
            return $this;
        } else {
            return parent::__call($method, $args);
        }
    }

    /**
     * 静态魔术方法
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed|false|integer|QueryHelper
     */
    public static function __callStatic($method, $args)
    {
        return QueryHelper::make(static::class, $method, $args, function ($method, $args) {
            return parent::__callStatic($method, $args);
        });
    }
}
