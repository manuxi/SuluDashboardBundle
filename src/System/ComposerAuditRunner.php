<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs "composer audit --locked" in the project folder. Only used by the console command: starting a process has no
 * place in a web request. The command is passed as a list (no shell), the output is read as JSON and reduced to
 * package, title and CVE.
 */
final class ComposerAuditRunner implements AuditRunnerInterface
{
    private const MAX_ADVISORIES = 50;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $composer = 'composer',
        private readonly int $timeout = 120,
    ) {
    }

    public function run(): AuditResult
    {
        $command = $this->command();
        if (null === $command) {
            return AuditResult::unavailable(AuditResult::ERROR_NOT_FOUND);
        }

        $process = new Process([...$command, 'audit', '--locked', '--format=json', '--no-interaction'], $this->projectDir, ['COMPOSER_NO_INTERACTION' => '1'], null, $this->timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return AuditResult::unavailable(AuditResult::ERROR_TIMEOUT);
        } catch (Throwable) {
            return AuditResult::unavailable(AuditResult::ERROR_FAILED);
        }

        // exit code 1 means "vulnerabilities found" and still comes with the JSON
        $data = json_decode($process->getOutput(), true);
        if (!\is_array($data)) {
            return AuditResult::unavailable(AuditResult::ERROR_FAILED);
        }

        return AuditResult::vulnerable($this->advisories($data));
    }

    /**
     * @return list<string>|null
     */
    private function command(): ?array
    {
        if (str_ends_with($this->composer, '.phar')) {
            $php = (new PhpExecutableFinder())->find(false);

            return false !== $php && is_file($this->composer) ? [$php, $this->composer] : null;
        }

        $binary = is_file($this->composer) ? $this->composer : (new ExecutableFinder())->find($this->composer);

        return null === $binary ? null : [$binary];
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array{package: string, title: string, cve: ?string}>
     */
    private function advisories(array $data): array
    {
        $result = [];
        $packages = \is_array($data['advisories'] ?? null) ? $data['advisories'] : [];

        foreach ($packages as $name => $list) {
            foreach (\is_array($list) ? $list : [] as $advisory) {
                if (!\is_array($advisory)) {
                    continue;
                }

                $package = \is_string($advisory['packageName'] ?? null) ? $advisory['packageName'] : (string) $name;
                $title = \is_string($advisory['title'] ?? null) ? $advisory['title'] : '';
                $cve = \is_string($advisory['cve'] ?? null) && '' !== $advisory['cve'] ? $advisory['cve'] : null;

                $result[] = [
                    'package' => $this->clean($package, 100),
                    'title' => $this->clean($title, 120),
                    'cve' => null === $cve ? null : $this->clean($cve, 30),
                ];

                if (\count($result) >= self::MAX_ADVISORIES) {
                    return $result;
                }
            }
        }

        return $result;
    }

    private function clean(string $text, int $length): string
    {
        return mb_strimwidth(trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text)), 0, $length, '...');
    }
}
