<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal;

use Prometheus\Storage\InMemory as PrometheusInMemory;

/**
 * @internal
 */
final class InMemory extends PrometheusInMemory
{
    public function remove(string $type, string $namespace, string $name, array $labels): void
    {
        $data = [
            'type' => $type,
            'name' => $namespace . '_' . $name,
            'labelValues' => $labels,
        ];

        $metaKey = $this->metaKey($data);
        $valueKey = $this->valueKey($data);

        if ($type === 'counter') {
            unset($this->counters[$metaKey]['samples'][$valueKey]);
        } elseif ($type === 'gauge') {
            unset($this->gauges[$metaKey]['samples'][$valueKey]);
        } elseif ($type === 'histogram') {
            unset($this->histograms[$metaKey]['samples'][$valueKey]);
        } elseif ($type === 'summary') {
            unset($this->summaries[$metaKey]['samples'][$valueKey]);
        }
    }
}
