<?php

namespace Flowpack\NodeTemplates\Domain\TemplateConfiguration;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingError;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Neos\Flow\Annotations as Flow;

/**
 * @internal implementation detail of {@see TemplateConfigurationProcessor}
 * @psalm-immutable
 * @Flow\Proxy(false)
 */
class TemplatePart
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
    private ProcessingErrors $processingErrors;

    /**
     * @psalm-param array<string, mixed> $configuration
     * @psalm-param array<string, mixed> $evaluationContext
     * @psalm-param \Closure(mixed $value, array<string, mixed> $evaluationContext): mixed $configurationValueProcessor
     * @throws StopBuildingTemplatePartException
     */
    private function __construct(
        array $configuration,
        array $fullPathToConfiguration,
        array $evaluationContext,
        \Closure $configurationValueProcessor,
        ProcessingErrors $processingErrors
    ) {
        $this->configuration = $configuration;
        $this->fullPathToConfiguration = $fullPathToConfiguration;
        $this->evaluationContext = $evaluationContext;
        $this->configurationValueProcessor = $configurationValueProcessor;
        $this->processingErrors = $processingErrors;
        $this->validateTemplateConfigurationKeys();
    }

    /**
     * @psalm-param array<string, mixed> $configuration
     * @psalm-param array<string, mixed> $evaluationContext
     * @psalm-param \Closure(mixed $value, array<string, mixed> $evaluationContext): mixed $configurationValueProcessor
     * @throws StopBuildingTemplatePartException
     */
    public static function createRoot(
        array $configuration,
        array $evaluationContext,
        \Closure $configurationValueProcessor,
        ProcessingErrors $processingErrors
    ): self {
        return new self(
            $configuration,
            [],
            $evaluationContext,
            $configurationValueProcessor,
            $processingErrors
        );
    }

    /**
     * @param string|list<string|int> $configurationPath
     */
    public function addProcessingErrorForPath(\Throwable $throwable, $configurationPath): void
    {
        $this->processingErrors->add(
            ProcessingError::fromException(
                $throwable
            )->withOrigin(sprintf(
                'Configuration "%s"',
                join('.', array_merge($this->getFullPathToConfiguration(), is_array($configurationPath) ? $configurationPath : [$configurationPath]))
            ))
        );
    }

    public function getFullPathToConfiguration(): array
    {
        return $this->fullPathToConfiguration;
    }

    /**
     * @psalm-param string|list<string> $configurationPath
     * @throws StopBuildingTemplatePartException
     */
    public function withConfigurationByConfigurationPath($configurationPath): self
    {
        return new self(
            $this->getRawConfiguration($configurationPath),
            array_merge($this->fullPathToConfiguration, $configurationPath),
            $this->evaluationContext,
            $this->configurationValueProcessor,
            $this->processingErrors
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
            $this->processingErrors
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
            $this->processingErrors->add(
                ProcessingError::fromException($exception)->withOrigin(
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
     * @param string|list<string> $configurationPath
     */
    public function getRawConfiguration($configurationPath)
    {
        /** @phpstan-ignore-next-line */
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
        /** @phpstan-ignore-next-line */
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

    /**
     * @throws StopBuildingTemplatePartException
     */
    private function validateTemplateConfigurationKeys(): void
    {
        $isRootTemplate = $this->fullPathToConfiguration === [];
        foreach (array_keys($this->configuration) as $key) {
            if (!in_array($key, ['type', 'name', 'properties', 'childNodes', 'when', 'withItems', 'withContext'], true)) {
                $this->addProcessingErrorForPath(
                    new \InvalidArgumentException(sprintf('Template configuration has illegal key "%s"', $key), 1686150349274),
                    $key
                );
                throw new StopBuildingTemplatePartException();
            }
            if ($isRootTemplate) {
                if (!in_array($key, ['properties', 'childNodes', 'when', 'withContext'], true)) {
                    $this->addProcessingErrorForPath(
                        new \InvalidArgumentException(sprintf('Root template configuration doesnt allow option "%s', $key), 1686150340657),
                        $key
                    );
                    throw new StopBuildingTemplatePartException();
                }
            }
        }
    }
}
