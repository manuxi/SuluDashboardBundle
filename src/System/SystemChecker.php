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

        $checkedAt = null === $this->now ? time() : (int) ($this->now)();
        $audit = $this->audit->run();
        $storage = $this->storage->measure();
        $media = $this->media?->measure();
        $updates = $this->updates?->run();
        $updatesError = null === $updates && $this->updates instanceof ReportsFailure ? $this->updates->failure() : null;

        $updatesErrorCode = null === $updates && $this->updates instanceof ReportsFailure ? $this->updates->failureCode() : null;

        $check = new SystemCheck($checkedAt, $audit, $storage, $media, $previous, $updates, $updatesError, $updatesErrorCode);
        $this->store->save($check);

        return $check;
    }
}
