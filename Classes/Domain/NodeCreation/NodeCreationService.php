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
 * @Flow\Scope("singleton")
 */
class NodeCreationService
{
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
     * Applies the root template and its descending configured child node templates on the given node.
     * @throws \InvalidArgumentException
     */
    public function createMutatorCollection(RootTemplate $template, NodeType $nodeType, Context $subgraph, CaughtExceptions $caughtExceptions): NodeMutatorCollection
    {
        $node = TransientNode::forRegular($nodeType, $subgraph, $template->getProperties());

        $validProperties = array_merge(
            $this->propertiesProcessor->processAndValidateProperties($node, $caughtExceptions),
            $this->referencesProcessor->processAndValidateReferences($node, $caughtExceptions)
        );

        $nodeMutators = NodeMutatorCollection::from(
            NodeMutator::setProperties($validProperties),
            $this->createMutatorForUriPathSegment($template->getProperties()),
        )->merge(
            $this->createMutatorCollectionFromTemplate(
                $template->getChildNodes(),
                $node,
                $caughtExceptions
            )
        );

        return $nodeMutators;
    }

    private function createMutatorCollectionFromTemplate(Templates $templates, TransientNode $parentNode, CaughtExceptions $caughtExceptions): NodeMutatorCollection
    {
        $nodeMutators = NodeMutatorCollection::empty();

        // `hasAutoCreatedChildNode` actually has a bug; it looks up the NodeName parameter against the raw configuration instead of the transliterated NodeName
        // https://github.com/neos/neos-ui/issues/3527
        $parentNodesAutoCreatedChildNodes = $parentNode->getNodeType()->getAutoCreatedChildNodes();
        foreach ($templates as $template) {
            if ($template->getName() && isset($parentNodesAutoCreatedChildNodes[$template->getName()->__toString()])) {
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

                $nodeMutators = $nodeMutators->withNodeMutators(
                    NodeMutator::isolated(
                        NodeMutatorCollection::from(
                            NodeMutator::selectChildNode($template->getName()),
                            NodeMutator::setProperties($validProperties)
                        )->merge($this->createMutatorCollectionFromTemplate(
                            $template->getChildNodes(),
                            $node,
                            $caughtExceptions
                        ))
                    )
                );

                continue;

            }
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

            $nodeType = $this->nodeTypeManager->getNodeType($template->getType()->getValue());

            if ($nodeType->isAbstract()) {
                $caughtExceptions->add(
                    CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be a non abstract NodeType. Got: "%s".', $template->getType()->getValue()), 1686417628976))
                );
                continue;
            }

            try {
                $parentNode->requireConstraintsImposedByAncestorsAreMet($nodeType);
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

            $nodeMutators = $nodeMutators->withNodeMutators(
                NodeMutator::isolated(
                    NodeMutatorCollection::from(
                        NodeMutator::createAndSelectNode($template->getType(), $template->getName()),
                        NodeMutator::setProperties($validProperties),
                        $this->createMutatorForUriPathSegment($template->getProperties())
                    )->merge($this->createMutatorCollectionFromTemplate(
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
        return NodeMutator::unsafeFromClosure(function (NodeInterface $previousNode) use ($properties) {
            if (!$previousNode->getNodeType()->isOfType('Neos.Neos:Document')) {
                return;
            }
            if (isset($properties['uriPathSegment'])) {
                return;
            }
            $previousNode->setProperty('uriPathSegment', $this->nodeUriPathSegmentGenerator->generateUriPathSegment($previousNode, $properties['title'] ?? null));
        });
    }
}
