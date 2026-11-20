<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs "composer outdated --locked --direct --patch-only" in the project folder and keeps the entries of the packages
 * the tab names. Only used by the console command (it asks the internet, about ten seconds). A patch release stays
 * inside the version the project has chosen, so updating it is the safe kind of update. A failure is no error of the
 * check: the tab then simply says nothing about updates.
 */
final class ComposerOutdatedRunner implements UpdateRunnerInterface, ReportsFailureCode
{
    /** @var list<string> */
    public const PACKAGES = ['sulu/sulu', 'symfony/framework-bundle'];

    public function __construct(
        private readonly string $projectDir,
        private readonly string $composer = 'composer',
        private readonly int $timeout = 90,
        private readonly ?ComposerEnvironment $environment = null,
    ) {
    }

    /** The check needs a GitHub token for a repository of the project (see the section on tokens in docs/system.en.md). */
    public const ERROR_GITHUB_AUTH = 'github_auth';

    private ?string $failure = null;

    private ?string $failureCode = null;

    public function failureCode(): ?string
    {
        return $this->failureCode;
    }

    public function failure(): ?string
    {
        return $this->failure;
    }

    public function run(): ?array
    {
        $this->failure = null;
        $this->failureCode = null;
        $command = ComposerCommand::resolve($this->composer);
        if (null === $command) {
            $this->failure = 'Composer was not found.';

            return null;
        }

        $arguments = ['outdated', '--locked', '--direct', '--patch-only', '--format=json', '--no-interaction'];
        $environment = $this->environment ?? new ComposerEnvironment($this->projectDir . '/var/sulu-dashboard/composer');
        $process = new Process([...$command, ...$arguments], $this->projectDir, $environment->variables(), null, $this->timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $this->failure = 'composer outdated took too long.';

            return null;
        } catch (Throwable $exception) {
            $this->failure = ComposerFailure::summarize($exception->getMessage(), $this->projectDir) ?? 'composer outdated could not be started.';

            return null;
        }

        $data = json_decode($process->getOutput(), true);
        if (!\is_array($data) || !\is_array($data['locked'] ?? null)) {
            $error = $process->getErrorOutput();
            $detail = ComposerFailure::reason('' !== trim($error) ? $error : $process->getOutput(), $this->projectDir)
                ?? 'exit code ' . $process->getExitCode();
            if (ComposerFailure::isGithubAuth($error)) {
                $this->failureCode = self::ERROR_GITHUB_AUTH;
                $this->failure = 'Composer needs a GitHub token for a repository of the project and the user of this PHP has none. Put an auth.json with "github-oauth" into the Composer home of this user or into the folder of sulu_dashboard.system.composer_home, or run the command over SSH. ' . $detail;
            } elseif (ComposerFailure::isNetwork($error)) {
                $this->failure = 'Composer cannot fetch the package list. Check the network access of the web PHP or run the command over SSH. ' . $detail;
            } else {
                $this->failure = $detail;
            }

            return null;
        }

        return self::parse($data['locked']);
    }

    /**
     * @param array<mixed> $locked the entries of "locked" in the output of composer outdated
     *
     * @return list<array{package: string, installed: string, latest: string}>
     */
    public static function parse(array $locked): array
    {
        $result = [];
        foreach ($locked as $entry) {
            if (!\is_array($entry) || !\is_string($entry['name'] ?? null) || !\in_array($entry['name'], self::PACKAGES, true)) {
                continue;
            }

            $installed = self::version($entry['version'] ?? null);
            $latest = self::version($entry['latest'] ?? null);
            if (null !== $installed && null !== $latest && $installed !== $latest) {
                $result[] = ['package' => $entry['name'], 'installed' => $installed, 'latest' => $latest];
            }
        }

        return $result;
    }

    private static function version(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $version = ltrim(trim($value), 'v');

        return 1 === preg_match('/^\d+(\.\d+){0,3}([.-][A-Za-z0-9.]+)?$/', $version) && \strlen($version) <= 40 ? $version : null;
    }
}
