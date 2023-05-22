<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\NodeTemplateDumper;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Algorithms;

/** @Flow\Scope("singleton") */
class CommentService
{
    private const SERIALIZED_PATTERN = <<<'REGEX'
    /(?<indentation>[ ]*)(?<property>.*?): Comment<(?<identifier>[a-z0-9\-]{1,255})>/
    REGEX;

    /** @var array<Comment> */
    private array $comments;

    public function serialize(\Closure $commentRenderFunction): string
    {
        $identifier = Algorithms::generateUUID();
        $comment = new Comment($commentRenderFunction);
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
                throw new \Exception('Error while trying to render comment ' . $matches[0] . ' commentId doesnt exist.', 1684309524383);
            }
            return $comment->toYamlComment($indentation, $property);
        }, $yamlDump);
    }
}
