<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

/**
 * A runner that, besides the reason, names a short code for a reason the tile can translate. A separate interface, so that
 * runners that only implement {@see ReportsFailure} keep working.
 */
interface ReportsFailureCode extends ReportsFailure
{
    /**
     * A short code for a reason the tile can translate (for example "github_auth"); null if there is none.
     */
    public function failureCode(): ?string;
}
