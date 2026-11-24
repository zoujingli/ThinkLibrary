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
use think\admin\Controller;
use think\admin\Library;
use think\App;
use think\Container;
use think\exception\HttpResponseException;
use think\Request;

/**
 * @internal
 * @coversNothing
 */
class ControllerTest extends TestCase
{
    public function testBuiltInActionsAreRejectedRegardlessOfCase(): void
    {
        $previousContainer = Container::getInstance();
        $previousApp = Library::$sapp;

        try {
            foreach (['fetch', 'Fetch', 'FETCH'] as $action) {
                $app = new App(sys_get_temp_dir() . '/thinkadmin-controller-' . bin2hex(random_bytes(8)));
                $app->instance('request', (new Request())->setAction($action));
                Library::$sapp = $app;

                try {
                    new Controller($app);
                    self::fail(sprintf('Action "%s" bypassed the built-in method guard.', $action));
                } catch (HttpResponseException $exception) {
                    self::assertStringContainsString('禁止访问内置方法！', $exception->getResponse()->getContent());
                }
            }
        } finally {
            Library::$sapp = $previousApp;
            Container::setInstance($previousContainer);
        }
    }
}
