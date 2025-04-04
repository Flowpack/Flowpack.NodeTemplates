<?php

namespace Flowpack\NodeTemplates\Domain\TemplateConfiguration;

use Neos\Eel\CompilingEvaluator;
use Neos\Eel\ParserException;
use Neos\Eel\Utility as EelUtility;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class EelEvaluationService
{
    /**
     * @Flow\Inject(lazy=false)
     * @var CompilingEvaluator
     */
    protected $eelEvaluator;

    /**
     * @Flow\InjectConfiguration(path="defaultEelContext")
     * @var array<string, string>
     */
    protected array $defaultContextConfiguration;

    /** @var array<string, mixed> */
    protected ?array $defaultContextVariables = null;

    /**
     * Evaluate an Eel expression.
     *
     * @param array<string, mixed> $contextVariables additional context for eel expressions
     * @return mixed The result of the evaluated Eel expression
     * @throws ParserException|\Exception
     */
    public function evaluateEelExpression(string $expression, array $contextVariables)
    {
        if ($this->defaultContextVariables === null) {
            $this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->defaultContextConfiguration);
        }
        $contextVariables = array_merge($this->defaultContextVariables, $contextVariables);
        return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, $contextVariables);
    }
}
