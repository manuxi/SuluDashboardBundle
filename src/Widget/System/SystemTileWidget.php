<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\System;

use InvalidArgumentException;
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Widget\GroupedDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\PinnedDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * One tile of the tab "System". One class, registered once per entry of {@see TILES}; the data comes from
 * {@see SystemTileData}. All of them need the permission {@see DashboardAdmin::SYSTEM_SECURITY_CONTEXT}.
 */
final class SystemTileWidget implements GroupedDashboardWidgetInterface, PinnedDashboardWidgetInterface
{
    public const KEY_PREFIX = 'sulu_system_';

    /**
     * Name => [title, type, column, priority]. All tiles are React components: the table of vulnerabilities
     * (widgets/SystemVulnerabilities.js), the storage (widgets/SystemStorage.js) and the tiles of facts "Condition",
     * "Versions" and "Environment" (widgets/SystemFacts.js, one component for the three).
     *
     * @var array<string, array{string, string, string, int}>
     */
    public const TILES = [
        'status' => ['sulu_dashboard.system_status', WidgetTypes::CUSTOM, WidgetColumns::MAIN, 110],
        'vulnerabilities' => ['sulu_dashboard.system_vulnerabilities', WidgetTypes::CUSTOM, WidgetColumns::MAIN, 100],
        'storage' => ['sulu_dashboard.system_storage', WidgetTypes::CUSTOM, WidgetColumns::MAIN, 60],
        'versions' => ['sulu_dashboard.system_versions', WidgetTypes::CUSTOM, WidgetColumns::SIDE, 80],
        'environment' => ['sulu_dashboard.system_environment', WidgetTypes::CUSTOM, WidgetColumns::SIDE, 70],
    ];

    public function __construct(
        private readonly SystemTileData $data,
        private readonly string $name,
    ) {
        if (!isset(self::TILES[$name])) {
            throw new InvalidArgumentException(\sprintf('Unknown system tile "%s". Known: %s.', $name, implode(', ', array_keys(self::TILES))));
        }
    }

    public function getKey(): string
    {
        return self::KEY_PREFIX . $this->name;
    }

    public function getTitle(): string
    {
        return self::TILES[$this->name][0];
    }

    public function getType(): string
    {
        return self::TILES[$this->name][1];
    }

    public function getSecurityContext(): string
    {
        return DashboardAdmin::SYSTEM_SECURITY_CONTEXT;
    }

    public function getColumn(): string
    {
        return self::TILES[$this->name][2];
    }

    public function getPriority(): int
    {
        return self::TILES[$this->name][3];
    }

    /**
     * The condition is the first thing to see and the table of vulnerabilities needs the wide column (six columns): the
     * balance never moves them. The others may move.
     */
    public function isPinned(): bool
    {
        return \in_array($this->name, ['status', 'vulnerabilities'], true);
    }

    public function getGroup(): string
    {
        return SystemTabProvider::GROUP;
    }

    public function getData(string $locale): array
    {
        return match ($this->name) {
            'status' => $this->data->status($locale),
            'vulnerabilities' => $this->data->vulnerabilities(),
            'versions' => $this->data->versions($locale),
            'environment' => $this->data->environment(),
            default => $this->data->storage($locale),
        };
    }
}
