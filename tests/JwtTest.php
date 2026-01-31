<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | Payment Plugin for ThinkAdmin
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
use think\admin\extend\JwtExtend;

/**
 * @internal
 * @coversNothing
 */
class JwtTest extends TestCase
{
    public function testJwtCreateAndVerify()
    {
        $jwtkey = 'thinkadmin';
        $testdata = ['user' => 'admin' . mt_rand(0, 1000), 'iss' => 'thinkadmin.top', 'exp' => time() + 30];
        $token = JwtExtend::token($testdata, $jwtkey);
        $result = JwtExtend::verify($token, $jwtkey);
        $this->assertEquals($testdata['user'], $result['user']);
    }
}
