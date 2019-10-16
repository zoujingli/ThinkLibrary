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

namespace library;

use library\storage\LocalStorage;
use library\storage\QiniuStorage;

/**
 * 文件存储引擎管理
 * Class Storage
 * @package library
 */
abstract class Storage
{
    protected $prefix;

    static protected $object = [];

    abstract public function set($name, $content, $safe = false);

    abstract public function get($name, $safe = false);

    abstract public function del($name, $safe = false);

    abstract public function has($name, $safe = false);

    abstract public function url($name, $safe = false);

    abstract public function path($name, $safe = false);

    abstract public function info($name, $safe = false);

    /**
     * 文件操作静态访问
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function __callStatic($name, $arguments)
    {
        $class = self::instance(sysconf('storage_type'));
        return call_user_func_array([$class, $name], $arguments);
    }

    /**
     * 设置文件驱动名称
     * @param string $name
     * @return LocalStorage|QiniuStorage
     * @throws \Exception
     */
    public static function instance($name)
    {
        if (isset(self::$object[$class = ucfirst(strtolower($name))])) {
            return self::$object[$class];
        }
        if (class_exists($object = __NAMESPACE__ . "\\storage\\{$class}Storage")) {
            return self::$object[$class] = new $object;
        }
        throw new \think\Exception("File driver [{$class}] does not exist.");
    }

    /**
     * 获取文件相对名称
     * @param string $url 文件链接
     * @param string $ext 文件后缀
     * @param string $pre 文件前缀（需要以/结尾）
     * @param string $fun 文件名生成方法
     * @return string
     */
    public static function name($url, $ext = '', $pre = '', $fun = 'md5')
    {
        empty($ext) && $ext = pathinfo($url, 4);
        empty($ext) || $ext = trim($ext, '.\\/');
        empty($pre) || $pre = trim($pre, '.\\/');
        $splits = array_merge([$pre], str_split($fun($url), 16));
        return trim(join('/', $splits), '/') . '.' . strtolower($ext ? $ext : 'tmp');
    }

    /**
     * 根据文件后缀获取文件MINE
     * @param array $exts 文件后缀
     * @param array $mime 文件MINE信息
     * @return string
     */
    public static function mime($exts, $mime = [])
    {
        $mimes = self::mimes();
        foreach (is_string($exts) ? explode(',', $exts) : $exts as $e) {
            $mime[] = isset($mimes[strtolower($e)]) ? $mimes[strtolower($e)] : 'application/octet-stream';
        }
        return join(',', array_unique($mime));
    }

    /**
     * 获取所有文件扩展的MINES
     * @return array
     */
    public static function mimes()
    {
        static $mimes = [];
        if (count($mimes) > 0) return $mimes;
        return $mimes = include __DIR__ . '/storage/bin/mimes.php';
    }

}