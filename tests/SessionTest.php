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
use think\contract\SessionHandlerInterface;
use think\session\Store;

/**
 * @internal
 * @coversNothing
 */
class SessionTest extends TestCase
{
    public function testSessionIdsAreDistinctAndSupportPrefixes(): void
    {
        $first = session_create_id();
        $second = session_create_id();
        $this->assertSame(1, preg_match('/\A[-,a-zA-Z0-9]+\z/', $first));
        $this->assertNotSame($first, $second);

        $prefix = 'ThinkAdmin-7,';
        $prefixed = session_create_id($prefix);
        $this->assertSame($prefix, substr($prefixed, 0, strlen($prefix)));
        $this->assertGreaterThan(strlen($prefix), strlen($prefixed));
    }

    public function testSessionCanBeCreatedResumedAndRegenerated(): void
    {
        $sessions = [];
        $handler = $this->createMock(SessionHandlerInterface::class);
        $handler->method('read')->willReturnCallback(static function (string $id) use (&$sessions): string {
            return $sessions[$id] ?? '';
        });
        $handler->method('write')->willReturnCallback(static function (string $id, string $data) use (&$sessions): bool {
            $sessions[$id] = $data;
            return true;
        });
        $handler->method('delete')->willReturnCallback(static function (string $id) use (&$sessions): bool {
            unset($sessions[$id]);
            return true;
        });

        $session = new Store('think-session', $handler);
        $session->init();
        $id = $session->getId();
        $this->assertSame(1, preg_match('/\A[a-zA-Z0-9]{32}\z/', $id));
        $session->set('compatibility', 'preserved');
        $session->save();

        $resumed = new Store('think-session', $handler);
        $resumed->setId($id);
        $resumed->init();
        $this->assertSame($id, $resumed->getId());
        $this->assertSame('preserved', $resumed->get('compatibility'));

        $resumed->regenerate(true);
        $this->assertNotSame($id, $resumed->getId());
        $this->assertSame(1, preg_match('/\A[a-zA-Z0-9]{32}\z/', $resumed->getId()));
        $resumed->save();
        $this->assertArrayNotHasKey($id, $sessions);

        $regenerated = new Store('think-session', $handler);
        $regenerated->setId($resumed->getId());
        $regenerated->init();
        $this->assertSame('preserved', $regenerated->get('compatibility'));
    }
}
