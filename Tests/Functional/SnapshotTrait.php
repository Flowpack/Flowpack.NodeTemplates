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
            return;
        }
        Assert::assertStringEqualsFile($snapshotFileName, $expectedString);
    }

    private function assertJsonStringEqualsJsonFileOrCreateSnapshot(string $snapshotFileName, string $expectedJsonString): void
    {
        $expectedJsonString = rtrim($expectedJsonString, "\n") . "\n";
        if (getenv('CREATE_SNAPSHOT') === '1') {
            file_put_contents($snapshotFileName, $expectedJsonString);
            $this->addWarning('Created snapshot.');
            return;
        }
        Assert::assertJsonStringEqualsJsonFile($snapshotFileName, $expectedJsonString);
    }
}
