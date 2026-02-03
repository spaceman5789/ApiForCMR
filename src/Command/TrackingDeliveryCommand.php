<?php

namespace App\Command;

use App\Service\Delivery\AramexService;
use App\Service\Delivery\BigBossService;
use App\Service\Delivery\DealService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tracking',
    description: 'Track deals shipment status',
    aliases: ['app:tracking']
)]
class TrackingDeliveryCommand extends Command
{

    /**
     *
     * @var DealService
     */
    private $deal;

    /**
     *
     * @var AramexService
     */
    private $aramex;

    /**
     *
     * @var BigBossService
     */
    private $bigboss;


    /**
     * Create a new instance.
     *
     * @param DealService $deal
     * @param AramexService $aramex
     * @param BigBossService $bigboss
     * 
     * @return void
     */
    public function __construct(DealService $deal, AramexService $aramex, BigBossService $bigboss)
    {
        parent::__construct();

        $this->deal = $deal;
        $this->aramex = $aramex;
        $this->bigboss = $bigboss;
    }

    protected function configure(): void
    {
        $this->setHelp("This command allows to track deals shipment status");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deals = $this->deal->all([], ["CATEGORY_ID" => 8, '!=UF_CRM_1719699229165' => '', "@STAGE_ID" => ["C8:PREPARATION", "C8:PREPAYMENT_INVOICE", "C8:EXECUTING", "C8:FINAL_INVOICE"]]);

        if ($deals) {
            $commands = [];
            $barcodes = [];
            foreach ($deals as $key => $deal) {

                if (empty($deal['trackNumber'])) {
                    continue;
                }

                $barcodes[$deal['trackNumber']] = $deal['id'];

                // if ($deal['company'] == 'ARAMEX') {
                //     $status = $this->aramex->track($deal['trackNumber']);
                // } else 
                // if ($deal['company'] == 'BIGBOSS') {
                //     $status = $this->bigboss->track($deal['trackNumber']);
                // } else {
                //     $status = null;
                // }

                // if ($status) {
                //     $commands[] = sprintf(
                //         'crm.deal.update?id=%d&fields[STAGE_ID]=%s',
                //         $deal['id'],
                //         $status,
                //     );
                // }

                // sleep(2);
            }

            $packages = $this->bigboss->batchTrack($barcodes);

            foreach ($packages as $package) {
                if (isset($barcodes[$package['code']])) {
                    $dealID = $barcodes[$package['code']];
                    $status = 'C8:PREPAYMENT_INVOICE';
                    switch ($package['etat']) {
                        case 'En Attente':
                        case 'A Enlever':
                        case 'Anomalie d`Enlévement':
                        case 'Enlevé':
                        case 'Au Dépôt':
                            $status = null;
                            break;
                        case 'Retour Reçu':
                        case 'Echange Reçu':
                            $status = 'C8:LOSE';
                            break;
                        case 'Livré Payé':
                            $status = 'C8:WON';
                            break;
                        case 'Livré':
                            $status = 'C8:EXECUTING'; //Delivered
                            break;
                        case 'Retour Expéditeur':
                        case 'Retour Définitif':
                        case 'Retour Client Agence':
                            $status = 'C8:FINAL_INVOICE'; //Returned
                            break;
                        case 'Anomalie de Livraison':
                        case 'En Cours de Livraison':
                        case 'Retour Dépôt':
                            $status = 'C8:PREPAYMENT_INVOICE'; //Shipping
                            break;
                    }

                    if ($status) {
                        $commands[] = sprintf('crm.deal.update?id=%d&fields[STAGE_ID]=%s', $dealID, $status);
                    }
                }
            }

            if (count($commands)) {
                $this->deal->executeBatch($commands, 15);
            }
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }

    /**
     * Enregistre les erreurs dans un fichier de log.
     *
     * @param string $context Contexte de l'erreur
     * @param string $message Message d'erreur
     */
    private function logError(string $context, string $message): void
    {
        file_put_contents(__DIR__ . '/error_' . date('Y-m-d') . '.log', sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            $context,
            $message
        ), FILE_APPEND);
    }
}
