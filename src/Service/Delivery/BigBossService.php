<?php

namespace App\Service\Delivery;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BigBossService
{

    /**
     *
     * @var array
     */
    private $config;

    /**
     *
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * Create a new instance.
     *
     * @param ParameterBagInterface $params
     * @return void
     */
    public function __construct(ParameterBagInterface $params, HttpClientInterface $httpClient)
    {
        $this->config = $params->get('delivery')['bigboss'];

        $this->httpClient = $httpClient;
    }

    /**
     * Prepare data to create colis.
     *
     * @param array $deal
     * @return array|bool
     */
    public function create(array $deal): array|bool
    {
        try {
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($this->payload($deal)),
            ];

            $response = $this->httpClient->request('POST', $this->config['endpoint'] . 'AjouterVColis', $options);

            $result = json_decode($response->getContent(false), true);

            if (isset($result['result_type'])) {

                if ($result['result_type'] === 'success') {
                    return [
                        'success' => true,
                        'id' => $result['result_content']['codeBar'],
                        'label' => '',
                        'company' => 'BIGBOSS'
                    ];
                }

                return [
                    'success' => false,
                    'error' => $result['result_content'],
                    'company' => 'BIGBOSS'
                ];
            }

            return [
                'success' => false,
                'error' => $response->getContent(false),
                'company' => 'BIGBOSS'
            ];
        } catch (\Exception $e) {
            $this->logError("Error processing BigBossService {$deal['id']} - ", $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'company' => 'BIGBOSS'
            ];
        }
    }

    /**
     * Track Shipments Status.
     *
     * @param string $barcode
     * @return string|bool
     */
    public function track(string $barcode): string|bool
    {
        try {
            $body = [
                "Uilisateur" => $this->config['username'],
                "Pass" => $this->config['password'],
                "codeBar" => $barcode,
            ];

            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($body),
            ];

            $response = $this->httpClient->request('POST', $this->config['endpoint'] . 'getColis', $options);

            $result = json_decode($response->getContent(false), true);

            if (isset($result['result_type'])) {
                if ($result['result_type'] === 'success') {
                    $status = 'C8:PREPAYMENT_INVOICE';
                    switch ($result['result_content']['etat']) {
                        case 'En Attente':
                        case 'A Enlever':
                        case 'Anomalie d`Enlévement':
                        case 'Enlevé':
                        case 'Au Dépôt':
                            return false;
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

                    return $status;
                }

                $this->logError("Error processing BigBossService Track {$barcode} - ", $result['result_content']);

                return false;
            }

            $this->logError("Error processing BigBossService Track {$barcode} - ", $response->getContent(false));
        } catch (\Exception $e) {
            $this->logError("Error processing BigBossService Track {$barcode} - ", $e->getMessage());
        }

        return false;
    }

    /**
     * Track Batch Shipments.
     *
     * @param array $barcodes
     * @return array
     */
    public function batchTrack(array $barcodes): array
    {
        try {
            $barcodes = implode(';', array_keys($barcodes));

            $body = [
                "Uilisateur" => $this->config['username'],
                "Pass" => $this->config['password'],
                "codeBar" => $barcodes,
            ];

            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($body),
            ];

            $response = $this->httpClient->request('POST', $this->config['endpoint'] . 'ListColis', $options);

            $result = json_decode($response->getContent(false), true);

            if (isset($result['result_type'])) {
                if ($result['result_type'] === 'success') {
                    
                    return $result['result_content']['colis'];
                }

                $this->logError("Error processing BigBossService Track {$barcodes} - ", $result['result_content']);

                return [];
            }

            $this->logError("Error processing BigBossService Track {$barcodes} - ", $response->getContent(false));
        } catch (\Exception $e) {
            $this->logError("Error processing BigBossService Track {$barcodes} - ", $e->getMessage());
        }

        return [];
    }

    /**
     * Get PDF Labels.
     *
     * @return string|bool
     */
    public function getLabels(): string|bool
    {
        try {
            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    "Uilisateur" => $this->config['username'],
                    "Pass" => $this->config['password'],
                ]),
            ];

            $response = $this->httpClient->request('POST', $this->config['endpoint'] . 'demanderEnlevement', $options);

            $result = json_decode($response->getContent(false), true);

            if (isset($result['result_type'])) {
                if ($result['result_type'] === 'success') {
                    return $result['result_content'];
                }

                $this->logError("Error processing BigBossService Labels - ", $result['result_content']);

                return false;
            }

            $this->logError("Error processing BigBossService Labels - ", $response->getContent(false));
        } catch (\Exception $e) {
            $this->logError("Error processing BigBossService Labels - ", $e->getMessage());
        }

        return false;
    }

    /**
     * Prepare data to create colis.
     *
     * @param array $deal
     * @return array
     */
    private function payload(array $deal): array
    {
        return [
            "Uilisateur" => $this->config['username'],
            "Pass" => $this->config['password'],
            "reference" => $deal['order'],
            "client" => $deal['contact']['full_name'],
            "adresse" => $deal['contact']['address'],
            "gouvernorat" => str_replace(['Beja', 'Gabes', 'Kebili', 'Medenine'], ['Béja', 'Gabès', 'Kébili', 'Médenine'], $deal['contact']['region']),
            "ville" => $deal['contact']['city'],
            // "code_postal" => $deal['contact']['zip_code'],
            "nb_pieces" => 1, //(int)$deal['offer']['quantity'],
            "prix" => (float)$deal['offer']['price'],
            "tel1" => substr($deal['contact']['phone'], -8),
            "tel2" => "",
            "designation" => $deal['offer']['name'],
            "commentaire" => "",
            "type" => "FIX",
            "echange" => 0
        ];
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
