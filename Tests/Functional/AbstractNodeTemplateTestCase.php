<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional;

use Flowpack\NodeTemplates\Domain\NodeTemplateDumper\NodeTemplateDumper;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\Behat\FlowEntitiesTrait;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\RootNodeCreation\Command\CreateRootNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateRootWorkspace;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindSubtreeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\Subtree;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\User\UserId;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceDescription;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceTitle;
use Neos\ContentRepository\TestSuite\Behavior\Features\Bootstrap\Helpers\FakeUserIdProvider;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Neos\FrontendRouting\NodeAddressFactory;
use Neos\Neos\Ui\Domain\Model\ChangeCollection;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\TypeConverter\ChangeCollectionConverter;
use PHPUnit\Framework\TestCase;

abstract class AbstractNodeTemplateTestCase extends TestCase // we don't use Flows functional test case as it would reset the database afterwards (see FlowEntitiesTrait)
{
    use SnapshotTrait;
    use FeedbackCollectionMessagesTrait;
    use JsonSerializeNodeTreeTrait;
    use WithConfigurationTrait;
    use FakeNodeTypeManagerTrait;
    use FlowEntitiesTrait;

    use ContentRepositoryTestTrait;

    protected Node $homePageNode;

    protected Node $homePageMainContentCollectionNode;

    private ContentSubgraphInterface $subgraph;

    private NodeTemplateDumper $nodeTemplateDumper;

    private RootTemplate $lastCreatedRootTemplate;

    private NodeTypeManager $nodeTypeManager;

    private string $fixturesDir;

    /** @deprecated please use {@see self::getObject()} instead */
    protected ObjectManagerInterface $objectManager;

    public function setUp(): void
    {
        $this->objectManager = Bootstrap::$staticObjectManager;

        $this->setupContentRepository();
        $this->nodeTemplateDumper = $this->getObject(NodeTemplateDumper::class);

        $templateFactory = $this->getObject(TemplateConfigurationProcessor::class);

        $templateFactoryMock = $this->getMockBuilder(TemplateConfigurationProcessor::class)->disableOriginalConstructor()->getMock();
        $templateFactoryMock->expects(self::once())->method('processTemplateConfiguration')->willReturnCallback(function (...$args) use($templateFactory) {
            $rootTemplate = $templateFactory->processTemplateConfiguration(...$args);
            $this->lastCreatedRootTemplate = $rootTemplate;
            return $rootTemplate;
        });
        $this->objectManager->setInstance(TemplateConfigurationProcessor::class, $templateFactoryMock);

        $ref = new \ReflectionClass($this);
        $this->fixturesDir = dirname($ref->getFileName() ?: '') . '/Snapshots';
    }

