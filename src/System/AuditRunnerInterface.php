<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

interface AuditRunnerInterface
{
    public function run(): AuditResult;
}
