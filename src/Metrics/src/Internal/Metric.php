<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal;

use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Plugin\Metrics\Exception\LabelsNotMatchException;
use PHPStreamServer\Plugin\Metrics\Internal\Message\RemoveMetricMessage;

/**
 * @internal
 */
abstract class Metric
{
    protected const TYPE = '';
    protected const FLUSH_TIMEOUT = 0.2;

    private int $labelsCount;

    public function __construct(
        protected readonly MessageBusInterface $messageBus,
        protected readonly string $namespace,
        protected readonly string $name,
        protected readonly string $help,
        protected readonly array $labels = [],
    ) {
        $this->labelsCount = \count($this->labels);
    }

    /**
     * @param array<string, string> $labels
     */
    public function remove(array $labels = []): void
    {
        $this->checkLabels($labels);
        $this->messageBus->dispatch(new RemoveMetricMessage(static::TYPE, $this->namespace, $this->name, $labels));
    }

    /**
     * @throws LabelsNotMatchException
     */
    final protected function checkLabels(array $labels = []): void
    {
        $assignedLabels = \array_keys($labels);
        if ($this->labelsCount !== \count($assignedLabels) || \array_diff($this->labels, $assignedLabels) !== []) {
            throw new LabelsNotMatchException($this->labels, $assignedLabels);
        }
    }
}
