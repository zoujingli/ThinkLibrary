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
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'post-autoload-dump' => [
                ['postAutoloadDump', 0],
            ],
        ];
    }

    public function postAutoloadDump()
    {
        echo __METHOD__ . PHP_EOL;
        foreach (func_get_args() as $obj) {
            var_dump(get_class($obj));
        }
    }
}