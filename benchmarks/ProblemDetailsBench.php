<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Benchmarks;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;
use Testo\Bench;

final class ProblemDetailsBench
{
    #[Bench(
        ['direct json_encode' => [self::class, 'serializeArray']],
        calls: 100_000,
        iterations: 5,
    )]
    public static function serializeProblem(): string
    {
        return self::problem()->toJson();
    }

    #[Bench(
        ['direct Nyholm response' => [self::class, 'createNyholmResponse']],
        calls: 50_000,
        iterations: 5,
    )]
    public static function createResponse(): ResponseInterface
    {
        $psr17Factory = new Psr17Factory();

        return (new ProblemDetailsResponseFactory($psr17Factory, $psr17Factory))
            ->toResponse(self::problem());
    }

    private static function problem(): ProblemDetails
    {
        return ProblemDetails::create(
            title: 'Validation failed',
            status: 422,
            type: 'https://example.com/problems/validation',
            extensions: ['errors' => ['email' => ['Invalid email address']]],
        );
    }

    public static function serializeArray(): string
    {
        return json_encode(
            self::problem()->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    public static function createNyholmResponse(): ResponseInterface
    {
        return new Response(
            status: 422,
            headers: [
                'Content-Type' => 'application/problem+json',
                'X-Content-Type-Options' => 'nosniff',
            ],
            body: self::problem()->toJson(),
        );
    }
}
