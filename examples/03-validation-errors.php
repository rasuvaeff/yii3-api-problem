<?php

declare(strict_types=1);

use Rasuvaeff\Yii3ApiProblem\InvalidParam;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;

require dirname(__DIR__) . '/vendor/autoload.php';

$problem = ProblemDetails::create(
    type: 'https://example.com/problems/validation',
    title: 'Validation failed',
    status: 422,
    detail: 'The request has invalid fields',
)->withInvalidParams(
    InvalidParam::create(name: 'email', reason: 'Invalid email address'),
    InvalidParam::create(name: 'age', reason: 'Must be at least 18'),
);

echo $problem->toJson(JSON_PRETTY_PRINT), PHP_EOL;
