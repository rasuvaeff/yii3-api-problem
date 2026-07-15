<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\Yii3ApiProblem\DefaultExceptionMapper;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsMiddleware;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$psr17Factory = new Psr17Factory();
$middleware = new ProblemDetailsMiddleware(
    responseFactory: new ProblemDetailsResponseFactory($psr17Factory, $psr17Factory),
    exceptionMapper: new DefaultExceptionMapper(),
    debug: false,
);
$handler = new class implements RequestHandlerInterface {
    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Internal message hidden in production');
    }
};

$response = $middleware->process(new ServerRequest('GET', '/reports'), $handler);

echo $response->getStatusCode(), PHP_EOL;
echo $response->getBody(), PHP_EOL;
