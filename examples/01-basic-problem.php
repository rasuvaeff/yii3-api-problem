<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$problem = ProblemDetails::create(
    type: 'https://example.com/problems/insufficient-funds',
    title: 'Insufficient funds',
    status: 403,
    detail: 'The account balance is too low',
    instance: '/transfers/42',
);
$psr17Factory = new Psr17Factory();
$response = (new ProblemDetailsResponseFactory($psr17Factory, $psr17Factory))
    ->toResponse($problem);

echo $response->getStatusCode(), PHP_EOL;
echo $response->getHeaderLine('Content-Type'), PHP_EOL;
echo $response->getBody(), PHP_EOL;
