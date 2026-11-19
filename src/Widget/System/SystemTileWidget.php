<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\System;

use InvalidArgumentException;
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Widget\ConditionalDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\GroupedDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\PinnedDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * One tile of the tab "System". One class, registered once per entry of {@see TILES}; the data comes from
 * {@see SystemTileData}. All of them need the permission {@see DashboardAdmin::SYSTEM_SECURITY_CONTEXT}. The tiles
 * "Database migrations" and "Failed messages" only exist where there is something to read (doctrine/migrations, a
 * failure transport of Messenger).
 */
final class SystemTileWidget implements GroupedDashboardWidgetInterface, PinnedDashboardWidgetInterface, ConditionalDashboardWidgetInterface
{
    public const KEY_PREFIX = 'sulu_system_';

    /**
     * Name => [title, type, column, priority]. The table of vulnerabilities and the storage are React components of their
     * own (widgets/SystemVulnerabilities.js, widgets/SystemStorage.js), the others are lists without a table head.
     *
     * @var array<string, array{string, string, string, int}>
     */
    public const TILES = [
        'vulnerabilities' => ['sulu_dashboard.system_vulnerabilities', WidgetTypes::CUSTOM, WidgetColumns::MAIN, 100],
        'last_check' => ['sulu_dashboard.system_last_check', WidgetTypes::LIST, WidgetColumns::SIDE, 90],
        'versions' => ['sulu_dashboard.system_versions', WidgetTypes::LIST, WidgetColumns::SIDE, 80],
        'environment' => ['sulu_dashboard.system_environment', WidgetTypes::LIST, WidgetColumns::SIDE, 70],
        'storage' => ['sulu_dashboard.system_storage', WidgetTypes::CUSTOM, WidgetColumns::MAIN, 60],
        'migrations' => ['sulu_dashboard.system_migrations', WidgetTypes::LIST, WidgetColumns::SIDE, 50],
        'failed_messages' => ['sulu_dashboard.system_failed_messages', WidgetTypes::LIST, WidgetColumns::SIDE, 40],
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
     * The table of vulnerabilities has six columns and needs the wide column. The tiles with one sentence ("Database
     * migrations", "Failed messages") belong in the narrow one. The balance never moves them.
     */
    public function isPinned(): bool
    {
        return \in_array($this->name, ['vulnerabilities', 'migrations', 'failed_messages'], true);
    }

    public function getGroup(): string
    {
        return SystemTabProvider::GROUP;
    }

    public function isAvailable(): bool
    {
        return match ($this->name) {
            'migrations' => $this->data->hasMigrations(),
            'failed_messages' => $this->data->hasFailedMessages(),
            default => true,
        };
    }

    public function getData(string $locale): array
    {
        return match ($this->name) {
            'vulnerabilities' => $this->data->vulnerabilities(),
            'last_check' => $this->data->lastCheck(),
            'versions' => $this->data->versions(),
            'environment' => $this->data->environment(),
            'storage' => $this->data->storage($locale),
            'migrations' => $this->data->migrations(),
            default => $this->data->failedMessages(),
        };
    }
}
