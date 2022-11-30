<?php

namespace think\admin\support;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        echo __METHOD__ . PHP_EOL;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        echo __METHOD__ . PHP_EOL;
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        echo __METHOD__ . PHP_EOL;
    }

    public static function getSubscribedEvents(): array
    {
        echo __METHOD__ . PHP_EOL;
        return [
            'postAutoloadDump' => [
                ['autoloadDump', 0],
            ],
        ];
    }

    public function autoloadDump()
    {
        echo __METHOD__ . PHP_EOL;
    }
}