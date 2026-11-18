<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures;

use Manuxi\SuluDashboardBundle\Widget\GroupedDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * A tile of a group, for tests.
 */
final class GroupedWidget implements GroupedDashboardWidgetInterface
{
    public function __construct(
        private readonly string $key,
        private readonly string $group,
        private readonly int $priority = 0,
        private readonly ?string $securityContext = null,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return 'test.title';
    }

    public function getType(): string
    {
        return WidgetTypes::NUMBER;
    }

    public function getSecurityContext(): ?string
    {
        return $this->securityContext;
    }

    public function getColumn(): string
    {
        return WidgetColumns::MAIN;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getData(string $locale): array
    {
        return ['value' => 1, 'locale' => $locale];
    }

    public function getGroup(): string
    {
        return $this->group;
    }
}
