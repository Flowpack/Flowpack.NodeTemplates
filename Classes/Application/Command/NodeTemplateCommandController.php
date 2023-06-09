<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Application\Command;

use Flowpack\NodeTemplates\Domain\NodeTemplateDumper\NodeTemplateDumper;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

class NodeTemplateCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var NodeTemplateDumper
     */
    protected $nodeTemplateDumper;

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
        // TODO re-enable
        throw new \BadMethodCallException('Not implemented.');
        $subgraph = $this->contentContextFactory->create([
            'workspaceName' => $workspaceName
        ]);
        $node = $subgraph->getNodeByIdentifier($startingNodeId);
        if (!$node) {
            throw new \InvalidArgumentException("Node $startingNodeId doesnt exist in workspace $workspaceName.");
        }
        echo $this->nodeTemplateDumper->createNodeTemplateYamlDumpFromSubtree($node);
    }
}
