<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

interface UpdateRunnerInterface
{
    /**
     * Newer patch releases of the packages the tab "System" names (Sulu, Symfony).
     *
     * @return list<array{package: string, installed: string, latest: string}>|null null if the check could not run
     *                                                                              (no internet, Composer missing); an empty list means: nothing newer
     */
    public function run(): ?array;
}
