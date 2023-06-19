<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional;

use Flowpack\NodeTemplates\Domain\NodeTemplateDumper\NodeTemplateDumper;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Repository\ContentDimensionRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Media\Domain\Repository\ImageRepository;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Ui\Domain\Model\ChangeCollection;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\TypeConverter\ChangeCollectionConverter;
use Neos\Utility\ObjectAccess;

class NodeTemplateTest extends FunctionalTestCase
{
    use SnapshotTrait;
    use FeedbackCollectionMessagesTrait;
    use WithConfigurationTrait;
    use JsonSerializeNodeTreeTrait;

    protected static $testablePersistenceEnabled = true;

    private ContextFactoryInterface $contextFactory;

    private NodeInterface $homePageNode;

    private NodeTemplateDumper $nodeTemplateDumper;

    private RootTemplate $lastCreatedRootTemplate;

    public function setUp(): void
    {
        parent::setUp();
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
        $this->inject($this->contextFactory, 'contextInstances', []);
        $this->objectManager->get(FeedbackCollection::class)->reset();
        $this->objectManager->forgetInstance(ContentDimensionRepository::class);
        $this->objectManager->forgetInstance(TemplateConfigurationProcessor::class);
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

        $nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);

        $sitesRootNode = $rootNode->createNode('sites');
        $testSiteNode = $sitesRootNode->createNode('test-site');
        $this->homePageNode = $testSiteNode->createNode(
            'homepage',
            $nodeTypeManager->getNodeType('Flowpack.NodeTemplates:Document.Page')
        );
    }

    /**
     * @param NodeInterface $targetNode
     * @param array<string, mixed> $nodeCreationDialogValues
     */
    private function createNodeInto(NodeInterface $targetNode, NodeTypeName $nodeTypeName, array $nodeCreationDialogValues): NodeInterface
    {
        $targetNodeContextPath = $targetNode->getContextPath();

        $changeCollectionSerialized = [[
            'type' => 'Neos.Neos.Ui:CreateInto',
            'subject' => $targetNodeContextPath,
            'payload' => [
                'parentContextPath' => $targetNodeContextPath,
                'parentDomAddress' => [
                    'contextPath' => $targetNodeContextPath,
                ],
                'nodeType' => $nodeTypeName->getValue(),
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


    /** @test */
    public function testNodeCreationMatchesSnapshot1(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode->getNode('main'),
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Columns.Two'),
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('TwoColumnPreset');

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        self::assertSame([], $this->getMessagesOfFeedbackCollection());

        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('TwoColumnPreset', $createdNode);
    }

    /** @test */
    public function testNodeCreationMatchesSnapshot2(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode->getNode('main'),
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Columns.Two.CreationDialogAndWithItems'),
            [
                'text' => '<p>bar</p>'
            ]
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('TwoColumnPreset');

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('TwoColumnPreset', $createdNode);
    }

    /** @test */
    public function testNodeCreationMatchesSnapshot3(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode->getNode('main'),
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Columns.Two.WithContext'),
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('TwoColumnPreset');

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('TwoColumnPreset', $createdNode);
    }


    /** @test */
    public function transliterateNodeName(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode->getNode('main'),
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.TransliterateNodeName'),
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('TransliterateNodeName');

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('TransliterateNodeName', $createdNode);
    }


    /** @test */
    public function testNodeCreationWithDifferentPropertyTypes(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode->getNode('main'),
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.DifferentPropertyTypes'),
            [
                'someNode' => $this->homePageNode->createNode('some-node', null, '7f7bac1c-9400-4db5-bbaa-2b8251d127c5')
            ]
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('DifferentPropertyTypes');

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('DifferentPropertyTypes', $createdNode);
    }

    /** @test */
    public function resolvablePropertyValues(): void
    {
        $this->homePageNode->createNode('some-node', null, 'some-node-id');
        $this->homePageNode->createNode('other-node', null, 'other-node-id');

        $resource = $this->objectManager->get(ResourceManager::class)->importResource(__DIR__ . '/image.png');

        $asset = new Asset($resource);
        ObjectAccess::setProperty($asset, 'Persistence_Object_Identifier', 'c228200e-7472-4290-9936-4454a5b5692a', true);
        $this->objectManager->get(AssetRepository::class)->add($asset);

        $resource = $this->objectManager->get(ResourceManager::class)->importResource(__DIR__ . '/image.png');

        $image = new Image($resource);
        ObjectAccess::setProperty($image, 'Persistence_Object_Identifier', 'c8ae9f9f-dd11-4373-bf42-4bf31ec5bd19', true);
        $this->objectManager->get(ImageRepository::class)->add($image);

        $this->persistenceManager->persistAll();

        $createdNode = $this->createNodeInto(
            $this->homePageNode->getNode('main'),
            NodeTypeName::fromString('Flowpack.NodeTemplates:Content.ResolvablePropertyValues'),
            [
                'realNode' => $this->homePageNode->createNode('real-node', null, 'real-node-id')
            ]
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('ResolvablePropertyValues');

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('ResolvablePropertyValues', $createdNode);
    }

    /** @test */
    public function unresolvablePropertyValues(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageNode->getNode('main'),
            NodeTypeName::fromString('Flowpack.NodeTemplates:Content.UnresolvablePropertyValues'),
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('UnresolvablePropertyValues');

        $this->assertCaughtExceptionsMatchesSnapshot('UnresolvablePropertyValues');
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('UnresolvablePropertyValues', $createdNode);
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

        $this->assertCaughtExceptionsMatchesSnapshot('WithEvaluationExceptions');
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
        $this->assertJsonStringEqualsJsonFileOrCreateSnapshot(__DIR__ . '/Fixtures/' . $snapShotName . '.template.json', json_encode($lastCreatedTemplate, JSON_PRETTY_PRINT));
    }

    private function assertCaughtExceptionsMatchesSnapshot(string $snapShotName): void
    {
        $this->assertJsonStringEqualsJsonFileOrCreateSnapshot(__DIR__ . '/Fixtures/' . $snapShotName . '.messages.json', json_encode($this->getMessagesOfFeedbackCollection(), JSON_PRETTY_PRINT));
    }

    private function assertNodeDumpAndTemplateDumpMatchSnapshot(string $snapShotName, NodeInterface $node): void
    {
        $serializedNodes = $this->jsonSerializeNodeAndDescendents($node);
        unset($serializedNodes['nodeTypeName']);
        $this->assertJsonStringEqualsJsonFileOrCreateSnapshot(__DIR__ . '/Fixtures/' . $snapShotName . '.nodes.json', json_encode($serializedNodes, JSON_PRETTY_PRINT));

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($node);

        $yamlTemplateWithoutOriginNodeTypeName = '\'{nodeTypeName}\'' . substr($dumpedYamlTemplate, strlen($node->getNodeType()->getName()) + 2);

        $this->assertStringEqualsFileOrCreateSnapshot(__DIR__ . '/Fixtures/' . $snapShotName . '.yaml', $yamlTemplateWithoutOriginNodeTypeName);
    }
}
