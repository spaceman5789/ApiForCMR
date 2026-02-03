<?php

namespace App\Command;

use App\Service\DealService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

class ExecuteBatchTNCommand extends Command
{
    protected static $defaultName = 'app:add-deals-TN';
    protected static $defaultDescription = 'Add deals to BitrixTN via DealService';

    private $dealService;
    private $logger;

    public function __construct(DealService $dealService, LoggerInterface $logger)
    {
        parent::__construct();
        $this->dealService = $dealService;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to add deals to BitrixTN by executing the addDealsToBitrixTN method');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Adding deals to BitrixTN');
        $this->logger->info('Starting addDealsToBitrixTN command.');

        try {
            $result = $this->dealService->addDealsToBitrixTN();
            if (!empty($result['successes'])) {
                $io->success('Deals TN added successfully with the following results:');
                foreach ($result['successes'] as $success) {
                    $io->writeln("Command: {$success['command']}, ID: {$success['id']}, Message: {$success['message']}");
                }
            }

            if (!empty($result['errors'])) {
                $io->error('Some deals TN failed with the following errors:');
                foreach ($result['errors'] as $error) {
                    $io->writeln("Command: {$error['command']}, Error: {$error['error']}, Description: {$error['error_description']}");
                }
            }
            if (empty($result['errors']) && empty($result['successes'] )) {
                $io->success('Nothing to add');
            }
            $this->logger->info('addDealsToBitrixTN command completed.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('An error occurred: ' . $e->getMessage());
            $io->error('An error occurred while adding TN deals: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