    public function tearDown(): void
    {
        $this->getObject(FeedbackCollection::class)->reset();
        $this->objectManager->forgetInstance(TemplateConfigurationProcessor::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    final protected function getObject(string $className): object
    {
        return $this->objectManager->get($className);
    }

    private function setupContentRepository(): void
    {
        $this->initCleanContentRepository(ContentRepositoryId::fromString('node_templates'));
        $this->truncateAndSetupFlowEntities();

        $this->nodeTypeManager = $this->contentRepository->getNodeTypeManager();
        $this->loadFakeNodeTypes();

        $liveWorkspaceCommand = CreateRootWorkspace::create(
            $workspaceName = WorkspaceName::fromString('live'),
            ContentStreamId::fromString('cs-identifier')
        );

        $this->contentRepository->handle($liveWorkspaceCommand);

        FakeUserIdProvider::setUserId(UserId::fromString('initiating-user-identifier'));

        $rootNodeCommand = CreateRootNodeAggregateWithNode::create(
            $workspaceName,
            $sitesId = NodeAggregateId::fromString('sites'),
            NodeTypeName::fromString('Neos.Neos:Sites')
        );

        $this->contentRepository->handle($rootNodeCommand);

        $siteNodeCommand = CreateNodeAggregateWithNode::create(
            $workspaceName,
            $testSiteId = NodeAggregateId::fromString('test-site'),
            NodeTypeName::fromString('Flowpack.NodeTemplates:Document.HomePage'),
            OriginDimensionSpacePoint::fromDimensionSpacePoint(
                $dimensionSpacePoint = DimensionSpacePoint::fromArray([])
            ),
            $sitesId,
        )->withNodeName(NodeName::fromString('test-site'));

        $this->contentRepository->handle($siteNodeCommand);

        $this->subgraph = $this->contentRepository->getContentGraph($workspaceName)->getSubgraph($dimensionSpacePoint, VisibilityConstraints::withoutRestrictions());

        $homePage = $this->subgraph->findNodeById($testSiteId);
        assert($homePage instanceof Node);
        $this->homePageNode = $homePage;

        $homePageMainCollection = $this->subgraph->findNodeByPath(
            NodeName::fromString('main'),
            $testSiteId
        );
        assert($homePageMainCollection instanceof Node);
        $this->homePageMainContentCollectionNode = $homePageMainCollection;

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
    protected function createNodeInto(Node $targetNode, string $nodeTypeName, array $nodeCreationDialogValues): Node
    {
        $targetNodeAddress = NodeAddress::fromNode($targetNode);
        $serializedTargetNodeAddress = $targetNodeAddress->toJson();

        $changeCollectionSerialized = [[
            'type' => 'Neos.Neos.Ui:CreateInto',
            'subject' => $serializedTargetNodeAddress,
            'payload' => [
                'parentContextPath' => $serializedTargetNodeAddress,
                'parentDomAddress' => [
                    'contextPath' => $serializedTargetNodeAddress,
                ],
                'nodeType' => $nodeTypeName,
                'name' => 'new-node',
                'nodeAggregateId' => '186b511b-b807-6208-9e1c-593e7c1a63d3',
                'data' => $nodeCreationDialogValues,
                'baseNodeType' => '',
            ],
        ]];

        $changeCollection = (new ChangeCollectionConverter())->convert($changeCollectionSerialized, $this->contentRepositoryId);
        assert($changeCollection instanceof ChangeCollection);
        $changeCollection->apply();

        $node = $this->subgraph->findNodeByPath(
            NodeName::fromString('new-node'),
            $targetNode->aggregateId
        );
        assert($node instanceof Node);
        return $node;
    }

    protected function createFakeNode(string $nodeAggregateId): Node
    {
        $this->contentRepository->handle(
            CreateNodeAggregateWithNode::create(
                $this->homePageNode->workspaceName,
                $someNodeId = NodeAggregateId::fromString($nodeAggregateId),
                NodeTypeName::fromString('unstructured'),
                $this->homePageNode->originDimensionSpacePoint,
                $this->homePageNode->aggregateId,
            )->withNodeName(NodeName::fromString(uniqid('node-')))
        );

        $node = $this->subgraph->findNodeById($someNodeId);
        assert($node instanceof Node);
        return $node;
    }

    protected function assertLastCreatedTemplateMatchesSnapshot(string $snapShotName): void
    {
        $lastCreatedTemplate = $this->serializeValuesInArray(
            $this->lastCreatedRootTemplate->jsonSerialize()
        );
        $this->assertJsonStringEqualsJsonFileOrCreateSnapshot($this->fixturesDir . '/' . $snapShotName . '.template.json', json_encode($lastCreatedTemplate, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    protected function assertCaughtExceptionsMatchesSnapshot(string $snapShotName): void
    {
        $this->assertJsonStringEqualsJsonFileOrCreateSnapshot($this->fixturesDir . '/' . $snapShotName . '.messages.json', json_encode($this->getMessagesOfFeedbackCollection(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    protected function assertNoExceptionsWereCaught(): void
    {
        self::assertSame([], $this->getMessagesOfFeedbackCollection());
    }

    protected function assertNodeDumpAndTemplateDumpMatchSnapshot(string $snapShotName, Node $node): void
    {
        $subtree = $this->subgraph->findSubtree(
            $node->aggregateId,
            FindSubtreeFilter::create(
                nodeTypes: 'Neos.Neos:Node'
            )
        );
        assert($subtree instanceof Subtree);
        $serializedNodes = $this->jsonSerializeNodeAndDescendents($subtree);
        unset($serializedNodes['nodeTypeName']);
        $this->assertJsonStringEqualsJsonFileOrCreateSnapshot($this->fixturesDir . '/' . $snapShotName . '.nodes.json', json_encode($serializedNodes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($node, $this->contentRepository);

        $yamlTemplateWithoutOriginNodeTypeName = '\'{nodeTypeName}\'' . substr($dumpedYamlTemplate, strlen($node->nodeTypeName->value) + 2);

        $this->assertStringEqualsFileOrCreateSnapshot($this->fixturesDir . '/' . $snapShotName . '.yaml', $yamlTemplateWithoutOriginNodeTypeName);
    }
}
