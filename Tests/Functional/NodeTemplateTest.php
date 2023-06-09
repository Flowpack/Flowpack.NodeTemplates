<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional;

use Flowpack\NodeTemplates\Domain\NodeTemplateDumper\NodeTemplateDumper;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\RootNodeCreation\Command\CreateRootNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateRootWorkspace;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindSubtreeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\User\UserId;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceDescription;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceTitle;
use Neos\ContentRepository\Core\Tests\Behavior\Features\Bootstrap\Helpers\FakeUserIdProvider;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Neos\FrontendRouting\NodeAddressFactory;
use Neos\Neos\Ui\Domain\Model\ChangeCollection;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\TypeConverter\ChangeCollectionConverter;
use PHPUnit\Framework\TestCase;

class NodeTemplateTest extends TestCase // we don't use Flows functional test case as it would reset the database afterwards
{
    use SnapshotTrait;
    use FeedbackCollectionMessagesTrait;
    use WithConfigurationTrait;
    use JsonSerializeNodeTreeTrait;

    use ContentRepositoryTestTrait;

    private Node $homePageNode;

    private Node $homePageMainContentCollectionNode;

    private ContentSubgraphInterface $subgraph;

    private NodeTemplateDumper $nodeTemplateDumper;

    private RootTemplate $lastCreatedRootTemplate;

    protected static $testablePersistenceEnabled = false;

    private ObjectManagerInterface $objectManager;

    public function setUp(): void
    {
        $this->objectManager = Bootstrap::$staticObjectManager;

        $this->setupContentRepository();
        $this->nodeTemplateDumper = $this->objectManager->get(NodeTemplateDumper::class);

        $templateFactory = $this->objectManager->get(TemplateConfigurationProcessor::class);

        $templateFactoryMock = $this->getMockBuilder(TemplateConfigurationProcessor::class)->disableOriginalConstructor()->getMock();
        $templateFactoryMock->expects(self::once())->method('processTemplateConfiguration')->willReturnCallback(function (...$args) use($templateFactory) {
            $rootTemplate = $templateFactory->processTemplateConfiguration(...$args);
            $this->lastCreatedRootTemplate = $rootTemplate;
            return $rootTemplate;
        });
        $this->objectManager->setInstance(TemplateConfigurationProcessor::class, $templateFactoryMock);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->objectManager->get(FeedbackCollection::class)->reset();
        $this->objectManager->forgetInstance(TemplateConfigurationProcessor::class);
    }

    private function setupContentRepository(): void
    {
        $this->initCleanContentRepository();

        $liveWorkspaceCommand = new CreateRootWorkspace(
            WorkspaceName::fromString('live'),
            new WorkspaceTitle('Live'),
            new WorkspaceDescription('The live workspace'),
            $contentStreamId = ContentStreamId::fromString('cs-identifier')
        );

        $this->contentRepository->handle($liveWorkspaceCommand)->block();

        FakeUserIdProvider::setUserId(UserId::fromString('initiating-user-identifier'));

        $rootNodeCommand = new CreateRootNodeAggregateWithNode(
            $contentStreamId,
            $sitesId = NodeAggregateId::fromString('sites'),
            NodeTypeName::fromString('Neos.Neos:Sites')
        );

        $this->contentRepository->handle($rootNodeCommand)->block();

        $siteNodeCommand = new CreateNodeAggregateWithNode(
            $contentStreamId,
            $testSiteId = NodeAggregateId::fromString('test-site'),
            NodeTypeName::fromString('Flowpack.NodeTemplates:Document.Page'),
            OriginDimensionSpacePoint::fromDimensionSpacePoint(DimensionSpacePoint::fromArray([])),
            $sitesId
        );

        $this->contentRepository->handle($siteNodeCommand)->block();

        $this->subgraph = $this->contentRepository->getContentGraph()->getSubgraph($contentStreamId, DimensionSpacePoint::fromArray([]), VisibilityConstraints::withoutRestrictions());

        $this->homePageNode = $this->subgraph->findNodeById($testSiteId);

        $this->homePageMainContentCollectionNode = $this->subgraph->findChildNodeConnectedThroughEdgeName(
            $testSiteId,
            NodeName::fromString('main')
        );

        // For the case you the Neos Site is expected to return the correct site node you can use:

        // $siteRepositoryMock = $this->getMockBuilder(SiteRepository::class)->disableOriginalConstructor()->getMock();
        // $siteRepositoryMock->expects(self::once())->method('findOneByNodeName')->willReturnCallback(function (string|SiteNodeName $nodeName) use ($testSite) {
        //     $nodeName = is_string($nodeName) ? SiteNodeName::fromString($nodeName) : $nodeName;
        //     return $nodeName->toNodeName()->equals($testSite->nodeName)
        //         ? $testSite
        //         : null;
        // });

        // or

        // $testSite = new Site($testSite->nodeName->value);
        // $testSite->setSiteResourcesPackageKey('Test.Site');
        // $siteRepository = $this->objectManager->get(SiteRepository::class);
        // $siteRepository->add($testSite);
        // $this->persistenceManager->persistAll();
    }

