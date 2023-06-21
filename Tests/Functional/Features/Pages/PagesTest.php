<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\Pages;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTest;

class PagesTest extends AbstractNodeTemplateTest
{
    /** @test */
    public function itMatchesSnapshot1(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageNode,
            'Flowpack.NodeTemplates:Document.StaticPages',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('Pages1');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('Pages', $createdNode);
    }

    /** @test */
    public function itMatchesSnapsho2(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageNode,
            'Flowpack.NodeTemplates:Document.DynamicPages',
            [
                'title' => 'Page1'
            ]
        );

        // we use a different snapshot here, because the uriPathSegments are not included at this time
        $this->assertLastCreatedTemplateMatchesSnapshot('Pages2');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('Pages', $createdNode);
    }
}
