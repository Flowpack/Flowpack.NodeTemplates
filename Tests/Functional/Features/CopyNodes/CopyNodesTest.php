<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\CopyNodes;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTestCase;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeCreation\Dto\NodeAggregateIdsByNodePaths;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;

class CopyNodesTest extends AbstractNodeTemplateTestCase
{
    /** @test */
    public function itMatchesSnapshot1(): void
    {
        $this->getContentRepository()->handle(
            CreateNodeAggregateWithNode::create(
                $this->homePageNode->subgraphIdentity->contentStreamId,
                $source = NodeAggregateId::fromString('source-node'),
                NodeTypeName::fromString('Flowpack.NodeTemplates:Document.Page'),
                $this->homePageNode->originDimensionSpacePoint,
                $this->homePageNode->nodeAggregateId,
                nodeName: NodeName::fromString(uniqid('node-')),
            )->withTetheredDescendantNodeAggregateIds(NodeAggregateIdsByNodePaths::fromArray([
                'main' => $main = NodeAggregateId::fromString('source-node-main-collection'),
            ]))
        )->block();

        $this->getContentRepository()->handle(
            CreateNodeAggregateWithNode::create(
                $this->homePageNode->subgraphIdentity->contentStreamId,
                NodeAggregateId::fromString('source-node-text-1'),
                NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Text'),
                $this->homePageNode->originDimensionSpacePoint,
                $main,
                nodeName: NodeName::fromString(uniqid('node-')),
                initialPropertyValues: PropertyValuesToWrite::fromArray([
                    'text' => 'Lorem ipsum 1'
                ])
            )
        )->block();

        $this->getContentRepository()->handle(
            CreateNodeAggregateWithNode::create(
                $this->homePageNode->subgraphIdentity->contentStreamId,
                NodeAggregateId::fromString('source-node-text-2'),
                NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Text'),
                $this->homePageNode->originDimensionSpacePoint,
                $main,
                nodeName: NodeName::fromString(uniqid('node-')),
                initialPropertyValues: PropertyValuesToWrite::fromArray([
                    'text' => 'Lorem ipsum 2'
                ])
            )
        )->block();


        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.StaticNodeCopy',
            [
                'sourceNode' => $this->subgraph->findNodeById($main)
            ]
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('StaticNodeCopy');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('StaticNodeCopy', $createdNode);
    }
}
