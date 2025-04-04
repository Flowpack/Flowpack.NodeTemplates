<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\NodeTemplateDumper;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Algorithms;

/**
 * Since the yaml dumper doesn't support comments, we insert `Comment<id>` markers into the array via {@see Comments::addCommentAndGetMarker}
 * that will be dumped and later can be processed via {@see Comments::renderCommentsInYamlDump}
 *
 * A comment is just a wrapper around a render function that will be called during {@see Comments::renderCommentsInYamlDump}
 *
 * @Flow\Proxy(false)
 */
class Comments
{
    private const SERIALIZED_PATTERN = <<<'REGEX'
    /(?<indentation>[ ]*)(?<property>.*?): Comment<(?<identifier>[a-z0-9\-]{1,255})>/
    REGEX;

    /** @var array<Comment> */
    private array $comments;

    private function __construct()
    {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function addCommentAndGetMarker(Comment $comment): string
    {
        $identifier = Algorithms::generateUUID();
        $this->comments[$identifier] = $comment;
        return 'Comment<' . $identifier . '>';
    }

    public function renderCommentsInYamlDump(string $yamlDump): string
    {
        return preg_replace_callback(self::SERIALIZED_PATTERN, function (array $matches) {
            [
                'indentation' => $indentation,
                'property' => $property,
                'identifier' => $identifier
            ] = $matches;
            $comment = $this->comments[$identifier] ?? null;
            if (!$comment instanceof Comment) {
                throw new \Exception('Error while trying to render comment ' . $matches[0] . '. Reason: comment id doesnt exist.', 1684309524383);
            }
            return $comment->toYamlComment($indentation, $property);
        }, $yamlDump) ?? throw new \Exception('Error in preg_replace_callback while trying to render comments.');
    }
}
