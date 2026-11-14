<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

/**
 * Wraps a service that is tagged "sulu_dashboard.widget" without implementing {@see DashboardWidgetInterface}: only the
 * method getData() is required, everything else comes from the tag attributes (or from a method of the same name).
 * This way a bundle can add a tile without any PHP dependency on this bundle.
 */
final class TaggedWidget implements DashboardWidgetInterface, ConditionalDashboardWidgetInterface, PinnedDashboardWidgetInterface
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private readonly object $service,
        private readonly array $attributes,
    ) {
    }

    public function getKey(): string
    {
        return (string) $this->value('key', 'getKey');
    }

    public function getTitle(): string
    {
        return (string) $this->value('title', 'getTitle');
    }

    public function getType(): string
    {
        return (string) $this->value('type', 'getType');
    }

    public function getSecurityContext(): ?string
    {
        $value = $this->value('security_context', 'getSecurityContext');

        return null === $value || '' === $value ? null : (string) $value;
    }

    public function getColumn(): string
    {
        return (string) ($this->value('column', 'getColumn') ?? WidgetColumns::MAIN);
    }

    public function getPriority(): int
    {
        return (int) ($this->value('priority', 'getPriority') ?? 0);
    }

    public function getData(string $locale): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->service->getData($locale);

        return $data;
    }

    public function isAvailable(): bool
    {
        return !\is_callable([$this->service, 'isAvailable']) || (bool) $this->service->isAvailable();
    }

    public function isPinned(): bool
    {
        return (bool) $this->value('pinned', 'isPinned');
    }

    public function getService(): object
    {
        return $this->service;
    }

    private function value(string $attribute, string $method): mixed
    {
        if (\array_key_exists($attribute, $this->attributes)) {
            return $this->attributes[$attribute];
        }

        return \is_callable([$this->service, $method]) ? $this->service->{$method}() : null;
    }
}
