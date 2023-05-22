<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\NodeTemplateDumper;

class Comment
{
    private \Closure $renderFunction;

    public function __construct(\Closure $renderFunction)
    {
        $this->renderFunction = $renderFunction;
    }

    public function toYamlComment(string $indentation, string $propertyName): string
    {
        return ($this->renderFunction)($indentation, $propertyName);
    }
}
