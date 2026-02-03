<?php

namespace App\Service\Delivery;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmsService
{

    /**
     *
     * @var string
     */
    private $apiKey = "";

    /**
     *
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * Create a new instance.
     *
     * @param HttpClientInterface $httpClient
     * @return void
     */
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Send sms
     *
     * @param array $deal
     * @return bool|string
     */
    public function send(array $deal): bool|string
    {
        $offer = $this->removeTNFromString($deal['offer']['name']);

        $message = "Cher " . $deal['contact']['first_name'] . ", nous vous informons que votre commande " . $offer . " a été envoyée, le service de livraison vous contactera. Montant du colis: " . $deal['offer']['price'] . " DT.";

        // Encode the message
        $encodedMessage = urlencode($message);

        // Build the URL using sprintf
        $url = sprintf(
            "",
            $this->apiKey,
            $deal['contact']['phone'],
            $encodedMessage,
            'Mc-Shopy',
            date('d/m/Y'),   // Current date formatted
            date('H:i')      // Current time formatted
        );

        file_put_contents(__DIR__ . '/URL_Send_SMS_Livraison_' . date('Y-m-d') . '.log', print_r($url, true), FILE_APPEND);

        // Send the HTTP request
        $response = $this->httpClient->request('GET', $url);

        // Check the response status
        if ($response->getStatusCode() !== 200) {
            $this->logError("Error processing SmsService - ", 'Erreur lors de l\'envoi du SMS');

            return false;
        }

        // Parse the XML response from the API
        $content = $response->getContent();

        $xmlResponse = simplexml_load_string($content);

        // Log the full response for debugging
        file_put_contents(__DIR__ . '/SMS_Response_Livraison_' . date('Y-m-d') . '.log', print_r($xmlResponse, true), FILE_APPEND);

        // Handle the response based on the status code
        $statusCode = (int) $xmlResponse->status->status_code;

        switch ($statusCode) {
            case 200:
                return (string) $xmlResponse->status->message_id;

            case 400:
                $this->logError("Error processing SmsService - ", 'Erreur: Absence de la clé API.');
                break;

            case 401:
                $this->logError("Error processing SmsService - ", 'Erreur: Clé API non autorisée.');
                break;

            case 402:
                $this->logError("Error processing SmsService - ", 'Erreur: Crédit insuffisant pour l\'envoi du SMS.');
                break;

            case 420:
                $this->logError("Error processing SmsService - ", 'Erreur: Quota journalier dépassé.');
                break;

            case 430:
                $this->logError("Error processing SmsService - ", 'Erreur: Contenu du message manquant.');
                break;

            case 431:
                $this->logError("Error processing SmsService - ", 'Erreur: Destination manquante.');
                break;

            case 440:
                $this->logError("Error processing SmsService - ", 'Erreur: Contenu du message trop long.');
                break;

            case 441:
                $this->logError("Error processing SmsService - ", 'Erreur: Destination non autorisée.');
                break;

            case 442:
                $this->logError("Error processing SmsService - ", 'Erreur: Expéditeur non autorisé.');
                break;

            case 500:
                $this->logError("Error processing SmsService - ", 'Erreur interne du serveur.');
                break;

            default:
                $this->logError("Error processing SmsService - ", 'Erreur inconnue: Code ' . $statusCode);
        }

        return false;
    }

    private function removeTNFromString($string): string
    {
        if (substr($string, 0, 2) === 'TN') {
            return ltrim(substr($string, 2));
        }

        return $string;
    }

    /**
     * Enregistre les erreurs dans un fichier de log.
     *
     * @param string $context Contexte de l'erreur
     * @param string $message Message d'erreur
     */
    private function logError(string $context, string $message): void
    {
        file_put_contents(__DIR__ . '/sms__' . date('Y-m-d') . '.log', sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            $context,
            $message
        ), FILE_APPEND);
    }
}
