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

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly LocalizationManagerInterface $localizationManager,
        private readonly WidgetRegistry $widgetRegistry,
        private readonly MetadataProviderRegistry $metadataProviderRegistry,
    ) {
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

        $viewCollection->add(
            $this->viewBuilderFactory->createViewBuilder(self::VIEW, '/', self::VIEW)
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
        $metadata = $this->metadataProviderRegistry->getMetadataProvider('form')->getMetadata($type, '', []);
        if (!$metadata instanceof TypedFormMetadata) {
            return [];
        }

        $titles = [];
        foreach ($metadata->getForms() as $form) {
            $titles[$form->getKey()] = $form->getTitles();
        }

        return $titles;
    }

    private function mayView(): bool
    {
        return $this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW);
    }
}
