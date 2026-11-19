<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Closure;

/**
 * The expensive checks of the "System" tab: known vulnerabilities, newer patch releases, the size of the folders and the media. Run by the
 * console command (cron), the result goes to the cache pool the tile reads. The result of the run before is kept inside
 * the new one, so the tile can say what grew or shrank.
 */
final class SystemChecker
{
    public function __construct(
        private readonly AuditRunnerInterface $audit,
        private readonly StorageMeasurer $storage,
        private readonly SystemCheckStore $store,
        private readonly ?Closure $now = null,
        private readonly ?MediaStatistics $media = null,
        private readonly ?UpdateRunnerInterface $updates = null,
    ) {
    }

    public function run(): SystemCheck
    {
        $before = $this->store->load();
        $previous = null === $before || [] === $before->storage
            ? null
            : ['checkedAt' => $before->checkedAt, 'storage' => $before->storage, 'media' => $before->media];

        $check = new SystemCheck(
            null === $this->now ? time() : (int) ($this->now)(),
            $this->audit->run(),
            $this->storage->measure(),
            $this->media?->measure(),
            $previous,
            $this->updates?->run(),
        );
        $this->store->save($check);

        return $check;
    }
}
