<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\DependencyInjection\Fixtures;

/**
 * A tile without any dependency on the dashboard bundle: only getData(), the rest comes from the tag.
 */
final class DuckWidget
{
    /**
     * @return array<string, mixed>
     */
    public function getData(string $locale): array
    {
        return ['items' => [], 'locale' => $locale];
    }
}
