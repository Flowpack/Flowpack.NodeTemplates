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
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Ui\Domain\Model\ChangeCollection;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\TypeConverter\ChangeCollectionConverter;
use Neos\Utility\Arrays;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractNodeTemplateTestCase extends FunctionalTestCase
{
    use SnapshotTrait;
    use FeedbackCollectionMessagesTrait;
    use JsonSerializeNodeTreeTrait;
    use WithConfigurationTrait;

    protected static $testablePersistenceEnabled = true;

    private ContextFactoryInterface $contextFactory;

    protected NodeInterface $homePageNode;

    protected NodeInterface $homePageMainContentCollectionNode;

    private NodeTemplateDumper $nodeTemplateDumper;

    private RootTemplate $lastCreatedRootTemplate;

    private NodeTypeManager $nodeTypeManager;

    private Context $subgraph;

    private string $fixturesDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);

        $this->loadFakeNodeTypes();

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

        $ref = new \ReflectionClass($this);
        $this->fixturesDir = dirname($ref->getFileName()) . '/Snapshots';
    }

    private function loadFakeNodeTypes(): void
    {
        $configuration = $this->objectManager->get(ConfigurationManager::class)->getConfiguration('NodeTypes');

        $fileIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/Features'));

        /** @var \SplFileInfo $fileInfo */
        foreach ($fileIterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'yaml' || strpos($fileInfo->getBasename(), 'NodeTypes.') !== 0) {
                continue;
            }

            $configuration = Arrays::arrayMergeRecursiveOverrule(
                $configuration,
                Yaml::parseFile($fileInfo->getRealPath()) ?? []
            );
        }

        $this->nodeTypeManager->overrideNodeTypes($configuration);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
        $this->objectManager->get(FeedbackCollection::class)->reset();
        $this->objectManager->forgetInstance(ContentDimensionRepository::class);
        $this->objectManager->forgetInstance(TemplateConfigurationProcessor::class);
        $this->objectManager->forgetInstance(NodeTypeManager::class);
    }

    private function setupContentRepository(): void
    {
        // Create an environment to create nodes.
        $this->objectManager->get(ContentDimensionRepository::class)->setDimensionsConfiguration([]);

        $liveWorkspace = new Workspace('live');
        $workspaceRepository = $this->objectManager->get(WorkspaceRepository::class);
        $workspaceRepository->add($liveWorkspace);

        $testSite = new Site('test-site');
        $testSite->setSiteResourcesPackageKey('Test.Site');
        $siteRepository = $this->objectManager->get(SiteRepository::class);
        $siteRepository->add($testSite);

        $this->persistenceManager->persistAll();
        $this->contextFactory = $this->objectManager->get(ContextFactoryInterface::class);
        $this->subgraph = $this->contextFactory->create(['workspaceName' => 'live']);

        $rootNode = $this->subgraph->getRootNode();


        $sitesRootNode = $rootNode->createNode('sites');
        $testSiteNode = $sitesRootNode->createNode('test-site');
        $this->homePageNode = $testSiteNode->createNode(
            'homepage',
            $this->nodeTypeManager->getNodeType('Flowpack.NodeTemplates:Document.Page')
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

        return $targetNode->getNode('new-node');
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
