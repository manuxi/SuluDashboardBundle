<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Command;

use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\SecurityBundle\System\SystemStoreInterface;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authentication\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

/**
 * Prints all tiles a user sees on the dashboard, with their data, as JSON: the same data the admin loads, without a
 * login. Read only.
 */
#[AsCommand(name: 'sulu:dashboard:dump', description: 'Prints the dashboard tiles of a user as JSON (read only)')]
class DashboardDumpCommand extends Command
{
    public function __construct(
        private readonly WidgetRegistry $widgetRegistry,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LocalizationManagerInterface $localizationManager,
        private readonly SystemStoreInterface $systemStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Username of the admin user whose permissions apply')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Content locale (default: first locale of the site)')
            ->addOption('widget', 'w', InputOption::VALUE_REQUIRED, 'Only this tile (key)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getOption('user');
        if (!\is_string($username) || '' === $username) {
            $io->error('Please name the admin user with --user=<username>.');

            return Command::INVALID;
        }

        $user = $this->userRepository->findUserByIdentifier($username);
        if (!$user instanceof UserInterface) {
            $io->error(\sprintf('The user "%s" does not exist.', $username));

            return Command::FAILURE;
        }

        $locales = array_values($this->localizationManager->getLocales());
        $locale = $input->getOption('locale');
        $locale = \is_string($locale) && '' !== $locale ? $locale : ($locales[0] ?? 'en');
        if (!\in_array($locale, $locales, true)) {
            $io->error(\sprintf('The locale "%s" is not configured. Available: %s.', $locale, implode(', ', $locales)));

            return Command::FAILURE;
        }

        // the admin sets the system on every request; without it Sulu grants every permission
        $this->systemStore->setSystem(Admin::SULU_ADMIN_SECURITY_SYSTEM);
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'admin', $user->getRoles()));

        $only = $input->getOption('widget');
        $failed = false;
        $tiles = [];

        foreach ($this->widgetRegistry->available() as $widget) {
            if (\is_string($only) && '' !== $only && $widget->getKey() !== $only) {
                continue;
            }

            $tile = [
                'key' => $widget->getKey(),
                'title' => $widget->getTitle(),
                'type' => $widget->getType(),
                'column' => $widget->getColumn(),
                'priority' => $widget->getPriority(),
            ];

            try {
                $tile['data'] = $widget->getData($locale);
            } catch (Throwable $exception) {
                $failed = true;
                $tile['error'] = $exception->getMessage();
            }

            $tiles[] = $tile;
        }

        $output->writeln((string) json_encode(
            ['user' => $username, 'locale' => $locale, 'widgets' => $tiles],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR,
        ));

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
