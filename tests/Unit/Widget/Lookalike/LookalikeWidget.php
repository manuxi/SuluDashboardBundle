<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundleExtra;

use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * A tile of a bundle whose namespace only starts like the one of the dashboard bundle.
 */
final class LookalikeWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly string $key,
        private readonly ?string $securityContext = null,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return 'acme.title';
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
        return 0;
    }

    public function getData(string $locale): array
    {
        return ['value' => 1];
    }
}
