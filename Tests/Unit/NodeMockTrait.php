<?php

namespace Flowpack\NodeTemplates\Tests\Unit;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\SerializedPropertyValues;
use Neos\ContentRepository\Core\Infrastructure\Property\PropertyConverter;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphIdentity;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\PropertyCollection;
use Neos\ContentRepository\Core\Projection\ContentGraph\Timestamps;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateClassification;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use PHPUnit\Framework\MockObject\MockBuilder;
use Symfony\Component\Serializer\Serializer;

/**
 * @method MockBuilder getMockBuilder(string $className)
 */
trait NodeMockTrait
{
    private function createNodeMock(NodeAggregateId $nodeAggregateId = null): Node
    {
        return new Node(
            ContentSubgraphIdentity::create(
                ContentRepositoryId::fromString("cr"),
                ContentStreamId::fromString("cs"),
                DimensionSpacePoint::fromArray([]),
                VisibilityConstraints::withoutRestrictions()
            ),
            $nodeAggregateId ?? NodeAggregateId::fromString("na"),
            OriginDimensionSpacePoint::fromArray([]),
            NodeAggregateClassification::CLASSIFICATION_REGULAR,
            NodeTypeName::fromString("nt"),
            $this->getMockBuilder(NodeType::class)->disableOriginalConstructor()->getMock(),
            new PropertyCollection(
                SerializedPropertyValues::createEmpty(),
                new PropertyConverter(new Serializer())
            ),
            NodeName::fromString("nn"),
            Timestamps::create($now = new \DateTimeImmutable(), $now, null, null)
        );
    }
}
