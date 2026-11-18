<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Doctrine\Migrations\DependencyFactory;

/**
 * Number of Doctrine migrations that have not run yet. Only available when doctrine/migrations is installed and
 * configured (the factory is then injected, otherwise null). Reads only.
 */
class PendingMigrations
{
    public function __construct(private readonly ?DependencyFactory $factory = null)
    {
    }

    public function isAvailable(): bool
    {
        return null !== $this->factory;
    }

    public function count(): int
    {
        if (null === $this->factory) {
            return 0;
        }

        return \count($this->factory->getMigrationStatusCalculator()->getNewMigrations());
    }
}
