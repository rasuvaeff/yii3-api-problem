<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ProblemDetailsResponseFactory::class)]
final class ProblemDetailsResponseFactoryTest
{
    public function createsHardenedRfc9457Response(): void
    {
        $psr17Factory = new Psr17Factory();
        $factory = new ProblemDetailsResponseFactory($psr17Factory, $psr17Factory);
        $problem = ProblemDetails::create(
            title: 'Not found',
            status: 404,
            detail: 'Widget 42 does not exist',
        );

        $response = $factory->toResponse($problem);

        Assert::same($response->getStatusCode(), 404);
        Assert::same($response->getHeaderLine('Content-Type'), 'application/problem+json');
        Assert::same($response->getHeaderLine('X-Content-Type-Options'), 'nosniff');
        Assert::same(
            json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
            $problem->toArray(),
        );
    }

    public function addsTransportHeadersWithoutAllowingSecurityOverrides(): void
    {
        $psr17Factory = new Psr17Factory();
        $factory = new ProblemDetailsResponseFactory($psr17Factory, $psr17Factory);

        $response = $factory->toResponse(
            ProblemDetails::fromStatus(status: 429),
            headers: [
                'Retry-After' => '120',
                'WWW-Authenticate' => ['Bearer realm="api"', 'Basic realm="api"'],
                'Content-Type' => 'text/html',
                'X-Content-Type-Options' => 'off',
            ],
        );

        Assert::same($response->getHeaderLine('Retry-After'), '120');
        Assert::same($response->getHeader('WWW-Authenticate'), ['Bearer realm="api"', 'Basic realm="api"']);
        Assert::same($response->getHeaderLine('Content-Type'), 'application/problem+json');
        Assert::same($response->getHeaderLine('X-Content-Type-Options'), 'nosniff');
    }
}