    /**
     * @param array<string, mixed> $nodeCreationDialogValues
     */
    private function createNodeInto(Node $targetNode, NodeTypeName $nodeTypeName, array $nodeCreationDialogValues): Node
    {
        $targetNodeAddress = NodeAddressFactory::create($this->contentRepository)->createFromNode($targetNode);
        $serializedTargetNodeAddress = $targetNodeAddress->serializeForUri();

        $changeCollectionSerialized = [[
            'type' => 'Neos.Neos.Ui:CreateInto',
            'subject' => $serializedTargetNodeAddress,
            'payload' => [
                'parentContextPath' => $serializedTargetNodeAddress,
                'parentDomAddress' => [
                    'contextPath' => $serializedTargetNodeAddress,
                ],
                'nodeType' => $nodeTypeName->value,
                'name' => 'new-node',
                'data' => $nodeCreationDialogValues,
                'baseNodeType' => '',
            ],
        ]];

        $changeCollection = (new ChangeCollectionConverter())->convert($changeCollectionSerialized, $this->contentRepositoryId);
        assert($changeCollection instanceof ChangeCollection);
        $changeCollection->apply();

        return $this->subgraph->findChildNodeConnectedThroughEdgeName(
            $targetNode->nodeAggregateId,
            NodeName::fromString('new-node')
        );
    }


    /** @test */
    public function testNodeCreationMatchesSnapshot1(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Columns.Two'),
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('TwoColumnPreset');

        self::assertSame([], $this->getMessagesOfFeedbackCollection());

        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('TwoColumnPreset', $createdNode);
    }

