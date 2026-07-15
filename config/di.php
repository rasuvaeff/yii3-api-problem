<?php

declare(strict_types=1);

use Rasuvaeff\Yii3ApiProblem\DefaultExceptionMapper;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsMiddleware;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactoryInterface;

/** @var array $params */

return [
    ProblemDetailsResponseFactoryInterface::class => ProblemDetailsResponseFactory::class,
    DefaultExceptionMapper::class => static fn (): DefaultExceptionMapper => new DefaultExceptionMapper(
        exceptionMap: $params['rasuvaeff/yii3-api-problem']['exception_map'],
    ),
    ProblemDetailsMiddleware::class => static fn (
        ProblemDetailsResponseFactoryInterface $factory,
        DefaultExceptionMapper $mapper,
    ): ProblemDetailsMiddleware => new ProblemDetailsMiddleware(
        responseFactory: $factory,
        exceptionMapper: $params['rasuvaeff/yii3-api-problem']['use_default_mapper'] ? $mapper : null,
        debug: $params['rasuvaeff/yii3-api-problem']['debug'],
    ),
];
