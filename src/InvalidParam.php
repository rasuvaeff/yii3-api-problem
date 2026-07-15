<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

use InvalidArgumentException;

/**
 * A field validation failure for the RFC 9457 `invalid-params` extension.
 *
 * @api
 */
final readonly class InvalidParam
{
    private function __construct(
        public string $name,
        public string $reason,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Invalid parameter name must not be empty');
        }

        if ($reason === '') {
            throw new InvalidArgumentException('Invalid parameter reason must not be empty');
        }
    }

    public static function create(string $name, string $reason): self
    {
        return new self(name: $name, reason: $reason);
    }

    /**
     * @return array{name: string, reason: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'reason' => $this->reason,
        ];
    }
}
