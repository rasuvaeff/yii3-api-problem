<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsException;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsMiddleware;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;
use Rasuvaeff\Yii3ApiProblem\ThrowableReporterInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$reporter = new class implements ThrowableReporterInterface {
    #[\Override]
    public function report(Throwable $throwable, ServerRequestInterface $request): void
    {
        echo 'Reported ', $throwable::class, ' for ', $request->getUri()->getPath(), PHP_EOL;
    }
};
$exception = ProblemDetailsException::forProblem(
    details: ProblemDetails::fromStatus(status: 429),
    headers: ['Retry-After' => '120'],
);
$handler = new class ($exception) implements RequestHandlerInterface {
    public function __construct(private readonly Throwable $throwable) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->throwable;
    }
};
$psr17Factory = new Psr17Factory();
$middleware = new ProblemDetailsMiddleware(
    responseFactory: new ProblemDetailsResponseFactory($psr17Factory, $psr17Factory),
    throwableReporter: $reporter,
);

$response = $middleware->process(new ServerRequest('GET', '/rate-limited'), $handler);

echo 'Retry-After: ', $response->getHeaderLine('Retry-After'), PHP_EOL;
echo $response->getBody(), PHP_EOL;
