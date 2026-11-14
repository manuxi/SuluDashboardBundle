<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Content\ContentReader;

final class PagesCountWidget extends AbstractCountWidget
{
    public function __construct(private readonly ContentReader $reader)
    {
    }

    public function getKey(): string
    {
        return 'sulu_pages';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.pages';
    }

    public function getPriority(): int
    {
        return 40;
    }

    public function getData(string $locale): array
    {
        return $this->fromPlaceCounts($this->reader->placeCounts(ContentReader::PAGES, $locale));
    }
}
