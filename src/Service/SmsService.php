<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\Offer;
use App\Repository\OfferRepository;

class SmsService
{
    private $apiKey;
    private $httpClient;
    private $offerRepository;

    public function __construct(HttpClientInterface $httpClient, OfferRepository $offerRepository)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = "";
        $this->offerRepository = $offerRepository;
    }


    public function sendSmsUrlConfirmation($dealDetails, $offerName)
    {
        $contactID = $dealDetails['result']['CONTACT_ID'];
        $clientData = $this->getContactDetails($contactID);
        $offerName = $this->removeTNFromString($offerName);
        $message = "Cher  " . $clientData['NAME'] . ", nous vous remercions pour votre confiance et nous vous informons que votre commande " . $offerName . " a été prise en charge.";
        // Encode the message
        $encodedMessage = urlencode($message);
        // Build the URL using sprintf
        $url = sprintf(
            "",
            $this->apiKey,
            $clientData['PHONE'][0]['VALUE'],
            $encodedMessage,
            'Mc-Shopy',
            date('d/m/Y'),   // Current date formatted
            date('H:i')      // Current time formatted
        );
        file_put_contents('../var/log/bitrix24_webhook_URL_Send_SMS_Confirmation.log', print_r($url, true), FILE_APPEND);

        // Send the HTTP request
        $response = $this->httpClient->request('GET', $url);

        // Check the response status
        if ($response->getStatusCode() !== 200) {
            file_put_contents('../var/log/bitrix24_webhook_URL_Send_SMS_Confirmation_ERROR.log', print_r($url, true), FILE_APPEND);

            throw new \Exception('Erreur lors de l\'envoi du SMS');
        }

        // Parse the XML response from the SMS API
        $content = $response->getContent();
        $xmlResponse = simplexml_load_string($content);

        // Log the full response for debugging
        file_put_contents('../var/log/SMS_Response_Confirmation.log', print_r($xmlResponse, true), FILE_APPEND);

        // Handle the response based on the status_code
        $statusCode = (int) $xmlResponse->status->status_code;
        switch ($statusCode) {
            case 200:
                // Success
                return (string) $xmlResponse->status->message_id;

            case 400:
                throw new \Exception('Erreur: Absence de la clé API.');

            case 401:
                throw new \Exception('Erreur: Clé API non autorisée.');

            case 402:
                throw new \Exception('Erreur: Crédit insuffisant pour l\'envoi du SMS.');

            case 420:
                throw new \Exception('Erreur: Quota journalier dépassé.');

            case 430:
                throw new \Exception('Erreur: Contenu du message manquant.');

            case 431:
                throw new \Exception('Erreur: Destination manquante.');

            case 440:
                throw new \Exception('Erreur: Contenu du message trop long.');

            case 441:
                throw new \Exception('Erreur: Destination non autorisée.');

            case 442:
                throw new \Exception('Erreur: Expéditeur non autorisé.');

            case 500:
                throw new \Exception('Erreur interne du serveur.');

            default:
                throw new \Exception('Erreur inconnue: Code ' . $statusCode);
        }
    }

    private function getContactDetails($contactId)
    {
        $bitrixWebhookUrl = '' . $contactId;
        $response = $this->httpClient->request('GET', $bitrixWebhookUrl);
        $data = $response->toArray();
        return $data["result"];
    }

    public function removeTNFromString($string): string
    {
        // Check if the string starts with "TN"
        if (substr($string, 0, 2) === 'TN') {
            // Remove "TN" and any space after it
            return ltrim(substr($string, 2));
        }

        // Return the string unchanged if it doesn't start with "TN"
        return $string;
    }


    public function sendSmsLivraison($clientName, $offerName, $phoneNumber, $price)
    {
        $offerName = $this->removeTNFromString($offerName);
        $message = "Cher " . $clientName . ", nous vous informons que votre commande " . $offerName . " a été envoyée, le service de livraison vous contactera. Montant du colis: " . $price . " DT.";

        // Encode the message
        $encodedMessage = urlencode($message);
        // Build the URL using sprintf
        $url = sprintf(
            "",
            $this->apiKey,
            $phoneNumber,
            $encodedMessage,
            'Mc-Shopy',
            date('d/m/Y'),   // Current date formatted
            date('H:i')      // Current time formatted
        );
        file_put_contents(__DIR__ . '/URL_Send_SMS_Livraison.log', print_r($url, true), FILE_APPEND);

        // Send the HTTP request
        $response = $this->httpClient->request('GET', $url);

        // Check the response status
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Erreur lors de l\'envoi du SMS');
        }
        // Parse the XML response from the API
        $content = $response->getContent();
        $xmlResponse = simplexml_load_string($content);

        // Log the full response for debugging
        file_put_contents(__DIR__ . '/SMS_Response_Livraison.log', print_r($xmlResponse, true), FILE_APPEND);

        // Handle the response based on the status code
        $statusCode = (int) $xmlResponse->status->status_code;
        switch ($statusCode) {
            case 200:
                // Success - Return the message ID
                return (string) $xmlResponse->status->message_id;

            case 400:
                throw new \Exception('Erreur: Absence de la clé API.');

            case 401:
                throw new \Exception('Erreur: Clé API non autorisée.');

            case 402:
                throw new \Exception('Erreur: Crédit insuffisant pour l\'envoi du SMS.');

            case 420:
                throw new \Exception('Erreur: Quota journalier dépassé.');

            case 430:
                throw new \Exception('Erreur: Contenu du message manquant.');

            case 431:
                throw new \Exception('Erreur: Destination manquante.');

            case 440:
                throw new \Exception('Erreur: Contenu du message trop long.');

            case 441:
                throw new \Exception('Erreur: Destination non autorisée.');

            case 442:
                throw new \Exception('Erreur: Expéditeur non autorisé.');

            case 500:
                throw new \Exception('Erreur interne du serveur.');

            default:
                throw new \Exception('Erreur inconnue: Code ' . $statusCode);
        }
        // Return the response body or any necessary result
        return $response->getContent(); // Assuming the API returns the result as text or JSON
    }
}
