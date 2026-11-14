<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tab;

/**
 * A whole page of a bundle as a tab of the dashboard (like Sulu does for the people and organizations of the contacts).
 * With at least one tab the dashboard becomes a tab view: the tab "Overview" with the tiles and one tab per provider.
 * Without a provider the dashboard has no tab bar.
 *
 * The page is a React view that is registered in the viewRegistry of Sulu under {@see getViewType()}.
 */
interface DashboardTabProviderInterface
{
    /**
     * Unique key, lower case letters, digits and underscores ("visitors"). It becomes part of the path (/visitors).
     */
    public function getKey(): string;

    /**
     * Title of the tab: a translation key of the admin domain, or plain text.
     */
    public function getTitle(): string;

    /**
     * Type of the view, the key of the React view in the viewRegistry.
     */
    public function getViewType(): string;

    /**
     * Options of the view (route.options in the React view).
     *
     * @return array<string, mixed>
     */
    public function getViewOptions(): array;

    /**
     * Sulu security context the user needs (view permission), or null for everyone who may use the dashboard.
     */
    public function getSecurityContext(): ?string;

    /**
     * Order of the tabs, higher values first (after the tab "Overview").
     */
    public function getPriority(): int;
}
