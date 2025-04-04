<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Application\Command;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Flowpack\NodeTemplates\Domain\NodeCreation\NodeCreationService;
use Flowpack\NodeTemplates\Domain\NodeTemplateDumper\NodeTemplateDumper;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Projection\ContentGraph\AbsoluteNodePath;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodePath;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;
use Neos\Neos\Ui\Domain\NodeCreation\NodeCreationCommands;

class NodeTemplateCommandController extends CommandController
{
    /**
     * @var NodeCreationService
     * @Flow\Inject
     */
    protected $nodeCreationService;

    /**
     * @Flow\Inject
     * @var NodeTemplateDumper
     */
    protected $nodeTemplateDumper;

    /**
     * @Flow\Inject
     * @var TemplateConfigurationProcessor
     */
    protected $templateConfigurationProcessor;

    /**
     * @var SiteRepository
     * @Flow\Inject
     */
    protected $siteRepository;

    /**
     * @var ContentRepositoryRegistry
     * @Flow\Inject
     */
    protected $contentRepositoryRegistry;

    /**
     * Dump the node tree structure into a NodeTemplate YAML structure.
     * References to Nodes and non-primitive property values are commented out in the YAML.
     *
     * @param string $startingNodeId specified root node of the node tree.
     * @param string|null $site the Neos site, which determines the content repository. Defaults to the first available one.
     * @param string $workspaceName custom workspace to dump from. Defaults to 'live'.
     * @return void
     */
    public function createFromNodeSubtreeCommand(string $startingNodeId, ?string $site = null, string $workspaceName = 'live'): void
    {
        $siteInstance = $site
            ? $this->siteRepository->findOneByNodeName($site)
            : $this->siteRepository->findDefault();

        if (!$siteInstance) {
            $this->outputLine(sprintf('<error>Site "%s" does not exist.</error>', $site));
            $this->quit(2);
        }

        $siteConfiguration = $siteInstance->getConfiguration();

        $contentRepository = $this->contentRepositoryRegistry->get($siteConfiguration->contentRepositoryId);

        // default context? https://github.com/neos/neos-development-collection/issues/5113
        $subgraph = $contentRepository->getContentGraph(WorkspaceName::fromString($workspaceName))->getSubgraph(
            $siteConfiguration->defaultDimensionSpacePoint,
            VisibilityConstraints::default()
        );

        $node = $subgraph->findNodeById(NodeAggregateId::fromString($startingNodeId));
        if (!$node) {
            throw new \InvalidArgumentException("Node $startingNodeId doesnt exist in workspace $workspaceName.");
        }
        echo $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($node, $contentRepository);
    }

    /**
     * Checks if all configured NodeTemplates are valid. E.g no syntax errors in EEL expressions,
     * that properties exist on the node type and their types match and other checks.
     *
     * We process and build all configured NodeType templates. No nodes will be created in the Content Repository.
     *
     * @param string|null $site the Neos site, which determines the content repository. Defaults to the first available one.
     */
    public function validateCommand(?string $site = null): void
    {
        $siteInstance = $site
            ? $this->siteRepository->findOneByNodeName($site)
            : $this->siteRepository->findDefault();

        if (!$siteInstance) {
            $this->outputLine(sprintf('<error>Site "%s" does not exist.</error>', $site));
            $this->quit(2);
        }

        $siteConfiguration = $siteInstance->getConfiguration();

        $contentRepository = $this->contentRepositoryRegistry->get($siteConfiguration->contentRepositoryId);

        $templatesChecked = 0;
        /**
         * nodeTypeNames as index
         * @var array<string, array{processingErrors: ProcessingErrors, dataWasAccessed: bool}> $faultyNodeTypeTemplates
         */
        $faultyNodeTypeTemplates = [];

        // default context? https://github.com/neos/neos-development-collection/issues/5113
        $subgraph = $contentRepository->getContentGraph(WorkspaceName::forLive())->getSubgraph(
            $siteConfiguration->defaultDimensionSpacePoint,
            VisibilityConstraints::default()
        );

        $sitesNode = $subgraph->findRootNodeByType(NodeTypeNameFactory::forSites());
        $siteNode = $sitesNode ? $subgraph->findNodeByPath(
            $siteInstance->getNodeName()->toNodeName(),
            $sitesNode->aggregateId
        ) : null;

        if (!$siteNode) {
            $this->outputLine(sprintf('<error>Could not resolve site node for site "%s".</error>', $siteInstance->getNodeName()->value));
            $this->quit(3);
        }

        foreach ($contentRepository->getNodeTypeManager()->getNodeTypes(false) as $nodeType) {
            $templateConfiguration = $nodeType->getOptions()['template'] ?? null;
            if (!$templateConfiguration) {
                continue;
            }
            $processingErrors = ProcessingErrors::create();


            $observableEmptyData = new class ([]) extends \ArrayObject
            {
                public bool $dataWasAccessed = false;
                public function offsetExists($key): bool
                {
                    $this->dataWasAccessed = true;
                    return false;
                }
            };

            $template = $this->templateConfigurationProcessor->processTemplateConfiguration(
                $templateConfiguration,
                [
                    'data' => $observableEmptyData,
                    'site' => $siteNode,
                    'parentNode' => $siteNode,
                ],
                $processingErrors
            );

            $fakeNodeCreationCommands = NodeCreationCommands::fromFirstCommand(
                CreateNodeAggregateWithNode::create(
                    $siteNode->workspaceName,
                    NodeAggregateId::create(),
                    $nodeType->name,
                    $siteNode->originDimensionSpacePoint,
                    $siteNode->aggregateId
                ),
                $contentRepository->getNodeTypeManager()
            );

            $this->nodeCreationService->apply($template, $fakeNodeCreationCommands, $contentRepository->getNodeTypeManager(), $subgraph, $nodeType, $processingErrors);

            if ($processingErrors->hasError()) {
                $faultyNodeTypeTemplates[$nodeType->name->value] = ['processingErrors' => $processingErrors, 'dataWasAccessed' => $observableEmptyData->dataWasAccessed];
            }
            $templatesChecked++;
        }

        $this->output(sprintf('<comment>Content repository "%s": </comment>', $contentRepository->id->value));

        if ($templatesChecked === 0) {
            $this->outputLine('<comment>No NodeType templates found.</comment>');
            return;
        }

        if (empty($faultyNodeTypeTemplates)) {
            $this->outputLine(sprintf('<success>%d NodeType templates validated.</success>', $templatesChecked));
            return;
        }

        $possiblyFaultyTemplates = count($faultyNodeTypeTemplates);
        $this->outputLine(sprintf('<comment>%d of %d NodeType template validated. %d could not be build standalone.</comment>', $templatesChecked - $possiblyFaultyTemplates, $templatesChecked, $possiblyFaultyTemplates));

        $this->outputLine();

        // sort so the result is deterministic in ci https://github.com/neos/flow-development-collection/issues/3300
        ksort($faultyNodeTypeTemplates);

        $hasError = false;
        foreach ($faultyNodeTypeTemplates as $nodeTypeName => ['processingErrors' => $processingErrors, 'dataWasAccessed' => $dataWasAccessed]) {
            if ($dataWasAccessed) {
                $this->outputLine(sprintf('<comment>%s</comment> <b>(depends on "data" context)</b>', $nodeTypeName));
            } else {
                $hasError = true;
                $this->outputLine(sprintf('<error>%s</error>', $nodeTypeName));
            }

            foreach ($processingErrors as $processingError) {
                $this->outputLine('  ' . $processingError->toMessage());
                $this->outputLine();
            }
        }
        if ($hasError) {
            $this->quit(1);
        }
    }
}
