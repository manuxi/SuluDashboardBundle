<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Admin;

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

    public const NAVIGATION_ITEM = 'sulu_dashboard.dashboard';

    public const SECURITY_CONTEXT = 'sulu.settings.dashboard';

    /** view of Sulu's search (SearchAdmin::SEARCH_VIEW), by name so the search package stays optional */
    private const SEARCH_VIEW = 'sulu_search.search';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly LocalizationManagerInterface $localizationManager,
        private readonly WidgetRegistry $widgetRegistry,
        private readonly MetadataProviderRegistry $metadataProviderRegistry,
        private readonly bool $startPage = true,
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
        $item->setView(self::VIEW);

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
            ];
        }

        if ($this->startPage) {
            $this->moveSearchAway($viewCollection);
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createViewBuilder(self::VIEW, $this->startPage ? '/' : '/dashboard', self::VIEW)
                ->setOption('locales', array_values($this->localizationManager->getLocales()))
                ->setOption('templateTitles', [
                    'pages' => $this->templateTitles(PageInterface::TEMPLATE_TYPE),
                    'articles' => $this->templateTitles(ArticleInterface::TEMPLATE_TYPE),
                ])
                ->setOption('widgets', $widgets),
        );
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
