<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional;

use Flowpack\NodeTemplates\Infrastructure\NodeTemplateDumper\NodeTemplateDumper;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
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

class NodeTemplateTest extends FunctionalTestCase
{
    use SnapshotTrait;
    use FeedbackCollectionMessagesTrait;
    use WithConfigurationTrait;
    use JsonSerializeNodeTreeTrait;

    protected static $testablePersistenceEnabled = true;

    private ContextFactoryInterface $contextFactory;

    private Node $homePageNode;

    private NodeTemplateDumper $nodeTemplateDumper;

    public function setUp(): void
    {
        parent::setUp();
        $this->setupContentRepository();
        $this->nodeTemplateDumper = $this->objectManager->get(NodeTemplateDumper::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
        $this->objectManager->get(FeedbackCollection::class)->reset();
        $this->objectManager->forgetInstance(ContentDimensionRepository::class);
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
     * @param Node|NodeInterface $targetNode
     * @param array<string, mixed> $nodeCreationDialogValues
     */
    private function createNodeInto(Node $targetNode, NodeTypeName $nodeTypeName, array $nodeCreationDialogValues): void
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
    }


    /** @test */
    public function testNodeCreationMatchesSnapshot1(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode->getNode('main'),
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Columns.Two'),
            []
        );

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

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        self::assertSame([], $this->getMessagesOfFeedbackCollection());
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('TwoColumnPreset', $createdNode);
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

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

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
                    'exceptionHandlingStrategy' => [
                        'continueWithPartiallyEvaluatedTemplate' => false
                    ]
                ]
            ]
        ], function () {
            $this->createNodeInto(
                $targetNode = $this->homePageNode->getNode('main'),
                $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.WithOneEvaluationException'),
                []
            );

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
    public function nodeCanBeHiddenViaTemplate(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode->getNode('main'),
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.Hidden'),
            []
        );

        $subgraphWithHiddenContent = $this->contextFactory->create(['workspaceName' => 'live', 'invisibleContentShown' => true]);

        $createdNode = $subgraphWithHiddenContent->getNodeByIdentifier($targetNode->getIdentifier())->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        self::assertTrue($createdNode->isHidden(), 'Expected node to be hidden.');

        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('HiddenNode', $createdNode);
    }

    /** @test */
    public function testPageNodeCreationMatchesSnapshot1(): void
    {
        $this->createNodeInto(
            $targetNode = $this->homePageNode,
            $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Document.Page.Static'),
            []
        );

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

    private function assertNodeDumpAndTemplateDumpMatchSnapshot(string $snapShotName, NodeInterface $node): void
    {
        $serializedNodes = $this->jsonSerializeNodeAndDescendents($node);
        unset($serializedNodes['nodeTypeName']);
        $this->assertStringEqualsFileOrCreateSnapshot(__DIR__ . '/Fixtures/' . $snapShotName . '.nodes.json', json_encode($serializedNodes, JSON_PRETTY_PRINT));

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($node);

        $yamlTemplateWithoutOriginNodeTypeName = '\'{nodeTypeName}\'' . substr($dumpedYamlTemplate, strlen($node->getNodeType()->getName()) + 2);

        $this->assertStringEqualsFileOrCreateSnapshot(__DIR__ . '/Fixtures/' . $snapShotName . '.yaml', $yamlTemplateWithoutOriginNodeTypeName);
    }
}
