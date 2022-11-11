<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2022 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\service;

use think\admin\extend\CodeExtend;
use think\admin\Service;

/**
 * 系统进程管理服务
 * Class ProcessService
 * @package think\admin\service
 */
class ProcessService extends Service
{

    /**
     * 获取 Think 指令内容
     * @param string $arguments 指令参数
     * @param boolean $simple 指令内容
     * @return string
     */
    public static function think(string $arguments = '', bool $simple = false): string
    {
        $command = with_path("think {$arguments}");
        try {
            if ($simple) return $command;
            if (!($binary = sysconf('base.binary')) || empty($binary)) {
                $attrs = pathinfo(str_replace('/sbin/php-fpm', '/bin/php', PHP_BINARY));
                $attrs['dirname'] = $attrs['dirname'] . DIRECTORY_SEPARATOR;
                $attrs['filename'] = preg_replace('#-(cgi|fpm)$#', '', $attrs['filename']);
                $attrs['extension'] = empty($attrs['extension']) ? '' : ".{$attrs['extension']}";
                $binary = $attrs['dirname'] . $attrs['filename'] . $attrs['extension'];
            }
            return (static::isfile($binary) ? $binary : 'php') . " {$command}";
        } catch (\Exception $exception) {
            trace_file($exception);
            return "php {$command}";
        }
    }

    /**
     * 检查 Think 运行进程
     * @param string $args 执行参数
     * @return array
     */
    public static function thinkQuery(string $args): array
    {
        return static::query(static::think($args, true));
    }

    /**
     * 执行 Think 指令内容
     * @param string $args 执行参数
     * @param integer $usleep 延时时间
     */
    public static function thinkCreate(string $args, int $usleep = 0)
    {
        static::create(static::think($args), $usleep);
    }

    /**
     * 创建异步进程
     * @param string $command 任务指令
     * @param integer $usleep 延时毫米
     */
    public static function create(string $command, int $usleep = 0)
    {
        if (static::iswin()) {
            static::exec(__DIR__ . "/bin/console.exe {$command}");
        } else {
            static::exec("{$command} > /dev/null 2>&1 &");
        }
        $usleep > 0 && usleep($usleep);
    }

    /**
     * 查询相关进程列表
     * @param string $cmd 任务指令
     * @param string $name 进程名称
     * @return array
     */
    public static function query(string $cmd, string $name = 'php.exe'): array
    {
        $list = [];
        if (static::iswin()) {
            $lines = static::exec('wmic process where name="' . $name . '" get processid,CommandLine', true);
            foreach ($lines as $line) if (static::_issub($line, $cmd) !== false) {
                $attr = explode(' ', static::_trim($line));
                $list[] = ['pid' => array_pop($attr), 'cmd' => join(' ', $attr)];
            }
        } else {
            $lines = static::exec("ps ax|grep -v grep|grep \"{$cmd}\"", true);
            foreach ($lines as $line) if (static::_issub($line, $cmd) !== false) {
                $attr = explode(' ', static::_trim($line));
                [$pid] = [array_shift($attr), array_shift($attr), array_shift($attr), array_shift($attr)];
                $list[] = ['pid' => $pid, 'cmd' => join(' ', $attr)];
            }
        }
        return $list;
    }

    /**
     * 关闭任务进程
     * @param integer $pid 进程号
     * @return boolean
     */
    public static function close(int $pid): bool
    {
        if (static::iswin()) {
            static::exec("wmic process {$pid} call terminate");
        } else {
            static::exec("kill -9 {$pid}");
        }
        return true;
    }

    /**
     * 立即执行指令
     * @param string $command 执行指令
     * @param boolean|array $outarr 返回类型
     * @return string|array
     */
    public static function exec(string $command, $outarr = false)
    {
        exec($command, $output);
        return $outarr ? $output : CodeExtend::text2utf8(join("\n", $output));
    }

    /**
     * 判断系统类型
     * @return boolean
     */
    public static function iswin(): bool
    {
        return PATH_SEPARATOR === ';';
    }

    /**
     * 检查文件是否存在
     * @param string $file 待检查的文件
     * @return boolean
     */
    public static function isfile(string $file): bool
    {
        if (static::iswin()) {
            return static::exec("if exist \"{$file}\" echo 1") === '1';
        } else {
            return static::exec("if [ -f \"{$file}\" ];then echo 1;fi") === '1';
        }
    }

    /**
     * 输出文档消息
     * @param string $message 输出内容
     * @param integer $backline 回退行数
     * @return void
     */
    public static function message(string $message, int $backline = 0)
    {
        while ($backline-- > 0) $message = "\033[1A\r\033[K{$message}";
        print_r($message . PHP_EOL);
    }

    /**
     * 清除空白字符过滤
     * @param string $content
     * @return string
     */
    private static function _trim(string $content): string
    {
        return preg_replace('|\s+|', ' ', strtr(trim($content), '\\', '/'));
    }

    /**
     * 判断是否包含字符串
     * @param string $content
     * @param string $searcher
     * @return boolean
     */
    private static function _issub(string $content, string $searcher): bool
    {
        return stripos(static::_trim($content), static::_trim($searcher)) !== false;
    }
}