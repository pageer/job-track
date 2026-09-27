<?php

namespace App\Command;

use App\Service\NetworkingBackfillService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-networking',
    description: 'Creates companies from existing jobs, people from existing interviews, and a contact per interviewer.',
)]
class BackfillNetworkingCommand extends Command
{
    public function __construct(
        private NetworkingBackfillService $backfillService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->backfillService->backfill();

        $io->success(sprintf(
            'Created %d companies, linked %d jobs, created %d people (skipped %d duplicate people), created %d contacts (skipped %d existing).',
            $result['companies'],
            $result['jobsLinked'],
            $result['people'],
            $result['peopleSkipped'],
            $result['contacts'],
            $result['contactsSkipped'],
        ));

        return Command::SUCCESS;
    }
}
