<?php

namespace Flowpack\NodeTemplates\Domain\ErrorHandling;


use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class ProcessingError
{
    private \Throwable $exception;

    private ?string $origin;

    private function __construct(\Throwable $exception, ?string $origin)
    {
        $this->exception = $exception;
        $this->origin = $origin;
    }

    public static function fromException(\Throwable $exception): self
    {
        return new self($exception, null);
    }

    public function withOrigin(string $origin): self
    {
        return new self($this->exception, $origin);
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function toMessage(): string
    {
        $messageLines = [];

        if ($this->origin) {
            $messageLines[] = $this->origin;
        }

        $level = 0;
        $exception = $this->exception;
        do {
            $level++;
            if ($level >= 8) {
                $messageLines[] = '...Recursion';
                break;
            }

            $reflexception = new \ReflectionClass($exception);
            $shortExceptionName = $reflexception->getShortName();
            if ($shortExceptionName === 'Exception') {
                $secondPartOfPackageName = explode('\\', $reflexception->getNamespaceName())[1] ?? '';
                $shortExceptionName = $secondPartOfPackageName . $shortExceptionName;
            }
            $messageLines[] = sprintf('%s(%s, %s)', $shortExceptionName, $exception->getMessage(), $exception->getCode());
        } while ($exception = $exception->getPrevious());

        return join(' | ', $messageLines);
    }
}
