<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Exception;

final class LabelsNotMatchException extends \InvalidArgumentException
{
    public function __construct(array $labels, array $givenLabels)
    {
        if ($labels === [] && $givenLabels !== []) {
            $text = \sprintf('Labels do not match: expected none, %s provided', \json_encode($givenLabels));
        } elseif ($labels !== [] && $givenLabels === []) {
            $text = \sprintf('Labels do not match: expected %s, none provided', \json_encode($labels));
        } else {
            $text = \sprintf('Labels do not match: expected %s, %s provided', \json_encode($labels), \json_encode($givenLabels));
        }

        parent::__construct($text);
    }
}
