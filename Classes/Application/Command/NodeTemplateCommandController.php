<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Application\Command;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Flowpack\NodeTemplates\Domain\NodeCreation\NodeCreationService;
use Flowpack\NodeTemplates\Domain\NodeTemplateDumper\NodeTemplateDumper;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

class NodeTemplateCommandController extends CommandController
{
    /**
     * @var NodeCreationService
     * @Flow\Inject
     */
    protected $nodeCreationService;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

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
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * Dump the node tree structure into a NodeTemplate YAML structure.
     * References to Nodes and non-primitive property values are commented out in the YAML.
     *
     * @param string $startingNodeId specified root node of the node tree
     * @param string $workspaceName
     * @return void
     */
    public function createFromNodeSubtreeCommand(string $startingNodeId, string $workspaceName = 'live'): void
    {
        $subgraph = $this->contextFactory->create([
            'workspaceName' => $workspaceName
        ]);
        /** @var ?NodeInterface $node */
        $node = $subgraph->getNodeByIdentifier($startingNodeId);
        if (!$node) {
            throw new \InvalidArgumentException("Node $startingNodeId doesnt exist in workspace $workspaceName.");
        }
        echo $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($node);
    }

    /**
     * Checks if all configured NodeTemplates are valid. E.g no syntax errors in EEL expressions,
     * that properties exist on the node type and their types match and other checks.
     *
     * We process and build all configured NodeType templates. No nodes will be created in the Content Repository.
     *
     */
    public function validateCommand(): void
    {
        $templatesChecked = 0;
        /**
         * nodeTypeNames as index
         * @var array<string, array{processingErrors: ProcessingErrors, dataWasAccessed: bool}> $faultyNodeTypeTemplates
         */
        $faultyNodeTypeTemplates = [];

        foreach ($this->nodeTypeManager->getNodeTypes(false) as $nodeType) {
            $templateConfiguration = $nodeType->getOptions()['template'] ?? null;
            if (!$templateConfiguration) {
                continue;
            }
            $processingErrors = ProcessingErrors::create();

            $subgraph = $this->contextFactory->create();

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
                    'triggeringNode' => $subgraph->getRootNode(),
                ],
                $processingErrors
            );

            $this->nodeCreationService->createMutatorsForRootTemplate($template, $nodeType, $this->nodeTypeManager, $subgraph, $processingErrors);

            if ($processingErrors->hasError()) {
                $faultyNodeTypeTemplates[$nodeType->getName()] = ['processingErrors' => $processingErrors, 'dataWasAccessed' => $observableEmptyData->dataWasAccessed];
            }
            $templatesChecked++;
        }

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
