<?php

namespace think\admin\tests;

use PHPUnit\Framework\TestCase;
use think\admin\extend\CodeExtend;

class CodeTest extends TestCase
{
    public function testUuidCreate()
    {
        $uuid = CodeExtend::uuid();
        $this->assertNotEmpty(preg_match('|^[a-z0-9]{8}-([a-z0-9]{4}-){3}[a-z0-9]{12}$|i', $uuid));
    }
}