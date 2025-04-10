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

namespace think\admin\contract;

/**
 * 流协议接口
 * @class StreamInterface
 * @package think\admin\contract
 */
interface StreamInterface
{
    public function dir_closedir(): bool;

    public function dir_opendir(string $path, int $options): bool;

    public function dir_readdir(): string;

    public function dir_rewinddir(): bool;

    public function mkdir(string $path, int $mode, int $options): bool;

    public function rename(string $path_from, string $path_to): bool;

    public function rmdir(string $path, int $options): bool;

    public function stream_cast(int $cast_as);

    public function stream_close(): void;

    public function stream_eof(): bool;

    public function stream_flush(): bool;

    public function stream_lock(int $operation): bool;

    public function stream_metadata(string $path, int $option, $value): bool;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool;

    /**
     * @param int $count
     * @return string|false
     */
    public function stream_read(int $count);

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool;

    public function stream_set_option(int $option, int $arg1, int $arg2): bool;

    /**
     * @return array|false
     */
    public function stream_stat();

    public function stream_tell(): int;

    public function stream_truncate(int $new_size): bool;

    public function stream_write(string $data): int;

    public function unlink(string $path): bool;

    /**
     * @param string $path
     * @param integer $flags
     * @return array|false
     */
    public function url_stat(string $path, int $flags);
}