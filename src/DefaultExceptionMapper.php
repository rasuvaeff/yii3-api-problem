<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * @api
 */
final readonly class DefaultExceptionMapper implements ExceptionMapperInterface
{
    /**
     * @param array<class-string<Throwable>, array{title: string, status: int, type?: string}> $exceptionMap
     */
    public function __construct(private array $exceptionMap = []) {}

    #[\Override]
    public function toProblem(Throwable $throwable): ?ProblemDetails
    {
        if ($throwable instanceof ProblemDetailsException) {
            return $throwable->getProblemDetails();
        }

        $mapping = $this->exceptionMap[$throwable::class] ?? null;
        if ($mapping !== null) {
            return ProblemDetails::create(
                title: $mapping['title'],
                status: $mapping['status'],
                type: $mapping['type'] ?? 'about:blank',
            );
        }

        if ($throwable instanceof InvalidArgumentException) {
            return ProblemDetails::fromStatus(status: 400);
        }

        if ($throwable instanceof RuntimeException) {
            return ProblemDetails::fromStatus(status: 500);
        }

        return null;
    }
}
