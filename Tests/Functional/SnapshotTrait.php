<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use PHPUnit\Framework\Assert;

trait SnapshotTrait
{
    private function assertStringEqualsFileOrCreateSnapshot(string $snapshotFileName, string $expectedString): void
    {
        $expectedString = rtrim($expectedString, "\n") . "\n";
        if (getenv('CREATE_SNAPSHOT') === '1') {
            file_put_contents($snapshotFileName, $expectedString);
            $this->addWarning('Created snapshot.');
        }
        Assert::assertStringEqualsFile($snapshotFileName, $expectedString);
    }
}
