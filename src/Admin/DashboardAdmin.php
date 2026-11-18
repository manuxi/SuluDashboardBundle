<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Admin;

use Manuxi\SuluDashboardBundle\Tab\ReplacesNavigationItemInterface;
use Manuxi\SuluDashboardBundle\Tab\TabRegistry;
use Manuxi\SuluDashboardBundle\Widget\PinnedDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Exception\MetadataNotFoundException;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Page\Domain\Model\PageInterface;

/**
 * Start page of the admin. The view sits on the path "/", which Sulu leaves empty: after the login the admin opens
 * there, and the first navigation item leads back to it. React view: src/Resources/js (sulu_dashboard.dashboard).
 */
class DashboardAdmin extends Admin
{
    public const VIEW = 'sulu_dashboard.dashboard';

    /** the tab view, only registered while at least one bundle brings a tab */
    public const TABS_VIEW = 'sulu_dashboard.tabs';

    /** views of the tabs of other bundles: prefix plus the key of the tab */
    private const TAB_VIEW_PREFIX = 'sulu_dashboard.tab_';

    public const NAVIGATION_ITEM = 'sulu_dashboard.dashboard';

    public const SECURITY_CONTEXT = 'sulu.settings.dashboard';

    /** permission for the "System" tile (versions, security check, disk use); not part of the dashboard permission */
    public const SYSTEM_SECURITY_CONTEXT = 'sulu.settings.dashboard_system';

