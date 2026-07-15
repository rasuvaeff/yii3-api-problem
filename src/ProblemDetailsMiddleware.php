<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * @api
 */
final readonly class ProblemDetailsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProblemDetailsResponseFactoryInterface $responseFactory,
        private ?ExceptionMapperInterface $exceptionMapper = null,
        private bool $debug = false,
        private ?CorrelationIdProvider $correlationIdProvider = null,
        private ?ThrowableReporterInterface $throwableReporter = null,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ProblemDetailsException $exception) {
            $throwable = $exception;
            $problemDetails = $exception->getProblemDetails();
            $headers = $exception->getHeaders();
        } catch (Throwable $throwable) {
            $problemDetails = $this->mapThrowable($throwable);
            $headers = [];
        }

        $this->throwableReporter?->report($throwable, $request);

        $correlationId = $this->correlationIdProvider?->getCorrelationId();
        if ($correlationId !== null) {
            $problemDetails = $problemDetails->withInstance($correlationId);
        }

        return $this->responseFactory->toResponse($problemDetails, $headers);
    }

    private function mapThrowable(Throwable $throwable): ProblemDetails
    {
        $problemDetails = $this->exceptionMapper?->toProblem($throwable)
            ?? ProblemDetails::fromStatus(status: 500);

        if (!$this->debug) {
            return $problemDetails;
        }

        return $problemDetails
            ->withDetail($throwable->getMessage())
            ->withExtension('trace', $throwable->getTraceAsString());
    }
}
