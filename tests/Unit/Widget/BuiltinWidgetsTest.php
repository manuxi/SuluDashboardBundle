<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget;

use Manuxi\SuluDashboardBundle\Content\ContentReader;
use Manuxi\SuluDashboardBundle\Widget\Builtin\ActivitiesWidget;
use Manuxi\SuluDashboardBundle\Widget\Builtin\ArticlesCountWidget;
use Manuxi\SuluDashboardBundle\Widget\Builtin\MediaCountWidget;
use Manuxi\SuluDashboardBundle\Widget\Builtin\OpenItemsWidget;
use Manuxi\SuluDashboardBundle\Widget\Builtin\PagesCountWidget;
use Manuxi\SuluDashboardBundle\Widget\Builtin\SeoWidget;
use Manuxi\SuluDashboardBundle\Widget\Builtin\SnippetsCountWidget;
use Manuxi\SuluDashboardBundle\Widget\Builtin\TranslationsWidget;
use Manuxi\SuluDashboardBundle\Widget\ConditionalDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;

class BuiltinWidgetsTest extends TestCase
{
    private function reader(): ContentReader
    {
        $reader = $this->createMock(ContentReader::class);
        $reader->method('placeCounts')->willReturn(['total' => 10, 'published' => 6, 'draft' => 3, 'unpublished' => 0, 'review' => 1]);
        $reader->method('countSnippets')->willReturn(4);
        $reader->method('countMedia')->willReturn(99);
        $reader->method('openItems')->willReturn([['id' => 'a']]);
        $reader->method('seoCoverage')->willReturn(['total' => 4, 'seoTitle' => 3, 'seoDescription' => 2, 'excerptImage' => 1, 'missing' => [
            ['resourceKey' => 'pages', 'id' => 'p1', 'webspace' => 'main', 'group' => null, 'title' => 'About'],
            ['resourceKey' => 'articles', 'id' => 'a1', 'webspace' => null, 'group' => 'blog', 'title' => 'Post'],
        ]]);
        $reader->method('translations')->willReturn(['source' => 'de', 'locales' => []]);

        return $reader;
    }

    public function testCountTilesSplitByStateAndSkipEmptyStates(): void
    {
        $data = (new PagesCountWidget($this->reader()))->getData('de');

        $this->assertSame(10, $data['value']);
        $this->assertSame(
            [
                ['label' => 'sulu_dashboard.place_published', 'count' => 6, 'state' => 'published'],
                ['label' => 'sulu_dashboard.place_draft', 'count' => 3, 'state' => 'draft'],
                ['label' => 'sulu_dashboard.place_review', 'count' => 1, 'state' => 'review'],
            ],
            $data['parts'],
        );
        $this->assertSame($data, (new ArticlesCountWidget($this->reader()))->getData('de'));
    }

    public function testSnippetsAndMediaAreBareNumbersWithTheirOwnPermission(): void
    {
        $snippets = new SnippetsCountWidget($this->reader());
        $media = new MediaCountWidget($this->reader());

        $this->assertSame(['value' => 4], $snippets->getData('de'));
        $this->assertSame(['value' => 99], $media->getData('de'));
        $this->assertSame('sulu.snippet.snippets', $snippets->getSecurityContext());
        $this->assertSame('sulu.media.collections', $media->getSecurityContext());
    }

    public function testOpenItemsAndTranslationsAreCustomTiles(): void
    {
        $localizations = $this->createMock(LocalizationManagerInterface::class);
        $localizations->method('getLocales')->willReturn(['de', 'en']);

        $this->assertSame(['items' => [['id' => 'a']]], (new OpenItemsWidget($this->reader()))->getData('de'));
        $this->assertSame(['source' => 'de', 'locales' => []], (new TranslationsWidget($this->reader(), $localizations))->getData('de'));
    }

    public function testTranslationsAreOnlyAvailableWithSeveralLanguages(): void
    {
        $single = $this->createMock(LocalizationManagerInterface::class);
        $single->method('getLocales')->willReturn(['de']);
        $multi = $this->createMock(LocalizationManagerInterface::class);
        $multi->method('getLocales')->willReturn(['de', 'en']);

        $this->assertFalse((new TranslationsWidget($this->reader(), $single))->isAvailable());
        $this->assertTrue((new TranslationsWidget($this->reader(), $multi))->isAvailable());
    }

    public function testActivitiesUseThePermissionOfSulusActivityList(): void
    {
        $widget = new ActivitiesWidget();

        $this->assertSame('sulu.activities.activities', $widget->getSecurityContext());
        $this->assertSame([], $widget->getData('de'));
    }

    public function testEveryBuiltinTileIsValidAndKeysAreReserved(): void
    {
        $localizations = $this->createMock(LocalizationManagerInterface::class);
        $widgets = [
            new PagesCountWidget($this->reader()),
            new ArticlesCountWidget($this->reader()),
            new SnippetsCountWidget($this->reader()),
            new MediaCountWidget($this->reader()),
            new OpenItemsWidget($this->reader()),
            new SeoWidget($this->reader()),
            new TranslationsWidget($this->reader(), $localizations),
            new ActivitiesWidget(),
        ];

        $keys = [];
        foreach ($widgets as $widget) {
            $this->assertInstanceOf(DashboardWidgetInterface::class, $widget);
            $this->assertStringStartsWith('sulu_', $widget->getKey());
            $this->assertContains($widget->getType(), WidgetTypes::all());
            $this->assertContains($widget->getColumn(), WidgetColumns::all());
            $this->assertStringStartsWith('sulu_dashboard.', $widget->getTitle());
            $keys[] = $widget->getKey();
        }

        $this->assertSame($keys, array_unique($keys));
        $this->assertCount(1, array_filter($widgets, static fn ($widget): bool => $widget instanceof ConditionalDashboardWidgetInterface));
    }

    public function testSeoTileHasThreeRingsAndLinksIntoTheSeoTab(): void
    {
        $widget = new SeoWidget($this->reader());
        $data = $widget->getData('de');

        $this->assertSame('progress', $widget->getType());
        $this->assertSame('main', $widget->getColumn());
        $this->assertSame('ring', $data['display']);
        $this->assertSame([3, 2, 1], array_column($data['items'], 'value'));
        $this->assertSame([4, 4, 4], array_column($data['items'], 'max'));
        $this->assertSame(4, $data['text']['parameters']['count']);

        $rows = $data['footer']['rows'];
        $this->assertSame('sulu_page.page_edit_form.seo', $rows[0]['link']['view']);
        $this->assertSame(['id' => 'p1', 'webspace' => 'main', 'locale' => 'de'], $rows[0]['link']['attributes']);
        $this->assertSame('sulu_article.article.edit_tabs_blog.seo', $rows[1]['link']['view']);
        $this->assertSame(['id' => 'a1', 'locale' => 'de'], $rows[1]['link']['attributes']);
    }
}
