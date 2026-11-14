<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Controller;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Controller\Admin\WidgetController;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\ArrayWidget;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WidgetControllerTest extends TestCase
{
    /**
     * @param list<ArrayWidget> $widgets
     */
    private function controller(array $widgets, ?LoggerInterface $logger = null): WidgetController
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturnCallback(static fn (mixed $subject): bool => 'sulu.allowed' === $subject);

        $localizations = $this->createMock(LocalizationManagerInterface::class);
        $localizations->method('getLocales')->willReturn(['de', 'en']);

        return new WidgetController(new WidgetRegistry($widgets, $checker), $localizations, $logger);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        return (array) json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function testReturnsTheDataOfOneTile(): void
    {
        $controller = $this->controller([new ArrayWidget('stars', type: 'bars', data: ['items' => [['label' => '5', 'value' => 3]]])]);

        $response = $controller->getAction('stars', new Request(['locale' => 'en']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['key' => 'stars', 'type' => 'bars', 'data' => ['items' => [['label' => '5', 'value' => 3]], 'locale' => 'en']],
            $this->json($response),
        );
    }

    public function testUsesTheFirstLocaleByDefault(): void
    {
        $response = $this->controller([new ArrayWidget('a')])->getAction('a', new Request());

        $this->assertSame('de', $this->json($response)['data']['locale']);
    }

    public function testRejectsAnUnknownLocale(): void
    {
        $response = $this->controller([new ArrayWidget('a')])->getAction('a', new Request(['locale' => 'xx']));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testAnUnknownTileAndATileWithoutPermissionBothAnswer404(): void
    {
        $controller = $this->controller([new ArrayWidget('secret', securityContext: 'sulu.denied'), new ArrayWidget('shown', securityContext: 'sulu.allowed')]);

        $this->assertSame(404, $controller->getAction('secret', new Request())->getStatusCode());
        $this->assertSame(404, $controller->getAction('missing', new Request())->getStatusCode());
        $this->assertSame(200, $controller->getAction('shown', new Request())->getStatusCode());
    }

    public function testAnErrorStaysInTheTileAndIsLoggedButNotShown(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $controller = $this->controller([new ArrayWidget('broken', failure: new RuntimeException('SQL secret: password=hunter2'))], $logger);

        $response = $controller->getAction('broken', new Request());

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['code' => 'widget_failed'], $this->json($response));
        $this->assertStringNotContainsString('hunter2', (string) $response->getContent());
    }

    public function testIsSecuredByThePermissionOfTheDashboard(): void
    {
        $controller = $this->controller([]);

        $this->assertSame(DashboardAdmin::SECURITY_CONTEXT, $controller->getSecurityContext());
        $this->assertSame('en', $controller->getLocale(new Request(['locale' => 'en'])));
        $this->assertNull($controller->getLocale(new Request()));
    }
}
