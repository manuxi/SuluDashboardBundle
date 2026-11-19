<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Extensions;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Tab\ConditionalDashboardTabInterface;
use Manuxi\SuluDashboardBundle\Tab\TilesTabProviderInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;

/**
 * The tab "Extensions": the tiles that other bundles and the project bring ({@see WidgetRegistry::groupFor()}), in the
 * group "extensions". Same "Customize" mode as the overview, own setting key. The tiles use the language (a glossary
 * for example), so the toolbar has the language select; there is no period. The tab exists only while the current user
 * may see at least one tile of the group: no extensions, no tab.
 */
final class ExtensionsTabProvider implements TilesTabProviderInterface, ConditionalDashboardTabInterface
{
    public const KEY = 'extensions';

    public const GROUP = 'extensions';

    public function __construct(private readonly WidgetRegistry $widgetRegistry)
    {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.extensions';
    }

    public function getViewType(): string
    {
        return DashboardAdmin::VIEW;
    }

    public function getViewOptions(): array
    {
        return [];
    }

    public function getSecurityContext(): ?string
    {
        return null;
    }

    /**
     * Right after the tab "Overview": before "Visitors" (priority 0) and "System" (-10).
     */
    public function getPriority(): int
    {
        return 10;
    }

    public function getGroup(): string
    {
        return self::GROUP;
    }

    public function getPeriods(): array
    {
        return [];
    }

    public function getDefaultPeriod(): int
    {
        return 0;
    }

    public function showLocale(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return [] !== $this->widgetRegistry->available(self::GROUP);
    }
}
