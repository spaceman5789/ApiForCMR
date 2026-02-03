<?php

namespace App\Service;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ContactService
{
    private $httpClient;


    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

    }

    public function removeAndReplaceCountryCode($phoneNumber) {
        // Remplacer l'indicatif du Maroc par 0 et supprimer pour le reste
        // Les codes sont +212 pour le Maroc, +216 pour la Tunisie, et +244 pour l'Angola
        $phoneNumber = preg_replace('/^\+212\s?/', '0', $phoneNumber);
        //$phoneNumber = preg_replace('/^\+(216|244)\s?/', '', $phoneNumber);// espace devant
        // Remove Tunisia's country code 216 or +216
        $phoneNumber = preg_replace('/^\+?216\s?/', '', $phoneNumber);
        $phoneNumber = preg_replace('/\+244\s?/', '', $phoneNumber);  // Remove Angola code
        return $phoneNumber;
    }



    public function addContact(array $contactData,string $country): array
    {
        $data = $this->duplicateContact($contactData, $country);

        if ($data['total']>0) {
            return ['ID' => $data['result'][0]['ID'],'IsDuplicated'=> true];
        }
        $contact = [
            'fields' => [
                "NAME" => $contactData["name"],
                "LAST_NAME" => $contactData["lastname"] ?? null,
                "PHONE" => [["VALUE" => $contactData["telephone"], "VALUE_TYPE" => "WORK"]]
            ]
        ];
        $bitrixWebhookUrl = $this->getWebhookUrl($country).'/crm.contact.add';
        $response = $this->httpClient->request('POST', $bitrixWebhookUrl, [
            'json' => $contact
        ]);
        $response = $response->toArray();
        return ['ID' => $response['result'],'IsDuplicated'=> false];
    }
    private function duplicateContact($contactData, $country) {
        $bitrixWebhookUrl = $this->getWebhookUrl($country).'/crm.contact.list';
        $oneMonthAgo = date('Y-m-d', strtotime('-1 month'));
        // to deal with time difference (timezone)
        $today = date('Y-m-d', strtotime('+1 day'));
        $filters = [
            '>=DATE_CREATE' => $oneMonthAgo . ' 00:00:00',
            '<=DATE_CREATE' => $today . ' 23:59:59',
        ];
        $filters ['PHONE'] = $contactData['telephone'];
        $response = $this->httpClient->request('POST', $bitrixWebhookUrl , [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'filter' => $filters,
                'select' => ["ID", "NAME", "PHONE", "DATE_CREATE"],
            ]
        ]);
        $data = $response->toArray();
        return $data;
        /* if (!empty($data['result'])) {
             return true; // Duplicate found
         }
         return false; // No duplicates*/
    }

    private function getWebhookUrl(string $country): string
    {
        if($country === "Angola"){
            return "";
        }else{
            return "";
        }
    }
}