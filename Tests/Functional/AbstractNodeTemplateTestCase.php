<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional;

use Flowpack\NodeTemplates\Domain\NodeTemplateDumper\NodeTemplateDumper;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\ContentDimensionRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Ui\Domain\Model\ChangeCollection;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\TypeConverter\ChangeCollectionConverter;

abstract class AbstractNodeTemplateTestCase extends FunctionalTestCase
{
    use SnapshotTrait;
    use FeedbackCollectionMessagesTrait;
    use JsonSerializeNodeTreeTrait;
    use WithConfigurationTrait;
    use FakeNodeTypeManagerTrait;

    protected static bool $showDisabledNodesInSubgraph = false;

    protected static $testablePersistenceEnabled = true;

    private ContextFactoryInterface $contextFactory;

    protected NodeInterface $homePageNode;

    protected NodeInterface $homePageMainContentCollectionNode;

    private NodeTemplateDumper $nodeTemplateDumper;

    private RootTemplate $lastCreatedRootTemplate;

    private NodeTypeManager $nodeTypeManager;

    private string $fixturesDir;

    /** @internal please use {@see self::getObject()} instead */
    protected $objectManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->nodeTypeManager = $this->getObject(NodeTypeManager::class);

        $this->loadFakeNodeTypes();

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
        $this->fixturesDir = dirname($ref->getFileName()) . '/Snapshots';
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
        $this->getObject(FeedbackCollection::class)->reset();
        $this->objectManager->forgetInstance(ContentDimensionRepository::class);
        $this->objectManager->forgetInstance(TemplateConfigurationProcessor::class);
        $this->objectManager->forgetInstance(NodeTypeManager::class);
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
        // Create an environment to create nodes.
        $this->getObject(ContentDimensionRepository::class)->setDimensionsConfiguration([]);

        $liveWorkspace = new Workspace('live');
        $workspaceRepository = $this->getObject(WorkspaceRepository::class);
        $workspaceRepository->add($liveWorkspace);

        $testSite = new Site('test-site');
        $testSite->setSiteResourcesPackageKey('Test.Site');
        $siteRepository = $this->getObject(SiteRepository::class);
        $siteRepository->add($testSite);

        $this->persistenceManager->persistAll();
        $this->contextFactory = $this->getObject(ContextFactoryInterface::class);
        $subgraph = $this->contextFactory->create(['workspaceName' => 'live', 'invisibleContentShown' => static::$showDisabledNodesInSubgraph]);

        $rootNode = $subgraph->getRootNode();

        $sitesRootNode = $rootNode->createNode('sites');
        $testSiteNode = $sitesRootNode->createNode('test-site');
        $this->homePageNode = $testSiteNode->createNode(
            'homepage',
            $this->nodeTypeManager->getNodeType('Flowpack.NodeTemplates:Document.HomePage')
        );

        $this->homePageMainContentCollectionNode = $this->homePageNode->getNode('main');
    }

    /**
     * @param NodeInterface $targetNode
     * @param array<string, mixed> $nodeCreationDialogValues
     */
    protected function createNodeInto(NodeInterface $targetNode, string $nodeTypeName, array $nodeCreationDialogValues): NodeInterface
    {
        self::assertTrue($this->nodeTypeManager->hasNodeType($nodeTypeName), sprintf('NodeType %s doesnt exits.', $nodeTypeName));

        $targetNodeContextPath = $targetNode->getContextPath();

        /** @see \Neos\Neos\Ui\Domain\Model\Changes\Create */
        $changeCollectionSerialized = [[
            'type' => 'Neos.Neos.Ui:CreateInto',
            'subject' => $targetNodeContextPath,
            'payload' => [
                'parentContextPath' => $targetNodeContextPath,
                'parentDomAddress' => [
                    'contextPath' => $targetNodeContextPath,
                ],
                'nodeType' => $nodeTypeName,
                'name' => 'new-node',
                'data' => $nodeCreationDialogValues,
                'baseNodeType' => '',
            ],
        ]];

        $changeCollection = (new ChangeCollectionConverter())->convertFrom($changeCollectionSerialized, null);
        assert($changeCollection instanceof ChangeCollection);
        $changeCollection->apply();

        $newNode = $targetNode->getNode('new-node');
        if (!$newNode) {
            throw new \RuntimeException('New node not found at expected location. Try settting $showDisabledNodesInSubgraph.', 1719079419);
        }
        return $newNode;
    }

    protected function createFakeNode(string $nodeAggregateId): NodeInterface
    {
        return $this->homePageNode->createNode(uniqid('node-'), $this->nodeTypeManager->getNodeType('unstructured'), $nodeAggregateId);
    }

    protected function assertLastCreatedTemplateMatchesSnapshot(string $snapShotName): void
    {
        $lastCreatedTemplate = $this->serializeValuesInArray(
            $this->lastCreatedRootTemplate->jsonSerialize()
        );
        $this->assertJsonStringEqualsJsonFileOrCreateSnapshot($this->fixturesDir . '/' . $snapShotName . '.template.json', json_encode($lastCreatedTemplate, JSON_PRETTY_PRINT));
    }

    protected function assertCaughtExceptionsMatchesSnapshot(string $snapShotName): void
    {
        $this->assertJsonStringEqualsJsonFileOrCreateSnapshot($this->fixturesDir . '/' . $snapShotName . '.messages.json', json_encode($this->getMessagesOfFeedbackCollection(), JSON_PRETTY_PRINT));
    }

    protected function assertNoExceptionsWereCaught(): void
    {
        self::assertSame([], $this->getMessagesOfFeedbackCollection());
    }

    protected function assertNodeDumpAndTemplateDumpMatchSnapshot(string $snapShotName, NodeInterface $node): void
    {
        $serializedNodes = $this->jsonSerializeNodeAndDescendents($node);
        unset($serializedNodes['nodeTypeName']);
        $this->assertJsonStringEqualsJsonFileOrCreateSnapshot($this->fixturesDir . '/' . $snapShotName . '.nodes.json', json_encode($serializedNodes, JSON_PRETTY_PRINT));

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($node);

        $yamlTemplateWithoutOriginNodeTypeName = '\'{nodeTypeName}\'' . substr($dumpedYamlTemplate, strlen($node->getNodeType()->getName()) + 2);

        $this->assertStringEqualsFileOrCreateSnapshot($this->fixturesDir . '/' . $snapShotName . '.yaml', $yamlTemplateWithoutOriginNodeTypeName);
    }
}
