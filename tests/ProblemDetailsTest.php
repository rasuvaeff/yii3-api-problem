<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Tests;

use InvalidArgumentException;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3ApiProblem\InvalidParam;
use Rasuvaeff\Yii3ApiProblem\ProblemDetails;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(ProblemDetails::class)]
final class ProblemDetailsTest
{
    public function createsACompleteProblem(): void
    {
        $problem = ProblemDetails::create(
            title: 'Insufficient funds',
            status: 403,
            type: 'https://example.com/problems/insufficient-funds',
            detail: 'The account balance is too low',
            instance: '/transfers/42',
            extensions: ['balance' => 50],
        );

        Assert::same($problem->toArray(), [
            'type' => 'https://example.com/problems/insufficient-funds',
            'title' => 'Insufficient funds',
            'status' => 403,
            'detail' => 'The account balance is too low',
            'instance' => '/transfers/42',
            'balance' => 50,
        ]);
    }

    public function createUsesRfcDefaults(): void
    {
        $problem = ProblemDetails::create(title: 'Conflict', status: 409);

        Assert::same($problem->type, 'about:blank');
        Assert::null($problem->detail);
        Assert::null($problem->instance);
        Assert::same($problem->extensions, []);
    }

    public function fromStatusUsesReasonPhrase(): void
    {
        $problem = ProblemDetails::fromStatus(status: 404);

        Assert::same($problem->title, 'Not Found');
        Assert::same($problem->type, 'about:blank');
    }

    public function fromStatusAllowsCustomTitleForAboutBlank(): void
    {
        $problem = ProblemDetails::fromStatus(status: 404, title: 'Resource not found');

        Assert::same($problem->title, 'Resource not found');
    }

    #[DataProvider('invalidStatusProvider')]
    public function rejectsStatusOutsideHttpRange(int $status): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Invalid HTTP status code');

