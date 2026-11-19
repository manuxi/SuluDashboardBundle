<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Content\ContentReader;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * "Search engine details": how many published pages and articles in this language have an own SEO title, a meta
 * description and a preview image (excerpt image), as three rings. Per ring the entries that lack exactly this detail
 * ("What is missing?"), each with a link into the tab of its form where the detail is entered: the SEO tab for title
 * and description, the excerpt tab for the preview image. Only what Sulu stores; rules of a project (for example a length
 * check) belong in a tile of that project. Rendered by the React component "sulu_seo".
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
        return WidgetTypes::CUSTOM;
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

        // detail => [label, tab of the form that holds it]
        $details = [
            'seoTitle' => ['sulu_dashboard.seo_title', 'seo'],
            'seoDescription' => ['sulu_dashboard.seo_description', 'seo'],
            'excerptImage' => ['sulu_dashboard.seo_image', 'excerpt'],
        ];

        $items = [];
        foreach ($details as $detail => [$label, $tab]) {
            $items[] = [
                'key' => $detail,
                'label' => $label,
                'value' => $seo[$detail],
                'max' => $total,
                'missing' => [
                    'count' => $total - $seo[$detail],
                    'rows' => array_map(fn (array $entry): array => $this->row($entry, $tab, $locale), $seo['missing'][$detail]),
                ],
            ];
        }

        return [
            'text' => ['key' => 'sulu_dashboard.seo_hint', 'parameters' => ['count' => $total]],
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array{title: string, icon: string, link: array{view: string, attributes: array<string, string>}}
     */
    private function row(array $entry, string $tab, string $locale): array
    {
        $isPage = ContentReader::PAGES === $entry['resourceKey'];

        return [
            'title' => $entry['title'],
            'icon' => $isPage ? 'su-webspace' : 'su-newspaper',
            'link' => [
                'view' => $isPage
                    ? 'sulu_page.page_edit_form.' . $tab
                    : 'sulu_article.article.edit_tabs_' . ($entry['group'] ?? 'default') . '.' . $tab,
                'attributes' => $isPage
                    ? ['id' => $entry['id'], 'webspace' => $entry['webspace'], 'locale' => $locale]
                    : ['id' => $entry['id'], 'locale' => $locale],
            ],
        ];
    }
}
