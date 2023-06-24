<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\NodeCreationHandler\DocumentTitleNodeCreationHandler;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationCommands;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;

/**
 * Augments the original neos ui document title node creation handler, which takes care of setting the "uriPathSegment" based of the title.
 * This handler steps in when a node has a node template with the property `uriPathSegment`.
 * In this case we will prevent the original handler from being called, as we handle setting the `uriPathSegment` ourselves and the original handler will just override our `uriPathSegment` again.
 *
 * @todo once we have sorting with https://github.com/neos/neos-ui/pull/3511 we can put our handler at the end instead.
 */
class DelegatingDocumentTitleNodeCreationHandler implements NodeCreationHandlerInterface
{
    /**
     * @Flow\Inject
     * @var DocumentTitleNodeCreationHandler
     */
    protected $originalDocumentTitleNodeCreationHandler;

    public function handle(
        NodeCreationCommands $commands,
        array $data,
        ContentRepository $contentRepository
    ): NodeCreationCommands {
        $nodeType = $contentRepository->getNodeTypeManager()
            ->getNodeType($commands->first->nodeTypeName);
        $template = $nodeType->getOptions()['template'] ?? null;
        if (
            !$template
            || !isset($template['properties']['uriPathSegment'])
        ) {
            return $this->originalDocumentTitleNodeCreationHandler->handle($commands, $data, $contentRepository);
        }

        // do nothing, as we handle this already when applying the template
        return $commands;
    }
}
