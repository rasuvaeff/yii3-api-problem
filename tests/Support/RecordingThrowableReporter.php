<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Tests\Support;

use Psr\Http\Message\ServerRequestInterface;
use Rasuvaeff\Yii3ApiProblem\ThrowableReporterInterface;
use Throwable;

final class RecordingThrowableReporter implements ThrowableReporterInterface
{
    public ?Throwable $throwable = null;

    public ?ServerRequestInterface $request = null;

    public int $calls = 0;

    #[\Override]
    public function report(Throwable $throwable, ServerRequestInterface $request): void
    {
        $this->throwable = $throwable;
        $this->request = $request;
        ++$this->calls;
    }
}
