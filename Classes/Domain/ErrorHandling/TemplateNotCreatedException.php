<?php

namespace Flowpack\NodeTemplates\Domain\ErrorHandling;

/**
 * Logged if the templateConfigurationProcessing was unsuccessful (due to an invalid EEL expression f.x),
 * and the {@see ErrorHandlingConfiguration} is configured not to continue
 */
class TemplateNotCreatedException extends \DomainException
{
}
