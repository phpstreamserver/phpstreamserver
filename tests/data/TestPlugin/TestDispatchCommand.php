<?php

declare(strict_types=1);

namespace PHPStreamServer\Test\data\TestPlugin;

use PHPStreamServer\Core\Console\Command;
use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPStreamServer\Core\MessageBus\SocketFileMessageBus;

use function PHPStreamServer\Core\isRunning;

final class TestDispatchCommand extends Command
{
    public static function getName(): string
    {
        return 'test-dispatch';
    }

    public static function getDescription(): string
    {
        return 'For testing purposes';
    }

    public function configure(): void
    {
        $this->addOptionDefinition('message', null, 'Serialized base64 string with MessageInterface instance');
    }

    public function execute(string $pidFile, string $socketFile): int
    {
        $isRunning = isRunning($pidFile);
        $message = (string) $this->getOption('message');
        $message = \base64_decode($message, true);

        \set_error_handler(static fn(): true => true);
        $message = \unserialize($message);
        \restore_error_handler();

        if (!$isRunning) {
            echo \serialize(null);
            return 1;
        }

        if (!$message instanceof MessageInterface) {
            echo \serialize(null);
            return 2;
        }

        $bus = new SocketFileMessageBus($socketFile);
        $answer = $bus->dispatch($message)->await();

        echo \serialize($answer);

        return 0;
    }
}
