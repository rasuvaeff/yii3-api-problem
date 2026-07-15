<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsException;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$psr17Factory = new Psr17Factory();
$responseFactory = new ProblemDetailsResponseFactory($psr17Factory, $psr17Factory);

try {
    throw ProblemDetailsException::forProblem(
        ProblemDetails::create(
            title: 'Conflict',
            status: 409,
            detail: 'The resource changed while the request was processed',
        ),
    );
} catch (ProblemDetailsException $exception) {
    $response = $responseFactory->toResponse($exception->getProblemDetails());
}

echo $response->getBody(), PHP_EOL;
