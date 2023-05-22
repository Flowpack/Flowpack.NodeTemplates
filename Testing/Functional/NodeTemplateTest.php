<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional;

use Flowpack\NodeTemplates\NodeTemplateDumper\NodeTemplateDumper;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Ui\Domain\Model\ChangeCollection;
use Neos\Neos\Ui\TypeConverter\ChangeCollectionConverter;

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

    /** @test */
    public function testNodeCreationMatchesSnapshot(): void
    {
        $nodeCreationDialogProperties = [
        ];

        $targetNode = $this->homePageNode->getNode('main');

        $targetNodeContextPath = $targetNode->getContextPath();

        $changeCollectionSerialized = [[
            'type' => 'Neos.Neos.Ui:CreateInto',
            'subject' => $targetNodeContextPath,
            'payload' => [
                'parentContextPath' => $targetNodeContextPath,
                'parentDomAddress' => [
                    'contextPath' => $targetNodeContextPath,
                ],
                'nodeType' => $toBeCreatedNodeTypeName = 'Flowpack.NodeTemplates:Content.Columns.Two',
                'data' => $nodeCreationDialogProperties,
                'baseNodeType' => '',
            ],
        ]];

        $changeCollection = (new ChangeCollectionConverter())->convertFrom($changeCollectionSerialized, null);
        self::assertInstanceOf(ChangeCollection::class, $changeCollection);
        $changeCollection->apply();

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName)[0];

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($createdNode);

        // file_put_contents(__DIR__ . '/Fixtures/TwoColumnPreset.yaml', $dumpedYamlTemplate);

        self::assertStringEqualsFile(__DIR__ . '/Fixtures/TwoColumnPreset.yaml', $dumpedYamlTemplate);
    }

    /** @test */
    public function testDynamicNodeCreationMatchesSnapshot(): void
    {
        $nodeCreationDialogProperties = [
            'text' => '<p>bar</p>'
        ];

        $targetNode = $this->homePageNode->getNode('main');

        $targetNodeContextPath = $targetNode->getContextPath();

        $changeCollectionSerialized = [[
            'type' => 'Neos.Neos.Ui:CreateInto',
            'subject' => $targetNodeContextPath,
            'payload' => [
                'parentContextPath' => $targetNodeContextPath,
                'parentDomAddress' => [
                    'contextPath' => $targetNodeContextPath,
                ],
                'nodeType' => $toBeCreatedNodeTypeName = 'Flowpack.NodeTemplates:Content.Columns.Two.Dynamic',
                'data' => $nodeCreationDialogProperties,
                'baseNodeType' => '',
            ],
        ]];

        $changeCollection = (new ChangeCollectionConverter())->convertFrom($changeCollectionSerialized, null);
        self::assertInstanceOf(ChangeCollection::class, $changeCollection);
        $changeCollection->apply();

        $createdNode = $targetNode->getChildNodes($toBeCreatedNodeTypeName)[0];

        $dumpedYamlTemplate = $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($createdNode);

        // file_put_contents(__DIR__ . '/Fixtures/TwoColumnPreset.yaml', $dumpedYamlTemplate);

        self::assertStringEqualsFile(__DIR__ . '/Fixtures/TwoColumnPreset.yaml', $dumpedYamlTemplate);
    }


    public function tearDown(): void
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
    }
}
