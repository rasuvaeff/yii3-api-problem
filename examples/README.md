# Examples

Run from the package root after `composer install`:

| Script | Shows | Needs server? |
|---|---|---|
| `01-basic-problem.php` | Manual Problem Details response | No |
| `02-throw-exception.php` | Client-safe `ProblemDetailsException` | No |
| `03-validation-errors.php` | 422 field errors in extensions | No |
| `04-middleware-setup.php` | PSR-15 exception handling | No |
| `05-custom-exception-map.php` | Exact-class domain mapping | No |
| `06-reporting-and-headers.php` | Throwable reporting and transport headers | No |

```bash
php examples/01-basic-problem.php
php examples/02-throw-exception.php
php examples/03-validation-errors.php
php examples/04-middleware-setup.php
php examples/05-custom-exception-map.php
php examples/06-reporting-and-headers.php
```

The scripts have no environment variables and make no network connections.
