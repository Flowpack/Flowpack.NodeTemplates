<?php

namespace Flowpack\NodeTemplates\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Eel\Utility as EelUtility;
use Neos\Eel\CompilingEvaluator;

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
     * @var array
     */
    protected $defaultContext;

    /**
     * @var array
     */
    protected $defaultContextVariables;

    /**
     * Evaluate an Eel expression.
     *
     * @param string $expression The Eel expression to evaluate
     * @param array $contextVariables
     * @return mixed The result of the evaluated Eel expression
     * @throws \Neos\Eel\Exception
     */
    public function evaluateEelExpression($expression, $contextVariables)
    {
        if ($this->defaultContextVariables === null) {
            $this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->defaultContext);
        }
        $contextVariables = array_merge($this->defaultContextVariables, $contextVariables);
        return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, $contextVariables);
    }

}