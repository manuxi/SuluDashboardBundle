<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Command;

use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\ByteFormatter;
use Manuxi\SuluDashboardBundle\System\SystemChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * The slow checks of the "System" tile, meant for a cron job: "composer audit", the size of public/uploads and of the folders of var, and the media of Sulu.
 * The result is left in the cache for the tile, so nothing slow happens when someone opens the dashboard.
 */
#[AsCommand(name: 'sulu:dashboard:system-check', description: 'Runs the slow checks of the dashboard tile "System" (composer audit, newer patch releases, folder sizes, media) and saves the result for the tile')]
class SystemCheckCommand extends Command
{
    public function __construct(private readonly SystemChecker $checker)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $check = $this->checker->run();
        } catch (Throwable $exception) {
            $io->error('The system check could not be saved: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $audit = $check->audit;
        if (AuditResult::OK === $audit->status) {
            $io->writeln('Security check: no known vulnerabilities.');
        } elseif (AuditResult::VULNERABLE === $audit->status) {
            $io->writeln(\sprintf('Security check: %d known vulnerabilities.', \count($audit->advisories)));
            foreach ($audit->advisories as $advisory) {
                $io->writeln(\sprintf('  - %s: %s%s', $advisory['package'], $advisory['title'], null === $advisory['cve'] ? '' : ' (' . $advisory['cve'] . ')'));
            }
        }

        foreach ($audit->abandoned as $package) {
            $io->writeln(\sprintf('Abandoned: %s%s', $package['package'], null === $package['replacement'] ? '' : ' (use ' . $package['replacement'] . ' instead)'));
        }

        if (null === $check->updates) {
            $io->writeln('Updates: could not be checked' . (null === $check->updatesError ? '.' : ': ' . $check->updatesError));
        } elseif ([] === $check->updates) {
            $io->writeln('Updates: no newer patch release of Sulu or Symfony.');
        }

        foreach ($check->updates ?? [] as $update) {
            $io->writeln(\sprintf('Update: %s %s -> %s (patch release)', $update['package'], $update['installed'], $update['latest']));
        }

        foreach ($check->storage as $folder) {
            $io->writeln(\sprintf('Storage %s: %s', $folder['path'], ByteFormatter::format($folder['bytes'], 'en')));
        }

        if (null !== $check->media) {
            $io->writeln(\sprintf('Media: %d files, %s', $check->media['count'], ByteFormatter::format($check->media['bytes'], 'en')));
        }

        if (null !== $check->previous) {
            $delta = array_sum(array_column($check->storage, 'bytes')) - array_sum(array_column($check->previous['storage'], 'bytes'));
            $io->writeln(\sprintf('Storage since the check %d minutes ago: %s%s', intdiv(max(0, $check->checkedAt - $check->previous['checkedAt']), 60), $delta < 0 ? '-' : '+', ByteFormatter::format(abs($delta), 'en')));
        }

        if (AuditResult::UNAVAILABLE === $audit->status) {
            $io->error(match ($audit->error) {
                AuditResult::ERROR_NOT_FOUND => 'The security check could not run: Composer was not found. Set sulu_dashboard.system.composer_binary to its path.',
                AuditResult::ERROR_TIMEOUT => 'The security check could not run: composer audit took too long.',
                AuditResult::ERROR_NETWORK => 'The security check could not run: Composer cannot fetch the security database. Check the network access of the web PHP or run the command over SSH.',
                default => 'The security check could not run: composer audit failed. Run "composer audit --locked" in the project folder to see why (it needs a composer.lock and internet access).',
            });
            if (null !== $audit->detail) {
                $io->writeln('Composer said: ' . $audit->detail);
            }

            return Command::FAILURE;
        }

        $io->success('The result is saved for the dashboard tile "System".');

        return Command::SUCCESS;
    }
}
