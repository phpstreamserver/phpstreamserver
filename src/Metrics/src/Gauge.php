<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics;

use PHPStreamServer\Plugin\Metrics\Exception\LabelsNotMatchException;
use PHPStreamServer\Plugin\Metrics\Internal\Message\SetGaugeMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Metric;
use Revolt\EventLoop;

final class Gauge extends Metric
{
    protected const TYPE = 'gauge';

    /**
     * @var array<string, array{0: float, 1: string}>
     */
    private array $buffer = [];

    /**
     * @param array<string, string> $labels
     * @throws LabelsNotMatchException
     */
    public function set(float $value, array $labels = []): void
    {
        $this->checkLabels($labels);

        $key = \hash('xxh128', \json_encode($labels) . 'set');
        $this->buffer[$key] ??= [0, ''];

        $bufferValue = &$this->buffer[$key][0];
        $bufferCallbackId = &$this->buffer[$key][1];
        $bufferValue = $value;

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
            $bus->dispatch(new SetGaugeMessage($namespace, $name, $labels, $value, false));
        });
    }

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
    public function dec(array $labels = []): void
    {
        $this->add(-1, $labels);
    }

    /**
     * @param array<string, string> $labels
     * @throws LabelsNotMatchException
     */
    public function add(float $value, array $labels = []): void
    {
        $this->checkLabels($labels);

        $key = \hash('xxh128', \json_encode($labels) . 'add');
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
            $bus->dispatch(new SetGaugeMessage($namespace, $name, $labels, $value, true));
        });
    }

    /**
     * @param array<string, string> $labels
     * @throws LabelsNotMatchException
     */
    public function sub(float $value, array $labels = []): void
    {
        $this->add(-$value, $labels);
    }
}
