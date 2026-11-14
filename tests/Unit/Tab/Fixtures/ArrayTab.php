<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Tab\Fixtures;

use Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface;

/**
 * A configurable tab for tests.
 */
final class ArrayTab implements DashboardTabProviderInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $key,
        private readonly string $title = 'test.tab',
        private readonly string $viewType = 'test.view',
        private readonly array $options = [],
        private readonly ?string $securityContext = null,
        private readonly int $priority = 0,
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

    public function getViewType(): string
    {
        return $this->viewType;
    }

    public function getViewOptions(): array
    {
        return $this->options;
    }

    public function getSecurityContext(): ?string
    {
        return $this->securityContext;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
