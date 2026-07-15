<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

use InvalidArgumentException;
use JsonException;

/**
 * An RFC 9457 Problem Details document.
 *
 * @api
 */
final readonly class ProblemDetails
{
    public const string INVALID_PARAMS_EXTENSION = 'invalid-params';

    private const array RESERVED_EXTENSION_KEYS = [
        'type',
        'title',
        'status',
        'detail',
        'instance',
    ];

    private const array REASON_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a teapot",
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public string $type,
        public string $title,
        public int $status,
        public ?string $detail = null,
        public ?string $instance = null,
        public array $extensions = [],
    ) {
        if ($type === '') {
            throw new InvalidArgumentException('Problem type must not be empty');
        }

        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException(sprintf('Invalid HTTP status code %d', $status));
        }

        $this->validateExtensions($extensions);
    }

    /**
     * @param array<string, mixed> $extensions
     */
    public static function create(
        string $title,
        int $status,
        string $type = 'about:blank',
        ?string $detail = null,
        ?string $instance = null,
        array $extensions = [],
    ): self {
        return new self(
            type: $type,
            title: $title,
            status: $status,
            detail: $detail,
            instance: $instance,
            extensions: $extensions,
        );
    }

    public static function fromStatus(
        int $status,
        ?string $title = null,
        string $type = 'about:blank',
    ): self {
        return self::create(
            title: $title ?? self::REASON_PHRASES[$status] ?? 'Unknown Status',
            status: $status,
            type: $type,
        );
    }

    public function withDetail(string $detail): self
    {
        return new self(
            type: $this->type,
            title: $this->title,
            status: $this->status,
            detail: $detail,
            instance: $this->instance,
            extensions: $this->extensions,
        );
    }

    public function withInstance(string $instance): self
    {
        return new self(
            type: $this->type,
            title: $this->title,
            status: $this->status,
            detail: $this->detail,
            instance: $instance,
            extensions: $this->extensions,
        );
    }

    public function withExtension(string $key, mixed $value): self
    {
        return $this->withExtensions(array_replace($this->extensions, [$key => $value]));
    }

    public function withInvalidParams(InvalidParam ...$invalidParams): self
    {
        if ($invalidParams === []) {
            throw new InvalidArgumentException('At least one invalid parameter is required');
        }

        return $this->withExtension(
            self::INVALID_PARAMS_EXTENSION,
            array_map(
                static fn(InvalidParam $invalidParam): array => $invalidParam->toArray(),
                $invalidParams,
            ),
        );
    }

    /**
     * Replaces all extension members.
     *
     * @param array<string, mixed> $extensions
     */
    public function withExtensions(array $extensions): self
    {
        return new self(
            type: $this->type,
            title: $this->title,
            status: $this->status,
            detail: $this->detail,
            instance: $this->instance,
            extensions: $extensions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
        ];

        if ($this->detail !== null) {
            $result['detail'] = $this->detail;
        }

        if ($this->instance !== null) {
            $result['instance'] = $this->instance;
        }

        return [...$result, ...$this->extensions];
    }

    /**
     * @throws JsonException
     */
    public function toJson(int $flags = 0): string
    {
        $json = json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | $flags,
        );

        if ($json === false) {
            throw new JsonException('Failed to encode problem details');
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function validateExtensions(array $extensions): void
    {
        foreach (self::RESERVED_EXTENSION_KEYS as $key) {
            if (array_key_exists($key, $extensions)) {
                throw new InvalidArgumentException(sprintf('Extension key "%s" is reserved', $key));
            }
        }
    }
}
