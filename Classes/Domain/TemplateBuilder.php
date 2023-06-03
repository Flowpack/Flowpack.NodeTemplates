<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class TemplateBuilder
{
    private array $configuration;

    private array $evaluationContext;

    private \Closure $configurationValueProcessor;

    private CaughtExceptions $caughtExceptions;

    /**
     * @param array $configuration
     * @param array $evaluationContext
     * @param \Closure $configurationValueProcessor
     * @param CaughtExceptions $caughtExceptions
     */
    private function __construct(
        array $configuration,
        array $evaluationContext,
        \Closure $configurationValueProcessor,
        CaughtExceptions $caughtExceptions
    ) {
        $this->configuration = $configuration;
        $this->evaluationContext = $evaluationContext;
        $this->configurationValueProcessor = $configurationValueProcessor;
        $this->caughtExceptions = $caughtExceptions;
    }

    /**
     * Creates a template tree based on the given configuration.
     */
    public static function createTemplate(
        array $configuration,
        array $evaluationContext,
        \Closure $configurationValueProcessor,
        CaughtExceptions $caughtExceptions
    ): RootTemplate {
        $builder = new self($configuration, $evaluationContext, $configurationValueProcessor, $caughtExceptions);
        $builder->validateRootLevelTemplateConfigurationKeys();
        $templates = self::createTemplatesFromBuilder($builder);
        /** @var Template[] $templateList */
        $templateList = iterator_to_array($templates, false);
        assert(\count($templateList) === 1);
        return new RootTemplate(
            $templateList[0]->getProperties(),
            $templateList[0]->getChildNodes(),
        );
    }

    private static function createTemplatesFromBuilder(self $builder): Templates
    {
        $builder = $builder->mergeContextAndWithContextConfiguration();

        if (!$builder->processConfiguration('when', true)) {
            return Templates::empty();
        }

        if (!$builder->hasConfiguration('withItems')) {
            return new Templates($builder->toTemplate());
        }

        $items = $builder->processConfiguration('withItems', []);
        if (!is_iterable($items)) {
            throw new \RuntimeException(sprintf('With items is not iterable. Configuration %s evaluated to type %s', json_encode($builder->configuration['withItems']), gettype($items)));
        }

        $templates = Templates::empty();
        foreach ($items as $itemKey => $itemValue) {
            $evaluationContextWithItem = $builder->evaluationContext;
            $evaluationContextWithItem['item'] = $itemValue;
            $evaluationContextWithItem['key'] = $itemKey;

            $itemBuilder = new self(
                $builder->configuration,
                $evaluationContextWithItem,
                $builder->configurationValueProcessor,
                $builder->caughtExceptions
            );

            $templates = $templates->withAdded($itemBuilder->toTemplate());
        }
        return $templates;
    }

    private function toTemplate(): Template
    {
        $type = $this->processConfiguration('type', null);
        $name = $this->processConfiguration('name', null);
        return new Template(
            $type ? NodeTypeName::fromString($type) : null,
            $name ? NodeName::fromString($name) : null,
            $this->processProperties(),
            $this->expandChildNodes()
        );
    }

    private function processProperties(): array
    {
        $processedProperties = [];
        foreach ($this->configuration['properties'] ?? [] as $propertyName => $value) {
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException(sprintf('Template configuration properties can only hold int|float|string|bool. Property "%s" has type "%s"', $propertyName, gettype($value)), 1685725310730);
            }
            $processedProperties[$propertyName] = ($this->configurationValueProcessor)($value, $this->evaluationContext);
        }
        return $processedProperties;
    }

    private function expandChildNodes(): Templates
    {
        if (!isset($this->configuration['childNodes'])) {
            return Templates::empty();
        }
        $templates = Templates::empty();
        foreach ($this->configuration['childNodes'] as $childNodeConfiguration) {
            $builder = new self(
                $childNodeConfiguration,
                $this->evaluationContext,
                $this->configurationValueProcessor,
                $this->caughtExceptions
            );
            $builder->validateNestedLevelTemplateConfigurationKeys();
            $templates = $templates->merge(self::createTemplatesFromBuilder($builder));
        }
        return $templates;
    }

    /** @return mixed */
    private function processConfiguration(string $configurationKey, $fallback)
    {
        if (!$this->hasConfiguration($configurationKey)) {
            return $fallback;
        }
        return ($this->configurationValueProcessor)($this->configuration[$configurationKey], $this->evaluationContext);
    }

    private function  hasConfiguration(string $configurationKey): bool
    {
        return array_key_exists($configurationKey, $this->configuration);
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
     */
    private function mergeContextAndWithContextConfiguration(): self
    {
        if (($this->configuration['withContext'] ?? []) === []) {
            return $this;
        }
        $withContext = [];
        foreach ($this->configuration['withContext'] as $key => $value) {
            $withContext[$key] = ($this->configurationValueProcessor)($value, $this->evaluationContext);
        }
        return new self(
            $this->configuration,
            array_merge($this->evaluationContext, $withContext),
            $this->configurationValueProcessor,
            $this->caughtExceptions
        );
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
