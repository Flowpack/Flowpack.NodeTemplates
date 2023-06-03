<?php

namespace Flowpack\NodeTemplates\Domain;

use Flowpack\NodeTemplates\Infrastructure\EelEvaluationService;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class TemplateFactory
{
    /**
     * @Flow\Inject
     * @var EelEvaluationService
     */
    protected $eelEvaluationService;

    /**
     * @psalm-param array<string, mixed> $configuration
     * @psalm-param array<string, mixed> $evaluationContext
     * @param CaughtExceptions $caughtEvaluationExceptions
     * @return RootTemplate
     */
    public function createFromTemplateConfiguration(array $configuration, array $evaluationContext, CaughtExceptions $caughtEvaluationExceptions): RootTemplate
    {
        $builder = TemplateBuilder::createForRoot(
            $configuration,
            $evaluationContext,
            fn ($value, $evaluationContext) => $this->preprocessConfigurationValue($value, $evaluationContext),
            $caughtEvaluationExceptions
        );
        try {
            return $this->createTemplatesFromBuilder($builder)->toRootTemplate();
        } catch (StopBuildingTemplatePartException $e) {
            // should actually never be thrown uncaught in toTemplate
            return new RootTemplate([], new Templates());
        }
    }

    private function createTemplatesFromBuilder(TemplateBuilder $builder): Templates
    {
        try {
            $builder = $builder->withMergedWithContext();
            if (!$builder->processConfiguration('when', true)) {
                return Templates::empty();
            }
            if (!$builder->getRawConfiguration('withItems')) {
                return new Templates($this->createTemplateFromBuilder($builder));
            }
            $items = $builder->processConfiguration('withItems', []);
        } catch (StopBuildingTemplatePartException $e) {
            return Templates::empty();
        }

        if (!is_iterable($items)) {
            $builder->getCaughtExceptions()->add(
                CaughtException::fromException(
                    new \RuntimeException(sprintf('Type %s is not iterable.', gettype($items)), 1685802354186)
                )->withCause(sprintf('Configuration %s malformed.', json_encode($builder->getRawConfiguration('withItems'))))
            );
            return Templates::empty();
        }

        $templates = Templates::empty();
        foreach ($items as $itemKey => $itemValue) {
            $itemBuilder = $builder->withMergedEvaluationContext([
               'item' => $itemValue,
               'key' => $itemKey
            ]);

            try {
                $templates = $templates->withAdded($this->createTemplateFromBuilder($itemBuilder));
            } catch (StopBuildingTemplatePartException $e) {
            }
        }
        return $templates;
    }

    private function createTemplateFromBuilder(TemplateBuilder $builder): Template
    {
        // process the properties
        $processedProperties = [];
        foreach ($builder->getRawConfiguration('properties') ?? [] as $propertyName => $value) {
            if (!is_scalar($value) && !is_null($value)) {
                throw new \InvalidArgumentException(sprintf('Template configuration properties can only hold int|float|string|bool|null. Property "%s" has type "%s"', $propertyName, gettype($value)), 1685725310730);
            }
            try {
                $processedProperties[$propertyName] = $builder->processConfiguration(['properties', $propertyName], null);
            } catch (StopBuildingTemplatePartException $e) {
            }
        }

        // process the childNodes
        $childNodeTemplates = Templates::empty();
        foreach ($builder->getRawConfiguration('childNodes') ?? [] as $childNodeConfiguration) {
            $childNodeBuilder = $builder->withConfiguration($childNodeConfiguration);
            $childNodeTemplates = $childNodeTemplates->merge($this->createTemplatesFromBuilder($childNodeBuilder));
        }

        $type = $builder->processConfiguration('type', null);
        $name = $builder->processConfiguration('name', null);
        return new Template(
            $type ? NodeTypeName::fromString($type) : null,
            $name ? NodeName::fromString($name) : null,
            $processedProperties,
            $childNodeTemplates
        );
    }

    /**
     * @psalm-param mixed $rawConfigurationValue
     * @psalm-param array<string, mixed> $evaluationContext
     * @psalm-return mixed
     * @throws \Neos\Eel\ParserException|\Exception
     */
    private function preprocessConfigurationValue($rawConfigurationValue, array $evaluationContext)
    {
        if (!is_string($rawConfigurationValue)) {
            return $rawConfigurationValue;
        }
        if (strpos($rawConfigurationValue, '${') !== 0) {
            return $rawConfigurationValue;
        }
        return $this->eelEvaluationService->evaluateEelExpression($rawConfigurationValue, $evaluationContext);
    }
}
