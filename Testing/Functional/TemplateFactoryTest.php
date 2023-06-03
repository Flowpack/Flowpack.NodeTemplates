<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Flowpack\NodeTemplates\Domain\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\TemplateFactory;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Tests\FunctionalTestCase;

class TemplateFactoryTest extends FunctionalTestCase
{
    /** @test */
    public function exceptionsAreCaughtAndPartialTemplateIsBuild(): void
    {
        $nodeType = $this->objectManager->get(NodeTypeManager::class)->getNodeType('Flowpack.NodeTemplates:Content.WithEvaluationExceptions');

        $template = $this->objectManager->get(TemplateFactory::class)->createFromTemplateConfiguration(
            $nodeType->getOptions()['template'],
            [],
            CaughtExceptions::create()
        );

        $actual = json_encode($template, JSON_PRETTY_PRINT);
        self::assertJsonStringEqualsJsonFile(__DIR__ . '/Fixtures/WithEvaluationExceptions.partial.json', $actual);
    }
}
