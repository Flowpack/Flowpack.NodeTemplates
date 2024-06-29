<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\DisabledTag;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTestCase;

class DisabledTagTest extends AbstractNodeTemplateTestCase
{
    protected static bool $showDisabledNodesInSubgraph = true;

    /** @test */
    public function itMatchesSnapshot1(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.DisabledTag',
            []
        );

        $this->assertNoExceptionsWereCaught();

        $this->assertLastCreatedTemplateMatchesSnapshot('DisabledTag');
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('DisabledTag', $createdNode);
    }
}
