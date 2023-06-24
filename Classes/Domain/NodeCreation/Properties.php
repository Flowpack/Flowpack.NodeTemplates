<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class Properties
{
    private array $properties;

    private array $references;

    private NodeType $nodeType;

    public function __construct(array $properties, array $references, NodeType $nodeType)
    {
        $this->properties = $properties;
        $this->references = $references;
        $this->nodeType = $nodeType;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getReferences(): array
    {
        return $this->references;
    }

    public function getNodeType(): NodeType
    {
        return $this->nodeType;
    }
}
