<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\Exceptions;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTestCase;
use Flowpack\NodeTemplates\Tests\Functional\WithConfigurationTrait;

class ExceptionsTest extends AbstractNodeTemplateTestCase
{
    use WithConfigurationTrait;

    /** @test */
    public function exceptionsAreCaughtAndPartialTemplateIsBuild(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.SomeExceptions',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('SomeExceptions');

        $this->assertCaughtExceptionsMatchesSnapshot('SomeExceptions');
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('SomeExceptions', $createdNode);
    }

    /** @test */
    public function exceptionsAreCaughtAndPartialTemplateIsNotBuild(): void
    {
        $this->withMockedConfigurationSettings([
            'Flowpack' => [
                'NodeTemplates' => [
                    'exceptionHandling' => [
                        'templateConfigurationProcessing' => [
                            'stopOnException' => true
                        ]
                    ]
                ]
            ]
        ], function () {
            $createdNode = $this->createNodeInto(
                $this->homePageMainContentCollectionNode,
                'Flowpack.NodeTemplates:Content.OnlyExceptions',
                []
            );

            $this->assertLastCreatedTemplateMatchesSnapshot('OnlyExceptions');

            // self::assertSame([
            //     [
            //         'message' => 'Template for "WithOneEvaluationException" was not applied. Only Node /sites/test-site/homepage/main/new-node@live[Flowpack.NodeTemplates:Content.WithOneEvaluationException] was created.',
            //         'severity' => 'ERROR'
            //     ],
            //     [
            //         'message' => 'Expression "${\'left open" in "childNodes.abort.when" | EelException(The EEL expression "${\'left open" was not a valid EEL expression. Perhaps you forgot to wrap it in ${...}?, 1410441849)',
            //         'severity' => 'ERROR'
            //     ]
            // ], $this->getMessagesOfFeedbackCollection());


            $this->assertCaughtExceptionsMatchesSnapshot('OnlyExceptions');
            $this->assertNodeDumpAndTemplateDumpMatchSnapshot('OnlyExceptions', $createdNode);
        });
    }

}
