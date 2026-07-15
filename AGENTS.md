# AGENTS.md — yii3-api-problem

Guidance for AI agents working on this package. Read before changing code.

## What this is

An RFC 9457 Problem Details implementation for Yii3 and any PSR-7/PSR-15
application, in namespace `Rasuvaeff\Yii3ApiProblem`. Public API:
`ProblemDetails`, `InvalidParam`, `ProblemDetailsException`, response factory
interface and implementation, exception mapper interface and default
implementation, `ProblemDetailsMiddleware`, optional `CorrelationIdProvider`,
and `ThrowableReporterInterface`.

The value object, response factory, and middleware are independent layers. Keep
runtime dependencies PSR-only; Yii3-specific behavior belongs in `config/`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Production responses never leak generic exception internals.** With debug
   disabled, do not expose ordinary exception messages or stack traces.
   `ProblemDetailsException` is the explicit client-safe disclosure boundary.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`composer.lock` is gitignored (library). `make test-coverage` and
`make mutation` bootstrap `pcov` inside the `composer:2` container because the
base image has no coverage driver.

## Invariants & gotchas

- `Content-Type` is always `application/problem+json`; every problem response
  carries `X-Content-Type-Options: nosniff`.
- Status is in 100..599, type is non-empty, and reserved standard members never
  occur in extensions.
- `invalid-params` is a package-defined extension shape. Do not describe it as
  a mandatory RFC 9457 member or change its `name`/`reason` contract silently.
- Caller transport headers are applied before mandatory security headers;
  `Content-Type` and `X-Content-Type-Options` must remain non-overridable.
- `ThrowableReporterInterface` is application-owned and deliberately unbound in
  core DI. It receives every caught throwable and request, is skipped for
  successful responses, and implementations must not throw.
- `ProblemDetails` is immutable. Every `with*` method returns a new instance.
- Required JSON flags are always active, even when a caller supplies more flags.
- Configured exception mapping is exact-class. Do not silently change it to
  inheritance or interface matching.
- The core config does not bind `ExceptionMapperInterface`. Exactly one source,
  the application, owns a replaceable mapper binding.
- The optional correlation provider replaces `instance` only when it returns a
  non-null ID. The correlation package remains a Composer suggestion.
- `ProblemDetailsException` detail, extensions, and headers are public data.
  Call sites must never place secrets in them.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment. Never revert
  to floating tags. Keep workflow-level `permissions: { contents: read }` and
  `persist-credentials: false` on every checkout. Verify workflow changes with
  `zizmor --persona=auditor .github/`.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects public API or release safety,
  also run `make release-check`. Paste the output.
