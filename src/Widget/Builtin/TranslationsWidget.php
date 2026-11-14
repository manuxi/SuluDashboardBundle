<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Content\ContentReader;
use Manuxi\SuluDashboardBundle\Widget\ConditionalDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;

/**
 * "Translations": per language how many pages and articles are missing or older than the original. Only shown when the
 * site has more than one language. Rendered by the React component "sulu_translations".
 */
final class TranslationsWidget extends AbstractBuiltinWidget implements ConditionalDashboardWidgetInterface
{
    public function __construct(
        private readonly ContentReader $reader,
        private readonly LocalizationManagerInterface $localizationManager,
    ) {
    }

    public function getKey(): string
    {
        return 'sulu_translations';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.translations';
    }

    public function getType(): string
    {
        return WidgetTypes::CUSTOM;
    }

    public function getColumn(): string
    {
        return WidgetColumns::SIDE;
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function isAvailable(): bool
    {
        return \count($this->localizationManager->getLocales()) > 1;
    }

    public function getData(string $locale): array
    {
        return $this->reader->translations();
    }
}
