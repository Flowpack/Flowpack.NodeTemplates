<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\StandaloneValidationCommand;

use Flowpack\NodeTemplates\Application\Command\NodeTemplateCommandController;
use Flowpack\NodeTemplates\Tests\Functional\ContentRepositoryTestTrait;
use Flowpack\NodeTemplates\Tests\Functional\FakeNodeTypeManagerTrait;
use Flowpack\NodeTemplates\Tests\Functional\SnapshotTrait;
use Neos\Behat\FlowEntitiesTrait;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\RootNodeCreation\Command\CreateRootNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateRootWorkspace;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\User\UserId;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\TestSuite\Behavior\Features\Bootstrap\Helpers\FakeUserIdProvider;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\Flow\Cli\Response;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Utility\ObjectAccess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class StandaloneValidationCommandTest extends TestCase // we don't use Flows functional test case as it would reset the database afterwards (see FlowEntitiesTrait)
{
    use SnapshotTrait;
    use ContentRepositoryTestTrait;
    use FakeNodeTypeManagerTrait;
    use FlowEntitiesTrait;

    /**
     * Matching configuration in Neos.Neos.sites.node-templates-site
     */
    private const TEST_SITE_NAME = 'node-templates-site';

    private NodeTypeManager $nodeTypeManager;

    private string $fixturesDir;

    protected ObjectManagerInterface $objectManager;

    public function setUp(): void
    {
        $this->objectManager = Bootstrap::$staticObjectManager;

        $this->setupContentRepository();

        $ref = new \ReflectionClass($this);
        $this->fixturesDir = dirname($ref->getFileName() ?: '') . '/Snapshots';
    }

    public function tearDown(): void
    {
        $this->objectManager->get(FeedbackCollection::class)->reset();
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
            NodeAggregateId::fromString('test-site'),
            NodeTypeName::fromString('Flowpack.NodeTemplates:Document.HomePage'),
            OriginDimensionSpacePoint::fromDimensionSpacePoint(
                DimensionSpacePoint::fromArray([])
            ),
            $sitesId,
        )->withNodeName(NodeName::fromString(self::TEST_SITE_NAME));

        $this->contentRepository->handle($siteNodeCommand);
    }

    /** @test */
    public function itMatchesSnapshot(): void
    {
        $commandController = $this->getObject(NodeTemplateCommandController::class);

        $testSite = new Site(self::TEST_SITE_NAME);
        $testSite->setSiteResourcesPackageKey('Test.Site');

        $siteRepositoryMock = $this->getMockBuilder(SiteRepository::class)->disableOriginalConstructor()->getMock();
        $siteRepositoryMock->expects(self::once())->method('findOneByNodeName')->willReturnCallback(function (string $nodeName) use ($testSite) {
            return $nodeName === $testSite->getNodeName()->value
                ? $testSite
                : null;
        });

        ObjectAccess::setProperty($commandController, 'siteRepository', $siteRepositoryMock, true);


        ObjectAccess::setProperty($commandController, 'response', $cliResponse = new Response(), true);
        ObjectAccess::getProperty($commandController, 'output', true)->setOutput($bufferedOutput = new BufferedOutput());

        try {
            $commandController->validateCommand(self::TEST_SITE_NAME);
        } catch (StopCommandException $e) {
        }

        $contents = $bufferedOutput->fetch();
        self::assertSame(1, $cliResponse->getExitCode());

        $this->assertStringEqualsFileOrCreateSnapshot($this->fixturesDir . '/NodeTemplateValidateOutput.log', $contents);
    }
}