    /** @test */
    public function testNodeCreationMatchesSnapshot2(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Columns.Two.CreationDialogAndWithItems'),            [
                'text' => '<p>bar</p>'
            ]
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('TwoColumnPreset');

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('TwoColumnPreset', $createdNode);
    }

    /** @test */
    public function testNodeCreationMatchesSnapshot3(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Columns.Two.WithContext'),
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('TwoColumnPreset');

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('TwoColumnPreset', $createdNode);
    }

    /** @test */
    public function testNodeCreationWithDifferentPropertyTypes(): void
    {
        $this->contentRepository->handle(
            new CreateNodeAggregateWithNode(
                $this->homePageNode->subgraphIdentity->contentStreamId,
                $someNodeId = NodeAggregateId::fromString('7f7bac1c-9400-4db5-bbaa-2b8251d127c5'),
                NodeTypeName::fromString('unstructured'),
                $this->homePageNode->originDimensionSpacePoint,
                $this->homePageNode->nodeAggregateId
            )
        )->block();

        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            NodeTypeName::fromString('Flowpack.NodeTemplates:Content.DifferentPropertyTypes'),
            [
                'someNode' => $this->subgraph->findNodeById($someNodeId)
            ]
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('DifferentPropertyTypes');

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('DifferentPropertyTypes', $createdNode);
    }

    /** @test */
    public function exceptionsAreCaughtAndPartialTemplateIsBuild(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode->getNode('main'),
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.WithEvaluationExceptions'),
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('WithEvaluationExceptions');

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        $this->assertStringEqualsFileOrCreateSnapshot(__DIR__ . '/Fixtures/WithEvaluationExceptions.messages.json', json_encode($this->getMessagesOfFeedbackCollection(), JSON_PRETTY_PRINT));

        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('WithEvaluationExceptions', $createdNode);
    }

    /** @test */
    public function exceptionsAreCaughtAndPartialTemplateNotBuild(): void
    {
        $this->withMockedConfigurationSettings([
            'Flowpack' => [
                'NodeTemplates' => [
                    'exceptionHandling' => [
                        'templateConfigurationProcessing' => [
                            'stopOnException' => true
                        ]
                    ]
                ]
            ]
        ], function () {
            $this->createNodeInto(
                $targetNode = $this->homePageNode->getNode('main'),
                $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.WithOneEvaluationException'),
                []
            );

            $this->assertLastCreatedTemplateMatchesSnapshot('WithOneEvaluationException');

            self::assertSame([
                [
                    'message' => 'Template for "WithOneEvaluationException" was not applied. Only Node /sites/test-site/homepage/main/new-node@live[Flowpack.NodeTemplates:Content.WithOneEvaluationException] was created.',
                    'severity' => 'ERROR'
                ],
                [
                    'message' => 'Expression "${\'left open" in "childNodes.abort.when" | EelException(The EEL expression "${\'left open" was not a valid EEL expression. Perhaps you forgot to wrap it in ${...}?, 1410441849)',
                    'severity' => 'ERROR'
                ]
            ], $this->getMessagesOfFeedbackCollection());

            $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

            self::assertEmpty($createdNode->getChildNodes());
        });
    }

    /** @test */
    public function testPageNodeCreationMatchesSnapshot1(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode,
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Document.Page.Static'),
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('PagePreset');

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('PagePreset', $createdNode);
    }

    /** @test */
    public function testPageNodeCreationMatchesSnapshot2(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode,
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Document.Page.Dynamic'),
            [
                'title' => 'Page1'
            ]
        );

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('PagePreset', $createdNode);
    }

    private function assertLastCreatedTemplateMatchesSnapshot(string $snapShotName): void
    {
        $lastCreatedTemplate = $this->serializeValuesInArray(
            $this->lastCreatedRootTemplate->jsonSerialize()
        );
        $this->assertStringEqualsFileOrCreateSnapshot(__DIR__ . '/Fixtures/' . $snapShotName . '.template.json', json_encode($lastCreatedTemplate, JSON_PRETTY_PRINT));
    }

    private function assertNodeDumpAndTemplateDumpMatchSnapshot(string $snapShotName, Node $node): void
    {
        $serializedNodes = $this->jsonSerializeNodeAndDescendents(
            $this->subgraph->findSubtree(
                $node->nodeAggregateId,
                FindSubtreeFilter::create(
                    nodeTypeConstraints: 'Neos.Neos:Node'
                )
            )
        );
        unset($serializedNodes['nodeTypeName']);
        $this->assertStringEqualsFileOrCreateSnapshot(__DIR__ . '/Fixtures/' . $snapShotName . '.nodes.json', json_encode($serializedNodes, JSON_PRETTY_PRINT));

        // todo test dumper
        return;

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($node);

        $yamlTemplateWithoutOriginNodeTypeName = '\'{nodeTypeName}\'' . substr($dumpedYamlTemplate, strlen($node->getNodeType()->getName()) + 2);

        $this->assertStringEqualsFileOrCreateSnapshot(__DIR__ . '/Fixtures/' . $snapShotName . '.yaml', $yamlTemplateWithoutOriginNodeTypeName);
    }
}
