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
use think\admin\extend\HttpExtend;
use think\admin\Storage;

/**
 * HTTP 公共入口的传输与资源兼容回归.
 * @internal
 * @coversNothing
 */
class HttpExtendTest extends TestCase
{
    private $directory;

    private $servers = [];

    private $urls = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/thinkadmin-http-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        for ($index = 0; $index < 2; ++$index) {
            $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $process = proc_open('exec ' . escapeshellarg(PHP_BINARY) . ' -S ' . escapeshellarg($address) . ' ' . escapeshellarg(__DIR__ . '/fixtures/http.php'), [
                0 => ['pipe', 'r'], 1 => ['file', $this->directory . '/server.log', 'a'], 2 => ['file', $this->directory . '/server.log', 'a'],
            ], $pipes);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $this->servers[] = $process;
            $this->urls[] = 'http://' . $address;
            $ready = false;
            for ($attempt = 0; $attempt < 100; ++$attempt) {
                $client = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
                if ($client) {
                    fclose($client);
                    $ready = true;
                    break;
                }
                usleep(20000);
            }
            $this->assertTrue($ready, 'HTTP fixture failed to start.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            proc_terminate($server);
            proc_close($server);
        }
        foreach (glob($this->directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testMultiPreservesKeysAndTransportFailures(): void
    {
        $url = 'invalid-protocol://localhost/file';
        $this->assertFalse(http_get($url));
        $this->assertSame(['broken' => false, 7 => false], http_multi([
            'broken' => ['url' => $url],
            7 => ['url' => $url, 'method' => 'POST', 'options' => ['data' => 'body']],
        ]));
        $this->assertSame([], HttpExtend::multiRequest([]));
    }

    public function testRequestsExecuteConcurrentlyAndRefillTheQueue(): void
    {
        $requests = [];
        foreach (['a', 'b', 'c', 'd'] as $index => $key) {
            $requests[$key] = ['url' => $this->urls[$index % 2] . '/barrier', 'options' => ['query' => [
                'directory' => $this->directory, 'self' => $key, 'peer' => ['b', 'a', 'd', 'c'][$index],
            ]]];
        }
        $this->assertSame(['a' => 'ready', 'b' => 'ready', 'c' => 'ready', 'd' => 'ready'], http_multi($requests, 2));
    }

    public function testSingleAndMultiPreserveRequestOptionsAndEmptyResponses(): void
    {
        $options = ['query' => ['word' => '中文'], 'data' => 'raw-body', 'cookie' => 'session=demo', 'headers' => ['X-Test: value']];
        $url = $this->urls[0] . '/echo?existing=1';
        $expected = HttpExtend::request('PUT', $url, $options);
        $response = json_decode($expected, true);
        $this->assertSame('PUT', $response['method']);
        $this->assertSame(['existing' => '1', 'word' => '中文'], $response['query']);
        $this->assertSame('raw-body', $response['body']);
        $this->assertSame('demo', $response['cookies']['session']);
        $this->assertSame('value', $response['header']);
        $this->assertSame([$expected, '', '0', 'missing'], http_multi([
            ['url' => $url, 'method' => 'PUT', 'options' => $options],
            ['url' => $this->urls[0] . '/empty'],
            ['url' => $this->urls[0] . '/zero'],
            ['url' => $this->urls[0] . '/missing'],
        ], 1));
    }

    public function testMultipartPreservesBinaryContentsAndCurlFileSupport(): void
    {
        $bytes = "binary\0\xff\r\ncontent";
        $path = $this->directory . '/payload.bin';
        file_put_contents($path, $bytes);
        $legacy = json_decode(HttpExtend::submit($this->urls[0] . '/echo', ['key' => 'one'], [
            'field' => 'file', 'name' => 'payload.bin', 'type' => 'application/octet-stream', 'content' => $bytes,
        ], [], 'POST', false), true);
        $this->assertSame(base64_encode($bytes), $legacy['file']);
        $result = http_multi([['url' => $this->urls[0] . '/echo', 'method' => 'POST', 'options' => ['data' => [
            'key' => 'one', 'file' => new \CURLFile($path, 'application/octet-stream', 'payload.bin'),
        ]]]]);
        $this->assertSame($legacy['file'], json_decode($result[0], true)['file']);
        $this->assertSame(['key' => 'one'], json_decode($result[0], true)['post']);
    }

    public function testTimeoutAndCallbackFailureDoNotBreakLaterRequests(): void
    {
        $this->assertSame([false, '0'], http_multi([
            ['url' => $this->urls[0] . '/slow', 'options' => ['setopt' => [[CURLOPT_TIMEOUT_MS, 50]]]],
            ['url' => $this->urls[1] . '/zero'],
        ]));
        $error = new \RuntimeException('callback failure');
        try {
            http_multi([['url' => $this->urls[1] . '/zero', 'options' => ['setopt' => [[CURLOPT_HEADERFUNCTION, static function () use ($error) { throw $error; }]]]]]);
            $this->fail('Expected the original callback exception.');
        } catch (\RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
        $this->assertSame(['0'], http_multi([['url' => $this->urls[1] . '/zero']]));
    }

    public function testResourceCleanupDoesNotEmitDeprecations(): void
    {
        set_error_handler(static function ($severity, $message, $file, $line) {
            if ($severity === E_DEPRECATED) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
            return false;
        });
        try {
            $this->assertSame('0', http_get($this->urls[0] . '/zero'));
            $this->assertSame(['0'], http_multi([['url' => $this->urls[0] . '/zero']]));
            $this->assertSame('', Storage::curlGet('invalid-protocol://localhost/file'));
            $this->assertSame('missing', Storage::curlGet($this->urls[0] . '/missing'));
        } finally {
            restore_error_handler();
        }
    }
}
