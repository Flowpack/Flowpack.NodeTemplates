<?php

namespace Flowpack\NodeTemplates\Service;

use Neos\Eel\ParserException;
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
     * @return mixed The result of the evaluated Eel expression
     * @throws \Neos\Eel\Exception
     */
    public function evaluateEelExpression(string $expression, array $contextVariables)
    {
        if ($this->defaultContextVariables === null) {
            $this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->defaultContext);
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
