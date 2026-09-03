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
use think\facade\Db;

$packageRoot = dirname(__DIR__);
$autoload = null;
foreach ([$packageRoot . '/vendor/autoload.php', dirname($packageRoot, 2) . '/vendor/autoload.php'] as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}
if ($autoload === null) {
    throw new RuntimeException('Composer autoload was not found. Run Composer install for the package or aggregate project.');
}

require_once $autoload;
require_once dirname($autoload) . '/topthink/framework/src/helper.php';

Db::setConfig([
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'type' => 'sqlite',
            'database' => ':memory:',
            'charset' => 'utf8',
            'debug' => true,
        ],
    ],
]);