        ProblemDetails::create(title: 'Invalid', status: $status);
    }

    public static function invalidStatusProvider(): iterable
    {
        yield 'below range' => [99];
        yield 'above range' => [600];
    }

    #[DataProvider('boundaryStatusProvider')]
    public function acceptsHttpRangeBoundary(int $status): void
    {
        $problem = ProblemDetails::create(title: 'Boundary', status: $status);

        Assert::same($problem->status, $status);
    }

    public static function boundaryStatusProvider(): iterable
    {
        yield 'lower boundary' => [100];
        yield 'upper boundary' => [599];
    }

    public function rejectsEmptyType(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('must not be empty');

        ProblemDetails::create(title: 'Invalid', status: 400, type: '');
    }

    #[DataProvider('reservedKeyProvider')]
    public function rejectsReservedExtensionKey(string $key): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('is reserved');

        ProblemDetails::create(title: 'Invalid', status: 400, extensions: [$key => 'duplicate']);
    }

    public static function reservedKeyProvider(): iterable
    {
        yield 'type' => ['type'];
        yield 'title' => ['title'];
        yield 'status' => ['status'];
        yield 'detail' => ['detail'];
        yield 'instance' => ['instance'];
    }

    public function withMethodsReturnNewValuesWithoutChangingOriginal(): void
    {
        $original = ProblemDetails::create(
            title: 'Validation failed',
            status: 422,
            extensions: ['first' => 1],
        );
        $changed = $original
            ->withDetail('Two fields are invalid')
            ->withInstance('/requests/abc')
            ->withExtension('second', 2);

        Assert::null($original->detail);
        Assert::null($original->instance);
        Assert::same($original->extensions, ['first' => 1]);
        Assert::same($changed->detail, 'Two fields are invalid');
        Assert::same($changed->instance, '/requests/abc');
        Assert::same($changed->extensions, ['first' => 1, 'second' => 2]);
    }

    public function withExtensionsReplacesExistingExtensions(): void
    {
        $original = ProblemDetails::create(
            title: 'Validation failed',
            status: 422,
            extensions: ['old' => true],
        );

        $changed = $original->withExtensions(['new' => true]);

        Assert::same($changed->extensions, ['new' => true]);
    }

    public function withInvalidParamsAddsTypedExtension(): void
    {
        $problem = ProblemDetails::fromStatus(status: 422)->withInvalidParams(
            InvalidParam::create(name: 'email', reason: 'Invalid email address'),
            InvalidParam::create(name: 'age', reason: 'Must be at least 18'),
        );

        Assert::same($problem->extensions[ProblemDetails::INVALID_PARAMS_EXTENSION], [
            ['name' => 'email', 'reason' => 'Invalid email address'],
            ['name' => 'age', 'reason' => 'Must be at least 18'],
        ]);
    }

    public function withInvalidParamsRejectsEmptyList(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('At least one');

        ProblemDetails::fromStatus(status: 422)->withInvalidParams();
    }


    public function toArrayOmitsNullOptionalMembers(): void
    {
        $problem = ProblemDetails::create(title: 'Bad Request', status: 400);

        Assert::same($problem->toArray(), [
            'type' => 'about:blank',
            'title' => 'Bad Request',
            'status' => 400,
        ]);
    }

    public function toJsonPreservesUrisAndUnicode(): void
    {
        $problem = ProblemDetails::create(
            title: 'Ошибка',
            status: 400,
            type: 'https://example.com/problems/input',
        );

        Assert::same(
            $problem->toJson(),
            '{"type":"https://example.com/problems/input","title":"Ошибка","status":400}',
        );
    }

    public function toJsonCombinesCallerFlagsWithRequiredFlags(): void
    {
        $problem = ProblemDetails::create(title: 'Bad Request', status: 400, extensions: ['value' => 1.0]);

        Assert::string($problem->toJson(JSON_PRESERVE_ZERO_FRACTION))->contains('1.0');
    }

    #[Property(runs: 200, generators: 'problemGenerators')]
    public function toJsonAndToArrayAgree(
        string $type,
        string $title,
        int $status,
        ?string $detail,
        ?string $instance,
        array $extensions,
    ): void {
        $problem = new ProblemDetails(
            type: $type,
            title: $title,
            status: $status,
            detail: $detail,
            instance: $instance,
            extensions: $extensions,
        );

        // A problem with no extensions and one carrying a nested array take
        // different paths through the encoder; both have to agree with
        // toArray().
        Classify::cover($extensions === [], 'no extensions', 15.0);
        Classify::cover($extensions !== [], 'with extensions', 40.0);
        Classify::when($detail === null && $instance === null, 'mandatory members only');

        Assert::same(json_decode($problem->toJson(), associative: true), $problem->toArray());
    }

    /**
     * @return iterable<string, array{string, string, int, ?string, ?string, array<string, mixed>}>
     */
    public static function toJsonAndToArrayAgreeExamples(): iterable
    {
        // The inputs where a hand-rolled encoder and a json_encode() disagree:
        // characters that must be escaped, a value that is null rather than
        // absent, and an extension holding a nested structure.
        yield 'mandatory members only' => ['about:blank', 'Not Found', 404, null, null, []];
        yield 'quotes and backslashes in the title' => ['about:blank', 'He said "no" \\ then left', 400, null, null, []];
        yield 'unicode title' => ['about:blank', 'Недостаточно средств', 402, null, null, []];
        yield 'newline in detail' => ['about:blank', 'Bad Request', 400, "line one\nline two", null, []];
        yield 'nested extension' => ['about:blank', 'Bad Request', 400, null, null, ['ext_a' => ['x', 1, null]]];
        yield 'status at the lower bound' => ['about:blank', 'Continue', 100, null, null, []];
        yield 'status at the upper bound' => ['about:blank', 'Unknown', 599, null, null, []];
    }

    #[Property(runs: 200)]
    public function anExtensionKeyIsAcceptedExactlyWhenItIsNotAReservedMember(string $key, int $value): void
    {
        $reserved = \in_array($key, ['type', 'title', 'status', 'detail', 'instance'], strict: true);

        Classify::cover($reserved, 'reserved member', 20.0);
        Classify::cover(!$reserved, 'free extension key', 20.0);

        if ($reserved) {
            // try/catch rather than Expect::exception(): a property body runs
            // hundreds of times inside one test, and only some of those runs
            // expect a throw. Registering an expectation on a shared test
            // result from inside a loop is not what that API is for.
            $threw = false;

            try {
                ProblemDetails::create(title: 'x', status: 400, extensions: [$key => $value]);
            } catch (InvalidArgumentException $e) {
                // Silently accepting a reserved key would let an extension
                // overwrite a mandatory member in the serialized document.
                $threw = true;
                Assert::string($e->getMessage())->contains('is reserved');
            }

            Assert::true($threw);

            return;
        }

        $problem = ProblemDetails::create(title: 'x', status: 400, extensions: [$key => $value]);

        Assert::same($problem->toArray()[$key], $value);
    }

    /**
     * @return array<string, ArbitraryInterface>
     */
    public static function anExtensionKeyIsAcceptedExactlyWhenItIsNotAReservedMemberGenerators(): array
    {
        return [
            // Half the draws are the reserved members themselves, so the
            // rejecting branch is reached by construction rather than by a
            // random string colliding with one of five words.
            'key' => Gen::frequency([
                [1, Gen::elements(['type', 'title', 'status', 'detail', 'instance'])],
                [1, Gen::regex('[a-z][a-z0-9_-]{0,12}')],
            ]),
            'value' => Gen::int(),
        ];
    }

    #[Property(runs: 200)]
    public function invalidParamsSurviveIntoTheExtensionVerbatim(array $pairs): void
    {
        $params = \array_map(
            static fn(array $pair): InvalidParam => InvalidParam::create(name: $pair[0], reason: $pair[1]),
            $pairs,
        );

        Classify::cover(\count($params) === 1, 'a single failure', 15.0);
        Classify::cover(\count($params) > 1, 'several failures', 40.0);

        $problem = ProblemDetails::create(title: 'Validation failed', status: 422)
            ->withInvalidParams(...$params);

        // RFC 9457 puts the list under one well-known key, in order; a client
        // rendering a form relies on both.
        Assert::same(
            $problem->toArray()[ProblemDetails::INVALID_PARAMS_EXTENSION],
            \array_map(static fn(InvalidParam $param): array => $param->toArray(), $params),
        );
    }

    /**
     * @return array<string, ArbitraryInterface>
     */
    public static function invalidParamsSurviveIntoTheExtensionVerbatimGenerators(): array
    {
        return [
            'pairs' => Gen::nonEmptyArrayOf(
                Gen::tuple(
                    Gen::stringFrom('abcdefghijklmnopqrstuvwxyz.[]0123456789', minLength: 1, maxLength: 16),
                    Gen::stringFrom('abcdefghijklmnopqrstuvwxyz ', minLength: 1, maxLength: 24),
                ),
                4,
            ),
        ];
    }

    #[Property(runs: 200)]
    public function toJsonIsDeterministic(string $type, string $title, int $status): void
    {
        $problem = new ProblemDetails(type: $type, title: $title, status: $status);

        Assert::same($problem->toJson(), $problem->toJson());
    }

    public static function toJsonIsDeterministicGenerators(): array
    {
        return [
            'type' => Gen::stringOf(1, 30),
            'title' => Gen::string(),
            'status' => Gen::intBetween(100, 599),
        ];
    }

    #[Property(runs: 200, generators: 'problemGenerators')]
    public function toArrayAlwaysHasMandatoryMembers(
        string $type,
        string $title,
        int $status,
        ?string $detail,
        ?string $instance,
        array $extensions,
    ): void {
        $problem = new ProblemDetails(
            type: $type,
            title: $title,
            status: $status,
            detail: $detail,
            instance: $instance,
            extensions: $extensions,
        );
        $array = $problem->toArray();

        Assert::same($array['type'], $type);
        Assert::same($array['title'], $title);
        Assert::same($array['status'], $status);
    }

    /**
     * @return array<string, ArbitraryInterface>
     */
    public static function problemGenerators(): array
    {
        $scalar = Gen::frequency([
            [3, Gen::string()],
            [3, Gen::int()],
            [1, Gen::bool()],
            [1, Gen::constant(null)],
        ]);

        $extensionValue = Gen::frequency([
            [3, $scalar],
            [1, Gen::arrayOf($scalar, 0, 3)],
        ]);

        return [
            'type' => Gen::stringOf(1, 30),
            'title' => Gen::string(),
            'status' => Gen::intBetween(100, 599),
            'detail' => Gen::nullable(Gen::string()),
            'instance' => Gen::nullable(Gen::string()),
            'extensions' => Gen::dictOf(
                Gen::map(
                    Gen::stringFrom('abcdefghijklmnopqrstuvwxyz', 1, 8),
                    static fn(string $suffix): string => 'ext_' . $suffix,
                ),
                $extensionValue,
                0,
                4,
            ),
        ];
    }
}
