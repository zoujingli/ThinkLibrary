<?php

namespace think\admin\support;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        echo __METHOD__;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        echo __METHOD__;
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        echo __METHOD__;
    }
}