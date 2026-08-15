<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Tests;

use Exception;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Rasuvaeff\Yii3ApiProblem\CorrelationIdProvider;
use Rasuvaeff\Yii3ApiProblem\DefaultExceptionMapper;
use Rasuvaeff\Yii3ApiProblem\ExceptionMapperInterface;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsException;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsMiddleware;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;
use Rasuvaeff\Yii3ApiProblem\Tests\Support\FakeHandler;
use Rasuvaeff\Yii3ApiProblem\Tests\Support\RecordingThrowableReporter;
use Rasuvaeff\Yii3ApiProblem\ThrowableReporterInterface;
use RuntimeException;
use Testo\Assert;
use Testo\Assert\Api\Json\JsonAbstract;
use Testo\Codecov\Covers;
use Testo\Test;
use Throwable;

#[Test]
#[Covers(ProblemDetailsMiddleware::class)]
final class ProblemDetailsMiddlewareTest
{
    public function passesSuccessfulResponseThroughUnchanged(): void
    {
        $handler = new FakeHandler();

        $response = $this->middleware()->process(new ServerRequest('GET', '/'), $handler);

        Assert::same($response->getStatusCode(), 204);
        Assert::same($response->getHeaderLine('Content-Type'), '');
    }

    public function rendersExplicitProblemWithoutRemovingDetail(): void
    {
        $problem = ProblemDetails::create(
            title: 'Validation failed',
            status: 422,
            detail: 'Email is invalid',
        );
        $exception = ProblemDetailsException::forProblem(
            details: $problem,
            headers: ['Retry-After' => '60'],
        );
        $handler = $this->throwingHandler($exception);

        $response = $this->middleware()->process(new ServerRequest('POST', '/users'), $handler);

        Assert::same($response->getStatusCode(), 422);
        Assert::same($response->getHeaderLine('Retry-After'), '60');
        Assert::json((string) $response->getBody())
            ->isObject()
            ->assertPath('$.detail', static function (JsonAbstract $json): void {
                Assert::same($json->decode(), 'Email is invalid');
            });
    }

    public function reportsGenericThrowableWithRequestContext(): void
    {
        $reporter = new RecordingThrowableReporter();
        $throwable = new RuntimeException('Database failed');
        $request = new ServerRequest('GET', '/reports');

        $this->middleware(throwableReporter: $reporter)
            ->process($request, $this->throwingHandler($throwable));

        Assert::same($reporter->calls, 1);
        Assert::same($reporter->throwable, $throwable);
        Assert::same($reporter->request, $request);
    }

    public function reportsExplicitProblemException(): void
    {
        $reporter = new RecordingThrowableReporter();
        $exception = ProblemDetailsException::forProblem(ProblemDetails::fromStatus(status: 409));

        $this->middleware(throwableReporter: $reporter)
            ->process(new ServerRequest('POST', '/orders'), $this->throwingHandler($exception));

        Assert::same($reporter->throwable, $exception);
    }

    public function doesNotReportSuccessfulResponse(): void
    {
        $reporter = new RecordingThrowableReporter();

        $this->middleware(throwableReporter: $reporter)
            ->process(new ServerRequest('GET', '/health'), new FakeHandler());

        Assert::same($reporter->calls, 0);
    }


    public function productionResponseDoesNotLeakGenericExceptionMessage(): void
    {
        $handler = $this->throwingHandler(new RuntimeException('Database password is secret'));

        $response = $this->middleware(new DefaultExceptionMapper())
            ->process(new ServerRequest('GET', '/'), $handler);
        $body = $this->body($response);

        Assert::same($response->getStatusCode(), 500);
        Assert::same($body['title'], 'Internal Server Error');
        Assert::false(array_key_exists('detail', $body));
        Assert::false(array_key_exists('trace', $body));
    }

