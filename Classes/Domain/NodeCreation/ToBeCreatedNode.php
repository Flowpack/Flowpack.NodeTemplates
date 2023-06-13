<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class ToBeCreatedNode
{
    private NodeType $nodeType;

    public function __construct(
        NodeType $nodeType
    ) {
        $this->nodeType = $nodeType;
    }

    public function getNodeType(): NodeType
    {
        return $this->nodeType;
    }
}
