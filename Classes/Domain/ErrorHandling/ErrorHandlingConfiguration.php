<?php

namespace Flowpack\NodeTemplates\Domain\ErrorHandling;

use Neos\Flow\Annotations as Flow;

class ErrorHandlingConfiguration
{
    /**
     * @Flow\InjectConfiguration(package="Flowpack.NodeTemplates", path="errorHandling")
     * @var array<mixed>
     */
    protected array $configuration;

    public function shouldStopOnExceptionAfterTemplateConfigurationProcessing(): bool
    {
        return $this->configuration['templateConfigurationProcessing']['stopOnException'] ?? false;
    }
}
