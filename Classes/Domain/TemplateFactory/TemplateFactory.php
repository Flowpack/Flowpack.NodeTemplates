<?php

namespace Flowpack\NodeTemplates\Domain\TemplateFactory;

use Flowpack\NodeTemplates\Domain\CaughtException;
use Flowpack\NodeTemplates\Domain\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template;
use Flowpack\NodeTemplates\Domain\Templates;
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
        return $this->createTemplatesFromBuilder($builder)->toRootTemplate();
    }

    private function createTemplatesFromBuilder(TemplateBuilder $builder): Templates
    {
        try {
            $withContext = [];
            foreach ($builder->getRawConfiguration('withContext') ?? [] as $key => $_) {
                $withContext[$key] = $builder->processConfiguration(['withContext', $key]);
            }
            $builder = $builder->withMergedEvaluationContext($withContext);

            if ($builder->hasConfiguration('when') && !$builder->processConfiguration('when')) {
                return Templates::empty();
            }

            if (!$builder->hasConfiguration('withItems')) {
                return new Templates($this->createTemplateFromBuilder($builder));
            }
            $items = $builder->processConfiguration('withItems');

            if (!is_iterable($items)) {
                $builder->getCaughtExceptions()->add(
                    CaughtException::fromException(
                        new \RuntimeException(sprintf('Type %s is not iterable.', gettype($items)), 1685802354186)
                    )->withOrigin(sprintf('Configuration "%s" in "%s"', json_encode($builder->getRawConfiguration('withItems')), join('.', array_merge($builder->getFullPathToConfiguration(), ['withItems']))))
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
        } catch (StopBuildingTemplatePartException $e) {
            return Templates::empty();
        }
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
                $processedProperties[$propertyName] = $builder->processConfiguration(['properties', $propertyName]);
            } catch (StopBuildingTemplatePartException $e) {
            }
        }

        // process the childNodes
        $childNodeTemplates = Templates::empty();
        foreach ($builder->getRawConfiguration('childNodes') ?? [] as $childNodeConfigurationPath => $_) {
            $childNodeBuilder = $builder->withConfigurationByConfigurationPath(['childNodes', $childNodeConfigurationPath]);
            $childNodeTemplates = $childNodeTemplates->merge($this->createTemplatesFromBuilder($childNodeBuilder));
        }

        $type = $builder->processConfiguration('type');
        $name = $builder->processConfiguration('name');
        return new Template(
            $type ? NodeTypeName::fromString($type) : null,
            $name ? NodeName::fromString($name) : null,
            $builder->hasConfiguration('hidden') ? (bool)$builder->processConfiguration('hidden') : null,
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
