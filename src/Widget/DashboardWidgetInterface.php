<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

/**
 * A tile on the dashboard. Implement this interface in a service: it is found automatically (autoconfiguration tags
 * it with "sulu_dashboard.widget") and its data is loaded on its own when the dashboard is opened.
 *
 * The interface is small on purpose and only changes in major versions.
 */
interface DashboardWidgetInterface
{
    /**
     * Unique key, lower case letters, digits and underscores ("testimonials_stars"). Keys starting with "sulu_" are
     * reserved for the tiles of this bundle.
     */
    public function getKey(): string;

    /**
     * Title of the tile: a translation key of the admin domain, or plain text.
     */
    public function getTitle(): string;

    /**
     * Kind of display, one of the constants of {@see WidgetTypes}.
     */
    public function getType(): string;

    /**
     * Sulu security context the user needs (view permission) to see the tile, or null for everyone who may use the
     * dashboard.
     */
    public function getSecurityContext(): ?string;

    /**
     * Place of the tile, one of the constants of {@see WidgetColumns}.
     */
    public function getColumn(): string;

    /**
     * Order inside the column, higher values first.
     */
    public function getPriority(): int;

    /**
     * Data of the tile in the shape of its type (see docs/widgets). Called per request and per tile, so an error stays
     * inside this tile.
     *
     * @return array<string, mixed>
     */
    public function getData(string $locale): array;
}
