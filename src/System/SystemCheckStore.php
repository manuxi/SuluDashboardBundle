<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

/**
 * The place where the console command leaves its result for the tile (a cache pool). Without a result the tile says
 * that the check has not run yet.
 */
final class SystemCheckStore
{
    private const KEY = 'sulu_dashboard.system_check';

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function load(): ?SystemCheck
    {
        $item = $this->cache->getItem(self::KEY);
        $data = $item->get();

        return $item->isHit() && \is_array($data) ? SystemCheck::fromArray($data) : null;
    }

    public function save(SystemCheck $check): void
    {
        $item = $this->cache->getItem(self::KEY);
        $item->set($check->toArray());
        $item->expiresAfter(null);

        if (!$this->cache->save($item)) {
            throw new RuntimeException('The result of the system check could not be saved to the cache.');
        }
    }
}
