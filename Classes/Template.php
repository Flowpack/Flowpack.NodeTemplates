<?php
namespace Flowpack\NodeTemplates;

use Neos\Flow\Annotations as Flow;
use Flowpack\NodeTemplates\Service\EelEvaluationService;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Utility as NodeUtility;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Neos\Service\NodeOperations;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Error;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Utility\ObjectAccess;

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
     * Options can be used to configure third party processing
     *
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $when;

    /**
     * @var string
     */
    protected $withItems;

    /**
     * @var array
     */
    protected $withContext;

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
     * @var PersistenceManager
     * @Flow\Inject
     */
    protected $persistenceManager;

    /**
     * @var FeedbackCollection
     * @Flow\Inject
     */
    protected $feedbackCollection;

    /**
     * Template constructor
     *
     * @param string $type
     * @param string $name
     * @param array $properties
     * @param array<Template> $childNodes
     * @param array $options
     * @param string $when
     * @param string $withItems
     * @param array $withContext
     */
    public function __construct(
        $type = null,
        $name = null,
        array $properties = [],
        array $childNodes = [],
        array $options = [],
        $when = null,
        $withItems = null,
        $withContext = []
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->properties = $properties;
        $this->childNodes = $childNodes;
        $this->options = $options;
        $this->when = $when;
        $this->withItems = $withItems;
        $this->withContext = $withContext;
    }

    /**
     * Apply this template to the given node while providing context for EEL processing
     *
     * The entry point
     */
    public function apply(NodeInterface $node, array $context): void
    {
        $context = $this->mergeContextAndWithContext($context);
        $this->applyTemplateOnNode($node, $context);
    }

    private function applyTemplateOnNode(NodeInterface $node, array $context): void
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
            $childNodeTemplate->createOrFetchAndApply($node, $context);
        }

        $messages = [];
        $this->checkIfPropertiesOfNodeAreValid($node, $messages);
        foreach ($messages as $message) {
            $info = new Error();
            $info->setMessage('[NodeTemplate] ' . $message);
            $this->feedbackCollection->add(
                $info
            );
        }

        $this->emitNodeTemplateApplied($node, $context, $this->options);
    }

    /**
     * @deprecated will be made internal and private
     * @internal
     */
    public function createOrFetchAndApply(NodeInterface $parentNode, array $context): void
    {
        $context['parentNode'] = $parentNode;

        $context = $this->mergeContextAndWithContext($context);

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

        foreach ($items as $key => $item) {
            // only set item context if withItems is set in template to prevent losing item context from parent template
            if ($this->withItems) {
                $context['item'] = $item;
                $context['key'] = $key;
            }
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
                $type = $this->type;
                if (preg_match(\Neos\Eel\Package::EelExpressionRecognizer, $type)) {
                    $type = $this->eelEvaluationService->evaluateEelExpression($type, $context);
                }
                $node = $this->nodeOperations->create($parentNode, ['nodeType' => $type, 'nodeName' => $name], 'into');

                // All document node types get a uri path segment; if it is not explicitly set in the properties,
                // it should be built based on the title property
                if ($node->getNodeType()->isOfType('Neos.Neos:Document')
                    && isset($this->properties['title'])
                    && !isset($this->properties['uriPathSegment'])) {
                    $node->setProperty('uriPathSegment', NodeUtility::renderValidNodeName($this->properties['title']));
                }
            }
            if ($node instanceof NodeInterface) {
                $this->applyTemplateOnNode($node, $context);
            }
        }
    }

    public function isApplicable(array $context): bool
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
     */
    protected function setProperties(NodeInterface $node, array $context): void
    {
        foreach ($this->properties as $propertyName => $propertyValue) {
            //evaluate Eel only on string properties
            if (is_string($propertyValue) && preg_match(\Neos\Eel\Package::EelExpressionRecognizer, $propertyValue)) {
                $this->persistenceManager->persistAll();
                $propertyValue = $this->eelEvaluationService->evaluateEelExpression($propertyValue, $context);
            }
            if ($propertyName[0] === '_') {
                ObjectAccess::setProperty($node, substr($propertyName, 1), $propertyValue);
            } else {
                $node->setProperty($propertyName, $propertyValue);
            }
        }
    }

    /**
     * Merge `withContext` onto the current $context, evaluating EEL if necessary
     *
     * The option `withContext` takes an array of items whose value can be any yaml/php type
     * and might also contain eel expressions
     *
     * ```yaml
     * withContext:
     *   someText: '<p>foo</p>'
     *   processedData: "${String.trim(data.bla)}"
     *   booleanType: true
     *   arrayType: ["value"]
     * ```
     *
     * scopes and order of evaluation:
     *
     * - inside `withContext` the "upper" context may be accessed in eel expressions,
     * but sibling context values are not available
     *
     * - `withContext` is evaluated before `when` and `withItems` so you can access computed values,
     * that means the context `item` from `withItems` will not be available yet
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function mergeContextAndWithContext(array $context): array
    {
        if ($this->withContext === []) {
            return $context;
        }
        $withContext = [];
        foreach ($this->withContext as $key => $value) {
            if (is_string($value) && preg_match(\Neos\Eel\Package::EelExpressionRecognizer, $value)) {
                $value = $this->eelEvaluationService->evaluateEelExpression($value, $context);
            }
            $withContext[$key] = $value;
        }
        return array_merge($context, $withContext);
    }

    /**
     * A few checks are run against the properties of the node
     *
     * 1. It is checked, that a node only has properties set, that were declared in the NodeType
     *
     * 2. In case the property is a select-box, it is checked, that the current value is a valid option of the select-box
     *
     * 3. It is made sure is that a property value is never null for the reason:
     *  In case that due to a condition in the nodeTemplate `null` is assigned to a node property, it will override the defaultValue.
     *  This is a problem, as setting `null` might not be possible via the Neos UI and the Fusion rendering is most likely not going to handle this edge case.
     *  So we assume this must have been a mistake. A cleaner, but also more difficult way would be to actually assert that the type matches
     *
     * @param array<int, string> $messages by reference in case one of the above constraints is not met.
     */
    private function checkIfPropertiesOfNodeAreValid(NodeInterface $node, array &$messages = []): void
    {
        $nodeType = $node->getNodeType();
        $defaultValues = $nodeType->getDefaultValuesForProperties();
        foreach ($node->getProperties() as $propertyName => $propertyValue) {
            if (!isset($nodeType->getProperties()[$propertyName])) {
                $value = json_encode($propertyValue);
                $messages[] = "Property '$propertyName' is not declared in NodeType {$nodeType->getName()} but was set to: " . $value;
                continue;
            }
            if (array_key_exists($propertyName, $defaultValues) && $propertyValue === null) {
                $defaultValue = json_encode($defaultValues[$propertyName]);
                $messages[] = "Property '$propertyName' of node {$nodeType->getName()} is not supposed to be null. The default value would be: $defaultValue.";
                continue;
            }
            if ($propertyValue === null) {
                // in case the nodeIdentifier of a reference property cannot be resolved, null will be written to the property, and we guard against this
                // also we want to enforce the actual type of the property which is likely not null
                $messages[] = "Property '$propertyName' of node {$nodeType->getName()} was set to null. Type is {$nodeType->getPropertyType($propertyName)}";
                continue;
            }
            $propertyConfiguration = $nodeType->getProperties()[$propertyName];
            $editor = $propertyConfiguration["ui"]["inspector"]["editor"] ?? null;
            $type = $propertyConfiguration["type"] ?? null;
            $selectBoxValues = $propertyConfiguration["ui"]["inspector"]["editorOptions"]["values"] ?? null;
            if ($editor === 'Neos.Neos/Inspector/Editors/SelectBoxEditor' && $selectBoxValues && in_array($type, ["string", "array"], true)) {
                $selectedValue = $type === "string" ? [$propertyValue] : $propertyValue;
                $difference = array_diff($selectedValue, array_keys($selectBoxValues));
                if (\count($difference) !== 0) {
                    $messages[] = "Property '$propertyName' of node {$nodeType->getName()} has illegal select-box value(s): (" . join(", ", $difference) . ')';
                    continue;
                }
            }
        }
    }

    /**
     * Signals that a node template has been applied to the given node.
     *
     * @Flow\Signal
     * @api
     */
    public function emitNodeTemplateApplied(NodeInterface $node, array $context, array $options): void
    {
    }
}
