<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\Variables;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTestCase;

class VariablesTest extends AbstractNodeTemplateTestCase
{
    /** @test */
    public function itMatchesSnapshot(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.Variables',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('Variables');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('Variables', $createdNode);
    }
}
