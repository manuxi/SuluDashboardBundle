<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Functional;

use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use Sulu\Bundle\TestBundle\Testing\KernelTestCase;
use Sulu\Component\HttpKernel\SuluKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The command of the system check must exist in the admin and in the website context (a project without a crontab starts
 * it from the website), and both contexts must read and write the same place, which cache:clear does not delete.
 */
class SystemCheckContextTest extends KernelTestCase
{
    private const COMMAND = 'sulu:dashboard:system-check';

    protected function tearDown(): void
    {
        (new Filesystem())->remove(self::projectStorageDir());
        parent::tearDown();
    }

    private static function projectStorageDir(): string
    {
        return \dirname(__DIR__) . '/App/var/sulu-dashboard';
    }

    /**
     * @return array{0: Application, 1: SystemCheckStore, 2: string}
     */
    private function boot(string $context): array
    {
        self::ensureKernelShutdown();
        $kernel = self::bootKernel(['sulu.context' => $context]);

        /** @var SystemCheckStore $store */
        $store = self::getContainer()->get(SystemCheckStore::class);

        return [new Application($kernel), $store, $kernel->getCacheDir()];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contexts(): iterable
    {
        yield 'admin' => [SuluKernel::CONTEXT_ADMIN];
        yield 'website' => [SuluKernel::CONTEXT_WEBSITE];
    }

    /**
     * @dataProvider contexts
     */
    public function testTheCommandExistsInEveryContext(string $context): void
    {
        [$application] = $this->boot($context);

        $this->assertTrue($application->has(self::COMMAND), 'the command is missing in the context ' . $context);
    }

    public function testBothContextsShareTheResultAndCacheClearDoesNotTouchIt(): void
    {
        $check = new SystemCheck(1795147200, AuditResult::ok(), [['path' => 'var/cache', 'bytes' => 1024]]);

        [, $websiteStore, $websiteCache] = $this->boot(SuluKernel::CONTEXT_WEBSITE);
        $this->assertNull($websiteStore->load());
        $websiteStore->save($check);

        [, $adminStore, $adminCache] = $this->boot(SuluKernel::CONTEXT_ADMIN);
        $loaded = $adminStore->load();
        $this->assertNotNull($loaded, 'the admin reads what the website context saved');
        $this->assertSame($check->toArray(), $loaded->toArray());

        $this->assertNotSame($websiteCache, $adminCache, 'Sulu gives every context its own cache folder');
        foreach ([$websiteCache, $adminCache] as $cacheDir) {
            $this->assertStringNotContainsString(self::projectStorageDir(), $cacheDir);
        }

        (new Filesystem())->remove([$websiteCache, $adminCache]);
        self::ensureKernelShutdown();
        [, $afterClear] = $this->boot(SuluKernel::CONTEXT_ADMIN);
        $this->assertNotNull($afterClear->load(), 'the result survives cache:clear');
        $this->assertDirectoryExists(self::projectStorageDir());
    }
}
