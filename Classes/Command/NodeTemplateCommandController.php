<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Command;

use Flowpack\NodeTemplates\NodeTemplateDumper\NodeTemplateDumper;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Neos\Domain\Service\ContentContextFactory;

class NodeTemplateCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contentContextFactory;

    /**
     * @Flow\Inject
     * @var NodeTemplateDumper
     */
    protected $nodeTemplateDumper;

    /**
     * Dump the node tree structure into a NodeTemplate Yaml structure.
     * References to Nodes and non-primitive property values are commented out in the Yaml.
     *
     * @param string $startingNodeId specified root node of the node tree
     * @param string $workspaceName
     * @return void
     */
    public function fromSubtreeCommand(string $startingNodeId, string $workspaceName = 'live'): void
    {
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
