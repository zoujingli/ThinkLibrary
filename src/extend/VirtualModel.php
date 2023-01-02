<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2023 Anyon<zoujingli@qq.com>
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

use think\Model;

/**
 * 虚拟模型构建协议
 * Class VirtualModel
 * @package think\admin\extend
 */
class VirtualModel
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

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        // 解析链接参数
        $attr = parse_url($path);
        if (empty($attr['fragment'])) $attr['fragment'] = '';
        $type = strtolower($attr['fragment'] ?: 'default');

        // 生成模型代码
        $this->position = 0;
        $this->template = '<?php ';
        $this->template .= "namespace virtual\\model\\_{$type}; ";
        $this->template .= "class {$attr['host']} extends \\think\\Model{ ";
        if (!empty($attr['fragment'])) {
            $this->template .= "protected \$connection='{$attr['fragment']}'; ";
        }
        $this->template .= '}';
        return true;
    }

    public function stream_read($count)
    {
        $content = substr($this->template, $this->position, $count);
        $this->position += strlen($content);
        return $content;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->template);
    }

    public function stream_cast()
    {
    }

    public function stream_close()
    {
    }

    public function stream_flush(): bool
    {
        return true;
    }

    public function stream_lock(): bool
    {
        return true;
    }

    public function stream_set_option(): bool
    {
        return true;
    }

    public function stream_metadata(): bool
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

    public function stream_truncate(): bool
    {
        return true;
    }

    public function stream_seek(): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        return strlen($data);
    }

    public function dir_opendir(): bool
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

    public function rmdir(): bool
    {
        return true;
    }

    public function rename(): bool
    {
        return true;
    }

    public function unlink(): bool
    {
        return true;
    }

    public function url_stat()
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