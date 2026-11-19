<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

use LogicException;
use Manuxi\SuluDashboardBundle\Widget\Extensions\ExtensionsTabProvider;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * All tiles of the dashboard. The services are collected by the compiler pass DashboardWidgetPass; this class checks
 * them (key, type, column, duplicates) and answers which of them the current user may see.
 */
final class WidgetRegistry
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /** namespace of this bundle: tiles from there are the built-in ones */
    private const OWN_NAMESPACE = 'Manuxi\\SuluDashboardBundle\\';

    /** @var array<string, DashboardWidgetInterface>|null */
    private ?array $widgets = null;

    /**
     * @param iterable<DashboardWidgetInterface> $sources
     * @param list<string>                       $disabled       keys of tiles that are switched off in the configuration
     * @param bool                               $extensions     true: tiles of other bundles and of the project go to the tab "Extensions"
     * @param list<string>                       $keepOnOverview keys of tiles that stay on the overview although they come from another bundle
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly array $disabled = [],
        private readonly bool $extensions = true,
        private readonly array $keepOnOverview = [],
    ) {
    }

    /**
     * Every registered tile, checked, without looking at permissions.
     *
     * @return array<string, DashboardWidgetInterface>
     */
    public function all(): array
    {
        if (null !== $this->widgets) {
            return $this->widgets;
        }

        $widgets = [];
        foreach ($this->sources as $widget) {
            $key = $widget->getKey();
            $this->validate($widget, $key);

            if (isset($widgets[$key])) {
                throw new LogicException(\sprintf('The dashboard widget key "%s" is used twice (%s and %s).', $key, $widgets[$key]::class, $widget::class));
            }

            $widgets[$key] = $widget;
        }

        return $this->widgets = $widgets;
    }

    /**
     * The tiles the current user may see, sorted by priority (highest first). Without a group the tiles of the overview:
     * those that belong to no group. With a group the tiles of that group.
     *
     * @return list<DashboardWidgetInterface>
     */
    public function available(?string $group = null): array
    {
        $available = [];
        foreach ($this->all() as $widget) {
            if ($this->groupFor($widget) === $group && $this->isVisible($widget)) {
                $available[] = $widget;
            }
        }

        usort($available, static fn (DashboardWidgetInterface $a, DashboardWidgetInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $available;
    }

    /**
     * A tile by key, only if the current user may see it.
     */
    public function find(string $key): ?DashboardWidgetInterface
    {
        $widget = $this->all()[$key] ?? null;

        return null !== $widget && $this->isVisible($widget) ? $widget : null;
    }

    /**
     * Group a tile names itself; null for a tile without a group.
     */
    public static function groupOf(DashboardWidgetInterface $widget): ?string
    {
        return $widget instanceof GroupedDashboardWidgetInterface ? $widget->getGroup() : null;
    }

    /**
     * The group a tile is shown in; null means the overview. A tile with its own group keeps it. A tile without a group
     * goes to the tab "Extensions" when its class does not belong to this bundle (another bundle or the project), unless
     * the tab is switched off or the key is listed in "keep_on_overview". The built-in tiles stay on the overview.
     */
    public function groupFor(DashboardWidgetInterface $widget): ?string
    {
        $own = self::groupOf($widget);
        if (null !== $own || !$this->extensions || \in_array($widget->getKey(), $this->keepOnOverview, true)) {
            return $own;
        }

        return self::isForeign($widget) ? ExtensionsTabProvider::GROUP : null;
    }

    /**
     * Whether the class of the tile (for a tagged service the class of the service) is outside the namespace of this
     * bundle. The trailing backslash keeps a bundle like "Manuxi\SuluDashboardBundleExtra" from counting as ours.
     */
    public static function isForeign(DashboardWidgetInterface $widget): bool
    {
        $class = $widget instanceof TaggedWidget ? $widget->getService()::class : $widget::class;

        return !str_starts_with($class, self::OWN_NAMESPACE);
    }

    private function isVisible(DashboardWidgetInterface $widget): bool
    {
        if (\in_array($widget->getKey(), $this->disabled, true)) {
            return false;
        }

        if ($widget instanceof ConditionalDashboardWidgetInterface && !$widget->isAvailable()) {
            return false;
        }

        $context = $widget->getSecurityContext();

        return null === $context || $this->securityChecker->hasPermission($context, PermissionTypes::VIEW);
    }

    private function validate(DashboardWidgetInterface $widget, string $key): void
    {
        $class = $widget::class;

        if (1 !== preg_match(self::KEY_PATTERN, $key)) {
            throw new LogicException(\sprintf('The dashboard widget key "%s" of %s is invalid: use lower case letters, digits and underscores, starting with a letter.', $key, $class));
        }

        if (!\in_array($widget->getType(), WidgetTypes::all(), true)) {
            throw new LogicException(\sprintf('The dashboard widget "%s" (%s) has the unknown type "%s". Allowed: %s.', $key, $class, $widget->getType(), implode(', ', WidgetTypes::all())));
        }

        if (!\in_array($widget->getColumn(), WidgetColumns::all(), true)) {
            throw new LogicException(\sprintf('The dashboard widget "%s" (%s) has the unknown column "%s". Allowed: %s.', $key, $class, $widget->getColumn(), implode(', ', WidgetColumns::all())));
        }

        if ($widget instanceof GroupedDashboardWidgetInterface && 1 !== preg_match(self::KEY_PATTERN, $widget->getGroup())) {
            throw new LogicException(\sprintf('The dashboard widget "%s" (%s) has the invalid group "%s": use lower case letters, digits and underscores, starting with a letter.', $key, $class, $widget->getGroup()));
        }

        if ('' === trim($widget->getTitle())) {
            throw new LogicException(\sprintf('The dashboard widget "%s" (%s) has an empty title.', $key, $class));
        }
    }
}
