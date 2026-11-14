<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Tab\Fixtures;

use Manuxi\SuluDashboardBundle\Tab\ReplacesNavigationItemInterface;

/**
 * A tab that takes the place of the menu item "foreign.statistics" and its view "foreign.view".
 */
final class ReplacingTab implements ReplacesNavigationItemInterface
{
    public function getKey(): string
    {
        return 'stats';
    }

    public function getTitle(): string
    {
        return 'test.stats';
    }

    public function getViewType(): string
    {
        return 'foreign.view';
    }

    public function getViewOptions(): array
    {
        return [];
    }

    public function getSecurityContext(): string
    {
        return 'sulu.tab';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getReplacedNavigationItem(): string
    {
        return 'foreign.statistics';
    }

    public function getReplacedViews(): array
    {
        return ['foreign.view'];
    }
}
