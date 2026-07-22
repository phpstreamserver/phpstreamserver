<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Exception;

final class LabelMismatchException extends \InvalidArgumentException
{
    public function __construct(array $expectedLabels, array $providedLabels)
    {
        if ($expectedLabels === [] && $providedLabels !== []) {
            $text = \sprintf('Labels do not match: expected none, got %s', \json_encode($providedLabels));
        } elseif ($expectedLabels !== [] && $providedLabels === []) {
            $text = \sprintf('Labels do not match: expected %s, got none', \json_encode($expectedLabels));
        } else {
            $text = \sprintf('Labels do not match: expected %s, got %s', \json_encode($expectedLabels), \json_encode($providedLabels));
        }

        parent::__construct($text);
    }
}
