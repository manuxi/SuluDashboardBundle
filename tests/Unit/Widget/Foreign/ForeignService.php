<?php

declare(strict_types=1);

namespace Acme\DashboardFixtures;

/**
 * A service that is tagged by hand, without any dependency on the dashboard bundle.
 */
final class ForeignService
{
    /**
     * @return array<string, mixed>
     */
    public function getData(string $locale): array
    {
        return ['value' => 1];
    }
}
