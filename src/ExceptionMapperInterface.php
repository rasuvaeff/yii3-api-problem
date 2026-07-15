<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

use Throwable;

/**
 * @api
 */
interface ExceptionMapperInterface
{
    public function toProblem(Throwable $throwable): ?ProblemDetails;
}
