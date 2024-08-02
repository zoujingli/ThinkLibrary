<?php

namespace think\admin\tests;

use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    public function testInit()
    {
        $this->assertEquals(1, 1);
    }
//    public function testAlist()
//    {
//        $alist = AlistStorage::instance();
//        $alist->set('test.tt', $content = uniqid());
//        $this->assertEquals($alist->get('test.tt'), $content);
//    }
}