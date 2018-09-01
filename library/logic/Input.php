<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2018 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://library.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace library\logic;

/**
 * 输入管理器
 * Class Input
 * @package library\logic
 */
class Input extends Logic
{

    /**
     * 验证器规则
     * @var array
     */
    protected $rule;

    /**
     * 待验证的数据
     * @var array
     */
    protected $data;

    /**
     * 验证结果消息
     * @var array
     */
    protected $message;

    /**
     * Validate constructor.
     * @param array|string $data 待验证的数据
     * @param array $rule 验证器规则
     * @param array $message 验证结果消息
     */
    public function __construct($data, $rule = [], $message = [])
    {
        $this->rule = $rule;
        $this->message = $message;
        $this->request = request();
        $this->data = $this->parseData($data);
    }

    /**
     * 解析数据
     * @param array|string $data
     * @return array
     */
    private function parseData($data)
    {
        if (is_array($data)) {
            return $data;
        }
        if (is_string($data)) {
            $result = [];
            foreach (explode(',', $data) as $field) {
                if (strpos($field, '|') === false) {
                    $result[$field] = input($field);
                } else {
                    list($f, $v) = explode('|', $field);
                    $result[$f] = input($f, $v);
                }
            }
            return $result;
        }
    }

    /**
     * 应用初始化
     * @return array
     */
    public function init()
    {
        $validate = \think\Validate::make($this->rule, $this->message);
        if ($validate->check($this->data)) {
            return $this->data;
        }
        $this->class->error($validate->getError());
    }

}