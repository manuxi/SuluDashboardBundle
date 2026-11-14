<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Content;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Infrastructure\Sulu\Admin\ArticleAdmin;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin;

/**
 * Reads figures about pages and articles for the built-in tiles. Read only: it looks at the current rows of the content
 * tables (version 0) and never writes.
 *
 * Sulu's list API cannot answer these questions in one request (counts per workflow place over several webspaces and
 * article types, mixed lists of pages and articles), so the queries are our own. They respect the same permissions as
 * Sulu: pages by the security context of their webspace (sulu.webspaces.<key>), articles by the context of their type
 * group (like ArticleAdmin).
 */
class ContentReader
{
    public const PAGES = 'pages';

    public const ARTICLES = 'articles';

    /** workflow places that count as "not yet live as edited" */
    private const OPEN_PLACES = ['unpublished', 'draft', 'review', 'review_draft'];

    /** a translation counts as outdated when the original was changed more than a day after it */
    private const OUTDATED_AFTER = 86400;

    private const MAX_MISSING_LISTED = 50;

    /** @var array<string, array{id: string, title: string, context: string}>|null */
    private ?array $articleGroups = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly LocalizationManagerInterface $localizationManager,
        private readonly GroupProviderInterface $groupProvider,
    ) {
    }

    /**
     * Number of pages or articles per workflow place in one locale. "review_draft" (live, changes in review) counts as
     * "review".
     *
     * @return array{total: int, published: int, draft: int, unpublished: int, review: int}
     */
    public function placeCounts(string $resource, string $locale): array
    {
        $counts = ['published' => 0, 'draft' => 0, 'unpublished' => 0, 'review' => 0];

        if (self::PAGES === $resource) {
            $scopes = $this->allowedWebspaces($locale);
            $sql = <<<'SQL'
                SELECT d.workflowPlace AS place, COUNT(*) AS n
                FROM pa_page_dimension_contents d
                JOIN pa_pages p ON p.uuid = d.pageUuid
                WHERE d.stage = 'draft' AND d.version = 0 AND d.locale = :locale AND p.webspaceKey IN (:scopes)
                GROUP BY d.workflowPlace
                SQL;
        } else {
            $scopes = array_keys($this->allowedArticleTemplates($locale));
            $sql = <<<'SQL'
                SELECT d.workflowPlace AS place, COUNT(*) AS n
                FROM ar_article_dimension_contents d
                WHERE d.stage = 'draft' AND d.version = 0 AND d.locale = :locale AND d.templateKey IN (:scopes)
                GROUP BY d.workflowPlace
                SQL;
        }

        if ([] !== $scopes) {
            $rows = $this->connection->fetchAllKeyValue($sql, ['locale' => $locale, 'scopes' => $scopes], ['scopes' => ArrayParameterType::STRING]);
            foreach ($rows as $place => $count) {
                $key = 'review_draft' === $place ? 'review' : (string) $place;
                $counts[$key] = ($counts[$key] ?? 0) + (int) $count;
            }
        }

        return ['total' => array_sum($counts)] + $counts;
    }

    public function countSnippets(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sn_snippets');
    }

    public function countMedia(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM me_media');
    }

    /**
     * Pages and articles whose current state is not live: never published, unpublished changes, or in review. Newest
     * change first.
     *
     * @return list<array{resourceKey: string, id: string, webspace: string|null, template: string, group: string|null, groupTitle: string|null, title: string, workflowPlace: string, changed: string, changerId: int|null, changer: string}>
     */
    public function openItems(string $locale, int $limit = 50): array
    {
        $limit = max(1, $limit);
        $webspaces = $this->allowedWebspaces($locale);
        $groups = $this->allowedArticleTemplates($locale);

        $rows = [];

        if ([] !== $webspaces) {
            $rows = [...$rows, ...$this->connection->fetchAllAssociative(
                <<<SQL
                    SELECT 'pages' AS resourceKey, d.pageUuid AS id, p.webspaceKey AS webspace, d.templateKey AS template, d.title,
                           d.workflowPlace, d.changed, d.idUsersChanger AS changerId, c.firstName, c.lastName
                    FROM pa_page_dimension_contents d
                    JOIN pa_pages p ON p.uuid = d.pageUuid
                    LEFT JOIN se_users u ON u.id = d.idUsersChanger
                    LEFT JOIN co_contacts c ON c.id = u.idContacts
                    WHERE d.stage = 'draft' AND d.version = 0 AND d.locale = :locale
                      AND d.workflowPlace IN (:places) AND p.webspaceKey IN (:scopes)
                    ORDER BY d.changed DESC
                    LIMIT {$limit}
                    SQL,
                ['locale' => $locale, 'places' => self::OPEN_PLACES, 'scopes' => $webspaces],
                ['places' => ArrayParameterType::STRING, 'scopes' => ArrayParameterType::STRING],
            )];
        }

        if ([] !== $groups) {
            $rows = [...$rows, ...$this->connection->fetchAllAssociative(
                <<<SQL
                    SELECT 'articles' AS resourceKey, d.articleUuid AS id, NULL AS webspace, d.templateKey AS template, d.title,
                           d.workflowPlace, d.changed, d.idUsersChanger AS changerId, c.firstName, c.lastName
                    FROM ar_article_dimension_contents d
                    LEFT JOIN se_users u ON u.id = d.idUsersChanger
                    LEFT JOIN co_contacts c ON c.id = u.idContacts
                    WHERE d.stage = 'draft' AND d.version = 0 AND d.locale = :locale
                      AND d.workflowPlace IN (:places) AND d.templateKey IN (:scopes)
                    ORDER BY d.changed DESC
                    LIMIT {$limit}
                    SQL,
                ['locale' => $locale, 'places' => self::OPEN_PLACES, 'scopes' => array_keys($groups)],
                ['places' => ArrayParameterType::STRING, 'scopes' => ArrayParameterType::STRING],
            )];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['changed'], (string) $a['changed']));

        $items = [];
        foreach (\array_slice($rows, 0, $limit) as $row) {
            $group = 'articles' === $row['resourceKey'] ? ($groups[$row['template']] ?? null) : null;

            $items[] = [
                'resourceKey' => (string) $row['resourceKey'],
                'id' => (string) $row['id'],
                'webspace' => null !== $row['webspace'] ? (string) $row['webspace'] : null,
                'template' => (string) $row['template'],
                'group' => $group['id'] ?? null,
                'groupTitle' => $group['title'] ?? null,
                'title' => (string) $row['title'],
                'workflowPlace' => (string) $row['workflowPlace'],
                'changed' => (string) $row['changed'],
                'changerId' => null !== $row['changerId'] ? (int) $row['changerId'] : null,
                'changer' => trim(\sprintf('%s %s', $row['firstName'] ?? '', $row['lastName'] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * Translation state per language over pages and articles. The original language is the default localization of the
     * webspace of a page; articles belong to no webspace and use the default localization of the first webspace.
     *
     * For each language: how many entries there are (a page counts for the languages of its webspace), how many are
     * missing in it, how many are outdated (the original was changed more than a day after this version) and the first
     * few of the missing and outdated ones.
     *
     * @return array{source: string, locales: array<string, array{total: int, missing: int, outdated: int, items: list<array<string, mixed>>}>}
     */
    public function translations(): array
    {
        $webspaces = [];
        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            $localizations = array_map(static fn ($localization): string => $localization->getLocale(), $webspace->getAllLocalizations());
            $default = $webspace->getDefaultLocalization();
            $webspaces[$webspace->getKey()] = [
                'source' => null !== $default ? $default->getLocale() : ($localizations[0] ?? 'en'),
                'locales' => $localizations,
            ];
        }

        $adminLocales = array_values($this->localizationManager->getLocales());
        $firstWebspace = reset($webspaces);
        $primary = false !== $firstWebspace ? $firstWebspace['source'] : ($adminLocales[0] ?? 'en');

        $result = [];
        $scopes = [
            ['pa_page_dimension_contents', 'pageUuid', self::PAGES],
            ['ar_article_dimension_contents', 'articleUuid', self::ARTICLES],
        ];

        foreach ($scopes as [$table, $uuidColumn, $resource]) {
            $select = self::PAGES === $resource
                ? "SELECT d.{$uuidColumn} AS id, d.locale, d.changed, d.title, d.templateKey, p.webspaceKey AS webspace FROM {$table} d JOIN pa_pages p ON p.uuid = d.pageUuid"
                : "SELECT d.{$uuidColumn} AS id, d.locale, d.changed, d.title, d.templateKey, NULL AS webspace FROM {$table} d";

            $byItem = [];
            foreach ($this->connection->fetchAllAssociative($select . " WHERE d.stage = 'draft' AND d.version = 0 AND d.locale IS NOT NULL") as $row) {
                $byItem[$row['id']]['webspace'] = $row['webspace'];
                $byItem[$row['id']]['template'] = $row['templateKey'];
                $byItem[$row['id']]['versions'][$row['locale']] = ['changed' => (int) strtotime((string) $row['changed']), 'title' => (string) $row['title']];
            }

            foreach ($byItem as $id => $item) {
                $webspace = null !== $item['webspace'] ? (string) $item['webspace'] : null;
                $source = null !== $webspace ? ($webspaces[$webspace]['source'] ?? $primary) : $primary;
                $locales = null !== $webspace ? ($webspaces[$webspace]['locales'] ?? $adminLocales) : $adminLocales;
                if (null !== $webspace && !$this->securityChecker->hasPermission(PageAdmin::SECURITY_CONTEXT_PREFIX . $webspace, PermissionTypes::VIEW)) {
                    continue;
                }
                $group = null;
                if (self::ARTICLES === $resource) {
                    $group = $this->articleGroupOf((string) $item['template']);
                    if (null === $group || !$this->securityChecker->hasPermission($group['context'], PermissionTypes::VIEW)) {
                        continue;
                    }
                }

                $versions = $item['versions'];
                $original = $versions[$source] ?? null;
                $title = $original['title'] ?? reset($versions)['title'];

                foreach ($locales as $locale) {
                    $result[$locale] ??= ['total' => 0, 'missing' => 0, 'outdated' => 0, 'items' => []];
                    ++$result[$locale]['total'];

                    $state = null;
                    if (!isset($versions[$locale])) {
                        $state = 'missing';
                    } elseif ($locale !== $source && null !== $original && $original['changed'] - $versions[$locale]['changed'] > self::OUTDATED_AFTER) {
                        $state = 'outdated';
                    }

                    if (null === $state) {
                        continue;
                    }

                    ++$result[$locale][$state];
                    if (\count($result[$locale]['items']) < self::MAX_MISSING_LISTED) {
                        $result[$locale]['items'][] = [
                            'state' => $state,
                            'resourceKey' => $resource,
                            'id' => (string) $id,
                            'webspace' => $webspace,
                            'group' => $group['id'] ?? null,
                            'title' => $title,
                        ];
                    }
                }
            }
        }

        ksort($result);

        return ['source' => $primary, 'locales' => $result];
    }

    /**
     * Article types (template key) the current user may see in this locale, with their group.
     *
     * @return array<string, array{id: string, title: string, context: string}>
     */
    private function allowedArticleTemplates(string $locale): array
    {
        $allowed = [];
        foreach ($this->articleGroupsByTemplate() as $template => $group) {
            if ($this->securityChecker->hasPermission(new SecurityCondition($group['context'], $locale), PermissionTypes::VIEW)) {
                $allowed[$template] = $group;
            }
        }

        return $allowed;
    }

    /**
     * @return array{id: string, title: string, context: string}|null
     */
    private function articleGroupOf(string $template): ?array
    {
        return $this->articleGroupsByTemplate()[$template] ?? null;
    }

    /**
     * Template key => group. The security context is resolved like in ArticleAdmin: the general context for the default
     * group or when there is only one group, otherwise one context per group.
     *
     * @return array<string, array{id: string, title: string, context: string}>
     */
    private function articleGroupsByTemplate(): array
    {
        if (null !== $this->articleGroups) {
            return $this->articleGroups;
        }

        $groups = $this->groupProvider->getGroups(ArticleInterface::TEMPLATE_TYPE);
        $map = [];
        foreach ($groups as $group) {
            $context = 1 === \count($groups) || GroupProviderInterface::DEFAULT_GROUP === $group->identifier
                ? ArticleAdmin::SECURITY_CONTEXT
                : ArticleAdmin::getArticleSecurityContext($group->identifier);

            foreach ($group->templates as $template) {
                $map[$template] = ['id' => $group->identifier, 'title' => $group->title, 'context' => $context];
            }
        }

        return $this->articleGroups = $map;
    }

    /**
     * Keys of the webspaces whose pages the current user may see in this locale.
     *
     * @return list<string>
     */
    private function allowedWebspaces(string $locale): array
    {
        $allowed = [];
        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            $context = PageAdmin::SECURITY_CONTEXT_PREFIX . $webspace->getKey();
            if ($this->securityChecker->hasPermission(new SecurityCondition($context, $locale), PermissionTypes::VIEW)) {
                $allowed[] = $webspace->getKey();
            }
        }

        return $allowed;
    }
}
