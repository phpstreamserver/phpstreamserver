<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal;

use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Plugin\Metrics\Counter;
use PHPStreamServer\Plugin\Metrics\Exception\MetricNotFoundException;
use PHPStreamServer\Plugin\Metrics\Gauge;
use PHPStreamServer\Plugin\Metrics\Histogram;
use PHPStreamServer\Plugin\Metrics\Internal\Message\GetMetricMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Message\GetMetricResponse;
use PHPStreamServer\Plugin\Metrics\Internal\Message\RegisterMetricMessage;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use PHPStreamServer\Plugin\Metrics\Summary;

/**
 * @internal
 */
final class MessageBusRegistry implements RegistryInterface
{
    private array $map = [];

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function registerCounter(string $namespace, string $name, string $help, array $labels = []): Counter
    {
        $key = \hash('xxh128', 'counter' . $namespace . $name);
        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        $this->messageBus->dispatch(RegisterMetricMessage::counter($namespace, $name, $help, $labels))->await();

        return $this->map[$key] = new Counter($this->messageBus, $namespace, $name, $help, $labels);
    }

    public function getCounter(string $namespace, string $name): Counter
    {
        $key = \hash('xxh128', 'counter' . $namespace . $name);
        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        /** @var GetMetricResponse|false $answer */
        $answer = $this->messageBus->dispatch(GetMetricMessage::counter($namespace, $name))->await();

        if ($answer === false) {
            throw new MetricNotFoundException('counter', $namespace, $name);
        }

        return $this->map[$key] = new Counter($this->messageBus, $namespace, $name, $answer->help, $answer->labels);
    }

    public function registerGauge(string $namespace, string $name, string $help, array $labels = []): Gauge
    {
        $key = \hash('xxh128', 'gauge' . $namespace . $name);
        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        $this->messageBus->dispatch(RegisterMetricMessage::gauge($namespace, $name, $help, $labels))->await();

        return $this->map[$key] = new Gauge($this->messageBus, $namespace, $name, $help, $labels);
    }

    public function getGauge(string $namespace, string $name): Gauge
    {
        $key = \hash('xxh128', 'gauge' . $namespace . $name);
        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        /** @var GetMetricResponse|false $answer */
        $answer = $this->messageBus->dispatch(GetMetricMessage::gauge($namespace, $name))->await();

        if ($answer === false) {
            throw new MetricNotFoundException('gauge', $namespace, $name);
        }

        return $this->map[$key] = new Gauge($this->messageBus, $namespace, $name, $answer->help, $answer->labels);
    }

    public function registerHistogram(string $namespace, string $name, string $help, array $labels = [], array|null $buckets = null): Histogram
    {
        $key = \hash('xxh128', 'histogram' . $namespace . $name);
        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        $buckets ??= Histogram::defaultBuckets();
        $this->messageBus->dispatch(RegisterMetricMessage::histogram($namespace, $name, $help, $labels, $buckets))->await();

        return $this->map[$key] = new Histogram($this->messageBus, $namespace, $name, $help, $labels);
    }

    public function getHistogram(string $namespace, string $name): Histogram
    {
        $key = \hash('xxh128', 'histogram' . $namespace . $name);
        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        /** @var GetMetricResponse|false $answer */
        $answer = $this->messageBus->dispatch(GetMetricMessage::histogram($namespace, $name))->await();

        if ($answer === false) {
            throw new MetricNotFoundException('histogram', $namespace, $name);
        }

        return $this->map[$key] = new Histogram($this->messageBus, $namespace, $name, $answer->help, $answer->labels);
    }

    public function registerSummary(string $namespace, string $name, string $help, array $labels = [], array|null $quantiles = null): Summary
    {
        $key = \hash('xxh128', 'summary' . $namespace . $name);
        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        $quantiles ??= Summary::getDefaultQuantiles();
        $this->messageBus->dispatch(RegisterMetricMessage::summary($namespace, $name, $help, $labels, $quantiles))->await();

        return $this->map[$key] = new Summary($this->messageBus, $namespace, $name, $help, $labels);
    }

    public function getSummary(string $namespace, string $name): Summary
    {
        $key = \hash('xxh128', 'summary' . $namespace . $name);
        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        /** @var GetMetricResponse|false $answer */
        $answer = $this->messageBus->dispatch(GetMetricMessage::summary($namespace, $name))->await();

        if ($answer === false) {
            throw new MetricNotFoundException('summary', $namespace, $name);
        }

        return $this->map[$key] = new Summary($this->messageBus, $namespace, $name, $answer->help, $answer->labels);
    }
}
