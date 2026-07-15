<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Tests;

use Exception;
use InvalidArgumentException;
use Rasuvaeff\Yii3ApiProblem\DefaultExceptionMapper;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsException;
use Rasuvaeff\Yii3ApiProblem\Tests\Support\ChildUserNotFoundException;
use Rasuvaeff\Yii3ApiProblem\Tests\Support\UserNotFoundException;
use RuntimeException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(DefaultExceptionMapper::class)]
final class DefaultExceptionMapperTest
{
    public function unwrapsProblemDetailsException(): void
    {
        $problem = ProblemDetails::fromStatus(status: 409);
        $mapper = new DefaultExceptionMapper();

        Assert::same($mapper->toProblem(ProblemDetailsException::forProblem($problem)), $problem);
    }

    public function mapsConfiguredExactClass(): void
    {
        $mapper = new DefaultExceptionMapper([
            UserNotFoundException::class => [
                'type' => 'https://example.com/problems/user-not-found',
                'title' => 'User not found',
                'status' => 404,
            ],
        ]);

        $problem = $mapper->toProblem(new UserNotFoundException());

        Assert::same($problem?->toArray(), [
            'type' => 'https://example.com/problems/user-not-found',
            'title' => 'User not found',
            'status' => 404,
        ]);
    }

    public function configuredMapDoesNotMatchSubclasses(): void
    {
        $mapper = new DefaultExceptionMapper([
            UserNotFoundException::class => ['title' => 'User not found', 'status' => 404],
        ]);

        $problem = $mapper->toProblem(new ChildUserNotFoundException());

        Assert::same($problem?->status, 500);
    }

    public function mapsInvalidArgumentToBadRequest(): void
    {
        $problem = (new DefaultExceptionMapper())->toProblem(new InvalidArgumentException());

        Assert::same($problem?->status, 400);
        Assert::same($problem?->title, 'Bad Request');
    }

    public function mapsRuntimeExceptionToInternalServerError(): void
    {
        $problem = (new DefaultExceptionMapper())->toProblem(new RuntimeException());

        Assert::same($problem?->status, 500);
        Assert::same($problem?->title, 'Internal Server Error');
    }

    public function returnsNullForUnknownThrowable(): void
    {
        Assert::null((new DefaultExceptionMapper())->toProblem(new Exception()));
    }
}
