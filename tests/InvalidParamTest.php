<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3ApiProblem\InvalidParam;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(InvalidParam::class)]
final class InvalidParamTest
{
    public function createsTypedExtensionMember(): void
    {
        $invalidParam = InvalidParam::create(name: 'email', reason: 'Invalid email address');

        Assert::same($invalidParam->name, 'email');
        Assert::same($invalidParam->reason, 'Invalid email address');
        Assert::same($invalidParam->toArray(), [
            'name' => 'email',
            'reason' => 'Invalid email address',
        ]);
    }

    #[DataProvider('emptyValueProvider')]
    public function rejectsEmptyValues(string $name, string $reason, string $message): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining($message);

        InvalidParam::create(name: $name, reason: $reason);
    }

    public static function emptyValueProvider(): iterable
    {
        yield 'empty name' => ['', 'Required', 'name must not be empty'];
        yield 'empty reason' => ['email', '', 'reason must not be empty'];
    }
}
