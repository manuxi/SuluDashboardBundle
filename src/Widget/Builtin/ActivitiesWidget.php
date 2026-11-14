<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Widget\PinnedDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\ActivityAdmin;

/**
 * "Recent activity". The React component "sulu_activities" reads the activity API of Sulu, which translates the texts on
 * the server and checks the permissions, so this class has no data of its own.
 */
final class ActivitiesWidget extends AbstractBuiltinWidget implements PinnedDashboardWidgetInterface
{
    public function getKey(): string
    {
        return 'sulu_activities';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.activities';
    }

    public function getType(): string
    {
        return WidgetTypes::CUSTOM;
    }

    public function getSecurityContext(): string
    {
        return ActivityAdmin::SECURITY_CONTEXT;
    }

    /**
     * Core tile: stays where it is when the columns are balanced.
     */
    public function isPinned(): bool
    {
        return true;
    }

    public function getColumn(): string
    {
        return WidgetColumns::SIDE;
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getData(string $locale): array
    {
        return [];
    }
}
