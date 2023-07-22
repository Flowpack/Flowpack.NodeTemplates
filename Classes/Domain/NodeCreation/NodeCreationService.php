<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template\Templates;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;

/**
 * Declares the steps how to create a node subtree starting from the root template {@see RootTemplate}
 *
 * The steps can to be applied to create the node structure via {@see NodeMutatorCollection::executeWithStartingNode()}
 *
 * @Flow\Scope("singleton")
 */
class NodeCreationService
{
    /**
     * @Flow\Inject
     * @var NodeUriPathSegmentGenerator
     */
    protected $nodeUriPathSegmentGenerator;

    /**
     * @Flow\Inject
     * @var PropertiesProcessor
     */
    protected $propertiesProcessor;

    /**
     * @Flow\Inject
     * @var ReferencesProcessor
     */
    protected $referencesProcessor;

    /**
     * Creates mutator {@see NodeMutatorCollection} for the root template and its descending configured child node templates to be applied on a node.
     * @throws \InvalidArgumentException
     */
    public function createMutatorsForRootTemplate(RootTemplate $template, NodeType $nodeType, NodeTypeManager $nodeTypeManager, Context $subgraph, CaughtExceptions $caughtExceptions): NodeMutatorCollection
    {
        $node = TransientNode::forRegular($nodeType, $nodeTypeManager, $subgraph, $template->getProperties());

        $validProperties = array_merge(
            $this->propertiesProcessor->processAndValidateProperties($node, $caughtExceptions),
            $this->referencesProcessor->processAndValidateReferences($node, $caughtExceptions)
        );

        return NodeMutatorCollection::from(
            NodeMutator::setProperties($validProperties),
            $this->createMutatorForUriPathSegment($template->getProperties()),
        )->merge(
            $this->createMutatorsForChildNodeTemplates(
                $template->getChildNodes(),
                $node,
                $caughtExceptions
            )
        );
    }

    private function createMutatorsForChildNodeTemplates(Templates $templates, TransientNode $parentNode, CaughtExceptions $caughtExceptions): NodeMutatorCollection
    {
        $nodeMutators = NodeMutatorCollection::empty();

        // `hasAutoCreatedChildNode` actually has a bug; it looks up the NodeName parameter against the raw configuration instead of the transliterated NodeName
        // https://github.com/neos/neos-ui/issues/3527
        $parentNodesAutoCreatedChildNodes = $parentNode->getNodeType()->getAutoCreatedChildNodes();
        foreach ($templates as $template) {
            if ($template->getName() && isset($parentNodesAutoCreatedChildNodes[$template->getName()->__toString()])) {
                /**
                 * Case 1: Auto created child nodes
                 */
                if ($template->getType() !== null) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template cant mutate type of auto created child nodes. Got: "%s"', $template->getType()->getValue()), 1685999829307))
                    );
                    // we continue processing the node
                }

                $node = $parentNode->forTetheredChildNode($template->getName(), $template->getProperties());

                $validProperties = array_merge(
                    $this->propertiesProcessor->processAndValidateProperties($node, $caughtExceptions),
                    $this->referencesProcessor->processAndValidateReferences($node, $caughtExceptions)
                );

                $nodeMutators = $nodeMutators->append(
                    NodeMutator::isolated(
                        NodeMutatorCollection::from(
                            NodeMutator::selectChildNode($template->getName()),
                            NodeMutator::setProperties($validProperties)
                        )->merge($this->createMutatorsForChildNodeTemplates(
                            $template->getChildNodes(),
                            $node,
                            $caughtExceptions
                        ))
                    )
                );

                continue;
            }

            /**
             * Case 2: Regular to be created nodes (non auto-created nodes)
             */
            if ($template->getType() === null) {
                $caughtExceptions->add(
                    CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be set for non auto created child nodes.'), 1685999829307))
                );
                continue;
            }
            if (!$parentNode->getNodeTypeManager()->hasNodeType($template->getType()->getValue())) {
                $caughtExceptions->add(
                    CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be a valid NodeType. Got: "%s".', $template->getType()->getValue()), 1685999795564))
                );
                continue;
            }

            $nodeType = $parentNode->getNodeTypeManager()->getNodeType($template->getType()->getValue());

            if ($nodeType->isAbstract()) {
                $caughtExceptions->add(
                    CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be a non abstract NodeType. Got: "%s".', $template->getType()->getValue()), 1686417628976))
                );
                continue;
            }

            try {
                $parentNode->requireConstraintsImposedByAncestorsToBeMet($nodeType);
            } catch (NodeConstraintException $nodeConstraintException) {
                $caughtExceptions->add(
                    CaughtException::fromException($nodeConstraintException)
                );
                continue;
            }

            $node = $parentNode->forRegularChildNode($nodeType, $template->getProperties());

            $validProperties = array_merge(
                $this->propertiesProcessor->processAndValidateProperties($node, $caughtExceptions),
                $this->referencesProcessor->processAndValidateReferences($node, $caughtExceptions)
            );

            $nodeMutators = $nodeMutators->append(
                NodeMutator::isolated(
                    NodeMutatorCollection::from(
                        NodeMutator::createAndSelectNode($template->getType(), $template->getName()),
                        NodeMutator::setProperties($validProperties),
                        $this->createMutatorForUriPathSegment($template->getProperties())
                    )->merge($this->createMutatorsForChildNodeTemplates(
                        $template->getChildNodes(),
                        $node,
                        $caughtExceptions
                    ))
                )
            );

        }

        return $nodeMutators;
    }

    /**
     * All document node types get a uri path segment; if it is not explicitly set in the properties,
     * it should be built based on the title property
     */
    private function createMutatorForUriPathSegment(array $properties): NodeMutator
    {
        return NodeMutator::unsafeFromClosure(function (NodeInterface $nodePointer) use ($properties) {
            if (!$nodePointer->getNodeType()->isOfType('Neos.Neos:Document')) {
                return;
            }
            if (isset($properties['uriPathSegment'])) {
                return;
            }
            $nodePointer->setProperty('uriPathSegment', $this->nodeUriPathSegmentGenerator->generateUriPathSegment($nodePointer, $properties['title'] ?? null));
        });
    }
}
