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
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/barrier') {
    $directory = $_GET['directory'];
    if (strpos($directory, sys_get_temp_dir() . '/thinkadmin-http-') !== 0 || !preg_match('/^[a-d]$/D', $_GET['self']) || !preg_match('/^[a-d]$/D', $_GET['peer'])) {
        throw new RuntimeException('Invalid fixture path');
    }
    touch($directory . '/' . $_GET['self']);
    $deadline = microtime(true) + 3;
    do {
        if (is_file($directory . '/' . $_GET['peer'])) {
            echo 'ready';
            return;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    echo 'barrier timeout';
} elseif ($path === '/slow') {
    usleep(300000);
    echo 'late';
} elseif ($path === '/zero') {
    echo '0';
} elseif ($path === '/missing') {
    http_response_code(404);
    echo 'missing';
} elseif ($path !== '/empty') {
    echo json_encode([
        'method' => $_SERVER['REQUEST_METHOD'], 'query' => $_GET, 'post' => $_POST,
        'cookies' => $_COOKIE, 'header' => $_SERVER['HTTP_X_TEST'] ?? '',
        'body' => file_get_contents('php://input'),
        'file' => isset($_FILES['file']) ? base64_encode(file_get_contents($_FILES['file']['tmp_name'])) : null,
    ]);
}
