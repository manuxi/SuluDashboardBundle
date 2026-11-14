<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Controller\Admin;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use Psr\Log\LoggerInterface;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Data of one tile. Every tile has its own request, so an error stays inside its tile. Behind the admin firewall and
 * the permission of the dashboard; the tile itself checks its own permission again.
 */
#[Route('/admin/api/dashboard')]
class WidgetController implements SecuredControllerInterface
{
    public function __construct(
        private readonly WidgetRegistry $widgetRegistry,
        private readonly LocalizationManagerInterface $localizationManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/widgets/{key}', name: 'sulu_dashboard.get_widget', requirements: ['key' => '[a-z][a-z0-9_]*'], methods: ['GET'])]
    public function getAction(string $key, Request $request): Response
    {
        $locales = array_values($this->localizationManager->getLocales());
        $locale = $request->query->get('locale', $locales[0] ?? 'en');

        if (!\is_string($locale) || !\in_array($locale, $locales, true)) {
            return new JsonResponse(['code' => 'invalid_locale'], Response::HTTP_BAD_REQUEST);
        }

        $widget = $this->widgetRegistry->find($key);
        if (null === $widget) {
            return new JsonResponse(['code' => 'widget_not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = $widget->getData($locale);
        } catch (Throwable $exception) {
            $this->logger?->error('Dashboard widget "{key}" failed: {message}', ['key' => $key, 'message' => $exception->getMessage(), 'exception' => $exception]);

            return new JsonResponse(['code' => 'widget_failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['key' => $widget->getKey(), 'type' => $widget->getType(), 'data' => $data]);
    }

    public function getSecurityContext(): string
    {
        return DashboardAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        $locale = $request->query->get('locale');

        return \is_string($locale) ? $locale : null;
    }
}
