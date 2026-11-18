<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Tab\Fixtures;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Tab\TilesTabProviderInterface;

/**
 * A tab built from the tiles of a group, for tests.
 */
final class TilesTab implements TilesTabProviderInterface
{
    /**
     * @param list<int>            $periods
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $key = 'visitors',
        private readonly string $group = 'visitors',
        private readonly array $periods = [7, 30, 90, 365],
        private readonly int $defaultPeriod = 30,
        private readonly bool $showLocale = false,
        private readonly array $options = [],
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return 'test.tab';
    }

    public function getViewType(): string
    {
        return DashboardAdmin::VIEW;
    }

    public function getViewOptions(): array
    {
        return $this->options;
    }

    public function getSecurityContext(): ?string
    {
        return null;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getPeriods(): array
    {
        return $this->periods;
    }

    public function getDefaultPeriod(): int
    {
        return $this->defaultPeriod;
    }

    public function showLocale(): bool
    {
        return $this->showLocale;
    }
}
