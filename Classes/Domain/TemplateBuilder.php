<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

/** @Flow\Proxy(false) */
class TemplateBuilder
{
    /**
     * @psalm-readonly
     */
    private array $configuration;

    private array $evaluationContext;

    /**
     * @psalm-readonly
     */
    private \Closure $configurationValueProcessor;

    /**
     * @psalm-readonly
     */
    private CaughtExceptions $caughtExceptions;

    private bool $ignoreLastResultBecauseOfException = false;

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
        $templates = $builder->toTemplates();
        /** @var Template[] $templateList */
        $templateList = iterator_to_array($templates, false);
        assert(\count($templateList) === 1);
        return new RootTemplate(
            $templateList[0]->getProperties(),
            $templateList[0]->getChildNodes(),
        );
    }

    private function toTemplates(): Templates
    {
        $this->mergeContextAndWithContextConfiguration();

        if (!$this->processConfiguration('when', true)) {
            return Templates::empty();
        }

        if (!isset($this->configuration['withItems'])) {
            $templates = new Templates($this->toTemplate());
            if (!$this->ignoreLastResultBecauseOfException) {
                return $templates;
            }
            return Templates::empty();
        }

        $items = $this->processConfiguration('withItems', []);
        if (!is_iterable($items)) {
            $this->exceptionCaught(
                CaughtException::fromException(
                    new \RuntimeException(sprintf('Type %s is not iterable.', gettype($items)), 1685802354186)
                )->withCause(sprintf('Configuration %s malformed.', json_encode($this->configuration['withItems'])))
            );
        }

        if ($this->ignoreLastResultBecauseOfException) {
            return Templates::empty();
        }

        $templates = Templates::empty();
        foreach ($items as $itemKey => $itemValue) {
            $this->evaluationContext['item'] = $itemValue;
            $this->evaluationContext['key'] = $itemKey;
            $itemTemplate = $this->toTemplate();
            if (!$this->ignoreLastResultBecauseOfException) {
                $templates = $templates->withAdded($itemTemplate);
            }
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
        $previous = $this->ignoreLastResultBecauseOfException;
        $this->ignoreLastResultBecauseOfException = false;
        $processedProperties = [];
        foreach ($this->configuration['properties'] ?? [] as $propertyName => $value) {
            if (!is_scalar($value) && !is_null($value)) {
                throw new \InvalidArgumentException(sprintf('Template configuration properties can only hold int|float|string|bool|null. Property "%s" has type "%s"', $propertyName, gettype($value)), 1685725310730);
            }
            $processedValue = $this->processConfiguration(['properties', $propertyName], null);
            if (!$this->ignoreLastResultBecauseOfException) {
                $processedProperties[$propertyName] = $processedValue;
            }
            $this->ignoreLastResultBecauseOfException = false;
        }
        // we can still create the template if there has been a error when evaluating the properties.
        $this->ignoreLastResultBecauseOfException = $previous;
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
            $templates = $templates->merge($builder->toTemplates());
        }
        return $templates;
    }

    /**
     * @param string|list<string> $configurationPath
     * @param mixed $fallback
     * @return mixed
     */
    private function processConfiguration($configurationPath, $fallback)
    {
        if (($value = Arrays::getValueByPath($this->configuration, $configurationPath)) === null) {
            return $fallback;
        }
        try {
            return ($this->configurationValueProcessor)($value, $this->evaluationContext);
        } catch (\Throwable $exception) {
            $this->exceptionCaught(
                CaughtException::fromException($exception)->withCause(
                    sprintf('Expression "%s" in "%s"', $value, is_array($configurationPath) ? join('.', $configurationPath) : $configurationPath)
                )
            );
            return $fallback;
        }
    }

    private function exceptionCaught(CaughtException $caughtException): void
    {
        $this->ignoreLastResultBecauseOfException = true;
        $this->caughtExceptions->add($caughtException);
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
    private function mergeContextAndWithContextConfiguration()
    {
        if (($this->configuration['withContext'] ?? []) === []) {
            return $this;
        }
        $withContext = [];
        foreach ($this->configuration['withContext'] as $key => $value) {
            $withContext[$key] = $this->processConfiguration(['withContext', $key], null);
        }
        $this->evaluationContext = array_merge($this->evaluationContext, $withContext);
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
