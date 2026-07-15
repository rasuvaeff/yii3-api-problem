<?php

declare(strict_types=1);

use Rasuvaeff\Yii3ApiProblem\DefaultExceptionMapper;

require dirname(__DIR__) . '/vendor/autoload.php';

$exception = new class ('User 42 does not exist') extends RuntimeException {};
$mapper = new DefaultExceptionMapper(exceptionMap: [
    $exception::class => [
        'type' => 'https://example.com/problems/user-not-found',
        'title' => 'User not found',
        'status' => 404,
    ],
]);
$problem = $mapper->toProblem($exception);

echo $problem?->toJson(JSON_PRETTY_PRINT), PHP_EOL;
