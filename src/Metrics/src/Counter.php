<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics;

use PHPStreamServer\Plugin\Metrics\Exception\LabelsNotMatchException;
use PHPStreamServer\Plugin\Metrics\Internal\Message\IncreaseCounterMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Metric;
use Revolt\EventLoop;

final class Counter extends Metric
{
    protected const TYPE = 'counter';

    private array $buffer = [];

    /**
     * @param array<string, string> $labels
     * @throws LabelsNotMatchException
     */
    public function inc(array $labels = []): void
    {
        $this->add(1, $labels);
    }

    /**
     * @param array<string, string> $labels
     * @throws LabelsNotMatchException
     */
    public function add(int $value, array $labels = []): void
    {
        $this->checkLabels($labels);

        $key = \hash('xxh128', \json_encode($labels));
        $this->buffer[$key] ??= [0, ''];

        $bufferValue = &$this->buffer[$key][0];
        $bufferCallbackId = &$this->buffer[$key][1];
        $bufferValue += $value;

        if ($bufferCallbackId !== '') {
            return;
        }

        $bus = $this->messageBus;
        $buffer = &$this->buffer;
        $namespace = $this->namespace;
        $name = $this->name;

        $bufferCallbackId = EventLoop::delay(self::FLUSH_TIMEOUT, static function () use ($bus, &$buffer, $labels, &$bufferValue, $key, $namespace, $name) {
            $value = $bufferValue;
            unset($buffer[$key]);
            $bus->dispatch(new IncreaseCounterMessage($namespace, $name, $labels, $value));
        });
    }
}
