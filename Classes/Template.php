<?php
namespace Flowpack\NodeTemplates;

use Neos\Flow\Annotations as Flow;
use Flowpack\NodeTemplates\Service\EelEvaluationService;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Utility as NodeUtility;
use Neos\Neos\Service\NodeOperations;

class Template
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $properties;

    /**
     * @var array<Template>
     */
    protected $childNodes;

    /**
     * @var string
     */
    protected $when;

    /**
     * @var string
     */
    protected $withItems;

    /**
     * @var EelEvaluationService
     * @Flow\Inject
     */
    protected $eelEvaluationService;

    /**
     * @var NodeOperations
     * @Flow\Inject
     */
    protected $nodeOperations;

    /**
     * Template constructor
     *
     * @param string $type
     * @param string $name
     * @param array $properties
     * @param array<Template> $childNodes
     * @param string $when
     * @param string $withItems
     */
    public function __construct(
        $type = null,
        $name = null,
        array $properties = [],
        array $childNodes = [],
        $when = null,
        $withItems = null
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->properties = $properties;
        $this->childNodes = $childNodes;
        $this->when = $when;
        $this->withItems = $withItems;
    }

    /**
     * Apply this template to the given node while providing context for EEL processing
     *
     * @param NodeInterface $node
     * @param array $context
     */
    public function apply(NodeInterface $node, array $context)
    {
        $context['node'] = $node;

        // Check if this template should be applied at all
        if (!$this->isApplicable($context)) {
            return;
        }
        $this->setProperties($node, $context);

        // Create child nodes if applicable
        /** @var Template $childNodeTemplate */
        foreach ($this->childNodes as $childNodeTemplate) {
            $context['parentNode'] = $node;
            $childNodeTemplate->createOrFetchAndApply($node, $context);
        }
    }

    /**
     * @param NodeInterface $parentNode
     * @param array $context
     */
    public function createOrFetchAndApply(NodeInterface $parentNode, array $context)
    {
        if (!$this->isApplicable($context)) {
            return;
        }

        $items = $this->withItems;
        if (!$items) { // Not set
            $items = [false];
        } else if (preg_match(\Neos\Eel\Package::EelExpressionRecognizer, $items)) { // Eel expression
            $items = $this->eelEvaluationService->evaluateEelExpression($items, $context);
        } else { // Yaml array converted to comma-delimited string
            $items = explode(',', $items);
        }

        foreach ($items as $item) {
            $context['item'] = $item;
            $node = null;
            $name = $this->name;
            if (preg_match(\Neos\Eel\Package::EelExpressionRecognizer, $name)) {
                $name = $this->eelEvaluationService->evaluateEelExpression($name, $context);
            }
            if ($name !== null) {
                $flowQuery = new FlowQuery(array($parentNode));
                $node = $flowQuery->find($name)->get(0);
            }
            if (!$node instanceof NodeInterface) {
                $node = $this->nodeOperations->create($parentNode, ['nodeType' => $this->type, 'nodeName' => $name],
                    'into');

                // All document node types get a uri path segment; if it is not explicitly set in the properties,
                // it should be built based on the title property
                if ($node->getNodeType()->isOfType('Neos.Neos:Document')
                    && isset($this->properties['title'])
                    && !isset($this->properties['uriPathSegment'])) {
                    $node->setProperty('uriPathSegment', NodeUtility::renderValidNodeName($this->properties['title']));
                }
            }
            if ($node instanceof NodeInterface) {
                $this->apply($node, $context);
            }
        }
    }

    /**
     * @param array $context
     * @return bool
     */
    public function isApplicable(array $context)
    {
        $isApplicable = true;
        if ($this->when !== null) {
            $isApplicable = $this->when;
            if (preg_match(\Neos\Eel\Package::EelExpressionRecognizer, $isApplicable)) {
                $isApplicable = $this->eelEvaluationService->evaluateEelExpression($isApplicable, $context);
            }
        }
        return (bool)$isApplicable;
    }

    /**
     * TODO: Handle EEL parsing for nested properties
     *
     * @param NodeInterface $node
     * @param array $context
     */
    protected function setProperties(NodeInterface $node, array $context)
    {
        foreach ($this->properties as $property => $propertyValue) {
            if (preg_match(\Neos\Eel\Package::EelExpressionRecognizer, $propertyValue)) {
                $propertyValue = $this->eelEvaluationService->evaluateEelExpression($propertyValue, $context);
            }
            $node->setProperty($property, $propertyValue);
        }
    }


}
