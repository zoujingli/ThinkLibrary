<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | ThinkAdmin Plugin for ThinkAdmin
 * +----------------------------------------------------------------------
 * | 版权所有 2014~2026 ThinkAdmin [ thinkadmin.top ]
 * +----------------------------------------------------------------------
 * | 官方网站: https://thinkadmin.top
 * +----------------------------------------------------------------------
 * | 开源协议 ( https://mit-license.org )
 * | 免责声明 ( https://thinkadmin.top/disclaimer )
 * | 会员特权 ( https://thinkadmin.top/vip-introduce )
 * +----------------------------------------------------------------------
 * | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
 * | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
 * +----------------------------------------------------------------------
 */

namespace think\admin\tests;

use PHPUnit\Framework\TestCase;
use think\admin\Storage;

/**
 * @internal
 * @coversNothing
 */
class StorageTest extends TestCase
{
    public function testInit()
    {
        $this->assertEquals(1, 1);
    }

    /**
     * @dataProvider unsafePathProvider
     */
    public function testRejectsUnsafeStoragePaths(string $path): void
    {
        $this->assertFalse(Storage::isPathSafe($path));
    }

    public static function unsafePathProvider(): array
    {
        return [
            'parent traversal' => ['down/../secret.php'],
            'absolute path' => ['/secret.php'],
            'fragment' => ['12/hash.jpg#/public/shell.php'],
            'query' => ['12/hash.jpg?x=1'],
            'encoded separator' => ['12/hash.jpg%2fsecret.php'],
            'backslash' => ['12\secret.php'],
            'hidden file' => ['12/.user.ini'],
        ];
    }

    public function testAllowsGeneratedStoragePath(): void
    {
        $this->assertTrue(Storage::isPathSafe('down/12/34567890abcdef.jpg'));
    }

    public function testNameStripsQueryStringFromUrlExtension(): void
    {
        $this->assertStringEndsWith('.jpg', Storage::name('https://cdn.example.test/image.jpg?signature=redacted'));
    }
    //    public function testAlist()
    //    {
    //        $alist = AlistStorage::instance();
    //        $alist->set('test.tt', $content = uniqid());
    //        $this->assertEquals($alist->get('test.tt'), $content);
    //    }
}
