<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Content\ContentReader;

final class ArticlesCountWidget extends AbstractCountWidget
{
    public function __construct(private readonly ContentReader $reader)
    {
    }

    public function getKey(): string
    {
        return 'sulu_articles';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.articles';
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function getData(string $locale): array
    {
        return $this->fromPlaceCounts($this->reader->placeCounts(ContentReader::ARTICLES, $locale));
    }
}
