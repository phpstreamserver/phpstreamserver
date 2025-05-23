<?php

declare(strict_types=1);

namespace PHPStreamServer\Core\Console;

use PHPStreamServer\Core\Internal\Console\OptionDefinition;

final class Options
{
    private array $parsedOptions;

    /**
     * @var array<OptionDefinition>
     */
    private array $defaultOptionDefinitions = [];

    /**
     * @var array<OptionDefinition>
     */
    private array $optionDefinitions = [];

    /**
     * @param list<string> $argv
     * @param array<OptionDefinition> $defaultOptionDefinitions
     */
    public function __construct(array $argv, array $defaultOptionDefinitions = [])
    {
        $this->parsedOptions = $this->parseArgvs($argv);
        foreach ($defaultOptionDefinitions as $defaultOptionDefinition) {
            $this->defaultOptionDefinitions[$defaultOptionDefinition->name] = $defaultOptionDefinition;
        }
    }

    private function parseArgvs(array $argv): array
    {
        $options = [];
        for ($i = 0; $i < \count($argv); $i++) {
            if (\str_starts_with($argv[$i], '--')) {
                $optionParts = \explode('=', \substr($argv[$i], 2), 2);
                $options[$optionParts[0]] = $optionParts[1] ?? true;
            } elseif (\str_starts_with($argv[$i], '-')) {
                $splitOtions = \str_split(\substr($argv[$i], 1));
                foreach ($splitOtions as $option) {
                    $options[$option] = true;
                    if (isset($argv[$i + 1]) && !\str_starts_with($argv[$i + 1], '-') && \count($splitOtions) === 1) {
                        $options[$option] = $argv[++$i];
                    }
                }
            }
        }
        return $options;
    }

    public function addOptionDefinition(string $name, string|null $shortcut = null, string $description = '', string|null $default = null): void
    {
        $this->optionDefinitions[$name] = new OptionDefinition($name, $shortcut, $description, $default);
    }

    /**
     * @return array<OptionDefinition>
     */
    public function getOptionDefinitions(): array
    {
        return [...$this->optionDefinitions, ...$this->defaultOptionDefinitions];
    }

    public function hasOption(string $name): bool
    {
        $definition = $this->getOptionDefinitions()[$name] ?? null;
        $fullName = $definition?->name;
        $shortName = $definition?->shortName;

        return ($fullName !== null && \array_key_exists($fullName, $this->parsedOptions))
            || ($shortName !== null && \array_key_exists($shortName, $this->parsedOptions));
    }

    public function getOption(string $name): string|true|null
    {
        $definition = $this->getOptionDefinitions()[$name] ?? null;
        $fullName = $definition?->name;
        $shortName = $definition?->shortName;
        $default = $definition?->default;

        return $this->parsedOptions[$fullName] ?? $this->parsedOptions[$shortName] ?? $default;
    }
}
