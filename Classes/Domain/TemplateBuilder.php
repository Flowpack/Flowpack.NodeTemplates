<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

/**
 * @internal implementation detail of {@see TemplateFactory}
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
    private array $evaluationContext;

    /**
     * @psalm-readonly
     */
    private \Closure $configurationValueProcessor;

    /**
     * @psalm-readonly
     */
    private CaughtExceptions $caughtExceptions;

    private function __construct(
        array $configuration,
        array $evaluationContext,
        \Closure $configurationValueProcessor,
        CaughtExceptions $caughtExceptions,
    ) {
        $this->configuration = $configuration;
        $this->evaluationContext = $evaluationContext;
        $this->configurationValueProcessor = $configurationValueProcessor;
        $this->caughtExceptions = $caughtExceptions;
        $this->validateNestedLevelTemplateConfigurationKeys();
    }

    public static function createForRoot(
        array $configuration,
        array $evaluationContext,
        \Closure $configurationValueProcessor,
        CaughtExceptions $caughtExceptions,
    ): self {
        $builder = new self(
            $configuration,
            $evaluationContext,
            $configurationValueProcessor,
            $caughtExceptions
        );
        $builder->validateRootLevelTemplateConfigurationKeys();
        return $builder;
    }

    public function getCaughtExceptions(): CaughtExceptions
    {
        return $this->caughtExceptions;
    }

    public function withConfiguration(array $configuration): self
    {
        return new self(
            $configuration,
            $this->evaluationContext,
            $this->configurationValueProcessor,
            $this->caughtExceptions
        );
    }

    public function withMergedEvaluationContext(array $evaluationContext): self
    {
        return new self(
            $this->configuration,
            array_merge($this->evaluationContext, $evaluationContext),
            $this->configurationValueProcessor,
            $this->caughtExceptions
        );
    }

    /**
     * @param string|list<string> $configurationPath
     * @param mixed $fallback
     * @return mixed
     * @throws StopBuildingTemplatePartException
     */
    public function processConfiguration($configurationPath, $fallback)
    {
        if (($value = $this->getRawConfiguration($configurationPath)) === null) {
            return $fallback;
        }
        try {
            return ($this->configurationValueProcessor)($value, $this->evaluationContext);
        } catch (\Throwable $exception) {
            $this->caughtExceptions->add(
                CaughtException::fromException($exception)->withCause(
                    sprintf('Expression "%s" in "%s"', $value, is_array($configurationPath) ? join('.', $configurationPath) : $configurationPath)
                )
            );
            throw new StopBuildingTemplatePartException();
        }
    }

    public function getRawConfiguration($configurationPath)
    {
        return Arrays::getValueByPath($this->configuration, $configurationPath);
    }

    /**
     * Merge `withContext` onto the current $evaluationContext, evaluating EEL if necessary and return a new Builder
     *
     * The option `withContext` takes an array of items whose value can be any yaml/php type
     * and might also contain eel expressions
     *
     * ```yaml
     * withContext:
     *   someText: '<p>foo</p>'
     *   processedData: "${String.trim(data.bla)}"
     *   booleanType: true
     *   arrayType: ["value"]
     * ```
     *
     * scopes and order of evaluation:
     *
     * - inside `withContext` the "upper" context may be accessed in eel expressions,
     * but sibling context values are not available
     *
     * - `withContext` is evaluated before `when` and `withItems` so you can access computed values,
     * that means the context `item` from `withItems` will not be available yet
     *
     * @throws StopBuildingTemplatePartException
     */
    public function withMergedWithContext(): self
    {
        if (($this->configuration['withContext'] ?? []) === []) {
            return $this;
        }
        $withContext = [];
        foreach ($this->configuration['withContext'] as $key => $value) {
            $withContext[$key] = $this->processConfiguration(['withContext', $key], null);
        }
        return $this->withMergedEvaluationContext($withContext);
    }

    private function validateNestedLevelTemplateConfigurationKeys(): void
    {
        foreach (array_keys($this->configuration) as $key) {
            if (!in_array($key, ['type', 'name', 'properties', 'childNodes', 'when', 'withItems', 'withContext'], true)) {
                throw new \InvalidArgumentException(sprintf('Template configuration has illegal key "%s', $key));
            }
        }
    }

    private function validateRootLevelTemplateConfigurationKeys(): void
    {
        foreach (array_keys($this->configuration) as $key) {
            if (!in_array($key, ['properties', 'childNodes', 'when', 'withContext'], true)) {
                throw new \InvalidArgumentException(sprintf('Root template configuration has illegal key "%s', $key));
            }
        }
    }
}
