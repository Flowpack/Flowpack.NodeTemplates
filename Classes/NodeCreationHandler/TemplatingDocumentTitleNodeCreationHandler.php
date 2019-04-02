<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\NodeCreationHandler;

use Neos\Eel\Package;
use Neos\Flow\Annotations as Flow;
use Flowpack\NodeTemplates\Service\EelEvaluationService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;

class TemplatingDocumentTitleNodeCreationHandler implements NodeCreationHandlerInterface
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
        $title = null;

        if (!$node->getNodeType()->isOfType('Neos.Neos:Document')) {
            return;
        }

        $titleTemplate = $node->getNodeType()->getOptions()['template']['properties']['title'] ?? '';

        if ($titleTemplate === '') {
            $title = $data['title'] ?? null;
        } else {
            if (preg_match(Package::EelExpressionRecognizer, $titleTemplate)) {
                $context = [
                    'data' => $data,
                    'triggeringNode' => $node,
                ];

                $title = $this->eelEvaluationService->evaluateEelExpression($titleTemplate, $context);
            }
        }

        $node->setProperty('title', (string) $title);
        $node->setProperty('uriPathSegment', $this->nodeUriPathSegmentGenerator->generateUriPathSegment($node, $title));
    }
}
