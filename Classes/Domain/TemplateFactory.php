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
     * @throws EelException
     */
    private function evaluateEelExpression(string $expression, array $contextVariables)
    {
        if ($this->defaultContextVariables === null) {
            $this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->defaultContextConfiguration);
        }
        $contextVariables = array_merge($this->defaultContextVariables, $contextVariables);
        try {
            return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, $contextVariables);
        } catch (ParserException $parserException) {
            throw new EelException('EEL Expression in NodeType template could not be parsed.', 1684788574212, $parserException);
        } catch (\Exception $exception) {
            throw new EelException(sprintf('EEL Expression "%s" in NodeType template caused an error.', $expression), 1684761760723, $exception);
        }
    }
}
