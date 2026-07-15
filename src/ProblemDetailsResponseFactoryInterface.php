<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

use Psr\Http\Message\ResponseInterface;

/**
 * @api
 */
interface ProblemDetailsResponseFactoryInterface
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function toResponse(ProblemDetails $problemDetails, array $headers = []): ResponseInterface;
}
