<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

/**
 * A runner that can say why its last run did not work (short, without paths and secrets, see {@see ComposerFailure}).
 */
interface ReportsFailure
{
    /**
     * The reason of the last run; null if it worked.
     */
    public function failure(): ?string;
}
