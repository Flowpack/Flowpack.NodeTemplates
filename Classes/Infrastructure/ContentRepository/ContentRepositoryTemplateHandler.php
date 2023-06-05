<?php

namespace Flowpack\NodeTemplates\Infrastructure\ContentRepository;

use Flowpack\NodeTemplates\Domain\CaughtException;
use Flowpack\NodeTemplates\Domain\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template;
use Flowpack\NodeTemplates\Domain\Templates;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Exception\NodeConstraintException;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\NodeOperations;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;

class ContentRepositoryTemplateHandler
{
    /**
     * @var NodeOperations
     * @Flow\Inject
     */
    protected $nodeOperations;

    /**
     * @var NodeTypeManager
     * @Flow\Inject
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var NodeUriPathSegmentGenerator
     */
    protected $nodeUriPathSegmentGenerator;

    /**
     * Applies the root template and its descending configured child node templates on the given node.
     * @throws \InvalidArgumentException
     */
    public function apply(RootTemplate $template, NodeInterface $node, CaughtExceptions $caughtExceptions): void
    {
        $nodeType = $node->getNodeType();
        $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($template->getProperties(), $nodeType);

        // set properties
        foreach ($propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions) as $key => $value) {
            $node->setProperty($key, $value);
        }

        // set references
        foreach ($propertiesAndReferences->requireValidReferences($nodeType, $node->getContext(), $caughtExceptions) as $key => $value) {
            $node->setProperty($key, $value);
        }

        if ($template->getHidden() === true) {
            $node->setHidden(true);
        }

        $this->ensureNodeHasUriPathSegment($node, $template);
        $this->applyTemplateRecursively($template->getChildNodes(), $node, $caughtExceptions);
    }

    private function applyTemplateRecursively(Templates $templates, NodeInterface $parentNode, CaughtExceptions $caughtExceptions): void
    {
        foreach ($templates as $template) {
            if ($template->getName() && $parentNode->getNodeType()->hasAutoCreatedChildNode($template->getName())) {
                $node = $parentNode->getNode($template->getName()->__toString());
                if ($template->getType() !== null) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template cant mutate type of auto created child nodes. Got: "%s"', $template->getType()->getValue()), 1685999829307))
                    );
                    // we continue processing the node
                }
            } else {
                if ($template->getType() === null) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be set for non auto created child nodes.'), 1685999829307))
                    );
                    continue;
                }
                if (!$this->nodeTypeManager->hasNodeType($template->getType()->getValue())) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be a valid NodeType. Got: "%s".', $template->getType()->getValue()), 1685999795564))
                    );
                    continue;
                }
                try {
                    $node = $this->nodeOperations->create(
                        $parentNode,
                        [
                            'nodeType' => $template->getType()->getValue(),
                            'nodeName' => $template->getName() ? $template->getName()->__toString() : null
                        ],
                        'into'
                    );
                } catch (NodeConstraintException $nodeConstraintException) {
                    $caughtExceptions->add(
                        CaughtException::fromException($nodeConstraintException)
                    );
                    continue; // try the next childNode
                }
            }
            $nodeType = $node->getNodeType();
            $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($template->getProperties(), $nodeType);

            // set properties
            foreach ($propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions) as $key => $value) {
                $node->setProperty($key, $value);
            }

            // set references
            foreach ($propertiesAndReferences->requireValidReferences($nodeType, $node->getContext(), $caughtExceptions) as $key => $value) {
                $node->setProperty($key, $value);
            }

            if ($template->getHidden() === true) {
                $node->setHidden(true);
            }
            $this->ensureNodeHasUriPathSegment($node, $template);
            $this->applyTemplateRecursively($template->getChildNodes(), $node, $caughtExceptions);
        }
    }

    /**
     * All document node types get a uri path segment; if it is not explicitly set in the properties,
     * it should be built based on the title property
     *
     * @param Template|RootTemplate $template
     */
    private function ensureNodeHasUriPathSegment(NodeInterface $node, $template)
    {
        if (!$node->getNodeType()->isOfType('Neos.Neos:Document')) {
            return;
        }
        $properties = $template->getProperties();
        if (isset($properties['uriPathSegment'])) {
            return;
        }
        $node->setProperty('uriPathSegment', $this->nodeUriPathSegmentGenerator->generateUriPathSegment($node, $properties['title'] ?? null));
    }
}
