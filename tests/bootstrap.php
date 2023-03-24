<?php

use think\facade\Db;

include_once dirname(__DIR__) . '/vendor/autoload.php';
include_once dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

Db::setConfig([
    'default'     => 'mysql',
    'connections' => [
        'mysql' => [
            'type'     => 'mysql',
            'hostname' => '127.0.0.1',
            'database' => 'admin_v6',
            'username' => 'admin_v6',
            'password' => 'FbYBHcWKr2',
            'hostport' => '3306',
            'charset'  => 'utf8mb4',
            'debug'    => true,
        ],
    ],
]);