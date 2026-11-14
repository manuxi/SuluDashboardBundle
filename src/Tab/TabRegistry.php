<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tab;

use LogicException;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * The tab providers of the dashboard, collected by the compiler pass DashboardTabPass, checked and filtered by the
 * permissions of the current user.
 */
final class TabRegistry
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /** keys that would clash with the tab "Overview" or the tab view itself */
    private const RESERVED_KEYS = ['overview', 'dashboard', 'tabs'];

    /** @var array<string, DashboardTabProviderInterface>|null */
    private ?array $tabs = null;

    /**
     * @param iterable<DashboardTabProviderInterface> $sources
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    /**
     * @return array<string, DashboardTabProviderInterface>
     */
    public function all(): array
    {
        if (null !== $this->tabs) {
            return $this->tabs;
        }

        $tabs = [];
        foreach ($this->sources as $tab) {
            $key = $tab->getKey();
            if (1 !== preg_match(self::KEY_PATTERN, $key) || \in_array($key, self::RESERVED_KEYS, true)) {
                throw new LogicException(\sprintf('The dashboard tab key "%s" of %s is invalid: use lower case letters, digits and underscores, starting with a letter; "%s" are reserved.', $key, $tab::class, implode('", "', self::RESERVED_KEYS)));
            }

            if (isset($tabs[$key])) {
                throw new LogicException(\sprintf('The dashboard tab key "%s" is used twice (%s and %s).', $key, $tabs[$key]::class, $tab::class));
            }

            if ('' === trim($tab->getViewType()) || '' === trim($tab->getTitle())) {
                throw new LogicException(\sprintf('The dashboard tab "%s" (%s) needs a title and a view type.', $key, $tab::class));
            }

            $tabs[$key] = $tab;
        }

        return $this->tabs = $tabs;
    }

    /**
     * The tabs the current user may see, highest priority first.
     *
     * @return list<DashboardTabProviderInterface>
     */
    public function available(): array
    {
        $available = [];
        foreach ($this->all() as $tab) {
            $context = $tab->getSecurityContext();
            if (null === $context || $this->securityChecker->hasPermission($context, PermissionTypes::VIEW)) {
                $available[] = $tab;
            }
        }

        usort($available, static fn (DashboardTabProviderInterface $a, DashboardTabProviderInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $available;
    }
}
