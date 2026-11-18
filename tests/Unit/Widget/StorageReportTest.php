<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget;

use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\Widget\System\StorageReport;
use PHPUnit\Framework\TestCase;

/**
 * The data of the tile "Storage": shares, changes since the measurement before, media and hints.
 */
class StorageReportTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private const MB = 1048576;

    /**
     * @param list<array{path: string, bytes: int}>                                                                             $storage
     * @param array{checkedAt: int, storage: list<array{path: string, bytes: int}>, media: ?array{count: int, bytes: int}}|null $previous
     * @param array{count: int, bytes: int}|null                                                                                $media
     */
    private function check(array $storage, ?array $previous = null, ?array $media = null, int $age = 3600): SystemCheck
    {
        return new SystemCheck(self::NOW - $age, AuditResult::ok(), $storage, $media, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(SystemCheck $check, string $locale = 'en', ?StorageReport $report = null, bool $stale = false): array
    {
        return ($report ?? new StorageReport())->build($check, $locale, self::NOW, $stale);
    }

    public function testEveryFolderGetsItsShareOfTheSumAndTheBiggestComesFirst(): void
    {
        $data = $this->build($this->check([
            ['path' => 'var/log', 'bytes' => 100 * self::MB],
            ['path' => 'public/uploads', 'bytes' => 300 * self::MB],
            ['path' => 'var/cache', 'bytes' => 100 * self::MB],
            ['path' => 'var/empty', 'bytes' => 0],
        ]), 'de');

        $this->assertSame('500,0 MB', $data['total']['text']);
        $this->assertCount(3, $data['items'], 'an empty folder has no bar');
        $this->assertSame('sulu_dashboard.system_storage_uploads', $data['items'][0]['label']);
        $this->assertSame('public/uploads', $data['items'][0]['path']);
        $this->assertSame('300,0 MB', $data['items'][0]['text']);
        $this->assertSame(60.0, $data['items'][0]['percent']);
        $this->assertSame(500 * self::MB, $data['items'][0]['max']);
        $this->assertSame(20.0, $data['items'][1]['percent']);
    }

    public function testAnUnknownFolderIsShownByItsRelativePathAsText(): void
    {
        $data = $this->build($this->check([['path' => 'var/map-tiles', 'bytes' => 5 * self::MB], ['path' => 'var', 'bytes' => 2 * self::MB]]));

        $this->assertSame(['text' => 'var/map-tiles'], $data['items'][0]['label']);
        $this->assertSame('var/map-tiles', $data['items'][0]['path']);
        $this->assertSame('sulu_dashboard.system_storage_other', $data['items'][1]['label']);
        $this->assertNull($data['items'][1]['path'], 'the loose files of var have no folder of their own');
        $this->assertStringNotContainsString(\DIRECTORY_SEPARATOR . 'projects', strtolower((string) json_encode($data)));
    }

    public function testManyFoldersAreSummedUpInOneBar(): void
    {
        $storage = [];
        foreach (range(1, 9) as $i) {
            $storage[] = ['path' => 'var/f' . $i, 'bytes' => $i * self::MB];
        }

        $data = $this->build($this->check($storage));

        $this->assertCount(StorageReport::MAX_ITEMS, $data['items']);
        $rest = $data['items'][StorageReport::MAX_ITEMS - 1];
        $this->assertSame(['key' => 'sulu_dashboard.system_storage_rest', 'parameters' => ['count' => 4]], $rest['label']);
        $this->assertSame((1 + 2 + 3 + 4) * self::MB, $rest['bytes']);
        $this->assertSame(45 * self::MB, array_sum(array_column($data['items'], 'bytes')));
    }

    public function testTheChangeSinceTheMeasurementBeforeIsShownPerFolderAndForTheSum(): void
    {
        $previous = ['checkedAt' => self::NOW - 90000, 'storage' => [['path' => 'public/uploads', 'bytes' => 100 * self::MB], ['path' => 'var/cache', 'bytes' => 50 * self::MB], ['path' => 'var/log', 'bytes' => 10 * self::MB]], 'media' => null];
        $data = $this->build($this->check(
            [['path' => 'public/uploads', 'bytes' => 112 * self::MB], ['path' => 'var/cache', 'bytes' => 45 * self::MB], ['path' => 'var/log', 'bytes' => 10 * self::MB]],
            $previous,
        ), 'de');

        $byPath = array_column($data['items'], null, 'path');
        $this->assertSame(['text' => '+12,0 MB', 'direction' => 'up'], $byPath['public/uploads']['change']);
        $this->assertSame(['text' => '−5,0 MB', 'direction' => 'down'], $byPath['var/cache']['change']);
        $this->assertNull($byPath['var/log']['change']);
        $this->assertSame(['text' => '+7,0 MB', 'direction' => 'up'], $data['total']['change']);
        $this->assertSame(['key' => 'sulu_dashboard.system_age_hours', 'parameters' => ['count' => 25]], $data['previousAge']);
    }

    public function testWithoutAMeasurementBeforeNothingChanged(): void
    {
        $data = $this->build($this->check([['path' => 'var/cache', 'bytes' => 5 * self::MB]]));

        $this->assertNull($data['previousAge']);
        $this->assertNull($data['total']['change']);
        $this->assertNull($data['items'][0]['change']);
    }

    public function testASmallChangeIsNotMentioned(): void
    {
        $previous = ['checkedAt' => self::NOW - 600, 'storage' => [['path' => 'var/cache', 'bytes' => 5 * self::MB]], 'media' => null];
        $data = $this->build($this->check([['path' => 'var/cache', 'bytes' => 5 * self::MB + 1000]], $previous));

        $this->assertNull($data['total']['change']);
        $this->assertSame(['key' => 'sulu_dashboard.system_age_minutes', 'parameters' => ['count' => 10]], $data['previousAge']);
    }

    public function testAFolderThatIsNewSinceTheMeasurementBeforeCountsAsChange(): void
    {
        $previous = ['checkedAt' => self::NOW - 600, 'storage' => [['path' => 'var/cache', 'bytes' => 5 * self::MB]], 'media' => null];
        $data = $this->build($this->check([['path' => 'var/cache', 'bytes' => 5 * self::MB], ['path' => 'var/pdf', 'bytes' => 3 * self::MB]], $previous));

        $this->assertSame('+3.0 MB', array_column($data['items'], null, 'path')['var/pdf']['change']['text']);
    }

    public function testTheAgeAndTheMediaAreHandedOver(): void
    {
        $data = $this->build($this->check([['path' => 'var/cache', 'bytes' => self::MB]], null, ['count' => 884, 'bytes' => 541_532_239], 7200), 'de');

        $this->assertSame(['key' => 'sulu_dashboard.system_age_hours', 'parameters' => ['count' => 2]], $data['age']);
        $this->assertSame(['count' => 884, 'text' => '516,4 MB'], $data['media']);
        $this->assertFalse($data['stale']);
        $this->assertSame('php bin/adminconsole sulu:dashboard:system-check', $data['command']);

        $this->assertNull($this->build($this->check([['path' => 'var/cache', 'bytes' => self::MB]]))['media']);
        $this->assertTrue($this->build($this->check([['path' => 'var/cache', 'bytes' => self::MB]]), 'en', null, true)['stale']);
    }

    public function testACacheOrLogOverTheLimitGetsAHintAndAWarningBar(): void
    {
        $report = new StorageReport(100, 50);
        $data = $this->build($this->check([
            ['path' => 'var/cache', 'bytes' => 150 * self::MB],
            ['path' => 'var/log', 'bytes' => 60 * self::MB],
            ['path' => 'public/uploads', 'bytes' => 900 * self::MB],
        ]), 'de', $report);

        $this->assertSame(['progress', 'warning', 'warning'], array_column($data['items'], 'skin'));
        $this->assertCount(2, $data['hints']);
        $this->assertSame('warning', $data['hints'][0]['severity']);
        $this->assertSame(['key' => 'sulu_dashboard.system_storage_cache_large', 'parameters' => ['size' => '150,0 MB']], $data['hints'][0]['title']);
        $this->assertSame('sulu_dashboard.system_storage_cache_large_hint', $data['hints'][0]['text']);
        $this->assertSame('sulu_dashboard.system_storage_logs_large', $data['hints'][1]['title']['key']);
    }

    public function testNothingOverTheLimitMeansNoHintAndALimitOfZeroTurnsTheHintOff(): void
    {
        $storage = [['path' => 'var/cache', 'bytes' => 150 * self::MB], ['path' => 'var/log', 'bytes' => 60 * self::MB]];

        $this->assertSame([], $this->build($this->check($storage))['hints']);
        $this->assertSame([], $this->build($this->check($storage), 'en', new StorageReport(0, 0))['hints']);
        $this->assertCount(1, $this->build($this->check($storage), 'en', new StorageReport(100, 0))['hints']);
    }
}
