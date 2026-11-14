<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * Figure tile of the top row: a total with an optional split by workflow place (green = live, orange = unpublished
 * changes, like in the lists of Sulu).
 */
abstract class AbstractCountWidget extends AbstractBuiltinWidget
{
    public function getType(): string
    {
        return WidgetTypes::NUMBER;
    }

    public function getColumn(): string
    {
        return WidgetColumns::ROW;
    }

    /**
     * @param array{total: int, published: int, draft: int, unpublished: int, review: int} $counts
     *
     * @return array{value: int, parts: list<array{label: string, count: int, state: string}>}
     */
    protected function fromPlaceCounts(array $counts): array
    {
        $parts = [];
        foreach (['published', 'draft', 'unpublished', 'review'] as $place) {
            if ($counts[$place] > 0) {
                $parts[] = ['label' => 'sulu_dashboard.place_' . $place, 'count' => $counts[$place], 'state' => $place];
            }
        }

        return ['value' => $counts['total'], 'parts' => $parts];
    }
}
