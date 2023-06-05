<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use PHPUnit\Framework\Assert;

trait SnapshotTrait
{
    private function assertStringEqualsFileOrCreateSnapshot(string $snapshotFileName, string $expectedString): void
    {
        if (getenv('CREATE_SNAPSHOT') === '1') {
            file_put_contents($snapshotFileName, $expectedString);
            $this->markTestSkipped("Wrote snapshot: $snapshotFileName");
        }
        Assert::assertStringEqualsFile($snapshotFileName, $expectedString);
    }
}
