<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Content;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Manuxi\SuluDashboardBundle\Content\ContentReader;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormGroup;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;

/**
 * Runs the queries against SQLite with the columns of the Sulu tables that the reader uses, and checks that content the
 * user may not see is not counted or listed.
 */
class ContentReaderTest extends TestCase
{
    private Connection $connection;

    /** @var list<string> security contexts the user may view */
    private array $allowed = [];

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ([
            'CREATE TABLE pa_pages (uuid TEXT, webspaceKey TEXT)',
            'CREATE TABLE pa_page_dimension_contents (pageUuid TEXT, stage TEXT, version INT, locale TEXT, workflowPlace TEXT, templateKey TEXT, title TEXT, changed TEXT, idUsersChanger INT, seoData TEXT, excerptData TEXT)',
            'CREATE TABLE ar_article_dimension_contents (articleUuid TEXT, stage TEXT, version INT, locale TEXT, workflowPlace TEXT, templateKey TEXT, title TEXT, changed TEXT, idUsersChanger INT, seoData TEXT, excerptData TEXT)',
            'CREATE TABLE se_users (id INT, idContacts INT)',
            'CREATE TABLE co_contacts (id INT, firstName TEXT, lastName TEXT)',
            'CREATE TABLE sn_snippets (id INT)',
            'CREATE TABLE me_media (id INT)',
        ] as $sql) {
            $this->connection->executeStatement($sql);
        }

        $this->connection->insert('co_contacts', ['id' => 1, 'firstName' => 'Erika', 'lastName' => 'Muster']);
        $this->connection->insert('se_users', ['id' => 7, 'idContacts' => 1]);
        $this->connection->insert('sn_snippets', ['id' => 1]);
        $this->connection->insert('sn_snippets', ['id' => 2]);
        $this->connection->insert('me_media', ['id' => 1]);

        // two webspaces; pages "a1" (published), "a2" (changes), "a3" (not published, only de), "b1" (other webspace)
        $this->page('a1', 'main', 'de', 'published', 'Home', '2026-11-01 10:00:00');
        $this->page('a1', 'main', 'en', 'published', 'Home EN', '2026-11-01 09:00:00');
        $this->page('a2', 'main', 'de', 'draft', 'About', '2026-11-05 10:00:00');
        $this->page('a2', 'main', 'en', 'draft', 'About EN', '2026-11-02 10:00:00'); // original is 3 days newer: outdated
        $this->page('a3', 'main', 'de', 'unpublished', 'New', '2026-11-06 10:00:00');
        $this->page('b1', 'other', 'de', 'review_draft', 'Secret', '2026-11-07 10:00:00');
        // a version row and a live row must not count
        $this->connection->insert('pa_page_dimension_contents', ['pageUuid' => 'a1', 'stage' => 'live', 'version' => 0, 'locale' => 'de', 'workflowPlace' => 'published', 'templateKey' => 'default', 'title' => 'Home', 'changed' => '2026-11-01 10:00:00', 'idUsersChanger' => null]);
        $this->connection->insert('pa_page_dimension_contents', ['pageUuid' => 'a1', 'stage' => 'draft', 'version' => 3, 'locale' => 'de', 'workflowPlace' => 'draft', 'templateKey' => 'default', 'title' => 'Old', 'changed' => '2026-10-01 10:00:00', 'idUsersChanger' => null]);

