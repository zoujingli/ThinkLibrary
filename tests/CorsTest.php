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
use think\admin\Library;
use think\App;
use think\Container;
use think\Request;
use think\Response;

/**
 * @internal
 * @coversNothing
 */
class CorsTest extends TestCase
{
    /**
     * @dataProvider corsProvider
     */
    public function testOptionsRespectsCorsOwnership(bool $enabled, int $status, ?string $origin, bool $custom, string $requestOrigin): void
    {
        $previous = Container::getInstance();
        try {
            $app = new class(sys_get_temp_dir() . '/thinkadmin-cors-fixture-' . bin2hex(random_bytes(8))) extends App {
                public function runningInConsole(): bool
                {
                    return false;
                }
            };
            $app->config->set(['cors_on' => $enabled, 'cors_host' => ['client.example']], 'app');
            $request = (new Request())->withGet(['not_init_session' => 1])->withServer([
                'REQUEST_METHOD' => 'OPTIONS',
            ])->withHeader(['origin' => $requestOrigin]);
            $app->instance('request', $request);
            (new Library($app))->register();
            $app->middleware->add(static function () use ($request): Response {
                return Response::create('custom preflight', 'html', 200)->header([
                    'Access-Control-Allow-Origin' => $request->header('origin'),
                    'X-Custom-Cors' => 'handled',
                ]);
            });
            $response = $app->middleware->pipeline()->send($request)->then(static function (): Response {
                return Response::create('unhandled', 'html', 500);
            });
            $this->assertSame($status, $response->getCode());
            $this->assertSame($origin, $response->getHeader('Access-Control-Allow-Origin'));
            $this->assertSame($custom ? 'handled' : null, $response->getHeader('X-Custom-Cors'));
            $this->assertSame('sameorigin', $response->getHeader('X-Frame-Options'));
        } finally {
            Container::setInstance($previous);
        }
    }

    public static function corsProvider(): array
    {
        return [
            'built in CORS enabled' => [true, 204, 'https://client.example', false, 'https://client.example'],
            'built in CORS rejects unlisted origin' => [true, 204, null, false, 'https://unlisted.example'],
            'custom CORS after disabling built in CORS' => [false, 200, 'https://client.example', true, 'https://client.example'],
        ];
    }
}
