<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Content\ContentReader;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * "Open drafts": pages and articles that are not live as edited. Rendered by the React component "sulu_open_items"
 * (table with filters and sorting).
 */
final class OpenItemsWidget extends AbstractBuiltinWidget
{
    public const LIMIT = 50;

    public function __construct(private readonly ContentReader $reader)
    {
    }

    public function getKey(): string
    {
        return 'sulu_open_items';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.open_items';
    }

    public function getType(): string
    {
        return WidgetTypes::CUSTOM;
    }

    public function getColumn(): string
    {
        return WidgetColumns::MAIN;
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getData(string $locale): array
    {
        return ['items' => $this->reader->openItems($locale, self::LIMIT)];
    }
}
