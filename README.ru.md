# rasuvaeff/yii3-api-проблема
[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/yii3-api-problem/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-api-problem)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-api-problem/downloads)](https://packagist.org/packages/rasuvaeff/yii3-api-problem)
[![Build](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-api-problem/actions/workflows/static-analysis.yml)
[![Psalm level](https://shepherd.dev/github/rasuvaeff/yii3-api-problem/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-api-problem)
[![License](https://poser.pugx.org/rasuvaeff/yii3-api-problem/license)](LICENSE.md)
RFC 9457 Подробности проблемы для Yii3 и любого приложения PSR-7/PSR-15. Используйте объект значения
 напрямую, превратите его в усиленный ответ или перехватывайте исключения
 с помощью промежуточного программного обеспечения, которое имеет явные политики раскрытия информации о производстве и отладке.

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) — это компактный справочник API
 > с правилами пакета и готовыми для копирования примерами.
## Требования
- PHP 8,3-8,5.
 — реализация PSR-7 и фабрики ответов/потоков PSR-17.
 — стек PSR-15 только при использовании «ProblemDetailsMiddleware».
## Установка
```bash
composer require rasuvaeff/yii3-api-problem
```
В примерах в качестве реализации PSR-7/17 используется `nyholm/psr7`:

```bash
composer require nyholm/psr7
```
## Использование
Создайте документ RFC 9457 в действии и верните его как ответ PSR-7:

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
Статус ответа зависит от проблемы. Его тип контента всегда
 `application/problem+json`, и он всегда содержит
 `X-Content-Type-Options: nosniff`.
### Объект значения
| Метод | Цель |
 |---|---|
 | `создать(название, статус, тип, деталь, экземпляр, расширения)` | Создайте полный документ о проблеме |
 | `fromStatus(статус, заголовок, тип)` | Создайте его, используя фразу причины HTTP в качестве заголовка по умолчанию |
 | `withDetail(деталь)` | Вернуть копию с пояснительными подробностями |
 | `withInstance(экземпляр)` | Вернуть копию с идентификатором вхождения |
 | `withExtension(ключ, значение)` | Добавить или заменить одного члена расширения |
 | `withExtensions(расширения)` | Заменить всех членов расширения |
 | `withInvalidParams(...params)` | Добавить ошибки проверки типизированных полей |
 | `toArray()` / `toJson(флаги)` | Сериализовать проблемный документ |

 `type`, `title`, `status`, `detail` и `instance` зарезервированы и не могут использоваться
 в качестве имен расширений. Необязательные нулевые члены во время сериализации опускаются.

 `InvalidParam` обеспечивает стабильную форму для ошибок проверки поля:

```php
use Rasuvaeff\Yii3ApiProblem\InvalidParam;

$problem = ProblemDetails::fromStatus(status: 422)->withInvalidParams(
    InvalidParam::create(name: 'email', reason: 'Invalid email address'),
    InvalidParam::create(name: 'age', reason: 'Must be at least 18'),
);
```
Это создает расширение invalid-params, показанное в примерах RFC 9457. RFC
 9457 разрешает элементы расширения, но не стандартизирует универсальную схему ошибок проверки
; потребители должны выбрать эту форму, определенную упаковкой.
### Транспортные заголовки
Передавайте заголовки, когда для проблемы требуются метаданные HTTP, такие как `Retry-After` или
 `WWW-Authenticate`:

```php
$response = $responseFactory->toResponse(
    ProblemDetails::fromStatus(status: 429),
    headers: ['Retry-After' => '120'],
);
```
Значения заголовка могут быть строками или списками строк. Заголовки вызывающего объекта не могут переопределить
 обязательный тип контента `application/problem+json` или политику `nosniff`.
### Бросить проблему
Действие может вызвать проблему, предназначенную для клиента:

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
`ProblemDetailsMiddleware` сохраняет этот явно предоставленный документ. В частности, в
 при производстве намеренно не удаляются «детали».
### Промежуточное программное обеспечение исключений
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
Поместите его вне обработчика приложения, который может выдать ошибку. Успешные ответы
 проходят без изменений. В рабочей среде обычные сообщения об исключениях и трассировки
 никогда не копируются в ответ. При `debug: true` `detail` содержит сообщение об исключении
, а расширение `trace` содержит трассировку стека. Никогда
 не включайте режим отладки в рабочей среде.
### Отчеты об исключениях
Промежуточное программное обеспечение может сообщить об исходном исключении, прежде чем вернуть безопасный ответ
. Реализуйте ThrowableReporterInterface в качестве небольшого адаптера для вашего регистратора
, Sentry или другой системы наблюдения:

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
Создатель отчетов получает как общие исключения, так и `ProblemDetailsException`,
 не вызывается для успешных ответов и не должен выдавать исключение.

 Сопоставитель по умолчанию обрабатывает следующие случаи:

 | Исключение | Результат |
 |---|---|
 | `ProblemDetailsException` | Прилагаемый документ |
 | Настроен точный класс исключений | Настроенный тип, заголовок и статус |
 | `InvalidArgumentException` | 400 неверный запрос |
 | `RuntimeException` | 500 Внутренняя ошибка сервера |
 | Любой другой `Throwable` | `ноль`; промежуточное программное обеспечение возвращается к общей версии 500 |

 Настроенные записи соответствуют конкретному классу, а не родительским классам или интерфейсам:

```php
$mapper = new DefaultExceptionMapper(exceptionMap: [
    App\Domain\UserNotFoundException::class => [
        'type' => 'https://example.com/problems/user-not-found',
        'title' => 'User not found',
        'status' => 404,
    ],
]);
```
Реализуйте ExceptionMapperInterface, когда для отображения требуется логика, специфичная для предметной области.
### Конфигурация Yii3
Плагин конфигурации связывает «ProblemDetailsResponseFactoryInterface», конкретный
 «DefaultExceptionMapper» и «ProblemDetailsMiddleware». Он намеренно
 не связывает `ExceptionMapperInterface` или `ThrowableReporterInterface`, поскольку приложение
 владеет этими заменяемыми вариантами.

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
Ваша реализация PSR-17 должна предоставлять в контейнере ResponseFactoryInterface и
 StreamFactoryInterface. Переопределить определение промежуточного программного обеспечения
 при предоставлении пользовательского сопоставителя, CorrelationIdProvider или генератора отчетов.
### Идентификатор корреляции
Реализуйте небольшой интерфейс CorrelationIdProvider этого пакета и передайте его
 промежуточному программному обеспечению. Ненулевой идентификатор становится «экземпляром» проблемы:

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
Адаптер сохраняет `rasuvaeff/yii3-correlation-id` необязательным.
## Когда какой использовать
Используйте этот пакет, если вам нужен небольшой объект значения RFC 9457, а также настраиваемый преобразователь исключений
, расширение типизированной проверки, транспортные заголовки, отчеты об исключениях
, политика раскрытия продукции/отладки, интеграция идентификаторов корреляции и подключение
 плагина конфигурации Yii3.
Use [`crell/api-problem`](https://packagist.org/packages/crell/api-problem) when
вам нужна зрелая экосистема общего PHP, сериализация XML или существующая интеграция
 PSR-7/15/17. Это установленный пакет; эта библиотека является
 самоуверенной альтернативой, ориентированной на Yii3, а не заявлением о том, что универсальная ниша пуста.

 Если `yiisoft/error-handler` уже форматирует каждое исключение в вашем приложении,
 используйте один путь форматирования ошибок. Это промежуточное программное обеспечение должно находиться вне обработчика выдачи
; он не может переформатировать ответ, уже созданный другим обработчиком ошибок.
## Безопасность
— В производстве оставляйте `debug` false. Обычные сообщения об исключениях и трассировки стека
 считаются конфиденциальными.
 — рассматривать значения расширения как данные ответа. Кодирование JSON предотвращает структурное внедрение
 JSON, но не обеспечивает безопасность доступа к учетным данным или личным данным.
 — URI типа проблемы — это идентификаторы, а не автоматически извлекаемая документация.
 — `ProblemDetailsException` — это явная граница раскрытия: выдавайте его
 только с безопасными для клиента `detail`, расширениями и заголовками.
 — реализации Reporter не должны выдавать и должны редактировать конфиденциальные данные запроса
 перед отправкой их в стороннюю телеметрию.
## Примеры
См. [examples/](examples/) для исполняемых сценариев, охватывающих ручные ответы, исключения
, расширения типизированной проверки, настройку промежуточного программного обеспечения, пользовательские сопоставления, отчеты
 и транспортные заголовки.
## Разработка
```bash
composer build
composer test
composer psalm
composer mutation
composer bench
```
В этом репозитории PHP и Composer запускаются через Docker; эквивалентными целями Make
 являются `make build`, `make test`, `make psalm`, `makemutation` и
`makebench`.
## Лицензия
Пакет выпущен под лицензией [BSD 3-Clause License](LICENSE.md).
