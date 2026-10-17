<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-api-problem' => [
        'debug' => false,
        'use_default_mapper' => true,
        'exception_map' => [],
        // FQCN of a ThrowableReporterInterface implementation resolved from the
        // container for ProblemDetailsMiddleware, or null — no reporter. The
        // package never binds the reporter itself: the application owns it.
        'throwable_reporter' => null,
    ],
];
