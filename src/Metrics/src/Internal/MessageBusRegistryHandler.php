<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal;

use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Plugin\Metrics\Internal\Message\GetMetricMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Message\GetMetricResponse;
use PHPStreamServer\Plugin\Metrics\Internal\Message\IncreaseCounterMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Message\ObserveHistorgamMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Message\ObserveSummaryMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Message\RegisterMetricMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Message\RemoveMetricMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Message\SetGaugeMessage;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricNotFoundException as PrometheusMetricNotFoundException;
use Prometheus\Exception\MetricsRegistrationException as PrometheusMetricRegistrationException;
use Prometheus\RegistryInterface as PrometheusRegistryInterface;
use Prometheus\RenderTextFormat;

use function Amp\weakClosure;

/**
 * @internal
 */
final class MessageBusRegistryHandler
{
    private InMemory $adapter;
    private PrometheusRegistryInterface $registry;

    public function __construct(MessageHandlerInterface $messageHandler)
    {
        $this->adapter = new InMemory();
        $this->registry = new CollectorRegistry($this->adapter, false);

        $messageHandler->subscribe(RegisterMetricMessage::class, weakClosure($this->registerMetric(...)));
        $messageHandler->subscribe(GetMetricMessage::class, weakClosure($this->getMetric(...)));
        $messageHandler->subscribe(IncreaseCounterMessage::class, weakClosure($this->increaseCounter(...)));
        $messageHandler->subscribe(SetGaugeMessage::class, weakClosure($this->setGauge(...)));
        $messageHandler->subscribe(ObserveHistorgamMessage::class, weakClosure($this->observeHistogram(...)));
        $messageHandler->subscribe(ObserveSummaryMessage::class, weakClosure($this->observeSummary(...)));
        $messageHandler->subscribe(RemoveMetricMessage::class, weakClosure($this->removeMetric(...)));
    }

    private function registerMetric(RegisterMetricMessage $message): bool
    {
        try {
            match ($message->type) {
                'counter' => $this->registry->registerCounter(
                    $message->namespace,
                    $message->name,
                    $message->help,
                    $message->labels,
                ),
                'gauge' => $this->registry->registerGauge(
                    $message->namespace,
                    $message->name,
                    $message->help,
                    $message->labels,
                ),
                'histogram' => $this->registry->registerHistogram(
                    $message->namespace,
                    $message->name,
                    $message->help,
                    $message->labels,
                    $message->buckets,
                ),
                'summary' => $this->registry->registerSummary(
                    $message->namespace,
                    $message->name,
                    $message->help,
                    $message->labels,
                    600,
                    $message->buckets,
                ),
            };
        } catch (PrometheusMetricRegistrationException) {
            return false;
        }

        return true;
    }

    private function getMetric(GetMetricMessage $message): GetMetricResponse|false
    {
        try {
            $metric = match ($message->type) {
                'counter' => $this->registry->getCounter($message->namespace, $message->name),
                'gauge' => $this->registry->getGauge($message->namespace, $message->name),
                'histogram' => $this->registry->getHistogram($message->namespace, $message->name),
                'summary' => $this->registry->getSummary($message->namespace, $message->name),
            };
        } catch (PrometheusMetricNotFoundException) {
            return false;
        }

        return new GetMetricResponse($message->type, $message->namespace, $message->name, $metric->getHelp(), $metric->getLabelNames());
    }

    private function removeMetric(RemoveMetricMessage $message): void
    {
        try {
            $metric = match ($message->type) {
                'counter' => $this->registry->getCounter($message->namespace, $message->name),
                'gauge' => $this->registry->getGauge($message->namespace, $message->name),
                'histogram' => $this->registry->getHistogram($message->namespace, $message->name),
                'summary' => $this->registry->getSummary($message->namespace, $message->name),
            };
        } catch (PrometheusMetricNotFoundException) {
            return;
        }

        $labels = [...\array_flip($metric->getLabelNames()), ...$message->labels];

        $this->adapter->remove($message->type, $message->namespace, $message->name, $labels);
    }

    private function increaseCounter(IncreaseCounterMessage $message): void
    {
        $counter = $this->registry->getCounter($message->namespace, $message->name);
        $labels = [...\array_flip($counter->getLabelNames()), ...$message->labels];
        $counter->incBy($message->value, $labels);
    }

    private function setGauge(SetGaugeMessage $message): void
    {
        $gauge = $this->registry->getGauge($message->namespace, $message->name);
        $labels = [...\array_flip($gauge->getLabelNames()), ...$message->labels];
        $message->increase ? $gauge->incBy($message->value, $labels) : $gauge->set($message->value, $labels);
    }

    private function observeHistogram(ObserveHistorgamMessage $message): void
    {
        $histogram = $this->registry->getHistogram($message->namespace, $message->name);
        $labels = [...\array_flip($histogram->getLabelNames()), ...$message->labels];
        foreach ($message->values as $value) {
            $histogram->observe($value, $labels);
        }
    }

    private function observeSummary(ObserveSummaryMessage $message): void
    {
        $summary = $this->registry->getSummary($message->namespace, $message->name);
        $labels = [...\array_flip($summary->getLabelNames()), ...$message->labels];
        foreach ($message->values as $value) {
            $summary->observe($value, $labels);
        }
    }

    public function render(): string
    {
        $renderer = new RenderTextFormat();

        return $renderer->render($this->registry->getMetricFamilySamples());
    }
}
