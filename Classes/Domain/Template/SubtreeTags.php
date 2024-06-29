<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\Template;

use Neos\Flow\Annotations as Flow;

/**
 * Minimal shim of Neos9' \Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTags
 * Only Neos 8.3' hidden aka disabled tag is allowed.
 *
 * @Flow\Proxy(false)
 */
final class SubtreeTags implements \JsonSerializable
{
    private bool $isDisabled;

    private function __construct(bool $isDisabled)
    {
        $this->isDisabled = $isDisabled;
    }

    public static function createEmpty(): self
    {
        return new self(false);
    }

    public static function createDisabled(): self
    {
        return new self(true);
    }

    public function isDisabled(): bool
    {
        return $this->isDisabled;
    }

    public function jsonSerialize(): array
    {
        return $this->isDisabled ? ['disabled'] : [];
    }
}
