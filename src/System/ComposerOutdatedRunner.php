<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs "composer outdated --locked --direct --patch-only" in the project folder and keeps the entries of the packages
 * the tab names. Only used by the console command (it asks the internet, about ten seconds). A patch release stays
 * inside the version the project has chosen, so updating it is the safe kind of update. A failure is no error of the
 * check: the tab then simply says nothing about updates.
 */
final class ComposerOutdatedRunner implements UpdateRunnerInterface
{
    /** @var list<string> */
    public const PACKAGES = ['sulu/sulu', 'symfony/framework-bundle'];

    public function __construct(
        private readonly string $projectDir,
        private readonly string $composer = 'composer',
        private readonly int $timeout = 90,
    ) {
    }

    public function run(): ?array
    {
        $command = ComposerCommand::resolve($this->composer);
        if (null === $command) {
            return null;
        }

        $arguments = ['outdated', '--locked', '--direct', '--patch-only', '--format=json', '--no-interaction'];
        $process = new Process([...$command, ...$arguments], $this->projectDir, ['COMPOSER_NO_INTERACTION' => '1'], null, $this->timeout);

        try {
            $process->run();
        } catch (Throwable) {
            return null;
        }

        $data = json_decode($process->getOutput(), true);
        if (!\is_array($data) || !\is_array($data['locked'] ?? null)) {
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
