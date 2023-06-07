<?php

namespace Flowpack\NodeTemplates\Domain\ExceptionHandling;

use Neos\Flow\Annotations as Flow;

class ExceptionHandlingConfiguration
{
    /**
     * @Flow\InjectConfiguration(package="Flowpack.NodeTemplates", path="exceptionHandling")
     */
    protected array $exceptionHandlingConfiguration;

    public function shouldStopOnExceptionAfterTemplateConfigurationProcessing(): bool
    {
        return $this->exceptionHandlingConfiguration['templateConfigurationProcessing']['stopOnException'] ?? false;
    }
}
