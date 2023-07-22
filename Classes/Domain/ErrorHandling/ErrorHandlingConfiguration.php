<?php

namespace Flowpack\NodeTemplates\Domain\ErrorHandling;

use Neos\Flow\Annotations as Flow;

class ErrorHandlingConfiguration
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