    public function debugResponseIncludesMessageAndTrace(): void
    {
        $handler = $this->throwingHandler(new RuntimeException('Database failed'));

        $response = $this->middleware(new DefaultExceptionMapper(), debug: true)
            ->process(new ServerRequest('GET', '/'), $handler);
        $body = $this->body($response);

        Assert::same($body['detail'], 'Database failed');
        Assert::true(array_key_exists('trace', $body));
    }

    public function nullMapperResultFallsBackToGeneric500(): void
    {
        $mapper = new class implements ExceptionMapperInterface {
            #[\Override]
            public function toProblem(Throwable $throwable): ?ProblemDetails
            {
                return null;
            }
        };
        $handler = $this->throwingHandler(new Exception('unknown failure'));

        $response = $this->middleware($mapper)->process(new ServerRequest('GET', '/'), $handler);

        Assert::same($this->body($response), [
            'type' => 'about:blank',
            'title' => 'Internal Server Error',
            'status' => 500,
        ]);
    }
    public function missingMapperFallsBackToGeneric500(): void
    {
        $handler = $this->throwingHandler(new Exception('unknown failure'));

        $response = $this->middleware()->process(new ServerRequest('GET', '/'), $handler);

        Assert::same($response->getStatusCode(), 500);
    }

    public function usesProblemReturnedByMapper(): void
    {
        $mapper = new class implements ExceptionMapperInterface {
            #[\Override]
            public function toProblem(Throwable $throwable): \Rasuvaeff\Yii3ApiProblem\ProblemDetails
            {
                return ProblemDetails::fromStatus(status: 418);
            }
        };
        $handler = $this->throwingHandler(new Exception('mapped failure'));

        $response = $this->middleware($mapper)->process(new ServerRequest('GET', '/'), $handler);

        Assert::same($response->getStatusCode(), 418);
    }


    public function correlationIdBecomesProblemInstance(): void
    {
        $provider = new class implements CorrelationIdProvider {
            #[\Override]
            public function getCorrelationId(): string
            {
                return 'request-abc';
            }
        };
        $handler = $this->throwingHandler(new RuntimeException());

        $response = $this->middleware(
            exceptionMapper: new DefaultExceptionMapper(),
            correlationIdProvider: $provider,
        )->process(new ServerRequest('GET', '/'), $handler);

        Assert::json((string) $response->getBody())
            ->isObject()
            ->assertPath('$.instance', static function (JsonAbstract $json): void {
                Assert::same($json->decode(), 'request-abc');
            });
    }

    public function nullCorrelationIdLeavesInstanceAbsent(): void
    {
        $provider = new class implements CorrelationIdProvider {
            #[\Override]
            public function getCorrelationId(): ?string
            {
                return null;
            }
        };
        $handler = $this->throwingHandler(new RuntimeException());

        $response = $this->middleware(
            exceptionMapper: new DefaultExceptionMapper(),
            correlationIdProvider: $provider,
        )->process(new ServerRequest('GET', '/'), $handler);

        Assert::false(array_key_exists('instance', $this->body($response)));
    }

    private function middleware(
        ?ExceptionMapperInterface $exceptionMapper = null,
        bool $debug = false,
        ?CorrelationIdProvider $correlationIdProvider = null,
        ?ThrowableReporterInterface $throwableReporter = null,
    ): ProblemDetailsMiddleware {
        $psr17Factory = new Psr17Factory();

        return new ProblemDetailsMiddleware(
            responseFactory: new ProblemDetailsResponseFactory($psr17Factory, $psr17Factory),
            exceptionMapper: $exceptionMapper,
            debug: $debug,
            correlationIdProvider: $correlationIdProvider,
            throwableReporter: $throwableReporter,
        );
    }

    private function throwingHandler(Throwable $throwable): FakeHandler
    {
        return new FakeHandler(static function () use ($throwable): never {
            throw $throwable;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function body(ResponseInterface $response): array
    {
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);

        return $body;
    }
}
