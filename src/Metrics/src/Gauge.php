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
        $buffer = &$this->buffer[$key][0];
        $callbackId = &$this->buffer[$key][1];
        $buffer = $value;

        if ($callbackId !== '') {
            return;
        }

        $callbackId = EventLoop::delay(self::FLUSH_TIMEOUT, function () use ($labels, &$buffer, $key) {
            $value = $buffer;
            unset($this->buffer[$key]);
            $this->messageBus->dispatch(new SetGaugeMessage($this->namespace, $this->name, $labels, $value, false));
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
        $buffer = &$this->buffer[$key][0];
        $callbackId = &$this->buffer[$key][1];
        $buffer += $value;

        if ($callbackId !== '') {
            return;
        }

        $callbackId = EventLoop::delay(self::FLUSH_TIMEOUT, function () use ($labels, &$buffer, $key) {
            $value = $buffer;
            unset($this->buffer[$key]);
            $this->messageBus->dispatch(new SetGaugeMessage($this->namespace, $this->name, $labels, $value, true));
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
