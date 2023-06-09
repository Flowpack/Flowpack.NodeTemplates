<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class ToBeCreatedNode
{
    public function __construct(
        public readonly ContentStreamId $contentStreamId,
        public readonly OriginDimensionSpacePoint $originDimensionSpacePoint,
        public readonly NodeAggregateId $nodeAggregateId,
        public readonly NodeType $nodeType
    ) {
    }

    public function withNodeTypeAndNodeAggregateId(NodeType $nodeType, NodeAggregateId $nodeAggregateId): self
    {
        return new self(
            $this->contentStreamId,
            $this->originDimensionSpacePoint,
            $nodeAggregateId,
            $nodeType
        );
    }
}
