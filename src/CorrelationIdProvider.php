<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ApiProblem;

/**
 * @api
 */
interface CorrelationIdProvider
{
    public function getCorrelationId(): ?string;
}