        $this->article('x1', 'blog', 'de', 'published', 'Post', '2026-11-03 10:00:00');
        $this->article('x2', 'blog', 'de', 'unpublished', 'Draft post', '2026-11-08 10:00:00');
        $this->article('y1', 'event', 'de', 'draft', 'Event draft', '2026-11-09 10:00:00');
    }

    private function page(string $uuid, string $webspace, string $locale, string $place, string $title, string $changed): void
    {
        if (!$this->connection->fetchOne('SELECT 1 FROM pa_pages WHERE uuid = ?', [$uuid])) {
            $this->connection->insert('pa_pages', ['uuid' => $uuid, 'webspaceKey' => $webspace]);
        }
        $this->connection->insert('pa_page_dimension_contents', ['pageUuid' => $uuid, 'stage' => 'draft', 'version' => 0, 'locale' => $locale, 'workflowPlace' => $place, 'templateKey' => 'default', 'title' => $title, 'changed' => $changed, 'idUsersChanger' => 7]);
    }

    private function article(string $uuid, string $template, string $locale, string $place, string $title, string $changed): void
    {
        $this->connection->insert('ar_article_dimension_contents', ['articleUuid' => $uuid, 'stage' => 'draft', 'version' => 0, 'locale' => $locale, 'workflowPlace' => $place, 'templateKey' => $template, 'title' => $title, 'changed' => $changed, 'idUsersChanger' => 7]);
    }

    /**
     * @param list<string> $allowed
     */
    private function reader(array $allowed): ContentReader
    {
        $this->allowed = $allowed;

        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturnCallback(function (mixed $subject, string $permission): bool {
            $context = $subject instanceof SecurityCondition ? $subject->getSecurityContext() : $subject;

            return 'view' === $permission && \in_array($context, $this->allowed, true);
        });

        $main = new Webspace();
        $main->setKey('main');
        $de = new Localization('de');
        $de->setDefault(true);
        $main->addLocalization($de);
        $main->addLocalization(new Localization('en'));

        $other = new Webspace();
        $other->setKey('other');
        $otherDe = new Localization('de');
        $otherDe->setDefault(true);
        $other->addLocalization($otherDe);

        $webspaces = $this->createMock(WebspaceManagerInterface::class);
        $webspaces->method('getWebspaceCollection')->willReturn(new WebspaceCollection(['main' => $main, 'other' => $other]));

        $localizations = $this->createMock(LocalizationManagerInterface::class);
        $localizations->method('getLocales')->willReturn(['de', 'en']);

        $groups = $this->createMock(GroupProviderInterface::class);
        $groups->method('getGroups')->willReturn([
            'default' => new FormGroup('default', 'Default', ['default_article']),
            'blog' => new FormGroup('blog', 'Blog', ['blog']),
            'event' => new FormGroup('event', 'Event', ['event']),
        ]);

        return new ContentReader($this->connection, $checker, $webspaces, $localizations, $groups);
    }

    public function testCountsPerWorkflowPlaceInOneLocaleOnly(): void
    {
        $reader = $this->reader(['sulu.webspaces.main', 'sulu.webspaces.other']);

        $this->assertSame(
            ['total' => 4, 'published' => 1, 'draft' => 1, 'unpublished' => 1, 'review' => 1],
            $reader->placeCounts(ContentReader::PAGES, 'de'),
            'review_draft counts as review; live rows and versions are ignored',
        );
        $this->assertSame(['total' => 2, 'published' => 1, 'draft' => 1, 'unpublished' => 0, 'review' => 0], $reader->placeCounts(ContentReader::PAGES, 'en'));
    }

    public function testPagesOfAWebspaceWithoutPermissionAreNotCounted(): void
    {
        $counts = $this->reader(['sulu.webspaces.main'])->placeCounts(ContentReader::PAGES, 'de');

        $this->assertSame(3, $counts['total']);
        $this->assertSame(0, $counts['review'], 'the only review page is in the forbidden webspace');
    }

    public function testNoPermissionAtAllCountsZero(): void
    {
        $reader = $this->reader([]);

        $this->assertSame(0, $reader->placeCounts(ContentReader::PAGES, 'de')['total']);
        $this->assertSame(0, $reader->placeCounts(ContentReader::ARTICLES, 'de')['total']);
        $this->assertSame([], $reader->openItems('de'));
    }

    public function testArticlesFollowTheSecurityContextOfTheirGroup(): void
    {
        // with several groups, the default group uses the general context and the others one context per group
        $reader = $this->reader(['sulu.article.articles_blog']);

        $this->assertSame(2, $reader->placeCounts(ContentReader::ARTICLES, 'de')['total']);

        $reader = $this->reader(['sulu.article.articles']);

        $this->assertSame(0, $reader->placeCounts(ContentReader::ARTICLES, 'de')['total'], 'the general context only covers the default group');
    }

    public function testOpenItemsAreNewestFirstWithGroupAndChanger(): void
    {
        $items = $this->reader(['sulu.webspaces.main', 'sulu.article.articles_blog'])->openItems('de');

        $this->assertSame(['x2', 'a3', 'a2'], array_column($items, 'id'), 'only items that are not live as edited, only permitted ones');

        $this->assertSame('articles', $items[0]['resourceKey']);
        $this->assertSame('blog', $items[0]['group']);
        $this->assertSame('Blog', $items[0]['groupTitle']);
        $this->assertSame('Erika Muster', $items[0]['changer']);
        $this->assertSame(7, $items[0]['changerId']);

        $this->assertSame('pages', $items[1]['resourceKey']);
        $this->assertSame('main', $items[1]['webspace']);
        $this->assertNull($items[1]['group']);
    }

    public function testOpenItemsRespectTheLimit(): void
    {
        $items = $this->reader(['sulu.webspaces.main', 'sulu.webspaces.other', 'sulu.article.articles_blog', 'sulu.article.articles_event'])->openItems('de', 2);

        $this->assertSame(['y1', 'x2'], array_column($items, 'id'));
    }

    public function testCountsSnippetsAndMedia(): void
    {
        $reader = $this->reader([]);

        $this->assertSame(2, $reader->countSnippets());
        $this->assertSame(1, $reader->countMedia());
    }

    public function testTranslationsListMissingAndOutdatedPerWebspaceLanguages(): void
    {
        $translations = $this->reader(['sulu.webspaces.main', 'sulu.webspaces.other', 'sulu.article.articles_blog', 'sulu.article.articles_event'])->translations();

        $this->assertSame('de', $translations['source']);

        // pages: a1, a2, a3 (main: de+en) and b1 (other: de only); articles: de only, 3 of them, in the languages of the site
        $en = $translations['locales']['en'];
        $this->assertSame(3 + 3, $en['total']);
        $this->assertSame(1 + 3, $en['missing'], 'a3 and the three articles have no English version');
        $this->assertSame(1, $en['outdated'], 'a2: the German original is more than a day newer');

        $this->assertSame(0, $translations['locales']['de']['outdated'], 'the original language is never outdated');
        $this->assertSame(0, $translations['locales']['de']['missing']);

        $states = array_column($en['items'], 'state');
        $this->assertContains('outdated', $states);
        $this->assertContains('missing', $states);
        $this->assertSame('a2', $en['items'][array_search('outdated', $states, true)]['id']);
    }

    public function testTranslationsLeaveOutContentWithoutPermission(): void
    {
        $translations = $this->reader(['sulu.webspaces.other'])->translations();

        $this->assertSame(1, $translations['locales']['de']['total'], 'only b1');
        $this->assertArrayNotHasKey('en', $translations['locales'], 'the other webspace has no English');
    }

    private function live(string $table, string $column, string $uuid, string $template, string $title, string $changed, string $seo, string $excerpt): void
    {
        $this->connection->insert($table, [$column => $uuid, 'stage' => 'live', 'version' => 0, 'locale' => 'de', 'workflowPlace' => 'published', 'templateKey' => $template, 'title' => $title, 'changed' => $changed, 'seoData' => $seo, 'excerptData' => $excerpt]);
    }

    public function testSeoCoverageCountsPublishedEntriesWithTitleDescriptionAndImage(): void
    {
        $this->connection->executeStatement("DELETE FROM pa_page_dimension_contents WHERE stage = 'live'");
        // pages: full, only title, nothing; b1 is in the other webspace
        $this->live('pa_page_dimension_contents', 'pageUuid', 'a1', 'default', 'Home', '2026-11-03 10:00:00', '{"title":"T","description":"D"}', '{"image":{"id":3}}');
        $this->live('pa_page_dimension_contents', 'pageUuid', 'a2', 'default', 'About', '2026-11-02 10:00:00', '{"title":"T","description":" "}', '{"image":null}');
        $this->live('pa_page_dimension_contents', 'pageUuid', 'b1', 'default', 'Secret', '2026-11-01 10:00:00', '{}', '{}');
        $this->live('ar_article_dimension_contents', 'articleUuid', 'x1', 'blog', 'Post', '2026-11-04 10:00:00', '{"title":"","description":"D"}', '{"image":{"id":9}}');
        $this->connection->update('pa_pages', ['webspaceKey' => 'other'], ['uuid' => 'b1']);

        $seo = $this->reader(['sulu.webspaces.main', 'sulu.article.articles_blog'])->seoCoverage('de');

        $this->assertSame(3, $seo['total'], 'b1 is in a webspace the user may not see');
        $this->assertSame(2, $seo['seoTitle']);
        $this->assertSame(2, $seo['seoDescription']);
        $this->assertSame(2, $seo['excerptImage']);
        $this->assertSame(['x1'], array_column($seo['missing']['seoTitle'], 'id'), 'x1 has an empty title');
        $this->assertSame(['a2'], array_column($seo['missing']['seoDescription'], 'id'));
        $this->assertSame(['a2'], array_column($seo['missing']['excerptImage'], 'id'));
        $this->assertSame('main', $seo['missing']['seoDescription'][0]['webspace']);
    }

    public function testEachListHoldsExactlyTheEntriesThatLackThatDetail(): void
    {
        $this->connection->executeStatement("DELETE FROM pa_page_dimension_contents WHERE stage = 'live'");
        $this->live('pa_page_dimension_contents', 'pageUuid', 'a1', 'default', 'Complete', '2026-11-05 10:00:00', '{"title":"T","description":"D"}', '{"image":{"id":3}}');
        $this->live('pa_page_dimension_contents', 'pageUuid', 'a2', 'default', 'No title', '2026-11-04 10:00:00', '{"description":"D"}', '{"image":{"id":3}}');
        $this->live('pa_page_dimension_contents', 'pageUuid', 'a3', 'default', 'No image', '2026-11-03 10:00:00', '{"title":"T","description":"D"}', '{}');

        $missing = $this->reader(['sulu.webspaces.main'])->seoCoverage('de')['missing'];

        $this->assertSame(['a2'], array_column($missing['seoTitle'], 'id'));
        $this->assertSame([], $missing['seoDescription']);
        $this->assertSame(['a3'], array_column($missing['excerptImage'], 'id'));
    }

    public function testSeoCoverageListsAtMostTheLimitEntriesNewestFirstAndKnowsTheArticleGroup(): void
    {
        $this->connection->executeStatement("DELETE FROM pa_page_dimension_contents WHERE stage = 'live'");
        foreach (range(1, 7) as $i) {
            $this->live('ar_article_dimension_contents', 'articleUuid', 'z' . $i, 'blog', 'Post ' . $i, '2026-11-0' . $i . ' 10:00:00', '{}', '{}');
        }

        $seo = $this->reader(['sulu.article.articles_blog'])->seoCoverage('de', 3);

        $this->assertSame(['z7', 'z6', 'z5'], array_column($seo['missing']['seoDescription'], 'id'));
        $this->assertSame(['z7', 'z6', 'z5'], array_column($seo['missing']['seoTitle'], 'id'));
        $this->assertSame('blog', $seo['missing']['seoTitle'][0]['group']);
        $this->assertSame(7, $seo['total']);
        $this->assertSame(0, $seo['seoTitle'], 'the counts are not cut by the limit: seven lack it, three are listed');
    }

    public function testTheListsOnlyContainWhatTheUserMaySeeInThisLanguage(): void
    {
        $this->connection->executeStatement("DELETE FROM pa_page_dimension_contents WHERE stage = 'live'");
        $this->live('pa_page_dimension_contents', 'pageUuid', 'a1', 'default', 'Home', '2026-11-03 10:00:00', '{}', '{}');
        $this->live('pa_page_dimension_contents', 'pageUuid', 'b1', 'default', 'Secret', '2026-11-02 10:00:00', '{}', '{}');
        $this->live('ar_article_dimension_contents', 'articleUuid', 'x1', 'blog', 'Post', '2026-11-01 10:00:00', '{}', '{}');
        $this->connection->update('pa_pages', ['webspaceKey' => 'other'], ['uuid' => 'b1']);
        $this->connection->insert('pa_page_dimension_contents', ['pageUuid' => 'a1', 'stage' => 'live', 'version' => 0, 'locale' => 'en', 'workflowPlace' => 'published', 'templateKey' => 'default', 'title' => 'Home EN', 'changed' => '2026-11-03 10:00:00', 'seoData' => '{}', 'excerptData' => '{}']);

        $missing = $this->reader(['sulu.webspaces.main'])->seoCoverage('de')['missing'];
        foreach (['seoTitle', 'seoDescription', 'excerptImage'] as $detail) {
            $this->assertSame(['a1'], array_column($missing[$detail], 'id'), $detail . ': no secret page, no article without its permission, no other language');
        }
    }

    public function testSeoCoverageIsEmptyWithoutPermission(): void
    {
        $this->connection->executeStatement("DELETE FROM pa_page_dimension_contents WHERE stage = 'live'");
        $this->live('pa_page_dimension_contents', 'pageUuid', 'a1', 'default', 'Home', '2026-11-03 10:00:00', '{}', '{}');

        $seo = $this->reader([])->seoCoverage('de');
        $this->assertSame(0, $seo['total']);
        $this->assertSame(['seoTitle' => [], 'seoDescription' => [], 'excerptImage' => []], $seo['missing']);
    }
}
