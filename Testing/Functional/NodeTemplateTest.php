<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional;

use Flowpack\NodeTemplates\NodeTemplateDumper\NodeTemplateDumper;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Ui\Domain\Model\ChangeCollection;
use Neos\Neos\Ui\Domain\Model\Feedback\AbstractMessageFeedback;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\Domain\Model\FeedbackInterface;
use Neos\Neos\Ui\TypeConverter\ChangeCollectionConverter;
use Neos\Utility\Arrays;
use Neos\Utility\ObjectAccess;

class NodeTemplateTest extends FunctionalTestCase
{
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

    private function setupContentRepository(): void
    {
        // Create an environment to create nodes.
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

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($createdNode);

        $this->assertMessagesOfFeedbackCollectionMatch([]);
        $snapshot = file_get_contents(__DIR__ . '/Fixtures/TwoColumnPreset.yaml');
        self::assertSame(
            str_replace('{nodeTypeName}', $toBeCreatedNodeTypeName->getValue(), $snapshot),
            $dumpedYamlTemplate
        );
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

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($createdNode);

        $this->assertMessagesOfFeedbackCollectionMatch([]);
        $snapshot = file_get_contents(__DIR__ . '/Fixtures/TwoColumnPreset.yaml');
        self::assertSame(
            str_replace('{nodeTypeName}', $toBeCreatedNodeTypeName->getValue(), $snapshot),
            $dumpedYamlTemplate
        );
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

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($createdNode);

        $this->assertMessagesOfFeedbackCollectionMatch([]);
        $snapshot = file_get_contents(__DIR__ . '/Fixtures/TwoColumnPreset.yaml');
        self::assertSame(
            str_replace('{nodeTypeName}', $toBeCreatedNodeTypeName->getValue(), $snapshot),
            $dumpedYamlTemplate
        );
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

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($createdNode);

        $this->assertMessagesOfFeedbackCollectionMatch([]);
        $snapshot = file_get_contents(__DIR__ . '/Fixtures/DifferentPropertyTypes.yaml');
        self::assertSame(
            $snapshot,
            $dumpedYamlTemplate
        );
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

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($createdNode);

        $this->assertMessagesOfFeedbackCollectionMatch(
            json_decode(file_get_contents(__DIR__ . '/Fixtures/WithEvaluationExceptions.messages.json'), true)
        );

        $snapshot = file_get_contents(__DIR__ . '/Fixtures/WithEvaluationExceptions.yaml');
        self::assertSame(
            $snapshot,
            $dumpedYamlTemplate
        );
    }

    /** @test */
    public function exceptionsAreCaughtAndPartialTemplateNotBuild(): void
    {
        $this->withMockedConfigurationSettings([
            'Flowpack' => [
                'NodeTemplates' => [
                    'exceptionHandlingBehaviour' => 'DONT_APPLY_PARTIAL_TEMPLATE'
                ]
            ]
        ], function () {
            $this->createNodeInto(
                $targetNode = $this->homePageNode->getNode('main'),
                $toBeCreatedNodeTypeName = NodeTypeName::fromString('Flowpack.NodeTemplates:Content.WithEvaluationExceptions'),
                []
            );

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

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName->getValue())[0];

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($createdNode);

        $this->assertMessagesOfFeedbackCollectionMatch([]);
        $snapshot = file_get_contents(__DIR__ . '/Fixtures/PagePreset.yaml');
        self::assertSame(
            str_replace('{nodeTypeName}', $toBeCreatedNodeTypeName->getValue(), $snapshot),
            $dumpedYamlTemplate
        );
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

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($createdNode);

        $this->assertMessagesOfFeedbackCollectionMatch([]);
        $snapshot = file_get_contents(__DIR__ . '/Fixtures/PagePreset.yaml');
        self::assertSame(
            str_replace('{nodeTypeName}', $toBeCreatedNodeTypeName->getValue(), $snapshot),
            $dumpedYamlTemplate
        );
    }

    private function assertMessagesOfFeedbackCollectionMatch(array $expectedMessages): void
    {
        /** @var FeedbackInterface[] $allFeedbacks */
        $allFeedbacks = ObjectAccess::getProperty($this->objectManager->get(FeedbackCollection::class), 'feedbacks', true);

        /** @var AbstractMessageFeedback[] $allFeedbacks */
        $messages = [];
        foreach ($allFeedbacks as $feedback) {
            if ($feedback instanceof AbstractMessageFeedback) {
                $messages[] = $feedback->serializePayload($this->createStub(ControllerContext::class));
            }
        }

        self::assertSame(
            $expectedMessages,
            $messages
        );
    }

    /**
     * Mock the settings of the configuration manager and cleanup afterwards
     *
     * WARNING: If you activate Singletons during this transaction they will later still have a reference to the mocked object manger, so you might need to call
     * {@see ObjectManagerInterface::forgetInstance()}. An alternative would be also to hack the protected $this->settings of the manager.
     *
     * @param array $additionalSettings settings that are merged onto the the current testing configuration
     * @param callable $fn test code that is executed in the modified context
     */
    private function withMockedConfigurationSettings(array $additionalSettings, callable $fn): void
    {
        $configurationManager = $this->objectManager->get(ConfigurationManager::class);
        $configurationManagerMock = $this->getMockBuilder(ConfigurationManager::class)->disableOriginalConstructor()->getMock();
        $mockedSettings = Arrays::arrayMergeRecursiveOverrule($configurationManager->getConfiguration('Settings'), $additionalSettings);
        $configurationManagerMock->expects(self::any())->method('getConfiguration')->willReturnCallback(function (string $configurationType, string $configurationPath = null) use($configurationManager, $mockedSettings) {
            if ($configurationType !== 'Settings') {
                return $configurationManager->getConfiguration($configurationType, $configurationPath);
            }
            return $configurationPath ? Arrays::getValueByPath($mockedSettings, $configurationPath) : $mockedSettings;
        });
        $this->objectManager->setInstance(ConfigurationManager::class, $configurationManagerMock);
        $fn();
        $this->objectManager->setInstance(ConfigurationManager::class, $configurationManager);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
        $this->objectManager->get(FeedbackCollection::class)->reset();
    }
}
