<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures;

use Manuxi\SuluDashboardBundle\Widget\ConditionalDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use Throwable;

/**
 * A configurable tile for tests.
 */
final class ArrayWidget implements ConditionalDashboardWidgetInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $key,
        private readonly string $type = WidgetTypes::NUMBER,
        private readonly string $column = WidgetColumns::MAIN,
        private readonly int $priority = 0,
        private readonly ?string $securityContext = null,
        private readonly string $title = 'test.title',
        private readonly bool $available = true,
        private readonly array $data = ['value' => 1],
        private readonly ?Throwable $failure = null,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSecurityContext(): ?string
    {
        return $this->securityContext;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getData(string $locale): array
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return $this->data + ['locale' => $locale];
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }
}
