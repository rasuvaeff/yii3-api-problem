<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem\Tests\Support;

use Closure;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class FakeHandler implements RequestHandlerInterface
{
    /**
     * @param Closure(ServerRequestInterface): ResponseInterface|null $callback
     */
    public function __construct(private ?Closure $callback = null) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->callback instanceof \Closure) {
            return ($this->callback)($request);
        }

        return new Response(status: 204);
    }
}
