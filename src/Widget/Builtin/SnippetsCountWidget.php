<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Content\ContentReader;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;

final class SnippetsCountWidget extends AbstractCountWidget
{
    public function __construct(private readonly ContentReader $reader)
    {
    }

    public function getKey(): string
    {
        return 'sulu_snippets';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.snippets';
    }

    public function getSecurityContext(): string
    {
        return SnippetAdmin::SECURITY_CONTEXT;
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function getData(string $locale): array
    {
        return ['value' => $this->reader->countSnippets()];
    }
}
