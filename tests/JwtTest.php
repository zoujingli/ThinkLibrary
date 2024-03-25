<?php

namespace think\admin\tests;

use PHPUnit\Framework\TestCase;
use think\admin\extend\JwtExtend;

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