<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2023 ThinkAdmin [ thinkadmin.top ]
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
use think\admin\extend\CodeExtend;
use think\admin\Library;
use think\admin\Service;

/**
 * 系统进程管理服务
 * Class ProcessService
 * @package think\admin\service
 */
class ProcessService extends Service
{
    /**
     * 生成 Think 脚本
     * @param string $args 指令参数
     * @param boolean $simple 仅返回内容
     * @return string
     */
    public static function think(string $args = '', bool $simple = false): string
    {
        $command = syspath("think {$args}");
        return $simple ? $command : static::getPhpExec() . " {$command}";
    }

    /**
     * 生成 Composer 脚本
     * @param string $args 参数
     * @return string
     */
    public static function composer(string $args = ''): string
    {
        static $comExec;
        if (empty($comExec) && self::isfile($comExec = self::getRunVar('com'))) {
            $comExec = self::getPhpExec() . ' ' . $comExec;
        }
        $root = Library::$sapp->getRootPath();
        return ($comExec ?: 'composer') . " -d {$root} {$args}";
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
     * 创建 Think 进程
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
     * 查询进程列表
     * @param string $cmd 任务指令
     * @param string $name 进程名称
     * @return array
     */
    public static function query(string $cmd, string $name = 'php.exe'): array
    {
        $list = [];
        if (static::iswin()) {
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
     * 关闭独立进程
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
        $process = Process::fromShellCommandline($command);
        $process->setWorkingDirectory(Library::$sapp->getRootPath())->run();
        $output = str_replace("\r\n", "\n", CodeExtend::text2utf8($process->getOutput()));
        return $outarr ? explode("\n", $output) : trim($output);
    }

    /**
     * 判断系统类型
     * @return boolean
     */
    public static function iswin(): bool
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * 检查文件是否存在
     * @param string $file 文件路径
     * @return boolean
     */
    public static function isfile(string $file): bool
    {
        try {
            return $file !== '' && is_file($file);
        } catch (\Error|\Exception $exception) {
            try {
                if (self::iswin()) {
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
     * 获取运行参数
     * @param string $field 指定字段
     * @return string
     */
    private static function getRunVar(string $field): string
    {
        $file = syspath('vendor/binarys.php');
        if (file_exists($file) && is_array($binarys = include $file)) {
            return $binarys[$field] ?? '';
        } else {
            return '';
        }
    }

    /**
     * 获取 PHP 路径
     * @return string
     */
    private static function getPhpExec(): string
    {
        static $phpExec;
        if ($phpExec) return $phpExec;
        if (self::isfile($phpExec = self::getRunVar('php'))) return $phpExec;
        $phpExec = str_replace('/sbin/php-fpm', '/bin/php', PHP_BINARY);
        $phpExec = preg_replace('#-(cgi|fpm)(\.exe)?$#', '$2', $phpExec);
        return self::isfile($phpExec) ? $phpExec : $phpExec = 'php';
    }
}