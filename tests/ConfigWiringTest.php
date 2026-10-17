<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Rasuvaeff\Yii3ApiProblem\DefaultExceptionMapper;
use Rasuvaeff\Yii3ApiProblem\ExceptionMapperInterface;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsMiddleware;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactoryInterface;
use Rasuvaeff\Yii3ApiProblem\Tests\Support\FakeHandler;
use Rasuvaeff\Yii3ApiProblem\Tests\Support\RecordingThrowableReporter;
use Rasuvaeff\Yii3ApiProblem\Tests\Support\UserNotFoundException;
use Rasuvaeff\Yii3ApiProblem\ThrowableReporterInterface;
use RuntimeException;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Throwable;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

#[Test]
#[CoversNothing]
final class ConfigWiringTest
{
    public function paramsUseSafeProductionDefaults(): void
    {
        Assert::same($this->params(), [
            'rasuvaeff/yii3-api-problem' => [
                'debug' => false,
                'use_default_mapper' => true,
                'exception_map' => [],
                'throwable_reporter' => null,
            ],
        ]);
    }

    public function responseFactoryInterfaceIsBound(): void
    {
        Assert::same(
            $this->di()[ProblemDetailsResponseFactoryInterface::class],
            ProblemDetailsResponseFactory::class,
        );
    }

    public function replaceableMapperInterfaceIsNotBound(): void
    {
        Assert::false(array_key_exists(ExceptionMapperInterface::class, $this->di()));
    }

    public function applicationOwnedReporterInterfaceIsNotBound(): void
    {
        Assert::false(array_key_exists(ThrowableReporterInterface::class, $this->di()));
    }


    public function defaultParamsBuildWorkingServices(): void
    {
        $container = $this->container();

        Assert::instanceOf($container->get(ProblemDetailsResponseFactoryInterface::class), ProblemDetailsResponseFactory::class);
        Assert::instanceOf($container->get(ProblemDetailsMiddleware::class), ProblemDetailsMiddleware::class);
    }

    public function applicationParamsConfigureMapperAndDebugMode(): void
    {
        $params = array_replace_recursive($this->params(), [
            'rasuvaeff/yii3-api-problem' => [
                'debug' => true,
                'exception_map' => [
                    UserNotFoundException::class => ['title' => 'Missing user', 'status' => 404],
                ],
            ],
        ]);
        $container = $this->container($params);
        $middleware = $container->get(ProblemDetailsMiddleware::class);
        $mapper = $container->get(DefaultExceptionMapper::class);

        Assert::true($this->property($middleware, 'debug'));
        Assert::same($mapper->toProblem(new UserNotFoundException())?->status, 404);
    }

    public function defaultMapperCanBeDisabled(): void
    {
        $params = array_replace_recursive($this->params(), [
            'rasuvaeff/yii3-api-problem' => ['use_default_mapper' => false],
        ]);
        $middleware = $this->container($params)->get(ProblemDetailsMiddleware::class);

        Assert::null($this->property($middleware, 'exceptionMapper'));
    }

    public function reporterParamDefaultsToNoReporter(): void
    {
        $middleware = $this->container()->get(ProblemDetailsMiddleware::class);

        Assert::null($this->property($middleware, 'throwableReporter'));
    }

    public function reporterParamResolvesClassFromContainer(): void
    {
        $params = array_replace_recursive($this->params(), [
            'rasuvaeff/yii3-api-problem' => ['throwable_reporter' => RecordingThrowableReporter::class],
        ]);
        $container = $this->container($params);
        $middleware = $container->get(ProblemDetailsMiddleware::class);
        $reporter = $container->get(RecordingThrowableReporter::class);

        Assert::same($this->property($middleware, 'throwableReporter'), $reporter);
        Assert::false(array_key_exists(ThrowableReporterInterface::class, $this->di($params)));
    }

    public function resolvedReporterReceivesCaughtThrowable(): void
    {
        $params = array_replace_recursive($this->params(), [
            'rasuvaeff/yii3-api-problem' => ['throwable_reporter' => RecordingThrowableReporter::class],
        ]);
        $container = $this->container($params);
        $middleware = $container->get(ProblemDetailsMiddleware::class);
        $reporter = $container->get(RecordingThrowableReporter::class);
        $request = new ServerRequest('GET', '/configured-reporter');

        $middleware->process($request, $this->throwingHandler(new RuntimeException('outbox write failed')));

        Assert::same($reporter->calls, 1);
        Assert::same($reporter->request, $request);
    }

    public function reporterParamAbsentFromParamsBehavesAsDefault(): void
    {
        $params = $this->params();
        unset($params['rasuvaeff/yii3-api-problem']['throwable_reporter']);
        $middleware = $this->container($params)->get(ProblemDetailsMiddleware::class);

        Assert::null($this->property($middleware, 'throwableReporter'));
    }

    private function throwingHandler(Throwable $throwable): FakeHandler
    {
        return new FakeHandler(static function () use ($throwable): never {
            throw $throwable;
        });
    }

    private function container(?array $params = null): Container
    {
        $definitions = [
            ...$this->di($params),
            Psr17Factory::class => Psr17Factory::class,
            ResponseFactoryInterface::class => Psr17Factory::class,
            StreamFactoryInterface::class => Psr17Factory::class,
        ];

        return new Container(ContainerConfig::create()->withDefinitions($definitions));
    }

    /**
     * @return array<string, mixed>
     */
    private function di(?array $params = null): array
    {
        $params ??= $this->params();

        return (static fn(array $params): array => require dirname(__DIR__) . '/config/di.php')($params);
    }

    /**
     * @return array<string, mixed>
     */
    private function params(): array
    {
        /** @var array<string, mixed> $params */
        $params = require dirname(__DIR__) . '/config/params.php';

        return $params;
    }

    private function property(object $object, string $name): mixed
    {
        return (new \ReflectionProperty($object, $name))->getValue($object);
    }
}
