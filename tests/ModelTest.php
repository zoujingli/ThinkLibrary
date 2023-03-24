<?php

namespace think\admin\tests;

use PHPUnit\Framework\TestCase;
use think\admin\model\SystemUser;

class ModelTest extends TestCase
{
    public function testVirtualModel()
    {
        $this->assertEquals(m('SystemUser')->getTable(), SystemUser::mk()->getTable(), '动态模型测试');
    }
}