<?php

namespace App\Command;

use App\Service\DealService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncBitrixDealsCommand extends Command
{
    protected static $defaultName = 'app:sync-bitrix-deals';
    protected static $defaultDescription = 'Synchronize deals from Bitrix for a given date range.';

    private DealService $dealService;

    public function __construct(DealService $dealService)
    {
        parent::__construct();
        $this->dealService = $dealService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('monthStart', InputArgument::REQUIRED, 'The start date (YYYY-MM-DD) for the synchronization.')
            ->addArgument('monthEnd', InputArgument::REQUIRED, 'The end date (YYYY-MM-DD) for the synchronization.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        ini_set('memory_limit', '2G'); 
        // Récupérer les arguments
        $monthStart = $input->getArgument('monthStart');
        $monthEnd = $input->getArgument('monthEnd');

        $io->title('Starting Bitrix Deals Synchronization');
        $io->text("Date Range: $monthStart to $monthEnd");

        try {
            // Appeler le service pour synchroniser les deals
            $deals = $this->dealService->getDealsForBD($monthStart, $monthEnd);

            $io->success(sprintf('Deals synchronized successfully! Total deals: %d', count($deals)));
        } catch (\Exception $e) {
            $io->error('An error occurred during the synchronization.');
            $io->error($e->getMessage());

            return Command::FAILURE; // Retourne un échec
        }

        return Command::SUCCESS; // Retourne un succès
    }
}
