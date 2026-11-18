<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\System;

use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\AuditRunnerInterface;
use Manuxi\SuluDashboardBundle\System\ByteFormatter;
use Manuxi\SuluDashboardBundle\System\ComposerAuditRunner;
use Manuxi\SuluDashboardBundle\System\FailedMessages;
use Manuxi\SuluDashboardBundle\System\StorageMeasurer;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\System\SystemChecker;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use Manuxi\SuluDashboardBundle\System\SystemVersions;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

class SystemClassesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/sulu_dashboard_system_' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/public/uploads/media', 0777, true);
        mkdir($this->directory . '/var/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ('.' !== $entry && '..' !== $entry) {
                    $this->remove($path . '/' . $entry);
                }
            }
            rmdir($path);

            return;
        }

        if (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }

    public function testByteFormatterUsesTheDecimalSeparatorOfTheLanguage(): void
    {
        $this->assertSame('0 B', ByteFormatter::format(0, 'en'));
        $this->assertSame('1023 B', ByteFormatter::format(1023, 'en'));
        $this->assertSame('1.5 KB', ByteFormatter::format(1536, 'en'));
        $this->assertSame('1,5 GB', ByteFormatter::format(1_610_612_736, 'de'));
        $this->assertSame('1,5 GB', ByteFormatter::format(1_610_612_736, 'nl_NL'));
        $this->assertSame('2048.0 TB', ByteFormatter::format(2 * 1024 ** 5, 'en'));
        $this->assertSame('0 B', ByteFormatter::format(-5, 'en'));
    }

    public function testVersionsComeFromTheRunningSystem(): void
    {
        $versions = SystemVersions::detect();

        $this->assertSame(\PHP_VERSION, $versions->php);
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', $versions->symfony);
        $this->assertNotNull($versions->sulu);
        $this->assertStringStartsNotWith('v', (string) $versions->sulu);
    }

    public function testStorageMeasurerCountsFilesAndReportsRelativePathsOnly(): void
    {
        file_put_contents($this->directory . '/public/uploads/media/a.bin', str_repeat('a', 1000));
        file_put_contents($this->directory . '/var/cache/b.bin', str_repeat('b', 500));
        file_put_contents($this->directory . '/var/c.bin', str_repeat('c', 24));

        $result = (new StorageMeasurer($this->directory, ['public/uploads', 'var', 'does/not/exist']))->measure();

        $this->assertSame([['path' => 'public/uploads', 'bytes' => 1000], ['path' => 'var', 'bytes' => 524]], $result);
        $this->assertStringNotContainsString($this->directory, (string) json_encode($result));
    }

    public function testStorageMeasurerDoesNotFollowSymbolicLinksInsideAFolder(): void
    {
        if (!@symlink($this->directory . '/public', $this->directory . '/var/link')) {
            $this->markTestSkipped('Symbolic links are not available.');
        }
        file_put_contents($this->directory . '/public/uploads/media/a.bin', str_repeat('a', 1000));

        $result = (new StorageMeasurer($this->directory, ['var']))->measure();

        $this->assertSame([['path' => 'var', 'bytes' => 0]], $result);
    }

    public function testTheStoreKeepsTheCheckAndTreatsBrokenDataAsMissing(): void
    {
        $pool = new ArrayAdapter();
        $store = new SystemCheckStore($pool);
        $this->assertNull($store->load());

        $check = new SystemCheck(1_800_000_000, AuditResult::vulnerable([['package' => 'a/b', 'title' => 'T', 'cve' => 'CVE-1']]), [['path' => 'var', 'bytes' => 5]]);
        $store->save($check);
        $this->assertSame($check->toArray(), $store->load()?->toArray());

        $store->save(new SystemCheck(1, AuditResult::unavailable(AuditResult::ERROR_TIMEOUT), []));
        $loaded = $store->load();
        $this->assertSame(AuditResult::UNAVAILABLE, $loaded?->audit->status);
        $this->assertSame(AuditResult::ERROR_TIMEOUT, $loaded->audit->error);

        $item = $pool->getItem('sulu_dashboard.system_check');
        $pool->save($item->set(['checkedAt' => 'yesterday']));
        $this->assertNull($store->load());
    }

    public function testNoAdvisoriesMeansOk(): void
    {
        $this->assertSame(AuditResult::OK, AuditResult::vulnerable([])->status);
    }

    public function testTheCheckerRunsBothChecksAndSavesTheResult(): void
    {
        file_put_contents($this->directory . '/var/c.bin', 'xx');
        $runner = new class implements AuditRunnerInterface {
            public function run(): AuditResult
            {
                return AuditResult::ok();
            }
        };
        $store = new SystemCheckStore(new ArrayAdapter());

        $check = (new SystemChecker($runner, new StorageMeasurer($this->directory), $store, static fn (): int => 1_800_000_000))->run();

        $this->assertSame(1_800_000_000, $check->checkedAt);
        $this->assertSame($check->toArray(), $store->load()?->toArray());
        $this->assertSame([['path' => 'public/uploads', 'bytes' => 0], ['path' => 'var', 'bytes' => 2]], $check->storage);
    }

    /**
     * A "composer.phar" that prints what the test needs, run with the PHP of the test.
     */
    private function fakeComposer(string $php): string
    {
        $file = $this->directory . '/composer.phar';
        file_put_contents($file, "<?php\n" . $php);

        return $file;
    }

    public function testComposerAuditIsParsedIntoPackageTitleAndCve(): void
    {
        $json = json_encode([
            'advisories' => [
                'vendor/one' => [
                    ['advisoryId' => 'PKSA-1', 'packageName' => 'vendor/one', 'title' => "Remote\ncode execution", 'cve' => 'CVE-2026-1', 'link' => 'https://example.com/1', 'affectedVersions' => '<1.2'],
                    ['advisoryId' => 'PKSA-2', 'packageName' => 'vendor/one', 'title' => 'XSS', 'cve' => null],
                ],
            ],
            'abandoned' => ['vendor/old' => null],
        ]);
        $composer = $this->fakeComposer('echo ' . var_export($json, true) . '; exit(1);');

        $result = (new ComposerAuditRunner($this->directory, $composer, 30))->run();

        $this->assertSame(AuditResult::VULNERABLE, $result->status);
        $this->assertSame(
            [['package' => 'vendor/one', 'title' => 'Remote code execution', 'cve' => 'CVE-2026-1'], ['package' => 'vendor/one', 'title' => 'XSS', 'cve' => null]],
            $result->advisories,
        );
        $this->assertStringNotContainsString('example.com', (string) json_encode($result->toArray()));
    }

    public function testComposerAuditWithoutAdvisoriesIsOk(): void
    {
        $composer = $this->fakeComposer('echo \'{"advisories":[]}\';');

        $this->assertSame(AuditResult::OK, (new ComposerAuditRunner($this->directory, $composer))->run()->status);
    }

    public function testComposerAuditThatPrintsNoJsonIsReportedAsFailed(): void
    {
        $composer = $this->fakeComposer('fwrite(STDERR, "Could not resolve host in /secret/path"); exit(100);');

        $result = (new ComposerAuditRunner($this->directory, $composer))->run();

        $this->assertSame(AuditResult::UNAVAILABLE, $result->status);
        $this->assertSame(AuditResult::ERROR_FAILED, $result->error);
        $this->assertStringNotContainsString('secret', (string) json_encode($result->toArray()));
    }

    public function testComposerAuditThatTakesTooLongIsReportedAsTimeout(): void
    {
        $composer = $this->fakeComposer('sleep(10);');

        $result = (new ComposerAuditRunner($this->directory, $composer, 1))->run();

        $this->assertSame(AuditResult::ERROR_TIMEOUT, $result->error);
    }

    public function testAMissingComposerIsReportedAsNotFound(): void
    {
        $this->assertSame(AuditResult::ERROR_NOT_FOUND, (new ComposerAuditRunner($this->directory, 'no-such-composer-binary-xyz'))->run()->error);
        $this->assertSame(AuditResult::ERROR_NOT_FOUND, (new ComposerAuditRunner($this->directory, $this->directory . '/missing.phar'))->run()->error);
    }

    public function testFailedMessagesCountWithWhatTheTransportOffers(): void
    {
        $this->assertFalse((new FailedMessages())->isConfigured());
        $this->assertNull((new FailedMessages(new stdClass()))->count());

        $counting = new class implements MessageCountAwareInterface {
            public function getMessageCount(): int
            {
                return 4;
            }
        };
        $this->assertSame(4, (new FailedMessages($counting))->count());

        $listing = new class implements ListableReceiverInterface {
            public function all(?int $limit = null): iterable
            {
                yield new Envelope(new stdClass());
                yield new Envelope(new stdClass());
            }

            public function find(mixed $id): ?Envelope
            {
                return null;
            }

            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }
        };
        $this->assertSame(2, (new FailedMessages($listing))->count());
    }
}
