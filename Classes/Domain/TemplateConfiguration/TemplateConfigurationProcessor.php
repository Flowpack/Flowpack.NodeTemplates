<?php

namespace Flowpack\NodeTemplates\Domain\TemplateConfiguration;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template\Template;
use Flowpack\NodeTemplates\Domain\Template\Templates;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class TemplateConfigurationProcessor
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
    public function processTemplateConfiguration(array $configuration, array $evaluationContext, CaughtExceptions $caughtEvaluationExceptions): RootTemplate
    {
        try {
            $templatePart = TemplatePart::createRoot(
                $configuration,
                $evaluationContext,
                fn ($value, $evaluationContext) => $this->preprocessConfigurationValue($value, $evaluationContext),
                $caughtEvaluationExceptions
            );
        } catch (StopBuildingTemplatePartException $e) {
            return RootTemplate::empty();
        }
        return $this->createTemplatesFromTemplatePart($templatePart)->toRootTemplate();
    }

    private function createTemplatesFromTemplatePart(TemplatePart $templatePart): Templates
    {
        try {
            $withContext = [];
            foreach ($templatePart->getRawConfiguration('withContext') ?? [] as $key => $_) {
                $withContext[$key] = $templatePart->processConfiguration(['withContext', $key]);
            }
            $templatePart = $templatePart->withMergedEvaluationContext($withContext);

            if ($templatePart->hasConfiguration('when') && !$templatePart->processConfiguration('when')) {
                return Templates::empty();
            }

            if (!$templatePart->hasConfiguration('withItems')) {
                return new Templates($this->createTemplateFromTemplatePart($templatePart));
            }
            $items = $templatePart->processConfiguration('withItems');

            if (!is_iterable($items)) {
                $templatePart->getCaughtExceptions()->add(
                    CaughtException::fromException(
                        new \RuntimeException(sprintf('Type %s is not iterable.', gettype($items)), 1685802354186)
                    )->withOrigin(sprintf('Configuration "%s" in "%s"', json_encode($templatePart->getRawConfiguration('withItems')), join('.', array_merge($templatePart->getFullPathToConfiguration(), ['withItems']))))
                );
                return Templates::empty();
            }

            $templates = Templates::empty();
            foreach ($items as $itemKey => $itemValue) {
                $itemTemplatePart = $templatePart->withMergedEvaluationContext([
                    'item' => $itemValue,
                    'key' => $itemKey
                ]);

                try {
                    $templates = $templates->withAdded($this->createTemplateFromTemplatePart($itemTemplatePart));
                } catch (StopBuildingTemplatePartException $e) {
                }
            }
            return $templates;
        } catch (StopBuildingTemplatePartException $e) {
            return Templates::empty();
        }
    }

    private function createTemplateFromTemplatePart(TemplatePart $templatePart): Template
    {
        // process the properties
        $processedProperties = [];
        foreach ($templatePart->getRawConfiguration('properties') ?? [] as $propertyName => $value) {
            if (!is_scalar($value) && !is_null($value)) {
                $templatePart->getCaughtExceptions()->add(CaughtException::fromException(
                    new \RuntimeException(sprintf('Template configuration properties can only hold int|float|string|bool|null. Property "%s" has type "%s"', $propertyName, gettype($value)), 1685725310730)
                ));
                continue;
            }
            try {
                $processedProperties[$propertyName] = $templatePart->processConfiguration(['properties', $propertyName]);
            } catch (StopBuildingTemplatePartException $e) {
            }
        }

        // process the childNodes
        $childNodeTemplates = Templates::empty();
        foreach ($templatePart->getRawConfiguration('childNodes') ?? [] as $childNodeConfigurationPath => $_) {
            try {
                $childNodeTemplatePart = $templatePart->withConfigurationByConfigurationPath(['childNodes', $childNodeConfigurationPath]);
            } catch (StopBuildingTemplatePartException $e) {
                continue;
            }
            $childNodeTemplates = $childNodeTemplates->merge($this->createTemplatesFromTemplatePart($childNodeTemplatePart));
        }

        $type = $templatePart->processConfiguration('type');
        $name = $templatePart->processConfiguration('name');
        return new Template(
            $type !== null ? NodeTypeName::fromString($type) : null,
            $name !== null ? NodeName::transliterateFromString($name) : null,
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
