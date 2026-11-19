<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\System;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Tab\TilesTabProviderInterface;

/**
 * The tab "System": condition, security vulnerabilities, versions, environment and storage as tiles of
 * the group "system" that the user can arrange, hide and reset like the tiles of the overview. Only for users with the
 * permission {@see DashboardAdmin::SYSTEM_SECURITY_CONTEXT}: without it there is no tab. No period, no language.
 */
final class SystemTabProvider implements TilesTabProviderInterface
{
    public const KEY = 'system';

    public const GROUP = 'system';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.system';
    }

    public function getViewType(): string
    {
        return DashboardAdmin::VIEW;
    }

    public function getViewOptions(): array
    {
        return [];
    }

    public function getSecurityContext(): string
    {
        return DashboardAdmin::SYSTEM_SECURITY_CONTEXT;
    }

    /**
     * After the tab "Visitors" (priority 0).
     */
    public function getPriority(): int
    {
        return -10;
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
        return false;
    }
}
