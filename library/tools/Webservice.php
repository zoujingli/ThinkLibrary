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

namespace library\tools;

/**
 * Class Webservice
 * @package library\tools
 */
class Webservice
{
    /**
     * SOAP实例对象
     * @var \SoapClient
     */
    protected $client;

    /**
     * SoapService constructor.
     * @param array $params Params连接参数
     * @param string|null $wsdl WSDL连接参数
     * @throws \think\Exception
     */
    public function __construct(array $params, $wsdl = null)
    {
        set_time_limit(3600);
        if (!extension_loaded('soap')) throw new \think\Exception('Not support soap.');
        $this->client = new \SoapClient($wsdl, $params);
    }

    /**
     * 魔术方法调用
     * @param string $name SOAP调用方法名
     * @param array|string $arguments SOAP调用参数
     * @return array|string|bool
     * @throws \think\Exception
     */
    public function __call($name, $arguments)
    {
        try {
            return $this->client->__soapCall($name, $arguments);
        } catch (\Exception $e) {
            throw new \think\Exception($e->getMessage(), $e->getCode());
        }
    }
}