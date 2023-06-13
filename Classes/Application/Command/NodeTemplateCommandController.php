<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Application\Command;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\NodeCreation\NodeCreationService;
use Flowpack\NodeTemplates\Domain\NodeCreation\ToBeCreatedNode;
use Flowpack\NodeTemplates\Domain\NodeTemplateDumper\NodeTemplateDumper;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

class NodeTemplateCommandController extends CommandController
{
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
        /** @var array<string, CaughtExceptions> $nodeTypeNamesWithTheirTemplateExceptions */
        $nodeTypeNamesWithTheirTemplateExceptions = [];

        foreach ($this->nodeTypeManager->getNodeTypes(false) as $nodeType) {
            $templateConfiguration = $nodeType->getOptions()['template'] ?? null;
            if (!$templateConfiguration) {
                continue;
            }
            $caughtExceptions = CaughtExceptions::create();

            $subgraph = $this->contextFactory->create();

            $template = $this->templateConfigurationProcessor->processTemplateConfiguration(
                $templateConfiguration,
                [
                    'data' => [],
                    'triggeringNode' => $subgraph->getRootNode(),
                ],
                $caughtExceptions
            );

            $nodeCreation = new NodeCreationService($subgraph);
            $nodeCreation->apply($template, new ToBeCreatedNode($nodeType), $caughtExceptions);


            if ($caughtExceptions->hasExceptions()) {
                $nodeTypeNamesWithTheirTemplateExceptions[$nodeType->getName()] = $caughtExceptions;
            }
            $templatesChecked++;
        }

        if (empty($nodeTypeNamesWithTheirTemplateExceptions)) {
            $this->outputFormatted(sprintf('<success>%d NodeType templates validated.</success>', $templatesChecked));
            return;
        }

        $possiblyFaultyTemplates = count($nodeTypeNamesWithTheirTemplateExceptions);
        $this->outputFormatted(sprintf('<comment>%d of %d NodeType template validated. %d could not be build standalone.</comment>', $templatesChecked - $possiblyFaultyTemplates, $templatesChecked, $possiblyFaultyTemplates));
        $this->outputFormatted('This might not be a problem, if they depend on certain data from the node-creation dialog.');

        $this->outputLine();

        foreach ($nodeTypeNamesWithTheirTemplateExceptions as $nodeTypeName => $caughtExceptions) {
            $this->outputLine($nodeTypeName);
            foreach ($caughtExceptions as $caughtException) {
                $this->outputFormatted($caughtException->toMessage(), [], 4);
                $this->outputLine();
            }
        }
    }
}
