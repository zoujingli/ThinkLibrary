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

namespace think\admin\extend;

use think\admin\contract\StreamInterface;
use think\Model;

/**
 * 虚拟模型构建协议
 * @class VirtualModel
 * @package think\admin\extend
 */
class VirtualModel extends \stdClass implements StreamInterface
{
    /**
     * 虚拟模型模板
     * @var string
     */
    private $template;

    /**
     * 读取进度标量
     * @var integer
     */
    private $position;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        // 解析链接参数
        $attr = parse_url($path);
        if (empty($attr['fragment'])) $attr['fragment'] = '';
        $type = strtolower($attr['fragment'] ?: 'default');

        // 生成模型代码
        $this->position = 0;
        $this->template = '<?php ';
        $this->template .= "namespace virtual\\model\\_{$type}; ";
        $this->template .= "class {$attr['host']} extends \\think\\admin\\Model { ";
        if (!empty($attr['fragment'])) {
            $this->template .= "protected \$connection='{$attr['fragment']}'; ";
        }
        $this->template .= '}';
        return true;
    }

    public function stream_read(int $count)
    {
        $content = substr($this->template, $this->position, $count);
        $this->position += strlen($content);
        return $content;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->template);
    }

    public function stream_cast(int $cast_as)
    {
    }

    public function stream_close(): void
    {
    }

    public function stream_flush(): bool
    {
        return true;
    }

    public function stream_lock(int $operation): bool
    {
        return true;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return true;
    }

    public function stream_metadata(string $path, int $option, $value): bool
    {
        return true;
    }

    public function stream_stat()
    {
        return stat(__FILE__);
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    public function stream_truncate(int $new_size): bool
    {
        return true;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        return strlen($data);
    }

    public function dir_opendir(string $path, int $options): bool
    {
        return true;
    }

    public function dir_readdir(): string
    {
        return __DIR__;
    }

    public function dir_closedir(): bool
    {
        return true;
    }

    public function dir_rewinddir(): bool
    {
        return true;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return true;
    }

    public function rmdir(string $path, int $options): bool
    {
        return true;
    }

    public function rename(string $path_from, string $path_to): bool
    {
        return true;
    }

    public function unlink(string $path): bool
    {
        return true;
    }

    public function url_stat(string $path, int $flags)
    {
        return stat(__FILE__);
    }

    /**
     * 创建虚拟模型
     * @param mixed $name 模型名称
     * @param array $data 模型数据
     * @param mixed $conn 默认链接
     * @return \think\Model
     */
    public static function mk(string $name, array $data = [], string $conn = ''): Model
    {
        $type = strtolower($conn ?: 'default');
        if (!class_exists($class = "\\virtual\\model\\_{$type}\\{$name}")) {
            if (!in_array('model', stream_get_wrappers())) {
                stream_wrapper_register('model', static::class);
            }
            include "model://{$name}#{$conn}";
        }
        return new $class($data);
    }
}