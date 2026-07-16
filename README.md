# rasuvaeff/yii3-api-problem

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/yii3-api-problem/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-api-problem)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-api-problem/downloads)](https://packagist.org/packages/rasuvaeff/yii3-api-problem)
[![Build](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/static-analysis.yml)
[![Psalm level](https://shepherd.dev/github/rasuvaeff/yii3-api-problem/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-api-problem)
[![License](https://poser.pugx.org/rasuvaeff/yii3-api-problem/license)](LICENSE.md)
[Русская версия](README.ru.md)

RFC 9457 Problem Details for Yii3 and any PSR-7/PSR-15 application. Use the
value object directly, turn it into a hardened response, or catch exceptions
with middleware that has explicit production and debug disclosure policies.

> Using an AI coding assistant? [llms.txt](llms.txt) is a compact API reference
> with the package rules and copy-ready examples.

## Requirements

- PHP 8.3-8.5.
- A PSR-7 implementation and PSR-17 response/stream factories.
- A PSR-15 stack only when using `ProblemDetailsMiddleware`.

## Installation

```bash
composer require rasuvaeff/yii3-api-problem
```

The examples use `nyholm/psr7` as the PSR-7/17 implementation:

```bash
composer require nyholm/psr7
```

## Usage

Create an RFC 9457 document in an action and return it as a PSR-7 response:

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;

$problem = ProblemDetails::create(
    type: 'https://example.com/problems/insufficient-funds',
    title: 'Insufficient funds',
    status: 403,
    detail: 'The account balance is too low.',
    instance: '/transfers/42',
);

$psr17 = new Psr17Factory();
$response = (new ProblemDetailsResponseFactory($psr17, $psr17))
    ->toResponse($problem);
```

The response status comes from the problem. Its content type is always
`application/problem+json`, and it always carries
`X-Content-Type-Options: nosniff`.

### Value object

| Method | Purpose |
|---|---|
| `create(title, status, type, detail, instance, extensions)` | Create a full problem document |
| `fromStatus(status, title, type)` | Create one using the HTTP reason phrase as the default title |
| `withDetail(detail)` | Return a copy with explanatory detail |
| `withInstance(instance)` | Return a copy with an occurrence identifier |
| `withExtension(key, value)` | Add or replace one extension member |
| `withExtensions(extensions)` | Replace all extension members |
| `withInvalidParams(...params)` | Add typed field-validation failures |
| `toArray()` / `toJson(flags)` | Serialize the problem document |

`type`, `title`, `status`, `detail`, and `instance` are reserved and cannot be
used as extension names. Optional null members are omitted during serialization.

`InvalidParam` provides a stable shape for field validation failures:

```php
use Rasuvaeff\Yii3ApiProblem\InvalidParam;

$problem = ProblemDetails::fromStatus(status: 422)->withInvalidParams(
    InvalidParam::create(name: 'email', reason: 'Invalid email address'),
    InvalidParam::create(name: 'age', reason: 'Must be at least 18'),
);
```

This produces the `invalid-params` extension shown in RFC 9457 examples. RFC
9457 permits extension members but does not standardize a universal validation
error schema; consumers must opt into this package-defined shape.

### Transport headers

Pass headers when a problem needs HTTP metadata such as `Retry-After` or
`WWW-Authenticate`:

```php
$response = $responseFactory->toResponse(
    ProblemDetails::fromStatus(status: 429),
    headers: ['Retry-After' => '120'],
);
```

Header values may be strings or lists of strings. Caller headers cannot override
the mandatory `application/problem+json` content type or `nosniff` policy.

### Throwing a problem

An action may throw a problem intended for the client:

```php
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsException;

throw ProblemDetailsException::forProblem(
    details: ProblemDetails::create(
        title: 'Validation failed',
        status: 422,
    ),
    headers: ['Retry-After' => '60'],
);
```

`ProblemDetailsMiddleware` preserves this explicitly supplied document. In
particular, an intentional `detail` is not removed in production.

### Exception middleware

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3ApiProblem\DefaultExceptionMapper;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsMiddleware;
use Rasuvaeff\Yii3ApiProblem\ProblemDetailsResponseFactory;

$psr17 = new Psr17Factory();
$middleware = new ProblemDetailsMiddleware(
    responseFactory: new ProblemDetailsResponseFactory($psr17, $psr17),
    exceptionMapper: new DefaultExceptionMapper(),
    debug: false,
);
```

Place it outside the application handler that may throw. Successful responses
pass through unchanged. In production, ordinary exception messages and traces
are never copied into the response. With `debug: true`, `detail` contains the
exception message and the `trace` extension contains its stack trace. Never
enable debug mode in production.

### Exception reporting

The middleware can report the original exception before returning a safe
response. Implement `ThrowableReporterInterface` as a small adapter to your
logger, Sentry, or another observability system:

```php
use Psr\Http\Message\ServerRequestInterface;
use Rasuvaeff\Yii3ApiProblem\ThrowableReporterInterface;

final readonly class SentryThrowableReporter implements ThrowableReporterInterface
{
    public function report(Throwable $throwable, ServerRequestInterface $request): void
    {
        Sentry\captureException($throwable);
    }
}

$middleware = new ProblemDetailsMiddleware(
    responseFactory: $responseFactory,
    exceptionMapper: $mapper,
    throwableReporter: new SentryThrowableReporter(),
);
```

The reporter receives both generic exceptions and `ProblemDetailsException`, is
not called for successful responses, and must not throw.

The default mapper handles these cases:

| Exception | Result |
|---|---|
| `ProblemDetailsException` | Its enclosed document |
| Configured exact exception class | Configured type, title, and status |
| `InvalidArgumentException` | 400 Bad Request |
| `RuntimeException` | 500 Internal Server Error |
| Any other `Throwable` | `null`; middleware falls back to generic 500 |

Configured entries match the exact class, not parent classes or interfaces:

```php
$mapper = new DefaultExceptionMapper(exceptionMap: [
    App\Domain\UserNotFoundException::class => [
        'type' => 'https://example.com/problems/user-not-found',
        'title' => 'User not found',
        'status' => 404,
    ],
]);
```

Implement `ExceptionMapperInterface` when mapping needs domain-specific logic.

### Yii3 configuration

The config plugin binds `ProblemDetailsResponseFactoryInterface`, the concrete
`DefaultExceptionMapper`, and `ProblemDetailsMiddleware`. It deliberately does
not bind `ExceptionMapperInterface` or `ThrowableReporterInterface`, because the
application owns those replaceable choices.

Default params:

```php
return [
    'rasuvaeff/yii3-api-problem' => [
        'debug' => false,
        'use_default_mapper' => true,
        'exception_map' => [],
    ],
];
```

Your PSR-17 implementation must provide `ResponseFactoryInterface` and
`StreamFactoryInterface` in the container. Override the middleware definition
when supplying a custom mapper, `CorrelationIdProvider`, or reporter.

### Correlation ID

Implement this package's small `CorrelationIdProvider` interface and pass it to
the middleware. A non-null ID becomes the problem `instance`:

```php
use Rasuvaeff\Yii3ApiProblem\CorrelationIdProvider;

final readonly class ApiProblemCorrelationIdProvider implements CorrelationIdProvider
{
    public function __construct(
        private Rasuvaeff\Yii3CorrelationId\CorrelationIdProvider $provider,
    ) {}

    public function getCorrelationId(): ?string
    {
        return $this->provider->tryGet();
    }
}
```

The adapter keeps `rasuvaeff/yii3-correlation-id` optional.

## When to use which

Use this package when you want a small RFC 9457 value object plus a configurable
exception mapper, typed validation extension, transport headers, exception
reporting, production/debug disclosure policy, correlation ID integration, and
Yii3 config-plugin wiring.

Use [`crell/api-problem`](https://packagist.org/packages/crell/api-problem) when
you need its mature generic-PHP ecosystem, XML serialization, or its existing
PSR-7/15/17 integration. It is an established package; this library is an
opinionated Yii3-oriented alternative, not a claim that the generic niche is empty.

If `yiisoft/error-handler` already formats every exception in your application,
use one error formatting path. This middleware must sit outside a throwing
handler; it cannot reformat a response already produced by another error handler.

## Security

- Keep `debug` false in production. Ordinary exception messages and stack traces
  are considered sensitive.
- Treat extension values as response data. JSON encoding prevents structural
  JSON injection but does not make credentials or personal data safe to expose.
- Problem type URIs are identifiers, not automatically fetched documentation.
- `ProblemDetailsException` is an explicit disclosure boundary: only throw it
  with client-safe `detail`, extensions, and headers.
- Reporter implementations must not throw and must redact sensitive request
  data before sending it to third-party telemetry.

## Examples

See [examples/](examples/) for executable scripts covering manual responses,
exceptions, typed validation extensions, middleware setup, custom mappings,
reporting, and transport headers.

## Development

```bash
composer build
composer test
composer psalm
composer mutation
composer bench
```

PHP and Composer are run through Docker in this repository; the equivalent Make
targets are `make build`, `make test`, `make psalm`, `make mutation`, and
`make bench`.

## License

The package is released under the [BSD 3-Clause License](LICENSE.md).
