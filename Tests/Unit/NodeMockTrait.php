<?php

namespace Flowpack\NodeTemplates\Tests\Unit;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\SerializedPropertyValues;
use Neos\ContentRepository\Core\Infrastructure\Property\PropertyConverter;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeTags;
use Neos\ContentRepository\Core\Projection\ContentGraph\PropertyCollection;
use Neos\ContentRepository\Core\Projection\ContentGraph\Timestamps;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateClassification;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Symfony\Component\Serializer\Serializer;

trait NodeMockTrait
{
    private function createNodeMock(NodeAggregateId $nodeAggregateId = null): Node
    {
        return Node::create(
            ContentRepositoryId::fromString("cr"),
            WorkspaceName::fromString('ws'),
            DimensionSpacePoint::createWithoutDimensions(),
            $nodeAggregateId ?? NodeAggregateId::fromString("na"),
            OriginDimensionSpacePoint::createWithoutDimensions(),
            NodeAggregateClassification::CLASSIFICATION_REGULAR,
            NodeTypeName::fromString("nt"),
            new PropertyCollection(
                SerializedPropertyValues::createEmpty(),
                new PropertyConverter(new Serializer())
            ),
            NodeName::fromString("nn"),
            NodeTags::createEmpty(),
            Timestamps::create($now = new \DateTimeImmutable(), $now, null, null),
            VisibilityConstraints::withoutRestrictions(),
        );
    }
}
