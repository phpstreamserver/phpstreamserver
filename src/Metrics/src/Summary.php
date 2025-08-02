<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics;

use PHPStreamServer\Plugin\Metrics\Exception\LabelsNotMatchException;
use PHPStreamServer\Plugin\Metrics\Internal\Message\ObserveSummaryMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Metric;
use Revolt\EventLoop;

final class Summary extends Metric
{
    protected const TYPE = 'summary';

    private array $buffer = [];

    /**
     * @param array<string, string> $labels
     * @throws LabelsNotMatchException
     */
    public function observe(float $value, array $labels = []): void
    {
        $this->checkLabels($labels);

        $key = \hash('xxh128', \json_encode($labels));
        $this->buffer[$key] ??= [[], ''];

        $bufferValue = &$this->buffer[$key][0];
        $bufferCallbackId = &$this->buffer[$key][1];
        $bufferValue[] = $value;

        if ($bufferCallbackId !== '') {
            return;
        }

        $bus = $this->messageBus;
        $buffer = &$this->buffer;
        $namespace = $this->namespace;
        $name = $this->name;

        $bufferCallbackId = EventLoop::delay(self::FLUSH_TIMEOUT, static function () use ($bus, &$buffer, $labels, &$bufferValue, $key, $namespace, $name) {
            $values = $bufferValue;
            unset($buffer[$key]);
            $bus->dispatch(new ObserveSummaryMessage($namespace, $name, $labels, $values));
        });
    }

    /**
     * Creates default quantiles.
     *
     * @return list<float>
     */
    public static function getDefaultQuantiles(): array
    {
        return [0.01, 0.05, 0.5, 0.95, 0.99];
    }
}
