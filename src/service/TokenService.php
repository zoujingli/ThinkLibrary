<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2020 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkLibrary
// | github 代码仓库：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin\service;

use think\admin\Service;

/**
 * 表单令牌管理服务
 * Class TokenService
 * @package think\admin\service
 */
class TokenService extends Service
{
    /**
     * 验证有效时间
     * @var integer
     */
    protected $expire = 600;

    /**
     * 缓存分组名称
     * @var string
     */
    protected $cachename;

    /**
     * 表单令牌服务初始化
     */
    protected function initialize()
    {
        $username = AdminService::instance()->getUserName();
        $this->cachename = 'systoken_' . ($username ?: 'default');
        $this->_getCacheList(true);
    }

    /**
     * 获取当前请求 CSRF 值
     * @return array|string
     */
    public function getInputToken()
    {
        return $this->app->request->header('user-form-token', input('_csrf_', ''));
    }

    /**
     * 验证 CSRF 是否有效
     * @param string $token 表单令牌
     * @param string $node 授权节点
     * @return boolean
     */
    public function checkFormToken($token = null, $node = null)
    {
        $cnode = NodeService::instance()->fullnode($node);
        $cache = $this->_getCacheItem($token ?: $this->getInputToken(), []);
        if (empty($cache['node']) || empty($cache['time']) || empty($cache['token'])) return false;
        if ($cache['time'] + 600 < time() || strtolower($cache['node']) !== strtolower($cnode)) return false;
        return true;
    }

    /**
     * 清理表单 CSRF 数据
     * @param string $token
     * @return $this
     */
    public function clearFormToken($token = null)
    {
        $this->_delCacheItem($token ?: $this->getInputToken());
        return $this;
    }

    /**
     * 生成表单 CSRF 数据
     * @param string $node
     * @return array
     */
    public function buildFormToken($node = null)
    {
        $cnode = NodeService::instance()->fullnode($node);
        [$token, $time] = [uniqid() . rand(100000, 999999), time()];
        $data = ['node' => $cnode, 'token' => $token, 'time' => $time];
        $this->_setCacheItem($token, $data, $this->expire);
        return $data;
    }

    /**
     * 清空所有 CSRF 数据
     */
    public function clearCache()
    {
        foreach ($this->app->cache->get($this->cachename, []) as $name) {
            $this->_delCacheItem($name);
        }
        $this->app->delete($this->cachename);
    }

    /**
     * 设置缓存数据
     * @param string $name
     * @param mixed $value
     * @param null $ttl
     * @return bool
     */
    private function _setCacheItem($name, $value, $ttl = null)
    {
        $this->app->cache->push($this->cachename, $name);
        return $this->app->cache->set($this->cachename . $name, $value, $ttl);
    }

    /**
     * 删除缓存
     * @param string $name
     * @return bool
     */
    private function _delCacheItem($name)
    {
        return $this->app->cache->delete($this->cachename . $name);
    }

    /**
     * 获取指定缓存
     * @param string $name
     * @param array $default
     * @return mixed
     */
    private function _getCacheItem(string $name, $default = [])
    {
        return $this->app->cache->get($this->cachename . $name, $default);
    }

    /**
     * 获取缓存列表
     * @param bool $clear 强制清理无效的记录
     * @return array
     */
    private function _getCacheList($clear = false): array
    {
        [$data, $time] = [[], time()];
        foreach ($this->app->cache->get($this->cachename, []) as $name) {
            $item = $this->_getCacheItem($name, []);
            if (is_array($item) && isset($item['time']) && $item['time'] + $this->expire > $time) {
                $data[$name] = $item;
            } elseif ($clear) {
                $this->_delCacheItem($item['token']);
            }
        }
        if ($clear) {
            $this->app->cache->set($this->cachename, array_keys($data));
        }
        return $data;
    }
}