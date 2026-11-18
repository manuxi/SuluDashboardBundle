<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Widget\System\SystemTileData;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * "System" on the overview: a small traffic light (what needs attention, or "Everything is in order", and the age of the
 * last check) with a link to the tab "System", where the details are. Only for users with the permission
 * {@see DashboardAdmin::SYSTEM_SECURITY_CONTEXT}.
 *
 * The key stays the one of the first version, so saved arrangements of the overview keep working.
 */
final class SystemWidget extends AbstractBuiltinWidget
{
    public function __construct(private readonly SystemTileData $data)
    {
    }

    public function getKey(): string
    {
        return 'sulu_system';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.system';
    }

    public function getType(): string
    {
        return WidgetTypes::LIST;
    }

    public function getSecurityContext(): string
    {
        return DashboardAdmin::SYSTEM_SECURITY_CONTEXT;
    }

    public function getColumn(): string
    {
        return WidgetColumns::SIDE;
    }

    public function getPriority(): int
    {
        return 5;
    }

    public function getData(string $locale): array
    {
        return $this->data->summary($locale);
    }
}
