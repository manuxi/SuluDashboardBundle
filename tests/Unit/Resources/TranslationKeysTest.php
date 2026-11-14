<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Resources;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

class TranslationKeysTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../../src/Resources';

    /**
     * @return array<string, string>
     */
    private function keys(string $locale): array
    {
        $data = Yaml::parseFile(self::ROOT . '/translations/admin.' . $locale . '.yaml');

        $flat = [];
        $walk = static function (array $node, string $prefix) use (&$walk, &$flat): void {
            foreach ($node as $key => $value) {
                \is_array($value) ? $walk($value, $prefix . $key . '.') : $flat[$prefix . $key] = (string) $value;
            }
        };
        $walk($data, '');

        return $flat;
    }

    public function testAllLanguagesHaveTheSameKeysAndNoEmptyTexts(): void
    {
        $de = $this->keys('de');

        foreach (['en', 'nl'] as $locale) {
            $keys = $this->keys($locale);
            $this->assertSame([], array_diff_key($de, $keys), 'missing in ' . $locale);
            $this->assertSame([], array_diff_key($keys, $de), 'too many in ' . $locale);
        }

        foreach (['de', 'en', 'nl'] as $locale) {
            foreach ($this->keys($locale) as $key => $text) {
                $this->assertNotSame('', trim($text), $key . ' in ' . $locale);
            }
        }
    }

    public function testEveryKeyUsedInCodeIsTranslated(): void
    {
        $keys = $this->keys('de');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT . '/..', FilesystemIterator::SKIP_DOTS));

        $used = [];
        foreach ($files as $file) {
            if (!\in_array($file->getExtension(), ['php', 'js'], true) || str_contains($file->getPathname(), 'node_modules') || !preg_match('#[\\\\/](js|Builtin|Admin)[\\\\/]#', $file->getPathname())) {
                continue;
            }
            preg_match_all('/[\'"](sulu_dashboard\.[a-z_]+)[\'"]/', (string) file_get_contents($file->getPathname()), $matches);
            foreach ($matches[1] as $key) {
                if (str_starts_with($key, 'sulu_dashboard.get_') || str_ends_with($key, '_')) {
                    continue; // route name, or the start of a key put together in code (checked below)
                }
                $used[$key] = $file->getFilename();
            }
        }

        $this->assertNotEmpty($used);
        foreach ($used as $key => $file) {
            $this->assertArrayHasKey($key, $keys, $key . ' is used in ' . $file);
        }

        // keys that are put together in code
        foreach (['published', 'draft', 'unpublished', 'review'] as $place) {
            $this->assertArrayHasKey('sulu_dashboard.place_' . $place, $keys);
        }
        foreach (['pages', 'articles'] as $type) {
            $this->assertArrayHasKey('sulu_dashboard.type_' . $type, $keys);
        }
        foreach (['missing', 'outdated'] as $state) {
            $this->assertArrayHasKey('sulu_dashboard.state_' . $state, $keys);
        }
    }
}
