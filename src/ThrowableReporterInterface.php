<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Reports caught exceptions before they are converted to client-safe responses.
 *
 * Implementations must not throw.
 *
 * @api
 */
interface ThrowableReporterInterface
{
    public function report(Throwable $throwable, ServerRequestInterface $request): void;
}
