<?php

namespace think\admin\tests;

use PHPUnit\Framework\TestCase;
use think\admin\storage\AlistStorage;

class StorageTest extends TestCase
{
    public function testAlist()
    {
        $alist = AlistStorage::instance();
        $alist->set('test.tt', $content = uniqid());
        $this->assertEquals($alist->get('test.tt'), $content);
    }
}