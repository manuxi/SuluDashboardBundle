<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\DependencyInjection;

use Manuxi\SuluDashboardBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    public function testTheColumnsAreBalancedByDefault(): void
    {
        $this->assertTrue($this->process([])['layout']['balance']);
    }

    public function testBalanceCanBeSwitchedOff(): void
    {
        $this->assertFalse($this->process(['layout' => ['balance' => false]])['layout']['balance']);
    }

    public function testTheSystemTileHasDefaults(): void
    {
        $this->assertSame(['composer_binary' => 'composer', 'max_age' => 48], $this->process([])['system']);
    }

    public function testTheMaximumAgeOfTheSystemCheckMustBePositive(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['system' => ['max_age' => 0]]);
    }

    public function testBalanceMustBeABoolean(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['layout' => ['balance' => 'sometimes']]);
    }
}
