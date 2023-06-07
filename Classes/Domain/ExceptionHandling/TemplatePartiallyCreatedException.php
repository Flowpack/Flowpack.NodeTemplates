<?php

namespace Flowpack\NodeTemplates\Domain\ExceptionHandling;

/**
 * Thrown in the following cases:
 * - the templateConfigurationProcessing was unsuccessful (due to an invalid EEL expression f.x)
 * - the nodeCreation was unsuccessful (f.x. due to constrains from the cr)
 */
class TemplatePartiallyCreatedException extends \DomainException
{
}
