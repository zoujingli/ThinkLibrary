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
use think\admin\extend\IndexNameService;
use think\admin\extend\PhinxExtend;

/**
 * @internal
 * @coversNothing
 */
class IndexNameTest extends TestCase
{
    public function testColumnFormsProduceStableNames(): void
    {
        self::assertSame(IndexNameService::generate('sample_table', 'id'), IndexNameService::generate('sample_table', ['id']));
        self::assertNotSame(IndexNameService::generate('sample_table', ['id', 'code']), IndexNameService::generate('sample_table', ['code', 'id']));
        self::assertLessThanOrEqual(64, strlen(IndexNameService::generate('sample_table', [str_repeat('column', 20)], true)));
    }

    public function testIndexSpecsAcceptStringsListsAndOptions(): void
    {
        $method = new \ReflectionMethod(PhinxExtend::class, 'parseIndexSpec');
        $method->setAccessible(true);
        foreach (['id', ['id']] as $spec) {
            self::assertSame([['id'], ['name' => IndexNameService::generate('sample_table', 'id')]], $method->invoke(null, 'sample_table', $spec));
        }
        self::assertSame([[], []], $method->invoke(null, 'sample_table', null));
        $result = $method->invoke(null, 'sample_table', ['columns' => ['tenant_id', 'code'], 'unique' => true, 'name' => 'custom_index']);
        self::assertSame([['tenant_id', 'code'], ['unique' => true, 'name' => 'custom_index']], $result);
        self::assertSame([], $method->invoke(null, 'sample_table', [])[0]);
    }

    public function testIndexLimitsAcceptScalarsAndMaps(): void
    {
        $method = new \ReflectionMethod(PhinxExtend::class, 'normalizeIndexLimits');
        $method->setAccessible(true);
        self::assertSame(['name' => 20], $method->invoke(null, ['name'], 20));
        self::assertSame(['name' => 20], $method->invoke(null, ['name'], ['name' => 20]));
        self::assertSame([], $method->invoke(null, ['name'], null));
    }
}
