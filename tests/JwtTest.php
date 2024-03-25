<?php

namespace think\admin\tests;

use PHPUnit\Framework\TestCase;
use think\admin\extend\JwtExtend;

class JwtTest extends TestCase
{
    public function testJwtCreateAndVerify()
    {
        $jwtkey = 'thinkadmin';
        $testdata = ['user' => 'admin', 'iss' => 'thinkadmin.top', 'exp' => time() + 30];
        $token = JwtExtend::token($testdata, $jwtkey);
        $result = JwtExtend::verify($token, $jwtkey);
        $this->assertJsonStringEqualsJsonString(json_encode($testdata), json_encode($result));
    }
}