<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Content\ContentReader;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * "Search engine details": how many published pages and articles in this language have an own SEO title, a meta
 * description and a preview image (excerpt image), as three rings. Below, the first entries without a meta description
 * with a link into the SEO tab of their form. Only what Sulu stores; rules of a project (for example a length check) belong
 * in a tile of that project.
 */
final class SeoWidget extends AbstractBuiltinWidget
{
    public function __construct(private readonly ContentReader $reader)
    {
    }

    public function getKey(): string
    {
        return 'sulu_seo';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.seo';
    }

    public function getType(): string
    {
        return WidgetTypes::PROGRESS;
    }

    public function getColumn(): string
    {
        return WidgetColumns::MAIN;
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function getData(string $locale): array
    {
        $seo = $this->reader->seoCoverage($locale);
        $total = $seo['total'];

        $rows = [];
        foreach ($seo['missing'] as $entry) {
            $isPage = ContentReader::PAGES === $entry['resourceKey'];
            $rows[] = [
                'title' => $entry['title'],
                'icon' => $isPage ? 'su-webspace' : 'su-newspaper',
                'link' => [
                    'view' => $isPage ? 'sulu_page.page_edit_form.seo' : 'sulu_article.article.edit_tabs_' . ($entry['group'] ?? 'default') . '.seo',
                    'attributes' => $isPage
                        ? ['id' => $entry['id'], 'webspace' => $entry['webspace'], 'locale' => $locale]
                        : ['id' => $entry['id'], 'locale' => $locale],
                ],
            ];
        }

        return [
            'text' => ['key' => 'sulu_dashboard.seo_hint', 'parameters' => ['count' => $total]],
            'display' => 'ring',
            'items' => [
                ['label' => 'sulu_dashboard.seo_title', 'value' => $seo['seoTitle'], 'max' => $total],
                ['label' => 'sulu_dashboard.seo_description', 'value' => $seo['seoDescription'], 'max' => $total],
                ['label' => 'sulu_dashboard.seo_image', 'value' => $seo['excerptImage'], 'max' => $total],
            ],
            'footer' => [] === $rows ? null : ['title' => 'sulu_dashboard.seo_missing', 'rows' => $rows],
        ];
    }
}
