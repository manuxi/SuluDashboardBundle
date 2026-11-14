<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Content\ContentReader;
use Sulu\Bundle\MediaBundle\Admin\MediaAdmin;

final class MediaCountWidget extends AbstractCountWidget
{
    public function __construct(private readonly ContentReader $reader)
    {
    }

    public function getKey(): string
    {
        return 'sulu_media';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.media';
    }

    public function getSecurityContext(): string
    {
        return MediaAdmin::SECURITY_CONTEXT;
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function getData(string $locale): array
    {
        return ['value' => $this->reader->countMedia()];
    }
}
