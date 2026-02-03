<?php

namespace App\Command;

use App\Service\Delivery\DealService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:temporary',
    description: 'Temporary command for random tasks',
    aliases: ['app:temporary']
)]
class TemporaryCommand extends Command
{

    /**
     *
     * @var DealService
     */
    private $deal;

    /**
     * Create a new instance.
     *
     * @param DealService $deal
     * 
     * @return void
     */
    public function __construct(DealService $deal)
    {
        parent::__construct();

        $this->deal = $deal;
    }

    protected function configure(): void
    {
        $this->setHelp("This command allows to run command for random tasks.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $leads = [];

        $commands = [];
        foreach ($leads as $lead) {
            $commands[] = sprintf(
                'crm.deal.update?id=%d&fields[STAGE_ID]=%s&fields[UF_CRM_1719699229165]=%s&fields[UF_CRM_1738157700839]=%s',
                $lead['Deal'],
                'C8:PREPARATION',
                $lead['TrackNumber'],
                $lead['Company']
            );
        }

        $this->deal->executeBatch($commands);

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
