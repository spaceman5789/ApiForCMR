<?php

namespace App\Command;

use App\Service\Delivery\AramexService;
use App\Service\Delivery\BigBossService;
use App\Service\Delivery\DealService;
use App\Service\Delivery\EmailService;
use App\Service\Delivery\SmsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use setasign\Fpdi\Fpdi;

#[AsCommand(
    name: 'app:delivery',
    description: 'Send deals to carrier\'s partners',
    aliases: ['app:delivery']
)]
class DeliveryCommand extends Command
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
     *
     * @var EmailService
     */
    private $mail;

    /**
     *
     * @var string
     */
    private $uploadsDir;

    /**
     *
     * @var ParameterBagInterface
     */
    private $params;

    /**
     *
     * @var SmsService
     */
    private $sms;

    /**
     * Create a new instance.
     *
     * @param DealService $deal
     * @param AramexService $aramex
     * @param BigBossService $bigboss
     * @param EmailService $mail
     * @param ParameterBagInterface $params
     * @param SmsService $sms
     * 
     * @return void
     */
    public function __construct(DealService $deal, AramexService $aramex, BigBossService $bigboss, EmailService $mail, ParameterBagInterface $params, SmsService $sms)
    {
        parent::__construct();

        $this->deal = $deal;
        $this->aramex = $aramex;
        $this->bigboss = $bigboss;
        $this->mail = $mail;
        $this->params = $params;
        $this->sms = $sms;

        $this->uploadsDir = $params->get('kernel.project_dir') . '/public/uploads/labels/';

        // Create directory if not exists
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }
    }

    protected function configure(): void
    {
        $this->setHelp("This command allows to send deals to carrier's partners");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deals = $this->deal->all([], ["CATEGORY_ID" => 8, "STAGE_ID" => "C8:NEW"]);

        if ($deals) {
            $data = [
                'deals' => [],
                'offers' => [],
                'packages' => 0,
                'packages_aramex' => 0,
                'packages_bigboss' => 0,
            ];

            // $aramex = 0;
            // $bigboss = 100;
            $total = count($deals);

            $aramex = 0; //$total <= 50 ? $total : 50; // (int)round(($aramex / 100) * $total);

            if (date('A') == 'PM') {
                $aramex = 0;
            }

            $bigboss = $total - $aramex; // (int)round(($bigboss / 100) * $total);

            $countBigboss = 0;
            $countAramex = 0;

            $errors = [];
            $commands = [];
            foreach ($deals as $deal) {
                if ($countBigboss < $bigboss) {
                    $shipment = $this->bigboss->create($deal);

                    if ($shipment['success']) {
                        $countBigboss++;
                    }
                } elseif ($countAramex < $aramex) {
                    $shipment = $this->aramex->create($deal);

                    if ($shipment['success']) {
                        $countAramex++;
                    }
                }

                if ($shipment['success']) {
                    $deal['sms'] = $this->sms->send($deal) ? 'Yes' : 'No';
                    $deal['shipment'] = $shipment;
                    $data['deals'][] = $deal;

                    if (!isset($data['offers'][$deal['offer']['name']])) {
                        $data['offers'][$deal['offer']['name']] = 0;
                    }

                    $data['offers'][$deal['offer']['name']] += (int)$deal['offer']['quantity'];

                    $data['packages'] += (int)$deal['offer']['quantity'];

                    if ($shipment['company'] === 'ARAMEX') {
                        $data['packages_aramex'] += (int)$deal['offer']['quantity'];
                    }

                    if ($shipment['company'] === 'BIGBOSS') {
                        $data['packages_bigboss'] += (int)$deal['offer']['quantity'];
                    }

                    $commands[] = sprintf(
                        'crm.deal.update?id=%d&fields[STAGE_ID]=%s&fields[UF_CRM_1719699229165]=%s&fields[UF_CRM_1738157700839]=%s',
                        $deal['id'],
                        'C8:PREPARATION',
                        $shipment['id'],
                        $shipment['company']
                    );
                } else {
                    $errors[] = [
                        'deal' => $deal['id'],
                        'company' => $shipment['company'],
                        'message' => $shipment['error']
                    ];
                }

                sleep(2);
            }

            $combined_aramex = $countAramex ? $this->mergePdfs($data['deals']) : false;

            $combined_bigboss = $countBigboss ? $this->bigboss->getLabels() : false;

            $this->deal->executeBatch($commands);

            $this->mail->send('Success in adding deals to delivery', 'summary', [
                'data' => $data,
                'combined_aramex' => $combined_aramex ?? '',
                'combined_bigboss' => $combined_bigboss ?? '',
                'errors' => $errors,
                'countAramex' => $countAramex,
                'countBigboss' => $countBigboss,
            ]);
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }

    private function mergePdfs(array $deals): string
    {
        try {
            $count = 0;

            $pdf = new Fpdi();

            foreach ($deals as $deal) {
                if (empty($deal['shipment']['label'])) {
                    continue;
                }

                $content = file_get_contents($deal['shipment']['label']);

                if ($content === false) {
                    $this->logError("Error processing merge pdfs {$deal['shipment']['id']} - ", "Impossible de télécharger le fichier PDF depuis l'URL : {$deal['shipment']['label']}");

                    return false;
                }

                $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';

                file_put_contents($tempPdf, $content);

                $pageCount = $pdf->setSourceFile($tempPdf);

                for ($i = 1; $i <= $pageCount; $i++) {
                    $pdf->AddPage();
                    $pdf->useTemplate($pdf->importPage($i));
                }

                $count++;

                unlink($tempPdf);
            }

            if ($count === 0) {
                return false;
            }

            $filename = 'combined_' . time() . '.pdf';

            $outputPath = $this->uploadsDir . $filename;

            $pdf->Output($outputPath, 'F');

            if (!file_exists($outputPath)) {
                $this->logError("Error processing merge pdfs $filename - ", "Le fichier combiné n'a pas été créé !");

                return false;
            }

            return "https://plumbill.io/download/$filename";

            // return $this->params->get('app_url') . "/download/$filename";
        } catch (\Exception $e) {
            $this->logError("Error processing merge pdfs - ", $e->getMessage());
        }

        return false;
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