    /** view of Sulu's search (SearchAdmin::SEARCH_VIEW), by name so the search package stays optional */
    private const SEARCH_VIEW = 'sulu_search.search';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly LocalizationManagerInterface $localizationManager,
        private readonly WidgetRegistry $widgetRegistry,
        private readonly MetadataProviderRegistry $metadataProviderRegistry,
        private readonly bool $startPage = true,
        private readonly ?TabRegistry $tabRegistry = null,
        private readonly bool $replaceNavigation = true,
        private readonly bool $balance = true,
    ) {
    }

    /**
     * Sulu sorts the admin classes by this value (tagged_iterator with defaultPriorityMethod, highest first, default 0).
     * A low value makes this class run after all others, so the view of the search (SearchAdmin, also on "/") already
     * exists when the dashboard moves it away.
     */
    public static function getPriority(): int
    {
        return -1024;
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if (!$this->mayView()) {
            return;
        }

        $item = new NavigationItem(self::NAVIGATION_ITEM);
        $item->setPosition(1);
        $item->setIcon('su-th-large');
        $tabs = $this->tabs();
        $item->setView([] === $tabs ? self::VIEW : self::TABS_VIEW);

        // the menu item stays highlighted in every view of the dashboard: Sulu compares the name of the active view with
        // the view of the item and its child views (Navigation.js, isItemActive), like for the media or the forms
        $item->addChildView(self::VIEW);
        if ([] !== $tabs) {
            $item->addChildView(self::TABS_VIEW);
            foreach ($tabs as $tab) {
                $item->addChildView(self::TAB_VIEW_PREFIX . $tab->getKey());
            }
        }

        // a tab can take the place of a menu item of another bundle ("Statistics"): the item goes, its views stay (deep
        // links keep working) and count as views of the dashboard item. This class runs after all other admin classes
        // (priority), so the item already exists. Only top-level items; the collection has no way to remove a child.
        if ($this->replaceNavigation) {
            foreach ($tabs as $tab) {
                if ($tab instanceof ReplacesNavigationItemInterface && $navigationItemCollection->has($tab->getReplacedNavigationItem())) {
                    $navigationItemCollection->remove($tab->getReplacedNavigationItem());
                    foreach ($tab->getReplacedViews() as $view) {
                        $item->addChildView($view);
                    }
                }
            }
        }

        $navigationItemCollection->add($item);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->mayView()) {
            return;
        }

        $widgets = [];
        foreach ($this->widgetRegistry->available() as $widget) {
            $widgets[] = [
                'key' => $widget->getKey(),
                'title' => $widget->getTitle(),
                'type' => $widget->getType(),
                'column' => $widget->getColumn(),
                'pinned' => $widget instanceof PinnedDashboardWidgetInterface && $widget->isPinned(),
            ];
        }

        if ($this->startPage) {
            $this->moveSearchAway($viewCollection);
        }

        $base = $this->startPage ? '/' : '/dashboard';
        $tabs = $this->tabs();

        $overview = $this->viewBuilderFactory->createViewBuilder(self::VIEW, [] === $tabs ? $base : $this->childPath('overview'), self::VIEW)
            ->setOption('locales', array_values($this->localizationManager->getLocales()))
            ->setOption('templateTitles', [
                'pages' => $this->templateTitles(PageInterface::TEMPLATE_TYPE),
                'articles' => $this->templateTitles(ArticleInterface::TEMPLATE_TYPE),
            ])
            ->setOption('balance', $this->balance)
            ->setOption('widgets', $widgets);

        if ([] === $tabs) {
            // no tab, no tab bar: the plain view
            $viewCollection->add($overview);

            return;
        }

        // Sulu's tab view: children get the path of the parent in front of their own. The parent takes the base path and
        // redirects to the tab with the highest priority, which is "Overview".
        $viewCollection->add($this->viewBuilderFactory->createTabViewBuilder(self::TABS_VIEW, $base));

        $viewCollection->add(
            $overview
                ->setParent(self::TABS_VIEW)
                ->setOption('tabTitle', 'sulu_dashboard.overview')
                ->setOption('tabOrder', 0)
                ->setOption('tabPriority', 1),
        );

        foreach ($tabs as $index => $tab) {
            $view = $this->viewBuilderFactory->createViewBuilder(self::TAB_VIEW_PREFIX . $tab->getKey(), $this->childPath($tab->getKey()), $tab->getViewType())
                ->setParent(self::TABS_VIEW)
                ->setOption('tabTitle', $tab->getTitle())
                ->setOption('tabOrder', $index + 1)
                ->setOption('tabPriority', 0);

            foreach ($tab->getViewOptions() as $name => $value) {
                $view->setOption($name, $value);
            }

            $viewCollection->add($view);
        }
    }

    /**
     * Path of a child of the tab view. Sulu puts the path of the parent in front of it, so below "/" the child must not
     * start with a slash (it would become "//x"), below "/dashboard" it must.
     */
    private function childPath(string $name): string
    {
        return ($this->startPage ? '' : '/') . $name;
    }

    /**
     * @return list<\Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface>
     */
    private function tabs(): array
    {
        return null === $this->tabRegistry ? [] : $this->tabRegistry->available();
    }

    /**
     * @return mixed[]
     */
    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Settings' => [
                    self::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                    ],
                    self::SYSTEM_SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                    ],
                ],
            ],
        ];
    }

    /**
     * Titles of the templates per admin language, as Sulu shows them in the template select: template key =>
     * [locale => title]. Shown in the column "Type" of the open drafts.
     *
     * @return array<string, array<string, string>>
     */
    private function templateTitles(string $type): array
    {
        try {
            $metadata = $this->metadataProviderRegistry->getMetadataProvider('form')->getMetadata($type, '', []);
        } catch (MetadataNotFoundException) {
            // a site without templates of this type (for example no article templates yet): the admin must still load
            return [];
        }

        if (!$metadata instanceof TypedFormMetadata) {
            return [];
        }

        $titles = [];
        foreach ($metadata->getForms() as $form) {
            $titles[$form->getKey()] = $form->getTitles();
        }

        return $titles;
    }

    /**
     * Sulu's search (SearchAdmin) also sits on "/". The router of the admin takes the first matching route, so one of
     * the two views would never open. The search keeps its view name, so its menu item (which navigates by view name)
     * and every link to it keep working; only the path changes.
     */
    private function moveSearchAway(ViewCollection $viewCollection): void
    {
        if (!$viewCollection->has(self::SEARCH_VIEW)) {
            return;
        }

        $old = $viewCollection->get(self::SEARCH_VIEW)->getView();
        if ('/' !== $old->getPath()) {
            return;
        }

        $new = $this->viewBuilderFactory->createViewBuilder(self::SEARCH_VIEW, '/search', $old->getType())->getView();

        $viewCollection->remove(self::SEARCH_VIEW);
        $viewCollection->add(new RelocatedViewBuilder($old, $new));
    }

    private function mayView(): bool
    {
        return $this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW);
    }
}
