<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\UserSetupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates an admin user (fails if the app is already set up or the email is taken).',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private UserSetupService $userSetupService,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The admin email address')
            ->addArgument('name', InputArgument::REQUIRED, 'The admin display name')
            ->addArgument('password', InputArgument::REQUIRED, 'The admin password (min. 8 characters)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) $input->getArgument('email'));
        $name = trim((string) $input->getArgument('name'));
        $password = (string) $input->getArgument('password');

        if (strlen($password) < 8) {
            $io->error('The password must be at least 8 characters long.');

            return Command::FAILURE;
        }

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('A user with the email "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $user = $this->userSetupService->createUser($name, $email, $password, ['ROLE_ADMIN']);

        $io->success(sprintf('Admin user "%s" (%s) created.', $user->getName(), $user->getEmail()));

        return Command::SUCCESS;
    }
}
