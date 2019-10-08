<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://library.thinkadmin.top
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
    protected $root;

    static protected $object = [];

    abstract public function put($name, $content, $safe = false);

    abstract public function get($name, $safe = false);

    abstract public function del($name, $safe = false);

    abstract public function has($name, $safe = false);

    abstract public function url($name, $safe = false);

    abstract public function path($name, $safe = false);

    abstract public function info($name, $safe = false);

    /**
     * 返回本地存储操作
     * @return LocalStorage
     * @throws \Exception
     */
    public static function LocalStorage()
    {
        return self::instance('local');
    }

    /**
     * 返回七牛云存储操作
     * @return QiniuStorage
     * @throws \Exception
     */
    public static function QiniuStorage()
    {
        return self::instance('qiniu');
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
     * @param array $ext 文件后缀
     * @param array $mine 文件后缀MINE信息
     * @return string
     */
    public static function mine($ext, $mine = [])
    {
        $mines = self::mines();
        foreach (is_string($ext) ? explode(',', $ext) : $ext as $e) {
            $mine[] = isset($mines[strtolower($e)]) ? $mines[strtolower($e)] : 'application/octet-stream';
        }
        return join(',', array_unique($mine));
    }

    /**
     * 获取所有文件扩展的MINES
     * @return array
     */
    public static function mines()
    {
        $mines = cache('all_ext_mine');
        if (empty($mines)) {
            $content = file_get_contents('http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types');
            preg_match_all('#^([^\s]{2,}?)\s+(.+?)$#ism', $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) foreach (explode(" ", $match[2]) as $ext) $mines[$ext] = $match[1];
            cache('all_ext_mine', $mines);
        }
        return $mines;
    }

}