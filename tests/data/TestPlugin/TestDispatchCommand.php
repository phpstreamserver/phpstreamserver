<?php

declare(strict_types=1);

namespace PHPStreamServer\Test\data\TestPlugin;

use PHPStreamServer\Core\Console\Command;
use PHPStreamServer\Core\Console\CommandContext;
use PHPStreamServer\Core\Console\OptionDefinition;
use PHPStreamServer\Core\Console\Options;
use PHPStreamServer\Core\Internal\MessageBus\SocketFileMessageBus;
use PHPStreamServer\Core\MessageBus\MessageInterface;

use function PHPStreamServer\Core\isRunning;

final class TestDispatchCommand extends Command
{
    public static function getName(): string
    {
        return 'test-dispatch';
    }

    public static function getDescription(): string
    {
        return 'Dispatch a message for testing';
    }

    public function getOptionDefinitions(): array
    {
        return [
            new OptionDefinition('message', null, 'Base64-encoded serialization of a MessageInterface instance', requiresValue: true),
        ];
    }

    public function execute(CommandContext $context, Options $options): int
    {
        $isRunning = isRunning($context->pidFile);
        $message = (string) $options->getOption('message');
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

        $bus = new SocketFileMessageBus($context->socketFile);
        $answer = $bus->dispatch($message)->await();

        echo \serialize($answer);

        return 0;
    }
}
