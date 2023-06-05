<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * @internal implementation detail of {@see TemplateFactory}
 * @psalm-immutable
 * @Flow\Proxy(false)
 */
class TemplateBuilder
{
    /**
     * @psalm-readonly
     */
    private array $configuration;

    /**
     * @psalm-readonly
     */
    private array $fullPathToConfiguration;

    /**
     * @psalm-readonly
     */
    private array $evaluationContext;

    /**
     * @psalm-readonly
     * @psalm-var \Closure(mixed $value, array<string, mixed> $evaluationContext): mixed
     */
    private \Closure $configurationValueProcessor;

    /**
     * @psalm-readonly
     */
    private CaughtExceptions $caughtExceptions;

    /**
     * @psalm-param array<string, mixed> $configuration
     * @psalm-param array<string, mixed> $evaluationContext
     * @psalm-param \Closure(mixed $value, array<string, mixed> $evaluationContext): mixed $configurationValueProcessor
     */
    private function __construct(
        array $configuration,
        array $fullPathToConfiguration,
        array $evaluationContext,
        \Closure $configurationValueProcessor,
        CaughtExceptions $caughtExceptions
    ) {
        $this->configuration = $configuration;
        $this->fullPathToConfiguration = $fullPathToConfiguration;
        $this->evaluationContext = $evaluationContext;
        $this->configurationValueProcessor = $configurationValueProcessor;
        $this->caughtExceptions = $caughtExceptions;
        $this->validateTemplateConfigurationKeys();
    }

    /**
     * @psalm-param array<string, mixed> $configuration
     * @psalm-param array<string, mixed> $evaluationContext
     * @psalm-param \Closure(mixed $value, array<string, mixed> $evaluationContext): mixed $configurationValueProcessor
     */
    public static function createForRoot(
        array $configuration,
        array $evaluationContext,
        \Closure $configurationValueProcessor,
        CaughtExceptions $caughtExceptions
    ): self {
        return new self(
            $configuration,
            [],
            $evaluationContext,
            $configurationValueProcessor,
            $caughtExceptions
        );
    }

    public function getCaughtExceptions(): CaughtExceptions
    {
        return $this->caughtExceptions;
    }

    public function getFullPathToConfiguration(): array
    {
        return $this->fullPathToConfiguration;
    }

    /**
     * @psalm-param string|list<string> $configurationPath
     */
    public function withConfigurationByConfigurationPath($configurationPath): self
    {
        return new self(
            $this->getRawConfiguration($configurationPath),
            array_merge($this->fullPathToConfiguration, $configurationPath),
            $this->evaluationContext,
            $this->configurationValueProcessor,
            $this->caughtExceptions
        );
    }

    /**
     * @psalm-param array<string, mixed> $evaluationContext
     */
    public function withMergedEvaluationContext(array $evaluationContext): self
    {
        if ($evaluationContext === []) {
            return $this;
        }
        return new self(
            $this->configuration,
            $this->fullPathToConfiguration,
            array_merge($this->evaluationContext, $evaluationContext),
            $this->configurationValueProcessor,
            $this->caughtExceptions
        );
    }

    /**
     * @psalm-param string|list<string> $configurationPath
     * @return mixed
     * @throws StopBuildingTemplatePartException
     */
    public function processConfiguration($configurationPath)
    {
        if (($value = $this->getRawConfiguration($configurationPath)) === null) {
            return null;
        }
        try {
            return ($this->configurationValueProcessor)($value, $this->evaluationContext);
        } catch (\Throwable $exception) {
            $fullConfigurationPath = array_merge(
                $this->fullPathToConfiguration,
                is_array($configurationPath) ? $configurationPath : [$configurationPath]
            );
            $this->caughtExceptions->add(
                CaughtException::fromException($exception)->withOrigin(
                    sprintf(
                        'Expression "%s" in "%s"',
                        $value,
                        join('.', $fullConfigurationPath)
                    )
                )
            );
            throw new StopBuildingTemplatePartException();
        }
    }

    /**
     * Minimal implementation of {@see \Neos\Utility\Arrays::getValueByPath()} (but we dont allow $configurationPath to contain dots.)
     *
     * @psalm-param string|list<string> $configurationPath
     */
    public function getRawConfiguration($configurationPath)
    {
        assert(is_array($configurationPath) || is_string($configurationPath));
        $path = is_array($configurationPath) ? $configurationPath : [$configurationPath];
        $array = $this->configuration;
        foreach ($path as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return null;
            }
        }
        return $array;
    }

    /**
     * @psalm-param string|list<string> $configurationPath
     */
    public function hasConfiguration($configurationPath): bool
    {
        assert(is_array($configurationPath) || is_string($configurationPath));
        $path = is_array($configurationPath) ? $configurationPath : [$configurationPath];
        $array = $this->configuration;
        foreach ($path as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return false;
            }
        }
        return true;
    }

    private function validateTemplateConfigurationKeys(): void
    {
        $isRootTemplate = $this->fullPathToConfiguration === [];
        foreach (array_keys($this->configuration) as $key) {
            if (!in_array($key, ['type', 'name', 'hidden', 'properties', 'childNodes', 'when', 'withItems', 'withContext'], true)) {
                throw new \InvalidArgumentException(sprintf('Template configuration has illegal key "%s"', $key));
            }
            if ($isRootTemplate) {
                if (!in_array($key, ['hidden', 'properties', 'childNodes', 'when', 'withContext'], true)) {
                    throw new \InvalidArgumentException(sprintf('Root template configuration doesnt allow option "%s', $key));
                }
            }
        }
    }
}
