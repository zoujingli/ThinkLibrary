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
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace library;

use library\tools\Cors;
use think\db\Query;
use think\exception\HttpResponseException;

/**
 * 标准控制器基类
 * Class Controller
 * @package library
 */
class Controller extends \stdClass
{

    /**
     * @var \think\Request
     */
    protected $request;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        Cors::optionsHandler();
        $this->request = request();
    }

    /**
     * 实例方法调用
     * @access public
     * @param string $method 函数名称
     * @param array $arguments 调用参数
     * @return mixed
     * @throws \think\Exception
     */
    public function __call($method, $arguments = [])
    {
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $arguments);
        }
        throw new \think\Exception('method not exists:' . get_class($this) . '->' . $method);
    }

    /**
     * 数据回调处理机制
     * @access public
     * @param string $name 回调方法名称
     * @param mixed $one 回调引用参数1
     * @param mixed $two 回调引用参数2
     * @return boolean
     */
    public function _callback($name, &$one, &$two = [])
    {
        $methods = [$name, "_{$this->request->action()}{$name}"];
        foreach ($methods as $method) if (method_exists($this, $method)) {
            if (false === $this->$method($one, $two)) return false;
        }
        return true;
    }

    /**
     * 返回成功的操作
     * @access protected
     * @param mixed $info 消息内容
     * @param array $data 返回数据
     * @param integer $code 返回代码
     */
    protected function success($info, $data = [], $code = 1)
    {
        $result = ['code' => $code, 'info' => $info, 'data' => $data];
        throw new HttpResponseException(json($result, 200, Cors::getRequestHeader()));
    }

    /**
     * 返回失败的请求
     * @access protected
     * @param mixed $info 消息内容
     * @param array $data 返回数据
     * @param integer $code 返回代码
     */
    protected function error($info, $data = [], $code = 0)
    {
        $result = ['code' => $code, 'info' => $info, 'data' => $data];
        throw new HttpResponseException(json($result, 200, Cors::getRequestHeader()));
    }

    /**
     * URL重定向
     * @access protected
     * @param string $url 重定向跳转链接
     * @param array $params 重定向链接参数
     * @param integer $code 重定向跳转代码
     */
    protected function redirect($url, $params = [], $code = 301)
    {
        throw new HttpResponseException(redirect($url, $params, $code));
    }

    /**
     * 返回视图内容
     * @access protected
     * @param string $tpl 模板名称
     * @param array $vars 模板变量
     * @param array $config 引擎配置
     * @return mixed
     */
    protected function fetch($tpl = '', $vars = [], $config = [])
    {
        return app('view')->assign((array)$this)->fetch($tpl, $vars, $config);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param  mixed $name 要显示的模板变量
     * @param  mixed $value 变量的值
     */
    protected function assign($name, $value = '')
    {
        app('view')->assign($name, $value);
    }

    /**
     * 输入指令绑定
     * @param array $data 验证数据
     * @param array $rule 验证规则
     * @param array $info 验证消息
     * @return array
     */
    protected function _input($data, $rule = [], $info = [])
    {
        return (new \library\logic\Input($data, $rule, $info))->init($this);
    }

    /**
     * 更新指令绑定
     * @param string|Query $dbQuery
     * @param string $pkField 指定数据对象主键
     * @param array $where 额外更新条件
     * @param array $data 表单扩展数据
     * @return boolean
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    protected function _save($dbQuery, $data = [], $pkField = '', $where = [])
    {
        return (new \library\logic\Save($dbQuery, $data, $pkField, $where))->init($this);

    }

    /**
     * 列表指令绑定
     * @param string $dbQuery 数据库查询对象
     * @param boolean $isPage 是否启用分页
     * @param boolean $isDisplay 是否渲染模板
     * @param boolean $total 集合分页记录数
     * @param integer $limit 集合每页记录数
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    protected function _page($dbQuery, $isPage = true, $isDisplay = true, $total = false, $limit = 0)
    {
        return (new \library\logic\Page($dbQuery, $isPage, $isDisplay, $total, $limit))->init($this);
    }

    /**
     * 表单指令绑定
     * @param string|Query $dbQuery
     * @param string $tplFile 模板名称
     * @param string $pkField 指定数据对象主键
     * @param array $where 额外更新条件
     * @param array $data 表单扩展数据
     * @return array|boolean
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    protected function _form($dbQuery, $tplFile = '', $pkField = '', $where = [], $data = [])
    {
        return (new \library\logic\Form($dbQuery, $tplFile, $pkField, $where, $data))->init($this);
    }

    /**
     * 删除指令绑定
     * @param string|Query $dbQuery
     * @param string $pkField 指定数据对象主键
     * @param array $where 额外更新条件
     * @return boolean|null
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    protected function _delete($dbQuery, $pkField = '', $where = [])
    {
        return (new \library\logic\Delete($dbQuery, $pkField, $where))->init($this);
    }

}