# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Mutation testing now counts uncovered code (`infection --with-uncovered`).
  Two source refactors support it, with no behaviour change: the
  `ProblemDetails::toJson()` signature is split over several lines, and the
  unreachable `$json === false` branch (`JSON_THROW_ON_ERROR` is always set) is
  replaced by an `assert()`.

## 1.1.0 — 2026-10-17

- Wire a `ThrowableReporterInterface` through params: new
  `params['rasuvaeff/yii3-api-problem']['throwable_reporter']` key (class name
  string or `null`, default `null`). `config/di.php` resolves the configured
  class from the container and passes it to `ProblemDetailsMiddleware`; the
  package still binds no reporter itself (#14).

## 1.0.0 — 2026-07-16

- Initial implementation.
