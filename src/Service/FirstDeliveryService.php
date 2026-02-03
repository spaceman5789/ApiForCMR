<?php

namespace App\Service;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\DealFieldsBRepository;
use App\Service\SmsService;
use App\Service\EmailService;
use App\Service\PdfMergerService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FirstDeliveryService
{
    private $httpClient;
    private $dealTnRepository;
    private $smsService;
    private $mailService;
    private $pdfMergerService;
    private $params;
    public function __construct(
        HttpClientInterface $httpClient,
        DealFieldsBRepository $dealFieldsBRepository,
        SmsService $smsService,
        EmailService $mailService,
        PdfMergerService $pdfMergerService,
        ParameterBagInterface $params
    )
    {
        $this->httpClient = $httpClient;
        $this->dealFieldsBRepository = $dealFieldsBRepository;
        $this->mailService = $mailService;
        $this->smsService = $smsService;
        $this->pdfMergerService = $pdfMergerService;
        $this->params = $params;
    }

   

    private function getContactDetailsInBatch(array $contactIds): array
    {
        $baseUrl = '';
        $batchSize = 50; // Limit of commands per batch (max is 50 for Bitrix24)
        $batchedContacts = array_chunk($contactIds, $batchSize);
        $contacts = [];
    
        foreach ($batchedContacts as $batch) {
            $batchPayload = [];
            foreach ($batch as $index => $contactId) {
                $batchPayload["cmd$index"] = "crm.contact.get?ID=$contactId";
            }
    
            try {
                // Send batch request
                $response = $this->httpClient->request('POST', $baseUrl . 'batch.json', [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => ['cmd' => $batchPayload],
                ]);
    
                $responseData = $response->toArray();
    
                if (isset($responseData['result']['result'])) {
                    foreach ($responseData['result']['result'] as $contactResult) {
                        if (!empty($contactResult)) {
                            $contacts[$contactResult['ID']] = $contactResult;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logError('Error fetching contacts in batch', $e->getMessage());
            }
        }
    
        return $contacts;
    }


    /**
     * Exécute une requête batch à l'API Bitrix
     */
    public function executeBatchRequest(array $commands): array
    {
        $endpoint = ''. '/batch.json';

        $response = $this->httpClient->request('POST', $endpoint, [
            'json' => [
                'cmd' => $commands,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Erreur lors de la requête batch à Bitrix');
        }

        return $response->toArray();
    }

    /**
     * Récupère les deals par batch en fonction de la pagination
     */
   
    public function getAllDeals( int $batchSize = 10, int $limit = 50): array
    {
        $allDeals = [];
        $currentOffset = 0;
        $hasMore = true;
        //$stageId = 'C8:NEW';
        $stageId = 'NEW';
        $categorie= 0;
        while ($hasMore) {
            $commands = [];
          
            // Préparer les commandes batch pour le lot actuel
            // for ($i = 0; $i < $batchSize; $i++) {
            //     $commands["cmd$i"] = sprintf(
            //         'crm.deal.list?filter[STAGE_ID]=%s&start=%d&select[]=ID&select[]=DATE_CREATE&select[]=CONTACT_ID&select[]=UF_CRM_1705750463237&select[]=STAGE_ID&select[]=CATEGORY_ID&select[]=UF_CRM_1705749663304&select[]=UF_CRM_1705749715974&select[]=UF_CRM_1705750486330&select[]=UF_CRM_1705750225691&select[]=UF_CRM_1705750299634',
            //         urlencode($stageId),
            //         $currentOffset + ($i * $limit)
            //     );
            // }
            // Préparer les commandes batch pour le lot actuel
            for ($i = 0; $i < $batchSize; $i++) {
                $commands["cmd$i"] = sprintf(
                    'crm.deal.list?filter[STAGE_ID]=%s&categorie=%d&start=%d&select[]=ID&select[]=DATE_CREATE&select[]=CONTACT_ID&select[]=UF_CRM_1705750463237&select[]=STAGE_ID&select[]=CATEGORY_ID&select[]=UF_CRM_1705749663304&select[]=UF_CRM_1705749715974&select[]=UF_CRM_1705750486330&select[]=UF_CRM_1705750225691&select[]=UF_CRM_1705750299634',
                    urlencode($stageId),
                    urldecode($categorie),
                    $currentOffset + ($i * $limit)
                );
            }
            // Exécuter la requête batch
            $result = $this->executeBatchRequest($commands);

            if (!isset($result['result']['result'])) {
                throw new \Exception('Structure de réponse inattendue : ' . json_encode($result));
            }

            $hasMore = false;

            // Ajouter les résultats de chaque commande au tableau global
            foreach ($result['result']['result'] as $cmdKey => $cmdResult) {
                if (!empty($cmdResult)) {
                    $allDeals = array_merge($allDeals, $cmdResult);

                    // Si une commande retourne moins de `limit` résultats, arrêter la boucle
                    if (count($cmdResult) < $limit) {
                        $hasMore = false;
                        break;
                    } else {
                        $hasMore = true;
                    }
                }
            }
 
            // Incrémenter l'offset pour le prochain lot uniquement si `hasMore` est vrai
            if ($hasMore) {
                $currentOffset += ($batchSize * $limit);
            }
        }
        $this->logSucces('$allDeals !!!!!! ', $allDeals);
        // Supprimer les doublons basés sur l'ID
        return array_unique($allDeals, SORT_REGULAR);
    }
    public function LaunchIntegration(){
        try {
            $deals = $this->getAllDeals();
            // Traitement des données reçues
            foreach ($deals as $deal) {
                // Collecter les IDs de contacts
                // Add the CONTACT_ID to the collection if it exists
                if (!empty($deal['CONTACT_ID'])) {
                    $contactIds[] = $deal['CONTACT_ID'];
                }
            }
            //remove duplicated contactIds
            $contactIds = array_unique($contactIds);
            // Fetch all contact details in batch
            $contacts = $this->getContactDetailsInBatch($contactIds);
            $this->logSucces('all contacts!!!!!! ', $contacts);
            $result = $this->sendShipmentAramex($deals,$contacts);
        } catch (\Exception $e) {
            // Log global des erreurs
            $this->logError('Fatal Error getDealsForDelivery', $e->getMessage());
            
        }
        
    }
        
    

   public function SendCommandFirstDelivery($deals, $contacts): array
    {
        $errors = [];
        $successes = [];

        if (empty($deals)) {
            $errors[] = ['error' => 'No deals provided'];
            return ['successes' => $successes, 'errors' => $errors];
        }

        foreach ($deals as $deal) {
            if (empty($deal['CONTACT_ID']) || !isset($contacts[$deal['CONTACT_ID']])) {
                $errors[] = ['deal_ID' => $deal['ID'], 'error' => 'Contact details not found'];
                continue;
            }

            $clientData = $contacts[$deal['CONTACT_ID']];
            $dealsFormatted = [
                "Client" => [
                    "nom" => $clientData['NAME'] . ' ' . $clientData['LAST_NAME'],
                    "gouvernerat" => $this->getIdValue('UF_CRM_1705750225691', $deal['UF_CRM_1705750225691']),
                    "ville" => $this->getIdValue('UF_CRM_1705750299634', $deal['UF_CRM_1705750299634']),
                    "adresse" => $deal['UF_CRM_1705750486330'],
                    "telephone" => $this->contactService->removeAndReplaceCountryCode($clientData['PHONE'][0]['VALUE'] ?? '')
                ],
                "Produit" => [
                    "prix" => floatval($this->getIdValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237'])),
                    "designation" => $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']),
                    "nombreArticle" => intval($this->getIdValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974'])),
                    "article" => $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304'])
                ]
            ];

            try {
               // $reponseFD = $this->AddCommandFirstDelivery($dealsFormatted);
                $reponseFD = json_decode($reponseFD, true);

                if (!empty($reponseFD['isError'])) {
                    $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Error sending deal to FD'];
                    continue;
                }

                $successes[] = ['order_ID' => $deal['ID'], 'message' => 'Deal successfully sent to FD'];
                // Collect the update payload for batch processing
                $batchUpdates[] = [
                    'ID' => $deal['ID'],
                    'barCode' => $reponseFD['result']['barCode']
                ];
               // $rep = $this->updateDealStage($deal['ID'], $reponseFD['result']['barCode']);

                // Prepare SMS variables
                $price = floatval($this->getIdValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237']));
                $offerName = $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']);
                $phoneNumber = $clientData['PHONE'][0]['VALUE'] ?? '';
                $name = $clientData['NAME'];

                // Send the SMS and handle the response
                try {
                    //$smsResponse = $this->smsService->sendSmsLivraison($name, $offerName, $phoneNumber, $price);
                    $successes[] = ['order_ID' => $deal['ID'], 'sms_message_id' => $smsResponse, 'message' => 'SMS sent successfully'];
                } catch (\Exception $e) {
                    $errors[] = ['order_ID' => $deal['ID'], 'error' => 'SMS sending failed: ' . $e->getMessage()];
                }

                if ($rep->getStatusCode() === 200) {
                    $successes[] = ['order_ID' => $deal['ID'], 'message' => 'Deal successfully updated'];
                } else {
                    $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Error updating deal'];
                }
            } catch (\Exception $e) {
                $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Unexpected error: ' . $e->getMessage()];
            }
        }
        // Process batch updates for deal stages
       // $batchUpdateResults = $this->updateDealStageInBatch($batchUpdates);
        foreach ($batchUpdateResults as $result) {
            if (!empty($result['error'])) {
                $errors[] = ['order_ID' => $result['deal_ID'], 'error' => $result['error']];
            } else {
                $successes[] = ['order_ID' => $result['deal_ID'], 'message' => 'Deal stage updated successfully'];
            }
        }
        // Log results to file
        $filePath = '/var/www/file.txt'; // Ensure this path matches your Docker container setup
        $date = new \DateTime();
        $formattedDate = $date->format('Y-m-d H:i:s');
        $logContent = "Execution time: $formattedDate\n" .
                    "Successes: " . json_encode($successes) . "\n" .
                    "Errors: " . json_encode($errors) . "\n";
        file_put_contents($filePath, $logContent, FILE_APPEND);

        return [
            'successes' => $successes,
            'errors' => $errors,
        ];
    }


    private function AddCommandFirstDelivery($command){
        $url = 'https://www.firstdeliverygroup.com/api/v2/create';

        $token = '';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => $command
            ]);

            if ($response->getStatusCode() === 200) {
                // Handle success
                $content = $response->getContent();
                return $content;
            } else {
                // Handle errors
                return $response->getContent(false);
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
    * Update deal stages in batch
    */
    public function updateDealStageInBatch(array $batchUpdates): array
    {
        $baseUrl = '';
        $batchSize = 10; // Maximum batch size for Bitrix24
        $batchedUpdates = array_chunk($batchUpdates, $batchSize);
        $results = [];
    
        foreach ($batchedUpdates as $batch) {
            $batchPayload = [];
            foreach ($batch as $index => $update) {
                $batchPayload["cmd$index"] = sprintf(
                    //'crm.deal.update?id=%d&fields[STAGE_ID]=%s',
                    'crm.deal.update?id=%d&fields[STAGE_ID]=%s&fields[UF_CRM_1719699229165]=%s',
                    $update['ID'],
                    //'C8:PREPARATION',
                    'PREPARATION',
                    $update['trackNumber']
                );
            }
    
            try {
                $response = $this->httpClient->request('POST', $baseUrl . 'batch.json', [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => ['cmd' => $batchPayload],
                ]);
    
                $responseData = $response->toArray();
                $this->logSucces('Batch response data', $responseData);
    
                if (isset($responseData['result']['result'])) {
                    foreach ($batch as $index => $update) {
                        $cmdKey = "cmd$index";
                        if (isset($responseData['result']['result'][$cmdKey])) {
                            $results[] = [
                                'deal_ID' => $update['ID'],
                                'success' => true,
                                'error' => null,
                            ];
                        } else {
                            $results[] = [
                                'deal_ID' => $update['ID'],
                                'success' => false,
                                'error' => 'Undefined response key: ' . $cmdKey,
                            ];
                        }
                    }
                } else {
                    foreach ($batch as $update) {
                        $results[] = [
                            'deal_ID' => $update['ID'],
                            'success' => false,
                            'error' => 'No results in batch response',
                        ];
                    }
                }
            } catch (\Exception $e) {
                foreach ($batch as $update) {
                    $results[] = [
                        'deal_ID' => $update['ID'],
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
    
        return $results;
    }
    


    /**
     * Enregistre les erreurs dans un fichier de log.
     *
     * @param string $context Contexte de l'erreur
     * @param string $message Message d'erreur
     */
    private function logError(string $context, string $message): void
    {
        file_put_contents(__DIR__ . '/error_log_prod.txt', sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            $context,
            $message
        ), FILE_APPEND);
    }

    private function logSucces(string $context, $message): void
    {
        var_dump('On log success');
        $logMessage = is_array($message) || is_object($message) 
            ? json_encode($message, JSON_PRETTY_PRINT) 
            : $message;

        file_put_contents(__DIR__ . '/succes_log_prod.txt', sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            $context,
            $logMessage
        ), FILE_APPEND);
    }

    private function getValueId(string $fieldName, string $value, string $country): ?string
    {
        
        // Récupérer les données stockées en fonction du pays
        $storedData = $this->dealFieldsBRepository->findOneBy(['country' => 'Tunisia']);
        
        //dd($storedData);
        if ($storedData) {
            // Parsez les données JSON stockées
            $fields = $storedData->getValue();
            if (is_string($fields)) {
                $fields = json_decode($fields, true);
            }
            // Cherchez la valeur dans les données stockées
            if (isset($fields['result'][$fieldName]['items'])) {
                foreach ($fields['result'][$fieldName]['items'] as $item) {
                    if ($item['VALUE'] === $value) {
                        return $item['ID'];
                    }
                }
            }
        }

        // Si aucune donnée correspondante n'est trouvée, retournez null
        return null;
    }
    public function getIdValue(string $fieldCode, string $value): ?string
    {
        $bitrixWebhookUrl="";

        //$bitrixWebhookUrl ="";

        // Obtenez d'abord la liste des champs de Bitrix24
        $response = $this->httpClient->request('GET', $bitrixWebhookUrl . 'crm.deal.fields');

        if ($response->getStatusCode() !== 200) {
            return new JsonResponse(['error' => 'Unable to retrieve fields '], $response->getStatusCode());
        }

        $fields = $response->toArray();
        // Supposons que le champ personnalisé est un type d'élément de liste énumérée
        foreach ($fields['result'] as $fieldName => $field) {
            if ($fieldCode === $fieldName) {
                foreach ($field['items'] as $item) {

                    if ($item['ID'] === $value) {
                        return $item['VALUE'];
                    }
                }
            }
        }

        return null; // Retourne null si l'VALEUR de la ID n'est pas trouvé
    }


     
    function createShipmentJsonSwared($clientData,$deal) {
     
        $jsonData = [
            "ClientInfo" => [
                "Version" => "1.0",
                "Password" => "SHopy@2025",
                "UserName" => "mc.shopy.shop@gmail.com",
                "AccountPin"=> "848513",
                "AccountEntity"=> "TUN",
                "AccountNumber"=>"72092559",
                "AccountCountryCode"=> "TN"
            ],
            "LabelInfo"=> [
                "ReportID" => 9824,
                "ReportType" => "URL"
            ],
            "Shipments" => [
                [
                    "Reference1" => "",
                    "Reference2" => "",
                    "Reference3" => "",
                    "Shipper" => [
                        "Reference1" => "",
                        "Reference2" => "",
                        "AccountNumber" => "72092559",//par defaut
                        "PartyAddress" => [
                            "Line1" => "Avenue Kuwait",
                            "Line2" => '',
                            "Line3" => '',
                            "City" => "Nabeul",
                            "StateOrProvinceCode" => "8050",
                            "PostCode" => "8050",
                            "CountryCode" => "Tunisia",
                            "Longitude" => 0,
                            "Latitude" => 0,
                        ],
                        "Contact" => [
                            "Department" => "",
                            "PersonName" => "MC shopy",
                            "Title" => '',
                            "CompanyName" =>"Shopywall-sw",
                            "PhoneNumber1" => "99112219",
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => "99112219",
                            "EmailAddress" => 'Ms.shopy.shop@gmail.com',
                            "Type" => ""
                        ]
                    ],
                    "Consignee" => [
                        "Reference1" => "", //opt
                        "Reference2" => "",//opt
                        "AccountNumber" => "", //vide
                        "PartyAddress" => [
                            "Line1" => $deal['UF_CRM_1705750486330'],// adresse
                            "Line2" => "",
                            "Line3" => "",
                            "City" =>  $this->getIdValue('UF_CRM_1705750299634', $deal['UF_CRM_1705750299634']), //city
                            "StateOrProvinceCode" => $this->getIdValue('UF_CRM_1705750225691', $deal['UF_CRM_1705750225691']),//state
                            "PostCode" => " ",//PostCode
                            "CountryCode" => "Tunisia",
                            "Longitude" => 0,
                            "Latitude" => 0,
                        ],
                        "Contact" => [
                            "Department" =>"",
                            "PersonName" => $clientData['NAME'] . ' ' . $clientData['LAST_NAME'],//client name
                            "Title" => '',
                            "CompanyName" => $clientData['NAME'] . ' ' . $clientData['LAST_NAME'],//client name
                            "PhoneNumber1" => $clientData['PHONE'][0]['VALUE'],//client phone number
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => $clientData['PHONE'][0]['VALUE'],//client phone number
                            "EmailAddress" => "mail@email.com",
                            "Type" => ""
                        ]
                    ],
                    "ShippingDateTime" => $this->getCurrentDateFormat(),
                    "DueDate" => "/Date(1730332800000+0000)/",
                    "Comments" => " ",// add quantité
                    "PickupLocation" => "Reception",
                    "OperationsInstructions" => " ",
                    "AccountingInstrcutions" => " ",
                    "Details" => [
                        "Dimensions" => null,  // Dimensions of the package, null if not provided
                        "ActualWeight" => [
                            "Unit" => "KG",       // Unit of weight, in this case, kilograms
                            "Value" => 0.5        // Actual weight of the shipment
                        ],
                        "ChargeableWeight" => null,  // Chargeable weight, null if not provided
                        "DescriptionOfGoods" => $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']) .' QTE :'. $this->getIdValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974']),  // Product -- quantity
                        "GoodsOriginCountry" => "TN",     // Country of origin, TN for Tunisia
                        "NumberOfPieces" => 1, // Number of individual pieces in the shipment
                        "ProductGroup" => "DOM",          // Product group, "DOM" for domestic
                        "ProductType" => "ONP",           // Type of product, "ONP" (e.g., On-demand Pickup)
                        "PaymentType" => "P",             // Payment type
                        "PaymentOptions" => "",           // Additional payment options, empty if not applicable
                        "CustomsValueAmount" => null,     // Customs value amount, null if not provided !!!!! 
                        "CashOnDeliveryAmount" => [
                            "CurrencyCode" => "TND",      // Currency code, "TND" for Tunisian Dinar
                            "Value" => floatval($this->getIdValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237'])),// Amount to collect on delivery ----- product price
                        ],
                        "InsuranceAmount" => null,        // Insurance amount, null if not applicable
                        "CashAdditionalAmount" => null,   // Additional cash amount, null if not provided
                        "CashAdditionalAmountDescription" => "",  // Description of the additional amount, empty if none
                        "CollectAmount" => null,          // Amount to collect, null if not provided
                        "Services" => "CODS",             // Services associated with the shipment, "CODS" for Cash on Delivery
                        "Items" =>  [
                            "PackageType" =>  $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']),  
                            "Quantity" =>  $this->getIdValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974']),
                            "Comments" =>  $this->getIdValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974']),     // Unit of weight, in this case, kilograms
                            "Weight" => 0.5        // Actual weight of the shipment
                        ],
                    ],
                    "Attachments" => [],
                    "ForeignHAWB" =>"",
                    "TransportType" => 0,
                    "PickupGUID" => "",
                    "Number" => null,
                    "ScheduledDelivery" => null
                ]
            ],
            "Transaction" => [
                "Reference1" => "",
                "Reference2" => "",
                "Reference3" => "",
                "Reference4" => "",
                "Reference5" => ""
            ],
            "thirdParty" => [
                "Reference1" => "",
                "Reference2" => "",
                "AccountNumber" => "",
                "PartyAddress" => [
                    "Line1" => "",
                    "Line2" => "",
                    "Line3" => "",
                    "City" => "",
                    "StateOrProvinceCode" => "",
                    "PostCode" => "",
                    "CountryCode" => "",
                    "Longitude" => 0,
                    "Latitude" => 0,
                    "BuildingNumber" => null,
                    "BuildingName" => null,
                    "Floor" => null,
                    "Apartment" => null,
                    "POBox" => null,
                    "Description" => null
                ],
                "Contact" => [
                    "Department" => "",
                    "PersonName" => "",
                    "Title" => "",
                    "CompanyName" => "",
                    "PhoneNumber1" => "",
                    "PhoneNumber1Ext" => "",
                    "PhoneNumber2" => "",
                    "PhoneNumber2Ext" => "",
                    "FaxNumber" => "",
                    "CellPhone" => "",
                    "EmailAddress" => "",
                    "Type" => ""
                ]
            ]
           
        ];
       
        return $jsonData;
    }
    function createShipmentJson1() {
        $jsonData = [
            "ClientInfo" => [
                "Version" => "1.0",
                "Password" => "Imed@@20062006",
                "UserName" => "imedm@aramex.com",
                "AccountPin" => "548536",
                "AccountEntity" => "TUN",
                "AccountNumber" => "60519122",
                "AccountCountryCode" => "TN"
            ],
            "Shipments" => [
                [
                    "Reference1" => "",
                    "Reference2" => "",
                    "Reference3" => "",
                    "Shipper" => [
                        "Reference1" => "",
                        "Reference2" => "",
                        "AccountNumber" => "60519122",
                        "PartyAddress" => [
                            "Line1" => "Company ADRESS",
                            "Line2" => "",
                            "Line3" => "",
                            "City" => "tunis",
                            "StateOrProvinceCode" => "",
                            "PostCode" => "",
                            "CountryCode" => "TN",
                            "Longitude" => 0,
                            "Latitude" => 0,
                            "BuildingNumber" => null,
                            "BuildingName" => null,
                            "Floor" => null,
                            "Apartment" => null,
                            "POBox" => null,
                            "Description" => null
                        ],
                        "Contact" => [
                            "Department" => "",
                            "PersonName" => "COMPANY NAME",
                            "Title" => "",
                            "CompanyName" => "COMPANY NAME",
                            "PhoneNumber1" => "56565656565",
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => "5656565656",
                            "EmailAddress" => "company@test.com",
                            "Type" => ""
                        ]
                    ],
                    "Consignee" => [
                        "Reference1" => "",
                        "Reference2" => "",
                        "AccountNumber" => "",
                        "PartyAddress" => [
                            "Line1" => "CLIENT ADRESSE",
                            "Line2" => "",
                            "Line3" => "",
                            "City" => "tunis",
                            "StateOrProvinceCode" => "",
                            "PostCode" => "",
                            "CountryCode" => "TN",
                            "Longitude" => 0,
                            "Latitude" => 0,
                            "BuildingNumber" => "",
                            "BuildingName" => "",
                            "Floor" => "",
                            "Apartment" => "",
                            "POBox" => null,
                            "Description" => ""
                        ],
                        "Contact" => [
                            "Department" => "",
                            "PersonName" => "CLIENT NAME",
                            "Title" => "",
                            "CompanyName" => "CLIENT NAMECompanyName",
                            "PhoneNumber1" => "009625515111",
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => "123456789",
                            "EmailAddress" => "teste@teste.com",
                            "Type" => ""
                        ]
                    ],
                    "ThirdParty" => [
                        "Reference1" => "",
                        "Reference2" => "",
                        "AccountNumber" => "",
                        "PartyAddress" => [
                            "Line1" => "",
                            "Line2" => "",
                            "Line3" => "",
                            "City" => "",
                            "StateOrProvinceCode" => "",
                            "PostCode" => "",
                            "CountryCode" => "",
                            "Longitude" => 0,
                            "Latitude" => 0,
                            "BuildingNumber" => null,
                            "BuildingName" => null,
                            "Floor" => null,
                            "Apartment" => null,
                            "POBox" => null,
                            "Description" => null
                        ],
                        "Contact" => [
                            "Department" => "",
                            "PersonName" => "",
                            "Title" => "",
                            "CompanyName" => "",
                            "PhoneNumber1" => "",
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => "",
                            "EmailAddress" => "",
                            "Type" => ""
                        ]
                    ],
                    "ShippingDateTime" => "/Date(1730332800000+0000)/",
                    "DueDate" => "/Date(1730332800000+0000)/",
                    "Comments" => "",
                    "PickupLocation" => "",
                    "OperationsInstructions" => "",
                    "AccountingInstrcutions" => "",
                    "Details" => [
                        "Dimensions" => null,
                        "ActualWeight" => [
                            "Unit" => "KG",
                            "Value" => 0.5
                        ],
                        "ChargeableWeight" => null,
                        "DescriptionOfGoods" => "FOOD",
                        "GoodsOriginCountry" => "TN",
                        "NumberOfPieces" => 1,
                        "ProductGroup" => "DOM",
                        "ProductType" => "ONP",
                        "PaymentType" => "P",
                        "PaymentOptions" => "",
                        "CustomsValueAmount" => null,
                        "CashOnDeliveryAmount" => [
                            "CurrencyCode" => "TND",
                            "Value" => 7700
                        ],
                        "InsuranceAmount" => null,
                        "CashAdditionalAmount" => null,
                        "CashAdditionalAmountDescription" => "",
                        "CollectAmount" => null,
                        "Services" => "CODS",
                        "Items" =>  [
                            "PackageType" =>  "PRODUCT NAME",  
                            "Quantity" =>  10,
                            "Comments" => " ",     // Unit of weight, in this case, kilograms
                            "Weight" => 0.5        // Actual weight of the shipment
                        ],
                    ],
                    "Attachments" => [],
                    "ForeignHAWB" => "",
                    "TransportType " => 0,
                    "PickupGUID" => "",
                    "Number" => null,
                    "ScheduledDelivery" => null
                ]
            ],
            "Transaction" => [
                "Reference1" => "",
                "Reference2" => "",
                "Reference3" => "",
                "Reference4" => "",
                "Reference5" => ""
            ],
            "LabelInfo" => [
                "ReportID" => 9824,
                "ReportType" => "URL"
            ]
        ];
   
        return $jsonData;
    }
  
    function createShipmentJsonShopy($accountNumber,$accountPin,$companyName,$clientData,$deal) {
        $jsonData = [
            "ClientInfo" => [
                "Version" => "1.0",
                "Password" => "SHopy@2025",
                "UserName" => "mc.shopy.shop@gmail.com",
                "AccountPin" => $accountPin ,//"590751",
                "AccountEntity" => "TUN",
                "AccountNumber" => $accountNumber, //"72092557",
                "AccountCountryCode" => "TN"
            ],
            "Shipments" => [
                [
                    "Reference1" => "",
                    "Reference2" => "",
                    "Reference3" => "",
                    "Shipper" => [
                        "Reference1" => "",
                        "Reference2" => "",
                        "AccountNumber" => $accountNumber ,//"60519122",
                        "PartyAddress" => [
                            "Line1" => "Rue Hammadi Ben Ammar, Dar Chaabane El Fehri, Tunisia",
                            "Line2" => "",
                            "Line3" => "",
                            "City" => "tunis",
                            "StateOrProvinceCode" => "",
                            "PostCode" => "",
                            "CountryCode" => "TN",
                            "Longitude" => 0,
                            "Latitude" => 0,
                            "BuildingNumber" => null,
                            "BuildingName" => null,
                            "Floor" => null,
                            "Apartment" => null,
                            "POBox" => null,
                            "Description" => null
                        ],
                        "Contact" => [
                            "Department" => "",
                            "PersonName" => "COMPANY NAME",
                            "Title" => "",
                            "CompanyName" => "COMPANY NAME",
                            "PhoneNumber1" => "56565656565",
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => "5656565656",
                            "EmailAddress" => "company@test.com",
                            "Type" => ""
                        ]
                    ],
                    "Consignee" => [
                        "Reference1" => "",
                        "Reference2" => "",
                        "AccountNumber" => "",
                        "PartyAddress" => [
                            "Line1" =>$deal['UF_CRM_1705750486330'] , // "CLIENT ADRESSE" 
                            "Line2" => "",
                            "Line3" => "",
                            "City" => $this->getIdValue('UF_CRM_1705750299634', $deal['UF_CRM_1705750299634']) .' '. $this->getIdValue('UF_CRM_1705750225691', $deal['UF_CRM_1705750225691']),// "CLIENT city region" 
                            "StateOrProvinceCode" => "",
                            "PostCode" => "",
                            "CountryCode" => "TN",
                            "Longitude" => 0,
                            "Latitude" => 0,
                            "BuildingNumber" => "",
                            "BuildingName" => "",
                            "Floor" => "",
                            "Apartment" => "",
                            "POBox" => null,
                            "Description" => ""
                        ],
                        "Contact" => [
                            "Department" => "",
                            "PersonName" => $clientData['NAME'] . ' ' . $clientData['LAST_NAME'],// "CLIENT NAME"
                            "Title" => "",
                            "CompanyName" =>$clientData['NAME'] . ' ' . $clientData['LAST_NAME'], //"CLIENT NAMECompanyName"
                            "PhoneNumber1" => $clientData['PHONE'][0]['VALUE'] ,// telephone 
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => " ",
                            "EmailAddress" => " ",
                            "Type" => ""
                        ]
                    ],
                    "ThirdParty" => [
                        "Reference1" => "",
                        "Reference2" => "",
                        "AccountNumber" => "",
                        "PartyAddress" => [
                            "Line1" => "",
                            "Line2" => "",
                            "Line3" => "",
                            "City" => "",
                            "StateOrProvinceCode" => "",
                            "PostCode" => "",
                            "CountryCode" => "",
                            "Longitude" => 0,
                            "Latitude" => 0,
                            "BuildingNumber" => null,
                            "BuildingName" => null,
                            "Floor" => null,
                            "Apartment" => null,
                            "POBox" => null,
                            "Description" => null
                        ],
                        "Contact" => [
                            "Department" => "",
                            "PersonName" => "",
                            "Title" => "",
                            "CompanyName" => "",
                            "PhoneNumber1" => "",
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => "",
                            "EmailAddress" => "",
                            "Type" => ""
                        ]
                    ],
                    "ShippingDateTime" => "/Date(1730332800000+0000)/",
                    "DueDate" => "/Date(1730332800000+0000)/",
                    "Comments" => "",
                    "PickupLocation" => "",
                    "OperationsInstructions" => "",
                    "AccountingInstrcutions" => "",
                    "Details" => [
                        "Dimensions" => null,
                        "ActualWeight" => [
                            "Unit" => "KG",
                            "Value" => 0.5
                        ],
                        "ChargeableWeight" => null,
                        "DescriptionOfGoods" => "FOOD",
                        "GoodsOriginCountry" => "TN",
                        "NumberOfPieces" => 1,
                        "ProductGroup" => "DOM",
                        "ProductType" => "ONP",
                        "PaymentType" => "P",
                        "PaymentOptions" => "",
                        "CustomsValueAmount" => null,
                        "CashOnDeliveryAmount" => [
                            "CurrencyCode" => "TND",
                            "Value" => floatval($this->getIdValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237']))
                        ],
                        "InsuranceAmount" => null,
                        "CashAdditionalAmount" => null,
                        "CashAdditionalAmountDescription" => "",
                        "CollectAmount" => null,
                        "Services" => "CODS",
                        "Items" =>  [
                            "PackageType" =>  $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']),//product Name 
                            "Quantity" =>  intval($this->getIdValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974'])), 
                            "Comments" => " ",     // Unit of weight, in this case, kilograms
                            "Weight" => 0.5        // Actual weight of the shipment
                        ],
                    ],
                    "Attachments" => [],
                    "ForeignHAWB" => "",
                    "TransportType " => 0,
                    "PickupGUID" => "",
                    "Number" => null,
                    "ScheduledDelivery" => null
                ]
            ],
            "Transaction" => [
                "Reference1" => "",
                "Reference2" => "",
                "Reference3" => "",
                "Reference4" => "",
                "Reference5" => ""
            ],
            "LabelInfo" => [
                "ReportID" => 9824,
                "ReportType" => "URL"
            ]
        ];
   
        return $jsonData;
    }
    function getCurrentDateFormat() {
        // Obtenir l'heure actuelle en millisecondes depuis le début de l'Unix epoch
        $milliseconds = round(microtime(true) * 1000);
        
        // Retourner la date formatée
        return "/Date(" . $milliseconds . "+0000)/";
    }
    function sendShipmentRequestAramex($clientData,$deal) {

        $url = "https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CreateShipments";
        //$jsonData = $this->createShipmentJson1();
        
        $jsonData = $this->createShipmentJsonSwared($clientData,$deal);
        try {
             $this->logSucces('sendShipmentRequestAramex $response ', $jsonData);
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => $jsonData
            ]);
            
           

            if ($response->getStatusCode() === 200) {
                // Décoder la réponse JSON
                $content = $response->getContent();
                $decodedJson = json_decode($content, true);
                $errors = " ";
                // Vérifier que le JSON est bien décodé
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Extraire `ID` et `LabelURL` du JSON
                    if (isset($decodedJson['Shipments'][0]['HasErrors']) && $decodedJson['Shipments'][0]['HasErrors'] === true) {
                        // Extract error notifications
                        if (isset($decodedJson['Shipments'][0]['Notifications'])) {
                            foreach ($decodedJson['Shipments'][0]['Notifications'] as $notification) {

                                if (isset($notification['Code']) && isset($notification['Message'])) {
                                    $errors .= $notification['Message'];
                                }
                            }
                        }
                        return ['error' => $errors];
                    } elseif (isset($decodedJson['Shipments'][0]['ID']) &&
                        isset($decodedJson['Shipments'][0]['ShipmentLabel']['LabelURL'])) {
                        // Extraire ID et LabelURL du JSON
                        return [
                            'ID' => $decodedJson['Shipments'][0]['ID'],
                            'LabelURL' => $decodedJson['Shipments'][0]['ShipmentLabel']['LabelURL']
                        ];
                    } else {
                        return ['error' => 'ID or LabelURL not found in the response'];
                    }
                } else {
                    return ['error' => 'Failed to decode JSON response'];
                }
            } else {
                // En cas de réponse non 200, renvoie le contenu complet comme erreur
                return ['error' => $response->getContent(false)];
            }
        } catch (\Exception $e) {
            // Retourne le message d'exception en cas d'erreur
            return ['error' => $e->getMessage()];
        }
    }


    public function sendShipmentAramex($deals,$contacts){
        $errors = [];
        $successes = [];

        if (empty($deals)) {
            $errors[] = ['error' => 'No deals provided'];
            return ['successes' => $successes, 'errors' => $errors];
        }

        foreach ($deals as $deal) {
            if (empty($deal['CONTACT_ID']) || !isset($contacts[$deal['CONTACT_ID']])) {
                $errors[] = ['deal_ID' => $deal['ID'], 'error' => 'Contact details not found'];
                continue;
            }

            $clientData = $contacts[$deal['CONTACT_ID']];
            try {
                
                // Call the Aramex API for shipment creation
                $shipmentResponse = $this->sendShipmentRequestAramex( $clientData, $deal);
                var_dump( "shipmentResponse");

                var_dump( $shipmentResponse);
                if (isset($shipmentResponse['error'])) {
                    $errors[] = ['order_ID' => $deal['ID'], 'error' => $shipmentResponse['error']];
                    continue;
                }
    
                $successes[] = [
                    'order_ID' => $deal['ID'],
                    'offer' => $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']),
                    'quantity' => $this->getIdValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974']),
                    'shipment_ID' => $shipmentResponse['ID'],
                    'LabelURL' => $shipmentResponse['LabelURL'],
                    'message' => 'Shipment created successfully'
                ];
                $batchUpdates[] = [
                    'trackNumber' => $shipmentResponse['ID'],
                    'ID' => $deal['ID'],
                ];                       
                    
                // Prepare SMS variables
                $price = floatval($this->getIdValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237']));
                $offerName = $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']);
                $phoneNumber = $clientData['PHONE'][0]['VALUE'] ?? '';
                $name = $clientData['NAME'];
                // Send the SMS and handle the response
                try {
                    $smsResponse = $this->smsService->sendSmsLivraison($name, $offerName, $phoneNumber, $price);
                    $successes[] = ['order_ID' => $deal['ID'], 'sms_message_id' => $smsResponse, 'message' => 'SMS sent successfully'];
                    $this->logSucces('Send message', $successes);

                } catch (\Exception $e) {
                    $errors[] = ['order_ID' => $deal['ID'], 'error' => 'SMS sending failed: ' . $e->getMessage()];
                    $this->logError('SMS sending failed:', $e->getMessage());


                } 
               
            } catch (\Exception $e) {
                $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Unexpected error: ' . $e->getMessage()];
            }

        } 
        
        // Update the deal stage to confirm shipment
        // Process batch updates for deal stages

        if (!empty($batchUpdates)) {
            $batchUpdateResults = $this->updateDealStageInBatch($batchUpdates);

            // Process batch update results
           // Process batch update results
            foreach ($batchUpdateResults as $result) {
                if ($result['success']) {
                    $successes[] = [
                        'order_ID' => $result['deal_ID'],
                        'message' => 'Deal stage / tracknumber updated ',
                    ];
                } else {
                    $errors[] = [
                        'order_ID' => $result['deal_ID'],
                        'error' => 'Failed to update deal' .' '. $result['error'],
                    ];
                }
            }

            $this->logSucces('batchUpdateResults', $batchUpdateResults);
        }

        $dealsResult = [
            'successes' => $successes,
            'errors' => $errors,
        ];

        $this->logSucces('Final deals result !!!!!', $dealsResult);   
        // Préparer la data pour Envoyer un email avec les détails de la commande
       $this->prepareDataForMail($dealsResult);
    }

    public function prepareDataForMail($data){
        
        // Initialisation des variables
        $totalColis = 0;
        $details = [];
        $labels = [];
        $offersTotals = [];
        $errors = [];
        $labelDetails = [];
        // Parcourir les succès pour organiser les données
        foreach ($data['successes'] as $success) {
            if (isset($success['offer'], $success['quantity'], $success['shipment_ID'])) {
                $details[] = [
                    'offer' => $success['offer'],
                    'quantity' => (int)$success['quantity'],
                    'shipment_ID' => $success['shipment_ID'],
                    'order_id' => $success['order_ID']
                ];
                $totalColis += (int)$success['quantity'];
                $offer = $success['offer'];
                if (!isset($offersTotals[$offer])) {
                    $offersTotals[$offer] = 0;
                }
                $offersTotals[$offer] += (int)$success['quantity'];
            }
            if (isset($success['LabelURL'])) {
                // Décoder et nettoyer l'URL
                $cleanLabelUrl =$success['LabelURL'] = stripslashes(str_replace("=>", ":", $success['LabelURL']));
                $labelDetails []= [      
                    'labelLink' =>$cleanLabelUrl,
                    'order_id' => $success['order_ID']
                ];
                $labels[] = $cleanLabelUrl;
            }
        }
       
        // Parcourir les erreurs
        foreach ($data['errors'] as $error) {
            $errors[] = [
                'order_ID' => $error['order_ID'],
                'error_message' => $error['error']
            ];
        }

        // Générer le fichier PDF combiné
        $mergedFileUrl = $this->generateMergedPdf($labels);

        // Créer le contenu de l'email
        $emailContent = "<h1>Rapport d'envoi</h1>";
        $emailContent .= "<p><strong>Total colis envoyés :</strong> $totalColis</p>";

        $emailContent .= "<h2>Nombre de colis par offre :</h2>";
        foreach ($offersTotals as $offer => $total) {
            $emailContent .= "<p>- <strong>$offer :</strong> $total</p>";
        }

        $emailContent .= "<h2>Détails des colis :</h2>";
        foreach ($details as $detail) {
            $emailContent .= "<p>- <strong>Order :</strong> {$detail['order_id']},<strong>Offer :</strong> {$detail['offer']}, <strong>Quantity :</strong> {$detail['quantity']}, <strong>Shipment ID :</strong> {$detail['shipment_ID']}</p>";
        }

        $emailContent .= "<h2>Labels individuels :</h2>";
        foreach ($labelDetails as $label) {
            if (!is_array($label)) {
                throw new \Exception("Unexpected data type in labelDetails: " . gettype($label));
            }
            $emailContent .= "<p>- <strong>Order :</strong> {$label['order_id']} <a href=\"{$label['labelLink']}\">Télécharger le label</a></p>";
        }
        $apiUrl = $this->params->get('app_url');
        $emailContent .= "<h2>Lien vers le fichier combiné :</h2>";
        $emailContent .= "<p>- <a href=\"{$apiUrl}/download/{$mergedFileUrl}\">Télécharger tous les labels combinés</a></p>";

        // Ajouter les erreurs
        $emailContent .= "<h2>Erreurs :</h2>";
        if (empty($errors)) {
            $emailContent .= "<p>Aucune erreur détectée.</p>";
        } else {
            foreach ($errors as $error) {
                $emailContent .= "<p>- <strong>Order ID :</strong> {$error['order_ID']}, <strong>Error :</strong> {$error['error_message']}</p>";
            }
        }
        $this->logSucces('mail content', $emailContent);

        $this->mailService->sendEmailSendDealsToAramex($emailContent);
    }


    

    private function generateMergedPdf(array $labelUrls)
    {
        return $this->pdfMergerService->mergePdfs($labelUrls);
    }
}