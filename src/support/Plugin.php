<?php

namespace think\admin\support;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

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

    public function postAutoloadDump(Event $event)
    {
        $root = dirname($event->getComposer()->getConfig()->get('vendor-dir'));
        file_exists($file = "{$root}/think") or copy(__DIR__ . '/command/stubs/think.stub', $file);
        $event->getComposer()->getEventDispatcher()->dispatchScript('@php think vendor:publish');
    }
}