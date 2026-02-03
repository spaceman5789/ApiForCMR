<?php

namespace App\Command;

use App\Service\FirstDeliveryService;
use App\Service\EmailService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

class FirstDeliverySendDealsCommand extends Command
{
    protected static $defaultName = 'app:fd';
    protected static $defaultDescription = 'Send deals to First delivery';

    private $firstDeliveryService;
    private $logger;
    private $mailService;

    public function __construct(FirstDeliveryService $firstDeliveryService,
                                LoggerInterface $logger,EmailService $mailService)
    {
        parent::__construct();
        $this->firstDeliveryService = $firstDeliveryService;
        $this->mailService = $mailService;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to add deals to First delivery');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Retreive deals from Bitrix ');
        $this->logger->info('Starting getDealsForDelivery command.');

        try {
            
            $result = $this->firstDeliveryService->LaunchIntegration();
            //$this->firstDeliveryService->prepareDataForMail();
            //$result = $this->firstDeliveryService->getDealsForDelivery();
            if (!empty($result['successes'])) {
                $io->success('Deals retreived successfully with the following results:');
                foreach ($result['successes'] as $success) {
                    $io->writeln("Command: {$success['command']}, ID: {$success['id']}, Message: {$success['message']}");
                }
            }

            if (!empty($result['errors'])) {
                $io->error('Some deals failed with the following errors:');
                foreach ($result['errors'] as $error) {
                    $io->writeln("Command: {$error['command']}, Error: {$error['error']}, Description: {$error['error_description']}");
                }
            }
            if (empty($result['errors']) && empty($result['successes'] )) {
                $io->success('Nothing to add');
            }
            $this->logger->info('FirstDeliverySendDealsCommand command completed.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('An error occurred: ' . $e->getMessage());
            $io->error('An error occurred while adding TN deals: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
