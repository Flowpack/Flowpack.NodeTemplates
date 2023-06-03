<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\Eel\CompilingEvaluator;
use Neos\Eel\ParserException;
use Neos\Eel\Utility as EelUtility;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class TemplateFactory
{
    /**
     * @Flow\Inject(lazy=false)
     * @var CompilingEvaluator
     */
    protected $eelEvaluator;

    /**
     * @Flow\InjectConfiguration(path="defaultEelContext")
     */
    protected array $defaultContextConfiguration;

    protected ?array $defaultContextVariables = null;

    public function createFromTemplateConfiguration(array $configuration, array $evaluationContext, CaughtExceptions $caughtEvaluationExceptions): RootTemplate
    {
        return TemplateBuilder::createTemplate(
            $configuration,
            $evaluationContext,
            fn ($value, $evaluationContext) => $this->preprocessConfigurationValue($value, $evaluationContext),
            $caughtEvaluationExceptions
        );
    }

    /** @return mixed */
    private function preprocessConfigurationValue($rawConfigurationValue, array $evaluationContext)
    {
        if (!is_string($rawConfigurationValue)) {
            return $rawConfigurationValue;
        }
        if (strpos($rawConfigurationValue, '${') !== 0) {
            return $rawConfigurationValue;
        }
        return $this->evaluateEelExpression($rawConfigurationValue, $evaluationContext);
    }

    /**
     * Evaluate an Eel expression.
     *
     * @param $contextVariables array<string, mixed> additional context for eel expressions
     * @return mixed The result of the evaluated Eel expression
     * @throws ParserException|\Exception
     */
    private function evaluateEelExpression(string $expression, array $contextVariables)
    {
        if ($this->defaultContextVariables === null) {
            $this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->defaultContextConfiguration);
        }
        $contextVariables = array_merge($this->defaultContextVariables, $contextVariables);
        return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, $contextVariables);
    }
}
