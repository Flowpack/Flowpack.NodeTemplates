<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\NodeCreationHandler;


use Neos\Flow\Annotations as Flow;
use Flowpack\NodeTemplates\Service\EelEvaluationService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Neos\Ui\NodeCreationHandler\DocumentTitleNodeCreationHandler;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;

class TemplatingDocumentTitleNodeCreationHandler extends DocumentTitleNodeCreationHandler
{
    /**
     * @var EelEvaluationService
     * @Flow\Inject
     */
    protected $eelEvaluationService;

    /**
     * @Flow\Inject
     * @var NodeUriPathSegmentGenerator
     */
    protected $nodeUriPathSegmentGenerator;

    /**
     * @param NodeInterface $node
     * @param array $data
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Neos\Exception
     */
    public function handle(NodeInterface $node, array $data)
    {
        if (!$node->getNodeType()->isOfType('Neos.Neos:Document')) {
            return;
        }

        $options = $node->getNodeType()->getOptions();
        if (!empty($options['template']['properties']['title'])) {
            $titleTemplate = $options['template']['properties']['title'];

            if (preg_match(\Neos\Eel\Package::EelExpressionRecognizer, $titleTemplate)) {
                $context = [
                    'data' => $data,
                    'triggeringNode' => $node,
                ];

                $data['title'] = $this->eelEvaluationService->evaluateEelExpression($titleTemplate, $context);
            } else {
                $data['title'] = $titleTemplate;
            }
        }

        if (isset($data['title'])) {
            $node->setProperty('title', $data['title']);
        }
        $node->setProperty('uriPathSegment', $this->nodeUriPathSegmentGenerator->generateUriPathSegment($node, $data['title'] ?? null));
    }
}
