<?php

namespace Flowpack\NodeTemplates\NodeCreationHandler;

use Flowpack\NodeTemplates\Service\EelException;
use Flowpack\NodeTemplates\Template;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Exception\NodeConstraintException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMapper;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Error;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;

class TemplateNodeCreationHandler implements NodeCreationHandlerInterface
{
    /**
     * @var PropertyMapper
     * @Flow\Inject
     */
    protected $propertyMapper;

    /**
     * @var integer
     * @Flow\InjectConfiguration(path="nodeCreationDepth")
     */
    protected $nodeCreationDepth;

    /**
     * @var FeedbackCollection
     * @Flow\Inject
     */
    protected $feedbackCollection;

    /**
     * Create child nodes and change properties upon node creation
     *
     * @param NodeInterface $node The newly created node
     * @param array $data incoming data from the creationDialog
     */
    public function handle(NodeInterface $node, array $data): void
    {
        if ($node->getNodeType()->hasConfiguration('options.template')) {
            $templateConfiguration = $node->getNodeType()->getConfiguration('options.template');
        } else {
            return;
        }

        $propertyMappingConfiguration = $this->propertyMapper->buildPropertyMappingConfiguration();

        $subPropertyMappingConfiguration = $propertyMappingConfiguration;
        for ($i = 0; $i < $this->nodeCreationDepth; $i++) {
            $subPropertyMappingConfiguration = $subPropertyMappingConfiguration
                ->forProperty('childNodes.*')
                ->allowAllProperties();
        }

        /** @var Template $template */
        $template = $this->propertyMapper->convert(
            $templateConfiguration,
            Template::class,
            $propertyMappingConfiguration
        );

        $context = [
            'data' => $data,
            'triggeringNode' => $node,
        ];

        try {
            $template->apply($node, $context);
        } catch (\Exception $exception) {
            $this->handleExceptions($node, $exception);
        }
    }

    /**
     * Known exceptions are logged to the Neos.Ui and caught
     *
     * @param NodeInterface $node the newly created node
     * @param \Exception $exception the exception to handle
     * @throws \Exception in case the exception is unknown and cant be handled
     */
    private function handleExceptions(NodeInterface $node, \Exception $exception): void
    {
        $nodeTemplateError = new Error();
        $nodeTemplateError->setMessage(sprintf('Template for "%s" only partially applied. Please check the newly created nodes.', $node->getNodeType()->getLabel()));

        if ($exception instanceof NodeConstraintException) {
            $this->feedbackCollection->add(
                $nodeTemplateError
            );

            $error = new Error();
            $error->setMessage($exception->getMessage());
            $this->feedbackCollection->add(
                $error
            );
            return;
        }
        if ($exception instanceof EelException) {
            $this->feedbackCollection->add(
                $nodeTemplateError
            );

            $error = new Error();
            $error->setMessage(
                $exception->getMessage()
            );
            $this->feedbackCollection->add(
                $error
            );

            $level = 0;
            while (($exception = $exception->getPrevious()) && $level <= 8) {
                $level++;
                $error = new Error();
                $error->setMessage(
                    sprintf('%s [%s(%s)]', $exception->getMessage(), get_class($exception), $exception->getCode())
                );
                $this->feedbackCollection->add(
                    $error
                );
            }
            return;
        }
        throw $exception;
    }
}
