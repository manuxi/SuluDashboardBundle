<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Number and total size of the media of Sulu (the current version of every file), read from the media tables. Cheap, but
 * only run by the console command like the folder sizes. Reads only; without a database or without the tables the answer
 * is null and the tile leaves the line out.
 */
class MediaStatistics
{
    public function __construct(private readonly ?Connection $connection = null)
    {
    }

    /**
     * @return array{count: int, bytes: int}|null
     */
    public function measure(): ?array
    {
        if (null === $this->connection) {
            return null;
        }

        try {
            $row = $this->connection->fetchAssociative(
                'SELECT COUNT(DISTINCT m.id) AS media_count, COALESCE(SUM(fv.size), 0) AS media_bytes'
                . ' FROM me_media m'
                . ' INNER JOIN me_files f ON f.idMedia = m.id'
                . ' INNER JOIN me_file_versions fv ON fv.idFiles = f.id AND fv.version = f.version'
            );
        } catch (Throwable) {
            return null;
        }

        if (!\is_array($row) || !is_numeric($row['media_count'] ?? null) || !is_numeric($row['media_bytes'] ?? null)) {
            return null;
        }

        return ['count' => (int) $row['media_count'], 'bytes' => (int) $row['media_bytes']];
    }
}
