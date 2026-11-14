<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

use LogicException;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * All tiles of the dashboard. The services are collected by the compiler pass DashboardWidgetPass; this class checks
 * them (key, type, column, duplicates) and answers which of them the current user may see.
 */
final class WidgetRegistry
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /** @var array<string, DashboardWidgetInterface>|null */
    private ?array $widgets = null;

    /**
     * @param iterable<DashboardWidgetInterface> $sources
     * @param list<string>                       $disabled keys of tiles that are switched off in the configuration
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly array $disabled = [],
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
     * The tiles the current user may see, sorted by priority (highest first).
     *
     * @return list<DashboardWidgetInterface>
     */
    public function available(): array
    {
        $available = [];
        foreach ($this->all() as $widget) {
            if ($this->isVisible($widget)) {
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

        if ('' === trim($widget->getTitle())) {
            throw new LogicException(\sprintf('The dashboard widget "%s" (%s) has an empty title.', $key, $class));
        }
    }
}
