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
use think\admin\extend\CodeExtend;

/**
 * @internal
 * @coversNothing
 */
class CodeTest extends TestCase
{
    public function testUuidCreate()
    {
        $uuid = CodeExtend::uuid();
        $this->assertNotEmpty(preg_match('|^[a-z0-9]{8}-([a-z0-9]{4}-){3}[a-z0-9]{12}$|i', $uuid));
    }

    public function testEncode()
    {
        $value = '235215321351235123dasfdasfasdfas';
        $encode = CodeExtend::encrypt($value, 'thinkadmin');
        $this->assertEquals($value, CodeExtend::decrypt($encode, 'thinkadmin'), '验证加密解密');
    }
}
