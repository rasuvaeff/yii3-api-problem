<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Rasuvaeff\Yii3ApiProblem\DefaultExceptionMapper;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsMiddleware;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactoryInterface;
use Rasuvaeff\Yii3ApiProblem\ThrowableReporterInterface;

/** @var array $params */

// ThrowableReporterInterface is deliberately NOT bound here: the application
// owns the reporter. When `throwable_reporter` names a class, the middleware
// builder resolves that single instance from the container instead.

return [
    ProblemDetailsResponseFactoryInterface::class => ProblemDetailsResponseFactory::class,
    DefaultExceptionMapper::class => static fn (): DefaultExceptionMapper => new DefaultExceptionMapper(
        exceptionMap: $params['rasuvaeff/yii3-api-problem']['exception_map'],
    ),
    ProblemDetailsMiddleware::class => static function (
        ProblemDetailsResponseFactoryInterface $factory,
        DefaultExceptionMapper $mapper,
        ContainerInterface $container,
    ) use ($params): ProblemDetailsMiddleware {
        /** @var class-string<ThrowableReporterInterface>|null $reporterClass */
        $reporterClass = $params['rasuvaeff/yii3-api-problem']['throwable_reporter'] ?? null;

        return new ProblemDetailsMiddleware(
            responseFactory: $factory,
            exceptionMapper: $params['rasuvaeff/yii3-api-problem']['use_default_mapper'] ? $mapper : null,
            debug: $params['rasuvaeff/yii3-api-problem']['debug'],
            throwableReporter: $reporterClass === null
                ? null
                : $container->get($reporterClass),
        );
    },
];
