<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

use RuntimeException;
use Throwable;

/**
 * @api
 */
final class ProblemDetailsException extends RuntimeException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    private function __construct(
        private readonly ProblemDetails $problemDetails,
        private readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $problemDetails->detail ?? $problemDetails->title,
            code: $problemDetails->status,
            previous: $previous,
        );
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public static function forProblem(
        ProblemDetails $details,
        ?Throwable $previous = null,
        array $headers = [],
    ): self {
        return new self(problemDetails: $details, headers: $headers, previous: $previous);
    }

    public function getProblemDetails(): ProblemDetails
    {
        return $this->problemDetails;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
