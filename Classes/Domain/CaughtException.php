<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\Neos\Ui\Domain\Model\Feedback\AbstractMessageFeedback;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Error;

class CaughtException
{
    private \Throwable $exception;

    private ?string $cause;

    private function __construct(\Throwable $exception, ?string $cause)
    {
        $this->exception = $exception;
        $this->cause = $cause;
    }

    public static function fromException(\Throwable $exception): self
    {
        return new self($exception, null);
    }

    public function withCause(string $cause): self
    {
        return new self($this->exception, $cause);
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getCause(): ?string
    {
        return $this->cause;
    }

    public function toMessageFeedback(): AbstractMessageFeedback
    {
        $messageLines = [];

        if ($this->cause) {
            $messageLines[] = $this->cause;
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

        $error = new Error();
        $error->setMessage(join(' | ', $messageLines));
        return $error;
    }
}
