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

namespace think\admin\service;

use Symfony\Component\Process\Process;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\admin\Library;
use think\admin\Service;

/**
 * 系统进程管理服务
 * @class ProcessService
 * @package think\admin\service
 */
class ProcessService extends Service
{

    /**
     * 生成 PHP 指令
     * @param string $args
     * @return string
     */
    public static function php(string $args = ''): string
    {
        return ModuleService::getPhpExec() . ' ' . $args;
    }

    /**
     * 生成 Think 指令
     * @param string $args 指令参数
     * @param boolean $simple 仅返回内容
     * @return string
     */
    public static function think(string $args = '', bool $simple = false): string
    {
        $command = syspath('think') . ' ' . $args;
        return $simple ? $command : self::php($command);
    }

    /**
     * 生成 Composer 指令
     * @param string $args 参数
     * @return string
     */
    public static function composer(string $args = ''): string
    {
        static $comExec;
        if (empty($comExec)) {
            $comExec = ModuleService::getRunVar('com');
            $comExec = self::isFile($comExec) ? self::php($comExec) : 'composer';
        }
        $root = Library::$sapp->getRootPath();
        return "{$comExec} -d {$root} {$args}";
    }

    /**
     * 创建 Think 进程
     * @param string $args 执行参数
     * @param integer $usleep 延时等待
     * @param boolean $doQuery 查询进程
     * @return array
     */
    public static function thinkExec(string $args, int $usleep = 0, bool $doQuery = false): array
    {
        static::create(static::think($args), $usleep);
        return $doQuery ? static::query(static::think($args, true)) : [];
    }

    /**
     * 检查 Think 进程
     * @param string $args 执行参数
     * @return array
     */
    public static function thinkQuery(string $args): array
    {
        return static::query(static::think($args, true));
    }

    /**
     * 创建异步进程
     * @param string $command 任务指令
     * @param integer $usleep 延时毫米
     */
    public static function create(string $command, int $usleep = 0)
    {
        if (static::isWin()) {
            static::exec(__DIR__ . "/bin/console.exe {$command}");
        } else {
            static::exec("{$command} > /dev/null 2>&1 &");
        }
        $usleep > 0 && usleep($usleep);
    }

    /**
     * 查询进程列表
     * @param string $cmd 任务指令
     * @param string $name 进程名称
     * @return array
     */
    public static function query(string $cmd, string $name = 'php.exe'): array
    {
        $list = [];
        if (static::isWin()) {
            $lines = static::exec("wmic process where name=\"{$name}\" get processid,CommandLine", true);
            foreach ($lines as $line) if (is_numeric(stripos($line, $cmd))) {
                $attr = explode(' ', trim(preg_replace('#\s+#', ' ', $line)));
                $list[] = ['pid' => array_pop($attr), 'cmd' => join(' ', $attr)];
            }
        } else {
            $lines = static::exec("ps ax|grep -v grep|grep \"{$cmd}\"", true);
            foreach ($lines as $line) if (is_numeric(stripos($line, $cmd))) {
                $attr = explode(' ', trim(preg_replace('#\s+#', ' ', $line)));
                [$pid] = [array_shift($attr), array_shift($attr), array_shift($attr), array_shift($attr)];
                $list[] = ['pid' => $pid, 'cmd' => join(' ', $attr)];
            }
        }
        return $list;
    }

    /**
     * 关闭指定进程
     * @param integer $pid 进程号
     * @return boolean
     */
    public static function close(int $pid): bool
    {
        if (static::isWin()) {
            static::exec("wmic process {$pid} call terminate");
        } else {
            static::exec("kill -9 {$pid}");
        }
        return true;
    }

    /**
     * 立即执行指令
     * @param string $command 执行指令
     * @param boolean $outarr 返回数组
     * @param ?callable $callable 逐行处理
     * @return string|array
     */
    public static function exec(string $command, bool $outarr = false, ?callable $callable = null)
    {
        $process = Process::fromShellCommandline($command)->setWorkingDirectory(Library::$sapp->getRootPath());
        $process->run(is_callable($callable) ? static function ($type, $text) use ($callable, $process) {
            call_user_func($callable, $process, $type, trim(CodeExtend::text2utf8($text))) === true && $process->stop();
        } : null);
        $output = str_replace("\r\n", "\n", CodeExtend::text2utf8($process->getOutput()));
        return $outarr ? explode("\n", $output) : trim($output);
    }

    /**
     * 输出命令行消息
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
     * 判断系统类型 WINDOWS
     * @return boolean
     */
    public static function isWin(): bool
    {
        return PATH_SEPARATOR === ';';
    }

    /**
     * 判断系统类型 UNIX
     * @return bool
     */
    public static function isUnix(): bool
    {
        return PATH_SEPARATOR !== ';';
    }

    /**
     * 检查文件是否存在
     * @param string $file 文件路径
     * @return boolean
     */
    public static function isFile(string $file): bool
    {
        try {
            return $file !== '' && is_file($file);
        } catch (\Error|\Exception $exception) {
            try {
                if (self::isWin()) {
                    return self::exec("if exist \"{$file}\" echo 1") === '1';
                } else {
                    return self::exec("if [ -f \"{$file}\" ];then echo 1;fi") === '1';
                }
            } catch (\Error|\Exception $exception) {
                return false;
            }
        }
    }

    /**
     * 静态兼容处理
     * @param string $method
     * @param array $arguments
     * @return array
     * @throws \think\admin\Exception
     */
    public static function __callStatic(string $method, array $arguments)
    {
        if ($method === 'thinkCreate') {
            return self::thinkExec(...$arguments);
        } else {
            throw new Exception("method not exists: ProcessService::{$method}()");
        }
    }
}