<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Closure;

/**
 * The expensive checks of the "System" tile: known vulnerabilities and the size of the folders. Run by the console
 * command (cron), the result goes to the cache pool the tile reads.
 */
final class SystemChecker
{
    public function __construct(
        private readonly AuditRunnerInterface $audit,
        private readonly StorageMeasurer $storage,
        private readonly SystemCheckStore $store,
        private readonly ?Closure $now = null,
    ) {
    }

    public function run(): SystemCheck
    {
        $check = new SystemCheck(null === $this->now ? time() : (int) ($this->now)(), $this->audit->run(), $this->storage->measure());
        $this->store->save($check);

        return $check;
    }
}
