<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Tests;

use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsException;
use RuntimeException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ProblemDetailsException::class)]
final class ProblemDetailsExceptionTest
{
    public function carriesProblemStatusMessageAndPreviousException(): void
    {
        $problem = ProblemDetails::create(
            title: 'Conflict',
            status: 409,
            detail: 'The resource was changed',
        );
        $previous = new RuntimeException('storage failure');

        $exception = ProblemDetailsException::forProblem(
            details: $problem,
            previous: $previous,
            headers: ['Retry-After' => '120'],
        );

        Assert::same($exception->getProblemDetails(), $problem);
        Assert::same($exception->getCode(), 409);
        Assert::same($exception->getMessage(), 'The resource was changed');
        Assert::same($exception->getPrevious(), $previous);
        Assert::same($exception->getHeaders(), ['Retry-After' => '120']);
        Assert::instanceOf($exception, RuntimeException::class);
    }

    public function fallsBackToProblemTitleAsMessage(): void
    {
        $problem = ProblemDetails::fromStatus(status: 404);

        $exception = ProblemDetailsException::forProblem($problem);

        Assert::same($exception->getMessage(), 'Not Found');
    }
}
