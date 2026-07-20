# rasuvaeff/yii3-api-problem

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/yii3-api-problem/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-api-problem)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-api-problem/downloads)](https://packagist.org/packages/rasuvaeff/yii3-api-problem)
[![Build](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/static-analysis.yml)
[![Psalm level](https://shepherd.dev/github/rasuvaeff/yii3-api-problem/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-api-problem)
[![License](https://poser.pugx.org/rasuvaeff/yii3-api-problem/license)](LICENSE.md)
[English version](README.md)

RFC 9457 Problem Details для Yii3 и любого PSR-7/PSR-15 приложения. Используйте
value object напрямую, превращайте его в защищённый ответ или перехватывайте
исключения middleware-ом с явными политиками раскрытия для production и debug.

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник
> с правилами пакета и готовыми к копированию примерами.

## Требования

- PHP 8.3-8.5.
- Реализация PSR-7 и фабрики ответов/потоков PSR-17.
- Стек PSR-15 — только при использовании `ProblemDetailsMiddleware`.

## Установка

```bash
composer require rasuvaeff/yii3-api-problem
```

В примерах используется `nyholm/psr7` как реализация PSR-7/17:

```bash
composer require nyholm/psr7
```

## Использование

Создайте документ RFC 9457 в действии и верните его как PSR-7 ответ:

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

Статус ответа берётся из problem. Content-type всегда
`application/problem+json`, и ответ всегда несёт
`X-Content-Type-Options: nosniff`.

### Value object

| Метод | Назначение |
|---|---|
| `create(title, status, type, detail, instance, extensions)` | Создать полный документ problem |
| `fromStatus(status, title, type)` | Создать, используя HTTP reason phrase как заголовок по умолчанию |
| `withDetail(detail)` | Вернуть копию с поясняющим detail |
| `withInstance(instance)` | Вернуть копию с идентификатором вхождения |
| `withExtension(key, value)` | Добавить или заменить один extension-member |
| `withExtensions(extensions)` | Заменить все extension-members |
| `withInvalidParams(...params)` | Добавить типизированные ошибки валидации полей |
| `toArray()` / `toJson(flags)` | Сериализовать документ problem |

`type`, `title`, `status`, `detail` и `instance` зарезервированы и не могут
использоваться как имена extension-ов. Опциональные null-члены опускаются при
сериализации.

`InvalidParam` предоставляет стабильную структуру для ошибок валидации полей:

```php
use Rasuvaeff\Yii3ApiProblem\InvalidParam;

$problem = ProblemDetails::fromStatus(status: 422)->withInvalidParams(
    InvalidParam::create(name: 'email', reason: 'Invalid email address'),
    InvalidParam::create(name: 'age', reason: 'Must be at least 18'),
);
```

Это создаёт extension `invalid-params`, показанный в примерах RFC 9457. RFC 9457
допускает extension-members, но не стандартизирует универсальную схему ошибок
валидации; потребители должны явно принять эту форму, определённую пакетом.

### Транспортные заголовки

Передавайте заголовки, когда problem нужны HTTP-метаданные вроде `Retry-After` или
`WWW-Authenticate`:

```php
$response = $responseFactory->toResponse(
    ProblemDetails::fromStatus(status: 429),
    headers: ['Retry-After' => '120'],
);
```

Значения заголовков могут быть строками или списками строк. Заголовки вызывающего
кода не могут переопределить обязательный content-type `application/problem+json`
или политику `nosniff`.

### Бросание problem

Действие может бросить problem, предназначенный клиенту:

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

`ProblemDetailsMiddleware` сохраняет этот явно переданный документ. В частности,
намеренный `detail` не удаляется в production.

### Middleware для исключений

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

Размещайте его снаружи обработчика приложения, который может бросить. Успешные
ответы проходят без изменений. В production обычные сообщения исключений и трейсы
никогда не копируются в ответ. При `debug: true` `detail` содержит сообщение
исключения, а extension `trace` — его stack trace. Никогда не включайте debug-режим
в production.

### Отчётность об исключениях

Middleware может отчитаться об исходном исключении до возврата безопасного ответа.
Реализуйте `ThrowableReporterInterface` как небольшой адаптер к вашему логгеру,
Sentry или другой системе наблюдения:

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

Репортёр получает и generic-исключения, и `ProblemDetailsException`, не
вызывается для успешных ответов и не должен бросать.

Дефолтный маппер обрабатывает следующие случаи:

| Исключение | Результат |
|---|---|
| `ProblemDetailsException` | Обёрнутый документ |
| Настроенный точный класс исключения | Настроенные type, title и status |
| `InvalidArgumentException` | 400 Bad Request |
| `RuntimeException` | 500 Internal Server Error |
| Любой другой `Throwable` | `null`; middleware fallback на generic 500 |

Настроенные записи совпадают по точному классу, а не по родительским классам или
интерфейсам:

```php
$mapper = new DefaultExceptionMapper(exceptionMap: [
    App\Domain\UserNotFoundException::class => [
        'type' => 'https://example.com/problems/user-not-found',
        'title' => 'User not found',
        'status' => 404,
    ],
]);
```

Реализуйте `ExceptionMapperInterface`, когда маппингу нужна доменная логика.

### Конфигурация Yii3

Config-plugin биндит `ProblemDetailsResponseFactoryInterface`, конкретный
`DefaultExceptionMapper` и `ProblemDetailsMiddleware`. Он намеренно не биндит
`ExceptionMapperInterface` или `ThrowableReporterInterface`, поскольку этими
заменяемыми выборами владеет приложение.

Параметры по умолчанию:

```php
return [
    'rasuvaeff/yii3-api-problem' => [
        'debug' => false,
        'use_default_mapper' => true,
        'exception_map' => [],
    ],
];
```

Ваша реализация PSR-17 должна предоставлять `ResponseFactoryInterface` и
`StreamFactoryInterface` в контейнере. Переопределите определение middleware, когда
подаёте кастомный маппер, `CorrelationIdProvider` или репортёр.

### Correlation ID

Реализуйте небольшой интерфейс `CorrelationIdProvider` этого пакета и передайте его
в middleware. Ненулевой ID становится `instance` problem:

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

Адаптер держит `rasuvaeff/yii3-correlation-id` опциональным.

## Когда что использовать

Используйте этот пакет, когда нужен небольшой RFC 9457 value object плюс
настраиваемый маппер исключений, типизированный validation-extension, транспортные
заголовки, отчётность об исключениях, политика раскрытия production/debug,
интеграция correlation ID и подключение через Yii3 config-plugin.

Используйте [`crell/api-problem`](https://packagist.org/packages/crell/api-problem),
когда нужна его зрелая экосистема общего PHP, XML-сериализация или готовая
интеграция PSR-7/15/17. Это устоявшийся пакет; данная библиотека —
opinionated-альтернатива с фокусом на Yii3, а не утверждение, что generic-ниша пуста.

Если `yiisoft/error-handler` уже форматирует каждое исключение в вашем приложении,
используйте один путь форматирования ошибок. Этот middleware должен располагаться
снаружи бросающего обработчика; он не может переформатировать ответ, уже созданный
другим error handler-ом.

## Безопасность

- Держите `debug` в `false` в production. Обычные сообщения исключений и трейсы
  стека считаются чувствительными.
- Рассматривайте значения extension-ов как данные ответа. JSON-кодирование
  предотвращает структурную JSON-инъекцию, но не делает безопасным раскрытие
  учётных данных или персональных данных.
- URI типа problem — это идентификаторы, а не автоматически извлекаемая документация.
- `ProblemDetailsException` — это явная граница раскрытия: бросайте его только с
  безопасными для клиента `detail`, extension-ами и заголовками.
- Реализации репортёра не должны бросать и должны редактировать чувствительные
  данные запроса перед отправкой в стороннюю телеметрию.

## Примеры

Исполняемые скрипты — в [examples/](examples/): ручные ответы, исключения,
типизированные validation-extension-ы, настройка middleware, кастомные маппинги,
отчётность и транспортные заголовки.

## Разработка

```bash
composer build
composer test
composer psalm
composer mutation
composer bench
```

В этом репозитории PHP и Composer запускаются через Docker; эквивалентные Make
-цели — `make build`, `make test`, `make psalm`, `make mutation` и `make bench`.

## Лицензия

Пакет выпущен под [BSD 3-Clause License](LICENSE.md).
