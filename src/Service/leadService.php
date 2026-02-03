<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class leadService
{
    private $httpClient;
    private $contactService;

    public function __construct(HttpClientInterface $httpClient,ContactService $contactService)
    {
        $this->httpClient = $httpClient;
        $this->contactService = $contactService;

    }

    public function createLead(Request $request): Response
    {
        $name = $request->get('name');
        $lastName = $request->get('lastName');
        $email = $request->get('email');
        $phone = $request->get('phone');

        $url = '';
        $response = $this->httpClient->request('POST', $url, [
            'body' => [
                'FIELDS[NAME]' => $name,
                'FIELDS[LAST_NAME]' => $lastName,
                'FIELDS[EMAIL][0][VALUE]' => $email,
                'FIELDS[EMAIL][0][VALUE_TYPE]' => 'WORK',
                'FIELDS[PHONE][0][VALUE]' => $phone,
                'FIELDS[PHONE][0][VALUE_TYPE]' => 'WORK',
                'FIELDS[CATEGORY_ID]' => 0,
            ]
        ]);
        return new Response($response->getContent());
    }
    public function createLeadJsonEntry($lead): Response
    {
        $postData = [
            'FIELDS[NAME]' => $lead['name'],
            'FIELDS[LAST_NAME]' => $lead['lastName'],
            'FIELDS[EMAIL][0][VALUE]' => $lead['email'],
            'FIELDS[PHONE][0][VALUE]' => $lead['phone']
        ];

        $url = '';
        $response = $this->httpClient->request('POST', $url, [
            'body' => $postData
        ]);
        return new Response($response->getContent());
    }
    public function createLeads(Request $request): Response
    {
        // Récupère le tableau de leads depuis la requête
        $data = json_decode($request->getContent(), true);
        $leads = $data["leads"];
        // URL de l'API CRM
        $url = '';

        // Réponse cumulée
        $responses = [];

        foreach ($leads as $lead) {
            // Prépare les données pour la requête POST
            $response= $this->createLeadJsonEntry($lead);
            $responses[] = $response;
        }
        // Retourne les réponses cumulées
        return new Response(json_encode($responses));
    }

    public function addContact(): Response
    {
        $contactData = [
            "NAME" => "John",
            "LAST_NAME" => "Doe",
            "OPENED" => "Y",
            "ASSIGNED_BY_ID" => 1,
            "TYPE_ID" => "CLIENT",
            "SOURCE_ID" => "SELF",
            "PHONE" => [["VALUE" => "555-1234", "VALUE_TYPE" => "WORK"]],
            "EMAIL" => [["VALUE" => "john.doe@example.com", "VALUE_TYPE" => "WORK"]]
        ];

        $result = $this->contactService->addContact($contactData);

        return new JsonResponse($result);
    }
}
