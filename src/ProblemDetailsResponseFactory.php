<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @api
 */
final readonly class ProblemDetailsResponseFactory implements ProblemDetailsResponseFactoryInterface
{
    public const string CONTENT_TYPE = 'application/problem+json';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /**
     * @param array<string, string|list<string>> $headers
     */
    #[\Override]
    public function toResponse(ProblemDetails $problemDetails, array $headers = []): ResponseInterface
    {
        $body = $this->streamFactory->createStream($problemDetails->toJson());
        $response = $this->responseFactory
            ->createResponse($problemDetails->status)
            ->withBody($body);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response
            ->withHeader('Content-Type', self::CONTENT_TYPE)
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
