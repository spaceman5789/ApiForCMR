<?php

namespace App\Service;

use App\Repository\OfferRepository;
use App\Service\ContactService;

use DateInterval;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\UserOfferPayoutRepository;
use App\Entity\Offer;
use App\Entity\User;
use App\Entity\DealTn;
use App\Repository\UserRepository;
use App\Service\SmsService;
use App\Repository\DealRepository;
use App\Repository\DealAnRepository;
use App\Repository\DealTnRepository;
use App\Repository\DealSnRepository;

use App\Repository\DealFieldsBRepository;

class DealService
{
    private string $bitrixWebhookUrl;
    private $offerRepository;
    private $contactService;
    private $httpClient;
    private $userOfferPayoutRepository;
    private $userRepository;
    private $smsService;
    private $dealRepository;
    private $dealFieldsBRepository;
    private $dealAnRepository;
    private $dealTnRepository;
    private $dealTSnRepository;

    public function __construct(
        HttpClientInterface $httpClient,
        OfferRepository $offerRepository,
        ContactService $contactService,
        UserOfferPayoutRepository $userOfferPayoutRepository,
        UserRepository $userRepository,
        SmsService $smsService,
        DealRepository $dealRepository,
        DealAnRepository $dealAnRepository,
        DealTnRepository $dealTnRepository,
        DealSnRepository $dealSnRepository,
        DealFieldsBRepository $dealFieldsBRepository

    ) {
        $this->httpClient = $httpClient;
        $this->bitrixWebhookUrl = '';
        $this->offerRepository = $offerRepository;
        $this->contactService = $contactService;
        $this->userOfferPayoutRepository = $userOfferPayoutRepository;
        $this->userRepository = $userRepository;
        $this->smsService = $smsService;
        $this->dealRepository = $dealRepository;
        $this->dealAnRepository = $dealAnRepository;
        $this->dealTnRepository = $dealTnRepository;
        $this->dealSnRepository = $dealSnRepository;

        $this->dealFieldsBRepository = $dealFieldsBRepository;
    }

    public function getTotalExistingDealsBy(string $idContact, string $idOffer)
    {
        $response = $this->httpClient->request('POST', '' . 'crm.deal.list', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'filter' => ['CONTACT_ID' => $idContact, '=UF_CRM_1707164894196' => $idOffer],
                'select' => ['ID', 'STAGE_ID', 'CONTACT_ID', 'UF_CRM_1707164894196', 'DATE_CREATE'],
            ],
        ]);

        return $response->toArray()['total'];
    }
    public function getTotalExistingDealsByAN(string $idContact, string $idOffer)
    {

        $response = $this->httpClient->request('POST', '' . '/crm.deal.list', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'filter' => ['CONTACT_ID' => $idContact, 'UF_CRM_1711153213235' => $idOffer],
                'select' => ['ID', 'STAGE_ID', 'CONTACT_ID', 'UF_CRM_1711153213235', 'DATE_CREATE'],
            ],
        ]);

        return $response->toArray()['total'];
    }
    private function getDateFilters(?string $dateStart, ?string $dateEnd): array
    {
        $dateFilters = [];
        if ($dateStart !== null && $dateEnd == null) {
            $dateFilters['>=DATE_CREATE'] = $dateStart . ' 00:00:00';
        }

        if ($dateEnd !== null && $dateStart == null) {
            $dateFilters['<=DATE_CREATE'] = $dateEnd . ' 23:59:59';
        }

        if ($dateStart !== null && $dateEnd !== null) {
            $dateFilters['>=DATE_CREATE'] = $dateStart . ' 00:00:00';
            $dateFilters['<=DATE_CREATE'] = $dateEnd . ' 23:59:59';
        }

        return $dateFilters;
    }
    private function getDealsFromApi($baseUrl, $filter, $selectFields): array
    {
        // Perform the API request
        $response = $this->httpClient->request('POST', $baseUrl . 'crm.deal.list', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'filter' => $filter,
                'order' => ['ID' => 'ASC'],
                'select' => $selectFields
            ],
        ]);

        // Parse and return the response
        return $response->toArray()['result'] ?? [];
    }

    private function getPaginatedData($baseUrl, $filter, $selectFields): array
    {
        $result = [];
        $lastDealId = 0;

        while (true) {
            $response = $this->getDealsFromApiPagination($baseUrl, $filter, $selectFields, $lastDealId);
            if (empty($response)) {
                break;
            }

            $result = array_merge($result, $response);
            if (count($response) < 50) {
                break; // No more data to fetch
            }
            $lastDealId = end($response)['ID'];
        }

        return $result;
    }

    private function getDealsFromApiPagination($baseUrl, $filter, $selectFields, $lastDealId = 0): array
    {
        // Perform the API request
        $response = $this->httpClient->request('POST', $baseUrl . 'crm.deal.list', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'filter' => array_merge($filter, ['>ID' => $lastDealId]),
                'order' => ['ID' => 'ASC'],
                'select' => $selectFields,
                'start' => -1 // Disable count query
            ]
        ]);

        // Parse and return the response
        $data = $response->toArray();
        return $data['result'] ?? [];
    }

    public function getAllDeals(
        ?int $page = null,
        ?int $pageSize = null,
        ?string $dateStart = null,
        ?string $dateEnd = null,
        string $userField = null,
        ?array $offersId = null,
        ?array $leadsId = null
    ): array {

        // Maroc/Tunis filter
        $filterMT['=UF_CRM_1707162226734'] = $userField;
        $filterMT['@CATEGORY_ID'] = [0, 6, 17, 21, 25, 29];

        // Angola filter
        $filterAN['CATEGORY_ID'] = [0];
        $filterAN['=UF_CRM_1711153543876'] = $userField;

        if ($offersId !== null) {
            $filterMT['UF_CRM_1707164894196'] = $offersId; // Maroc/Tunis
            //  $filterAN['UF_CRM_1711153213235'] = $offersId; // Angola
        }
        if ($leadsId !== null) {
            $filterMT['=UF_CRM_1707163372336'] = $leadsId;
            //  $filterAN['=UF_CRM_1711153623306'] = $leadsId;
        }

        // Common date filters
        $commonDateFilters = $this->getDateFilters($dateStart, $dateEnd);
        $filterMT = array_merge($filterMT, $commonDateFilters);
        // $filterAN = array_merge($filterAN, $commonDateFilters);

        // Define select fields for Maroc/Tunis/Kenya
        $selectFieldsMT = ['ID', 'DATE_CREATE', 'UF_CRM_1707162226734', 'STAGE_ID', 'UF_CRM_1707163372336', 'UF_CRM_1707164894196', 'UF_CRM_1731502147567'];

        // Define select fields for Angola
        // $selectFieldsAN = ['ID', 'DATE_CREATE', 'UF_CRM_1711153543876', 'STAGE_ID', 'UF_CRM_1711153213235', 'UF_CRM_1711153623306','UF_CRM_1731571888854'];

        // Retrieve data with pagination
        $allDataMT = $this->getPaginatedData("", $filterMT, $selectFieldsMT);
        //$allDataMT = $this->getPaginatedData("", $filterMT, $selectFieldsMT);
        // $allDataAN = $this->getPaginatedData("", $filterAN, $selectFieldsAN);

        // Normalize and merge data
        $filteredDealsMT = $this->normalizeDealData($allDataMT, 'MT');
        // $filteredDealsAN = $this->normalizeDealData($allDataAN, 'AN');
        $allData = $filteredDealsMT;

        // Total items before pagination
        $totalItems = count($allData);

        // Pagination only if both page and pageSize are specified
        $applyPagination = $page !== null && $pageSize !== null;

        // Apply pagination if requested
        if ($applyPagination) {
            $pageStart = ($page - 1) * $pageSize;
            $allData = array_slice($allData, $pageStart, min($pageSize, $totalItems - $pageStart));
            // Return paginated data and additional information
            return [
                'data' => $allData,
                'totalItems' => $totalItems,
                'page' => $page,
                'pageSize' => $pageSize
            ];
        }

        // Return all data and total items
        return [
            'data' => $allData,
            'totalItems' => $totalItems
        ];
    }
    // This function can now handle data from both MT and AN, normalizing the fields.
    public function normalizeDealData($deals, $country)
    {
        $normalizedDeals = [];
        $fieldMappings = [
            'MT' => [
                'Offer_ID' => 'UF_CRM_1707164894196',
                'Lead_ID' => 'UF_CRM_1707163372336',
                'Sub_ID' => 'UF_CRM_1731502147567',

            ],
            // 'AN' => [
            //     'Offer_ID' => 'UF_CRM_1711153213235',
            //     'Lead_ID' => 'UF_CRM_1711153623306',
            //     'Sub_ID' => 'UF_CRM_1731571888854',
            // ],
        ];
        // Récupérer les noms des statuts une seule fois, pour optimiser les performances
        $statusNames = $this->getStatusNames();
        foreach ($deals as $deal) {


            $stageId = $deal['STAGE_ID'];
            $statusName = $statusNames[$stageId] ?? 'Unknown';

            if ($statusName == 'Unknown') {
                file_put_contents('../var/log/error_bitrix_status_unknown_' . date('Y-m-d') . '.log', json_encode($deal) . PHP_EOL, FILE_APPEND);
            }

            $offerField = $fieldMappings[$country]['Offer_ID'];
            $leadField = $fieldMappings[$country]['Lead_ID'];
            $subIDField =  $fieldMappings[$country]['Sub_ID'];

            // Check the creation date to determine if Sub ID or ID should be used
            $createdAt = $deal['DATE_CREATE'] ?? null;
            $createdDate = new \DateTime($createdAt);
            $cutoffDate = new \DateTime('2024-11-16');

            $normalizedDeals[] = [
                'order_ID' => $createdDate >= $cutoffDate ? ($deal[$subIDField] ?? null) : $deal['ID'],
                'Offer_ID' => $deal[$offerField] ?? null,
                'Lead_ID' => $deal[$leadField] ?? null,
                'Created_at' => $deal['DATE_CREATE'] ?? null,
                'Status' => $statusName,
            ];
        }

        return $normalizedDeals;
    }
    public function getStatusNames(): array
    {
        return [
            'NEW' => 'New lead',
            'C6:NEW' => 'New lead', // Tunisie 
            'C17:NEW' => 'New lead', //ke
            'C21:NEW' => 'New lead', //LY
            'C25:NEW' => 'New lead', //SN
            'C29:NEW' => 'New lead', //AN


            'PREPARATION' => 'Processing', //MA
            'C6:PREPARATION' => 'Processing', //TN
            'C17:PREPARATION' => 'Processing', //ke
            'C21:PREPARATION' => 'Processing', //LY
            'C25:PREPARATION' => 'Processing', //SN
            'C29:PREPARATION' => 'Processing', //AN

            'C6:UC_00EJVW' => 'Processing', // call back TN
            'C17:PREPAYMENT_INVOIC' => 'Processing', // call back KENYA
            //'UC_ROPKBH' => 'Processing', // CallBack AN
            'C21:PREPAYMENT_INVOIC' => 'Processing', // call back LY
            'C25:PREPAYMENT_INVOIC' => 'Processing', // call back SN
            'C29:PREPAYMENT_INVOIC' => 'Processing', // call back AN


            'C6:UC_9TTZDM' => 'Processing', // Verification TN
            //'UC_11IBWS' => 'Processing', // Verification AN
            'C17:EXECUTING' => 'Processing', // Verification KENYA
            'C21:EXECUTING' => 'Processing', // Verification LY
            'C25:EXECUTING' => 'Processing', // Verification SN
            'C29:EXECUTING' => 'Processing', // Verification AN


            'C6:UC_Q69K6V' => 'Processing', // Nom TN

            'LOSE' => 'Cancel', //AN
            'C6:LOSE' => 'Cancel', //TN
            'C17:LOSE' => 'Cancel', //KENYA
            'C21:LOSE' => 'Cancel', //LY
            'C25:LOSE' => 'Cancel', //SN
            'C29:LOSE' => 'Cancel', //AN


            'WON' => 'Approved', //MA
            'C6:WON' => 'Approved', //TN
            'C17:WON' => 'Approved', //KE
            'C21:WON' => 'Approved', //LY
            'C25:WON' => 'Approved', //SN
            'C29:WON' => 'Approved', //AN


            'PREPAYMENT_INVOICE' => 'Cancel', // Fusionner "Approvedtocg MA" avec "Cancel"
            'C6:EXECUTING' => 'Cancel', // Fusionner "Approvedtocg TN" avec "Cancel"
            'C17:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg KE" avec "Cancel"
            'C21:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg LY" avec "Cancel"
            'C25:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg SN" avec "Cancel"
            'C29:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg AN" avec "Cancel"


            'UC_GNK6HS' => 'SPAM', //MA SPAM
            'C6:UC_RS2SSM' => 'SPAM', //TN SPAM
            'C17:UC_X0677F' => 'SPAM', // KY
            'C21:1' => 'SPAM', // LY Spam
            'C21:UC_I2B1ZE' => 'SPAM', //LY SPAM
            'C25:1' => 'SPAM', // SN Spam
            'C29:UC_8ZODDQ' => 'SPAM', // AN Spam   
            'C29:1' => 'SPAM', // AN Spam   

            'C6:UC_KS3JD1' => 'SPAM', //TN BLACK LIST
            'C17:UC_QQ7EZA' => 'SPAM', //KY BLACK LIST
            'C21:4' => 'SPAM', // LY BLACK LIST
            'C21:UC_J3ETBK' => 'SPAM',//LY BLACK LIST
            'C25:4' => 'SPAM', // SN BLACK LIST
            'C29:UC_3SQYKW' => 'SPAM', // AN BLACK LIST
            'C29:3' => 'SPAM', // AN BLACK LIST


            'C6:UC_VFCGR5' => 'DOUBLE', // Double TN
            'UC_4Y0D8A' => 'DOUBLE', // Double MA
            'C17:UC_2446OA' => 'DOUBLE', // Double KE
            'C21:2' => 'DOUBLE', // Double LY
            'C25:2' => 'DOUBLE', // Double SN
            'C29:UC_6XBK1Y' => 'DOUBLE', // Double AN
            'C29:2' => 'DOUBLE', // Double AN 


            'UC_VTU4BP' => 'Approved', //// Fusionner "Approved" avec "Approve+ MA"
            'C6:UC_9GP25Z' => 'Approved', // Fusionner "Approved" avec "Approve+ TN"
            'C17:UC_23YD73' => 'Approved', // Fusionner "Approved" avec "KE Approve+"
            'C21: 3' => 'Approved', // Fusionner "Approved" avec "LY Approve+"
            'C21:3' => 'Approved', // Fusionner "Approved" avec "LY Approve+"
            'C25:3' => 'Approved', // Fusionner "Approved" avec "SN Approve+"
            'C29:UC_QJZYOG' => 'Approved' // Fusionner "Approved" avec "AN Approve+"


        ];
    }


    private function getValueId(string $fieldName, string $value, string $country): ?string
    {

        // Récupérer les données stockées en fonction du pays
        $storedData = $this->dealFieldsBRepository->findOneBy(['country' => $country]);

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

    public function addDeals(array $deals, string $partnerID): array
    {
        $successes = [];
        $errors = [];

        foreach ($deals as $dealData) {
            if (!isset($dealData['offer']) || !isset($dealData['webmasterID']) || !isset($dealData['leadID'])) {
                $errors[] = ['error' => 'Missing information'];
                continue;
            }

            $offerValue = $dealData['offer'];
            $webmasterId = $dealData['webmasterID'];
            $leadId = $dealData['leadID'];

            if (!isset($dealData['contact']) || !isset($dealData['contact']['telephone'])) {
                $errors[] = ['leadID' => $leadId, 'offer' => $offerValue, 'error' => 'Contact not found'];
                continue;
            }

            $contactData = $dealData['contact'];

            if (empty(trim($contactData["telephone"]))) {
                $errors[] = ['leadID' => $leadId, 'offer' => $offerValue, 'error' => 'Contact not found'];
                continue;
            }

            $offer = $this->offerRepository->findOneBy(['offerId' => $offerValue]);
            if (!$offer) {
                $errors[] = ['leadID' => $leadId, 'offer' => $offerValue, 'error' => 'Offer not found'];
                continue;
            }

            $offerName = $offer->getName();
            $offerCountry = $offer->getCountry();

            //Verifier si le deal est dupliqué (meme numero de telephone , meme offerID et dans un interval d'un mois )
            $hasRecentDeal = $this->dealRepository->hasRecentDeal($contactData["telephone"], $offerValue);
            // if ($hasRecentDeal) {
            //     $errors[] = ['leadID' => $leadId, 'offer' => $offerValue, 'error' => 'Duplicate deal'];
            //     continue;
            // }

            // Determine stageFieldId based on country and recent deal presence
            $stageFieldId = $this->determineStageFieldId($offerCountry, $hasRecentDeal);

            //`dealId` est unique pour chaque deal
            $dealId = str_replace('.', '', uniqid('', true));

            // Création du deal et enregistrement en BDD
            $newDeal = $this->dealRepository->createDeal($dealId, $leadId, $partnerID, $stageFieldId, $contactData["telephone"], $offerValue, $dealData);

            if ($newDeal === null) {
                $errors[] = ['order_ID' => $dealId, 'lead_ID' => $leadId, 'offer_ID' => $offerValue, 'error' => 'Duplicate or error adding deal'];
            } else {
                // if($offerCountry == 'Angola'){
                //     // Création du deal et enregistrement en BDD
                //     $newDealAN = $this->dealAnRepository->createDealANBD($dealId, $leadId, $partnerID, $stageFieldId, $contactData["telephone"], $offerValue,$dealData);
                // }else{
                //     // Création du deal et enregistrement en BDD
                //     $newDealTN = $this->dealTnRepository->createDealTNBD($dealId, $leadId, $partnerID, $stageFieldId, $contactData["telephone"], $offerValue,$dealData);
                // }

                $successes[] = ['order_ID' => $dealId, 'lead_ID' => $leadId, 'offer_ID' => $offerValue, 'message' => 'Deal successfully added'];
            }
        }

        return [
            'successes' => $successes,
            'errors' => $errors,
        ];
    }

    private function determineStageFieldId(string $country, bool $hasRecentDeal): string
    {
        $stageFieldId = 'NEW';

        if ($hasRecentDeal) {
            return match ($country) {
                "Morocco" => "UC_4Y0D8A",
                "Tunisia" => "C6:UC_VFCGR5",
                "Kenya" => "C17:UC_2446OA",
                "Senegal" => "C25:2",
                "Libya" => "C21:2",
                "Angola" => "C29:UC_6XBK1Y",
                default => "NEW",
            };
        } else {
            return match ($country) {
                "Morocco" => "NEW",
                "Tunisia" => "C6:NEW",
                "Kenya" => "C17:NEW",
                "Senegal" => "C25:NEW",
                "Libya" => "C21:NEW",
                "Angola" => "C29:NEW",
                default => "NEW",
            };
        }
    }

    public function addDealsToBitrixTN(): array
    {
        $successes = [];
        $errors = [];
        $batchCommands = [];
        $batchSize = 36; // Bitrix batch limit for commands (deals and contacts)
        $dealIndexMap = []; // Pour mapper les commandes deal_add avec les deals
        $dealCount = 0;

        // Retrieve all unsent deals
        do {
            // Retrieve unsent deals from the database
            $unsentDeals = $this->dealRepository->findUnsentDealsTN(); // Each deal includes a contact command, a deal command, and an update command, so divide by 3

            foreach ($unsentDeals as $index => $deal) {
                $dealData = $deal->getJsonValue();

                $offerValue = $dealData['offer'];
                $webmasterId = $dealData['webmasterID'];
                $leadId = $dealData['leadID'];
                $contactData = $dealData['contact'] ?? [];

                $offer = $this->offerRepository->findOneBy(['offerId' => $offerValue]);
                if (!$offer) {
                    $errors[] = ['leadID' => $leadId, 'offer' => $offerValue, 'error' => 'Offer not found'];
                    continue;
                }

                // Determine country-specific parameters
                $offerName = $offer->getName();
                $offerCountry = $offer->getCountry();
                $hasRecentDeal = $this->dealRepository->hasRecentDeal($contactData["telephone"], $offerValue);

                // Define unique command keys for contact, deal, and update in the batch
                $contactCommandKey = "contact_add_{$dealCount}";
                $dealCommandKey = "deal_add_{$dealCount}";
                $dealUpdateCommandKey = "deal_update_{$dealCount}";
                // Initialize deal fields
                $dealFields = [];

                if ($offerCountry != "Angola") {
                    // Add deal creation command to the batch
                    $dealFields = $this->createDealMATN(
                        ['ID' => '$result[' . $contactCommandKey . ']'], // Passing reference to created contact ID
                        $offerValue,
                        $webmasterId,
                        $leadId,
                        $offerName,
                        $dealData,
                        $deal->getClientId(),
                        $offerCountry,
                        $deal->getStatus(),
                        $deal->getOrderId()
                    );

                    // Vérifiez si le pays est "Angola" et ignorez ce deal

                    // Map the deal ID to the command key for future reference
                    $dealIndexMap[$dealCommandKey] = $deal;
                    $dealCount++;

                    // Add contact creation command to the batch
                    $batchCommands[$contactCommandKey] = "crm.contact.add?" . http_build_query([
                        'fields' => [
                            'NAME' => $contactData['name'],
                            'PHONE' => [['VALUE' => $contactData['telephone'], 'VALUE_TYPE' => 'WORK']],
                        ],
                        'params' => ["REGISTER_SONET_EVENT" => "Y"]
                    ]);


                    // Add deal creation command to the batch using generated deal data
                    $batchCommands[$dealCommandKey] = "crm.deal.add?" . http_build_query($dealFields);

                    // Add deal update command to link the contact to the deal
                    $batchCommands[$dealUpdateCommandKey] = "crm.deal.update?" . http_build_query([
                        'id' => '$result[' . $dealCommandKey . ']',
                        'fields' => [
                            'CONTACT_ID' => '$result[' . $contactCommandKey . ']'
                        ]
                    ]);

                    // Check if batch size limit is reached, then execute the batch
                    if (count($batchCommands) >= $batchSize) {
                        $this->executeBatchTN($batchCommands, $successes, $errors, $dealIndexMap);
                        $batchCommands = []; // Reset batch commands
                        $dealIndexMap = []; // Reset the mapping for each batch execution

                    }
                }
            }

            // Execute any remaining commands in the final batch
            if (!empty($batchCommands)) {
                $this->executeBatchTN($batchCommands, $successes, $errors, $dealIndexMap);
                $batchCommands = [];
                $dealIndexMap = [];
            }
        } while (!empty($unsentDeals)); // Continue until there are no more unsent deals

        return [
            'successes' => $successes,
            'errors' => $errors,
        ];
    }

    /**
     * Executes a batch request and processes the response.
     */
    private function executeBatchTN(array $batchCommands, array &$successes, array &$errors, array $dealIndexMap)
    {
        try {
            // Send the batch request
            $response = $this->httpClient->request('POST', '', [
                'json' => [
                    'halt' => 0,
                    'cmd' => $batchCommands,
                ],
            ]);

            $responseData = $response->toArray();
            $dealsToMarkAsSent = [];

            // Process the results of the batch commands
            foreach ($batchCommands as $commandKey => $command) {
                if (isset($responseData['result']['result'][$commandKey])) {
                    $resultId = $responseData['result']['result'][$commandKey];
                    $successes[] = [
                        'command' => $commandKey,
                        'id' => $resultId,
                        'message' => 'Successfully processed',
                    ];

                    // Handle deal creation success
                    if (strpos($commandKey, 'deal_add') === 0 && isset($dealIndexMap[$commandKey])) {
                        $dealsToMarkAsSent[] = [
                            'deal' => $dealIndexMap[$commandKey],
                            'bitrixId' => $resultId,
                        ];
                    }
                } else {
                    // Handle errors
                    $errorDetail = $responseData['result']['result_error'][$commandKey] ?? ['error' => 'Unknown error'];
                    $errors[] = [
                        'command' => $commandKey,
                        'error' => $errorDetail['error'],
                        'error_description' => $errorDetail['error_description'] ?? 'No description available',
                    ];
                }
            }

            // Mark successfully processed deals as sent
            foreach ($dealsToMarkAsSent as $entry) {
                try {
                    $this->dealRepository->markDealAsSent($entry['deal'], $entry['bitrixId']);

                    // $this->dealTnRepository->markDealAsSent($entry['deal']->getLeadId(), $entry['bitrixId']);

                } catch (\Exception $e) {
                    // Log or track the failure to mark the deal as sent
                    $errors[] = [
                        'command' => 'deal_add',
                        'error' => 'Failed to mark deal as sent',
                        'description' => $e->getMessage(),
                    ];
                }
            }
        } catch (TransportExceptionInterface $e) {
            // Handle transport exceptions
            $errors[] = [
                'error' => $e->getMessage(),
            ];
        }
    }

    public function addDealsToBitrixIQ(): array
    {
        $successes = [];
        $errors = [];
        $batchCommands = [];
        $batchSize = 36; // Bitrix batch limit for commands (deals and contacts)
        $dealIndexMap = []; // Pour mapper les commandes deal_add avec les deals
        $dealCount = 0;

        // Retrieve all unsent deals
        do {
            // Retrieve unsent deals from the database
            $unsentDeals = $this->dealRepository->findUnsentDealsIQ(); // Each deal includes a contact command, a deal command, and an update command, so divide by 3

            foreach ($unsentDeals as $index => $deal) {
                $dealData = $deal->getJsonValue();

                $offerValue = $dealData['offer'];
                $webmasterId = $dealData['webmasterID'];
                $leadId = $dealData['leadID'];
                $contactData = $dealData['contact'] ?? [];

                $offer = $this->offerRepository->findOneBy(['offerId' => $offerValue]);

                if (!$offer) {
                    $errors[] = ['leadID' => $leadId, 'offer' => $offerValue, 'error' => 'Offer not found'];
                    continue;
                }

                // Determine country-specific parameters
                $offerName = $offer->getName();
                $offerCountry = $offer->getCountry();
                $hasRecentDeal = $this->dealRepository->hasRecentDeal($contactData["telephone"], $offerValue);

                // Define unique command keys for contact, deal, and update in the batch
                $contactCommandKey = "contact_add_{$dealCount}";
                $dealCommandKey = "deal_add_{$dealCount}";
                $dealUpdateCommandKey = "deal_update_{$dealCount}";
                // Initialize deal fields
                $dealFields = [];

                if ($offerCountry === "Iraq") {
                    // Add deal creation command to the batch
                    $dealFields = $this->createDealMATN(
                        ['ID' => '$result[' . $contactCommandKey . ']'], // Passing reference to created contact ID
                        $offerValue,
                        $webmasterId,
                        $leadId,
                        $offerName,
                        $dealData,
                        $deal->getClientId(),
                        $offerCountry,
                        $deal->getStatus(),
                        $deal->getOrderId()
                    );

                    // Map the deal ID to the command key for future reference
                    $dealIndexMap[$dealCommandKey] = $deal;
                    $dealCount++;

                    // Add contact creation command to the batch
                    $batchCommands[$contactCommandKey] = "crm.contact.add?" . http_build_query([
                        'fields' => [
                            'NAME' => $contactData['name'],
                            'PHONE' => [['VALUE' => $contactData['telephone'], 'VALUE_TYPE' => 'WORK']],
                        ],
                        'params' => ["REGISTER_SONET_EVENT" => "Y"]
                    ]);


                    // Add deal creation command to the batch using generated deal data
                    $batchCommands[$dealCommandKey] = "crm.deal.add?" . http_build_query($dealFields);

                    // Add deal update command to link the contact to the deal
                    $batchCommands[$dealUpdateCommandKey] = "crm.deal.update?" . http_build_query([
                        'id' => '$result[' . $dealCommandKey . ']',
                        'fields' => [
                            'CONTACT_ID' => '$result[' . $contactCommandKey . ']'
                        ]
                    ]);

                    // Check if batch size limit is reached, then execute the batch
                    if (count($batchCommands) >= $batchSize) {
                        $this->executeBatchTN($batchCommands, $successes, $errors, $dealIndexMap);
                        $batchCommands = []; // Reset batch commands
                        $dealIndexMap = []; // Reset the mapping for each batch execution

                    }
                }
            }

            // Execute any remaining commands in the final batch
            if (!empty($batchCommands)) {
                $this->executeBatchTN($batchCommands, $successes, $errors, $dealIndexMap);
                $batchCommands = [];
                $dealIndexMap = [];
            }
        } while (!empty($unsentDeals)); // Continue until there are no more unsent deals

        return [
            'successes' => $successes,
            'errors' => $errors,
        ];
    }

    /**
     * Executes a batch request and processes the response.
     */
    private function executeBatchAN(array $batchCommands, array &$successes, array &$errors, array $dealIndexMap)
    {
        try {
            $response = $this->httpClient->request('POST', '', [
                'json' => [
                    'halt' => 0,
                    'cmd' => $batchCommands
                ],
            ]);
            $responseData = $response->toArray();
            $dealsToMarkAsSent = [];

            // Process each command in the response
            foreach ($batchCommands as $commandKey => $command) {
                if (isset($responseData['result']['result'][$commandKey])) {
                    $resultId = $responseData['result']['result'][$commandKey];
                    $successes[] = ['command' => $commandKey, 'id' => $resultId, 'message' => 'Successfully processed'];
                    // Check if this is a deal creation command, and add 
                    if (strpos($commandKey, 'deal_add') === 0 && isset($dealIndexMap[$commandKey])) {
                        $dealsToMarkAsSent[] = [
                            'deal' => $dealIndexMap[$commandKey],
                            'bitrixId' => $resultId,
                        ];
                    }
                } else {
                    // Check if there's an error message for this command
                    $errorDetail = $responseData['result']['result_error'][$commandKey] ?? ['error' => 'Unknown error'];
                    $errors[] = [
                        'command' => $commandKey,
                        'error' => $errorDetail['error'],
                        'error_description' => $errorDetail['error_description'] ?? 'No description available'
                    ];
                }
            }
            // Mark successfully processed deals as sent
            foreach ($dealsToMarkAsSent as $entry) {
                try {
                    $this->dealRepository->markDealAsSent($entry['deal'], $entry['bitrixId']);

                    // $this->dealAnRepository->markDealAsSent($entry['deal']->getLeadId(), $entry['bitrixId']);

                } catch (\Exception $e) {
                    // Log or track the failure to mark the deal as sent
                    $errors[] = [
                        'command' => 'deal_add',
                        'error' => 'Failed to mark deal as sent',
                        'description' => $e->getMessage(),
                    ];
                }
            }
        } catch (TransportExceptionInterface $e) {
            // En cas d'erreur de transport
            $errors[] = [
                'error' => $e->getMessage()
            ];
        }
    }



    /**
     * Determines the country-specific fields based on the offer country and recent deal status.
     */
    private function determineCountrySpecificFields($country, $hasRecentDeal, $offerName): array
    {
        // Set default values for the stage and IDs
        $stageFieldId = 'NEW';
        $offerId = $this->getValueId('UF_CRM_1705749663304', $offerName, $country);
        $quantiteId = $this->getValueId('UF_CRM_1711152959510', 1, $country);
        if ($country === "Angola") {
            $stageFieldId = $hasRecentDeal ? "UC_K0GLPN" : "NEW";
        } else {
            if ($hasRecentDeal) {
                switch ($country) {
                    case "Morocco":
                        $stageFieldId = "UC_4Y0D8A";
                        $offerId = $this->getValueId('UF_CRM_1705749663304', $offerName, $country);
                        break;
                    case "Tunisia":
                        $stageFieldId = "C6:UC_VFCGR5";
                        $offerId = $this->getValueId('UF_CRM_1705749663304', $offerName, $country);
                        break;
                    case "Kenya":
                        $stageFieldId = "C17:UC_2446OA";
                        $offerId = $this->getValueId('UF_CRM_1715945258', $offerName, $country);
                        break;
                    case "Senegal":
                        $stageFieldId = "C25:2";
                        $offerId = $this->getValueId('UF_CRM_1726836101941', $offerName, $country);
                        break;
                    case "Libya":
                        $stageFieldId = "C21:2";
                        $offerId = $this->getValueId('UF_CRM_1726839636544', $offerName, $country);
                        break;
                    case "Iraq":
                        $stageFieldId = "C21:2";
                        $offerId = $this->getValueId('UF_CRM_1726839636544', $offerName, $country);
                        break;
                }
            }
        }

        return [$stageFieldId, $offerId, $quantiteId];
    }

    public function createDealAn($contactResult, $offerValue, $webmasterId, $leadId, $offerId, $dealData, $partnerID, $stageFieldId, $orderId)
    {
        $categoryFieldId = 0;
        $contactId = $contactResult['ID'];
        $fields = [
            'TITLE' => $dealData['contact']['name'],
            'STAGE_ID' => $stageFieldId,
            'OPENED' => 'Y',
            'UF_CRM_1711153213235' => $offerValue,
            'UF_CRM_1711152959510' => 1,
            'CATEGORY_ID' => $categoryFieldId,
            'UF_CRM_1711153543876' => $partnerID,
            'UF_CRM_1711153244751' => $webmasterId,
            'CONTACT_ID' => $contactId,
            'UF_CRM_1711153623306' => $leadId,
            'UF_CRM_1710232452937' => $this->getValueId('UF_CRM_1710232452937', $offerId, 'Angola'),
            'UF_CRM_1731571888854' => $orderId
        ];

        // Vérifiez si la clé existe dans le tableau $dealData
        if (array_key_exists('sub1', $dealData) && !empty($dealData['sub1'])) {
            $fields['UF_CRM_1726852312218'] = $dealData['sub1'];  // Sub1 field
        }
        if (array_key_exists('sub2', $dealData) && !empty($dealData['sub2'])) {
            $fields['UF_CRM_1726852335223'] = $dealData['sub2'];  // Sub2 field
        }
        if (array_key_exists('sub3', $dealData) && !empty($dealData['sub3'])) {
            $fields['UF_CRM_1726852357112'] = $dealData['sub3'];  // Sub3 field
        }
        if (array_key_exists('sub4', $dealData) && !empty($dealData['sub4'])) {
            $fields['UF_CRM_1726852365691'] = $dealData['sub4'];  // Sub4 field
        }
        if (array_key_exists('sub5', $dealData) && !empty($dealData['sub5'])) {
            $fields['UF_CRM_1726852380221'] = $dealData['sub5'];  // Sub5 field
        }
        if (array_key_exists('AdresseIP', $dealData) && !empty($dealData['AdresseIP'])) {
            $fields['UF_CRM_1726852403535'] = $dealData['AdresseIP'];  // IP address field
        }
        if (array_key_exists('userAgent', $dealData) && !empty($dealData['userAgent'])) {
            $fields['UF_CRM_1726852418089'] = $dealData['userAgent'];  // User agent field
        }
        return $dealData = [
            'fields' => $fields,
            'params' => ["REGISTER_SONET_EVENT" => "Y"]
        ];
    }

    public function createDealMATN($contactResult, $offerValue, $webmasterId, $leadId, $offerName, $dealData, $partnerID, $offerCountry, $stageFieldId, $orderId)
    {
        $offerFeildId = "UF_CRM_1705749663304";
        if ($offerCountry === "Morocco") {
            $categoryFieldId = 0;
        } elseif ($offerCountry === "Tunisia") {
            $categoryFieldId = 6;
            $offerFeildId = "UF_CRM_1705749663304";
        } elseif ($offerCountry === "Kenya") {
            $categoryFieldId = 17;
            $offerFeildId = "UF_CRM_1715945258";
        } elseif ($offerCountry === "Senegal") {
            $categoryFieldId = 25;
            $offerFeildId = "UF_CRM_1726836101941";
        } elseif ($offerCountry === "Libya") {
            $categoryFieldId = 21;
            $offerFeildId = "UF_CRM_1726839636544";
        } elseif ($offerCountry === "Iraq") {
            $categoryFieldId = 21;
            $offerFeildId = "UF_CRM_1726839636544";
        } elseif ($offerCountry === "Angola") {
            $categoryFieldId = 29;
            $offerFeildId = "UF_CRM_1734014728";
        }

        $contactId = $contactResult['ID'];

        $fields = [
            'TITLE' => $dealData['contact']['name'],
            'STAGE_ID' => $stageFieldId,
            'OPENED' => 'Y',
            'UF_CRM_1707164894196' => $offerValue,
            'UF_CRM_1705749715974' => 1,
            'CATEGORY_ID' => $categoryFieldId,
            'UF_CRM_1707162226734' => $partnerID,
            'UF_CRM_1707162327037' => $webmasterId,
            'CONTACT_ID' => $contactId,
            'UF_CRM_1707163372336' => $leadId,
            'UF_CRM_1731502147567' => $orderId,
            $offerFeildId => $this->getValueId($offerFeildId, $offerName, $offerCountry)
        ];

        // Vérifiez si la clé existe dans le tableau $dealData
        if (array_key_exists('sub1', $dealData) && !empty($dealData['sub1'])) {
            $fields['UF_CRM_1725742133013'] = $dealData['sub1'];  // Sub1 field
        }
        if (array_key_exists('sub2', $dealData) && !empty($dealData['sub2'])) {
            $fields['UF_CRM_1725742147493'] = $dealData['sub2'];  // Sub2 field
        }
        if (array_key_exists('sub3', $dealData) && !empty($dealData['sub3'])) {
            $fields['UF_CRM_1725742215564'] = $dealData['sub3'];  // Sub3 field
        }
        if (array_key_exists('sub4', $dealData) && !empty($dealData['sub4'])) {
            $fields['UF_CRM_1725742225050'] = $dealData['sub4'];  // Sub4 field
        }
        if (array_key_exists('sub5', $dealData) && !empty($dealData['sub5'])) {
            $fields['UF_CRM_1725742293109'] = $dealData['sub5'];  // Sub5 field
        }
        if (array_key_exists('AdresseIP', $dealData) && !empty($dealData['AdresseIP'])) {
            $fields['UF_CRM_1725742509286'] = $dealData['AdresseIP'];  // IP address field
        }
        if (array_key_exists('userAgent', $dealData) && !empty($dealData['userAgent'])) {
            $fields['UF_CRM_1725742687858'] = $dealData['userAgent'];  // User agent field
        }
        $dealData = [
            'fields' => $fields,
            'params' => ["REGISTER_SONET_EVENT" => "Y"]
        ];
        return $dealData;
    }




    public function getAllDealsDB(?int $page = null, ?int $pageSize = null, ?string $dateStart = null, ?string $dateEnd = null, string $userField = null, ?array $offersId = null, ?array $leadsId = null): array
    {
        $deals = $this->dealAnRepository->getAllDealsDB($page, $pageSize, $dateStart, $dateEnd, $userField, $offersId, $leadsId);
        $dealsFromDB = $deals['data'];

        // Identifier les `leadsId` et `offersId` manquants
        $missingLeadsId = [];
        $missingOffersId = [];
        if ($leadsId !== null) {
            $foundLeadsId = array_map(fn($deal) => $deal['Lead_ID'], $dealsFromDB);
            $missingLeadsId = array_diff($leadsId, $foundLeadsId);
        }
        if ($offersId !== null) {
            $foundOffersId = array_map(fn($deal) => $deal['Offer_ID'], $dealsFromDB);
            $missingOffersId = array_diff($offersId, $foundOffersId);
        }

        // Appeler Bitrix pour les critères manquants
        $dealsFromBitrix = [];
        if (!empty($missingLeadsId) || !empty($missingOffersId)) {
            $dealsFromBitrix = $this->getAllDeals($page, $pageSize, $dateStart, $dateEnd, $userField, $missingOffersId, $missingLeadsId);
            // Combiner les résultats BDD et Bitrix
            $dealsFromDB = array_merge($dealsFromDB, $dealsFromBitrix['data']);
        }

        // Combiner les résultats BDD et Bitrix
        $combinedDeals = $dealsFromDB;

        // Pagination sur les résultats combinés
        $totalCombinedItems = count($combinedDeals);
        if ($page !== null && $pageSize !== null) {
            $offset = ($page - 1) * $pageSize;
            $pagedDeals = array_slice($combinedDeals, $offset, $pageSize);

            return [

                'data' => $pagedDeals,
                'totalItems' => $totalCombinedItems,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => ceil($totalCombinedItems / $pageSize),

            ];
        }
        // Retour sans pagination
        return [

            'data' => $combinedDeals,
            'totalItems' => $totalCombinedItems,

        ];
    }


    public function getIdValue(string $fieldCode, string $value): ?string
    {
        $bitrixWebhookUrl = "";

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

    public function SendCommandFirstDelivery(): array
    {
        $errors[] = [];
        $successes[] = [];
        // Tunis filter
        $filters['CATEGORY_ID'] = [8];
        $filters['STAGE_ID'] = 'C8:NEW';
        //$filters ['ID']='45381';

        // Define select fields for Tunis /price TN enum/offer enum /Quantite enum /adresse /region TN enum /ville TN enum
        $selectFields = [
            'ID',
            'DATE_CREATE',
            'CONTACT_ID',
            'UF_CRM_1705750463237',
            'STAGE_ID',
            'CATEGORY_ID',
            'UF_CRM_1705749663304',
            'UF_CRM_1705749715974',
            'UF_CRM_1705750486330',
            'UF_CRM_1705750225691',
            'UF_CRM_1705750299634'
        ];

        // Make API call to retrieve deals

        $responseT = $this->getPaginatedData("", $filters, $selectFields, 145207, '');

        //$responseT = $this->getDealsFromApi("", $filters, $selectFields);
        if (empty($responseT)) {
            $errors[] = ['error' => 'Failed to fetch deals from API', 'details' => $responseT['error'] ?? 'No additional error info'];
        }

        $dealsFormatted = [];
        foreach ($responseT as $deal) {
            if ($deal['CONTACT_ID']) {
                $clientData = $this->getContactDetails($deal['CONTACT_ID']);
                $dealsFormatted = [
                    "Client" => [
                        "nom" => $clientData['NAME'] . ' ' . $clientData['LAST_NAME'],
                        "gouvernerat" => $this->getIdValue('UF_CRM_1705750225691', $deal['UF_CRM_1705750225691']),
                        "ville" => $this->getIdValue('UF_CRM_1705750299634', $deal['UF_CRM_1705750299634']),
                        "adresse" => $deal['UF_CRM_1705750486330'],
                        "telephone" => $this->contactService->removeAndReplaceCountryCode($clientData['PHONE'][0]['VALUE'])

                    ],
                    "Produit" => [
                        "prix" => floatval($this->getIdValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237'])),
                        "designation" =>  $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']),
                        "nombreArticle" => intval($this->getIdValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974'])),
                        "article" => $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304'])
                    ]
                ];
                $reponseFD = $this->AddCommandFirstDelivery($dealsFormatted);
                $reponseFD = json_decode($reponseFD, true);

                if ($reponseFD['isError']) {
                    $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Error sending deal to FD'];
                } else {
                    $successes[] = ['order_ID' => $deal['ID'], 'message' => 'Deal successfully sent to FD '];
                    $rep = $this->updateDealStage($deal['ID'], $reponseFD['result']['barCode']);

                    // Prepare SMS variables
                    $price = floatval($this->getIdValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237']));
                    $offerName = $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']);
                    $phoneNumber = $clientData['PHONE'][0]['VALUE'];
                    $name = $clientData['NAME'];

                    // Send the SMS and handle the response
                    try {
                        $smsResponse = $this->smsService->sendSmsLivraison($name, $offerName, $phoneNumber, $price);
                        $successes[] = ['order_ID' => $deal['ID'], 'sms_message_id' => $smsResponse, 'message' => 'SMS sent successfully'];
                    } catch (\Exception $e) {
                        $errors[] = ['order_ID' => $deal['ID'], 'error' => 'SMS sending failed: ' . $e->getMessage()];
                    }
                    if ($rep->getStatusCode() === 200) {
                        $successes[] = ['order_ID' => $deal['ID'], 'message' => 'Deal successfully updated'];
                    } else {
                        $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Error updating deal'];
                    }
                }
            }
        }
        // Chemin du fichier à la racine du projet Docker
        $filePath = '/var/www/file.txt'; // Assurez-vous que ce chemin correspond à l'emplacement du fichier dans le conteneur

        // Formatez le contenu à écrire
        $date = new \DateTime();
        $formattedDate = $date->format('Y-m-d H:i:s');
        $logContent = "Execution time: $formattedDate\n" . "Successes: " . json_encode($successes) . "\nErrors: " . json_encode($errors) . "\n";

        // Écrivez dans le fichier
        file_put_contents($filePath, $logContent, FILE_APPEND);
        return [
            'successes' => $successes,
            'errors' => $errors,
        ];
    }
    private function getContactDetails($contactId)
    {
        // fetching contact details
        $bitrixWebhookUrl = '' . $contactId;
        $response = $this->httpClient->request('GET', $bitrixWebhookUrl);
        $data = $response->toArray();
        return $data["result"];
    }

    private function AddCommandFirstDelivery($command)
    {
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

    //update to send on TN/confirm
    private function updateDealStage($dealID, $trackNumber)
    {
        $response = $this->httpClient->request('POST', '' . 'crm.deal.update', [

            'json' => [
                'id' => $dealID,
                'fields' => ['STAGE_ID' => 'C8:PREPARATION', 'UF_CRM_1719699229165' => $trackNumber]
            ]
        ]);
        return $response;
    }




    // fetch commande first delivery

    public function FetchCommandFirstDelivery(int $pageNumber, int $limit)
    {
        $createdAtTo = new \DateTime();
        //3 weeks back 
        $createdAtFrom = (clone $createdAtTo)->modify('-3 weeks');
        $createdAtFromString = $createdAtFrom->format('Y-m-d');
        $createdAtToString = $createdAtTo->format('Y-m-d');

        $response = $this->httpClient->request('POST', 'https://www.firstdeliverygroup.com/api/v2/' . 'filter', [
            'headers' => [
                'Authorization' => 'Bearer ' . '42f9befe-82dc-491b-ad1f-7fa0cf2ab971',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'json' => [
                'createdAtFrom' => $createdAtFromString,
                'createdAtTo' => $createdAtToString,
                'pagination' => [
                    'pageNumber' => $pageNumber,
                    'limit' => $limit,
                ],
            ],
        ]);
        if ($response->getStatusCode() === 429) {
            // En cas de 429, attendre et réessayer après 10 secondes
            sleep(10);
            return $this->FetchCommandFirstDelivery($pageNumber, $limit);
        }
        return $response->toArray();
    }

    public function fetchAllDeliveries(int $limit): array
    {
        $pageNumber = 1;
        $allItems = [];
        do {
            $response = $this->FetchCommandFirstDelivery($pageNumber, $limit);
            if (!isset($response['result']['Items'])) {
                break;
            }
            $allItems = array_merge($allItems, $response['result']['Items']);
            $pageNumber++;
            $totalPages = $response['result']['TotalPages'];
        } while ($pageNumber <= $totalPages);

        //return $allItems;
        return $this->extractBarCodeAndStateAndUpdateDeals($allItems);
    }
    //update deals on bitrix by extacting barCode and status from FD 
    private function extractBarCodeAndStateAndUpdateDeals(array $items): array
    {
        $result = [];
        $successCount = 0;
        foreach ($items as $item) {
            if (isset($item['barCode'], $item['state'])) {
                $barCode = $item['barCode'];
                $state = $item['state'];

                // Get the deal ID by track number (bar code)
                $dealID = $this->getDealByTrackNumber($barCode);
                if ($dealID) {
                    // Update the deal stage with the FD state
                    try {
                        $this->updateDealStageWithFD($dealID, $state);
                        $result[] = [
                            'barCode' => $barCode,
                            'state' => $state,
                            'dealID' => $dealID,
                            'status' => 'success'
                        ];
                        $successCount++;
                    } catch (Exception $e) {
                        $result[] = [
                            'barCode' => $barCode,
                            'state' => $state,
                            'dealID' => $dealID,
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ];
                    }
                } else {
                    $result[] = [
                        'barCode' => $barCode,
                        'state' => $state,
                        'dealID' => null,
                        'status' => 'error',
                        'message' => 'Deal not found'
                    ];
                }
            }
        }
        // Add the count of successful updates to the result
        $result['successCount'] = $successCount;
        return $result;
    }
    //update to deals with fd's stages
    private function updateDealStageWithFD($dealID, $fdStatus)
    {
        // Define the mapping between FD statuses and Bitrix statuses
        $statusMapping = [
            'En attente' => 'C8:PREPARATION',
            'Au dépot' => 'C8:PREPAYMENT_INVOICE',
            'En cours' => 'C8:PREPAYMENT_INVOICE',
            'Rtn dépot' => 'C8:PREPAYMENT_INVOICE',
            'Livrés' => 'C8:EXECUTING',
            'Livrés payés' => 'C8:UC_UXLCI4',
            'Retour definitif' => 'C8:FINAL_INVOICE',
            'Rtn client agence' => 'C8:FINAL_INVOICE',
            'Retour éxpediteur' => 'C8:UC_U2CLAA'
        ];

        // Get the corresponding Bitrix status
        $bitrixStatus = isset($statusMapping[$fdStatus]) ? $statusMapping[$fdStatus] : null;
        if ($bitrixStatus) {
            $response = $this->httpClient->request('POST', '', [
                'json' => [
                    'id' => $dealID,
                    'fields' => ['STAGE_ID' => $bitrixStatus]
                ]
            ]);
            return $response;
        } else {
            throw new Exception('Invalid FD status provided.');
        }
    }
    private function getDealByTrackNumber($trackNumber)
    {
        $filter['UF_CRM_1719699229165'] = $trackNumber;
        $selectField = ['ID'];
        $deal = $this->getDealsFromApi("", $filter, $selectField);
        // Check if the result is not empty and return the ID
        if (!empty($deal) && isset($deal[0]['ID'])) {
            return $deal[0]['ID'];
        }
        return null;
    }

    public function SendSpecifiqueCommandFirstDelivery($dealsId): array
    {
        $errors = [];
        $successes = [];

        $filters['CATEGORY_ID'] = [8];
        $filters['STAGE_ID'] = 'C8:NEW';
        $filters['@ID'] = $dealsId;

        $selectFields = [
            'ID',
            'DATE_CREATE',
            'CONTACT_ID',
            'UF_CRM_1705750463237',
            'STAGE_ID',
            'CATEGORY_ID',
            'UF_CRM_1705749663304',
            'UF_CRM_1705749715974',
            'UF_CRM_1705750486330',
            'UF_CRM_1705750225691',
            'UF_CRM_1705750299634'
        ];

        $responseT = $this->getPaginatedData("", $filters, $selectFields, 145207, '');

        if (empty($responseT)) {
            $errors[] = ['error' => 'Failed to fetch deals from API', 'details' => $responseT['error'] ?? 'No additional error info'];
        }

        $dealsFormatted = [];
        foreach ($responseT as $deal) {
            if ($deal['CONTACT_ID']) {
                $clientData = $this->getContactDetails($deal['CONTACT_ID']);
                $dealsFormatted = [
                    "Client" => [
                        "nom" => $clientData['NAME'] . ' ' . $clientData['LAST_NAME'],
                        "gouvernerat" => $this->getIdValue('UF_CRM_1705750225691', $deal['UF_CRM_1705750225691']),
                        "ville" => $this->getIdValue('UF_CRM_1705750299634', $deal['UF_CRM_1705750299634']),
                        "adresse" => $deal['UF_CRM_1705750486330'],
                        "telephone" => $this->contactService->removeAndReplaceCountryCode($clientData['PHONE'][0]['VALUE'])
                    ],
                    "Produit" => [
                        "prix" => floatval($this->getIdValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237'])),
                        "designation" => $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']),
                        "nombreArticle" => intval($this->getIdValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974'])),
                        "article" => $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304'])
                    ]
                ];
                $reponseFD = $this->AddCommandFirstDelivery($dealsFormatted);
                $reponseFD = json_decode($reponseFD, true);

                if ($reponseFD['isError']) {
                    $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Error sending deal to FD'];
                } else {
                    $successes[] = ['order_ID' => $deal['ID'], 'message' => 'Deal successfully sent to FD '];
                    $rep = $this->updateDealStage($deal['ID'], $reponseFD['result']['barCode']);
                    // Prepare SMS variables
                    $price = floatval($this->getIdValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237']));
                    $offerName = $this->getIdValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304']);
                    $phoneNumber = $clientData['PHONE'][0]['VALUE'];
                    $lastName = $clientData['LAST_NAME'];

                    // Send the SMS and handle the response
                    try {
                        $smsResponse = $this->smsService->sendSmsLivraison($lastName, $offerName, $phoneNumber, $price);
                        $successes[] = ['order_ID' => $deal['ID'], 'sms_message_id' => $smsResponse, 'message' => 'SMS sent successfully'];
                    } catch (\Exception $e) {
                        $errors[] = ['order_ID' => $deal['ID'], 'error' => 'SMS sending failed: ' . $e->getMessage()];
                    }
                    if ($rep->getStatusCode() === 200) {
                        $successes[] = ['order_ID' => $deal['ID'], 'message' => 'Deal successfully updated'];
                    } else {
                        $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Error updating deal'];
                    }
                }
            }
        }

        $filePath = '/var/www/file.txt';

        $date = new \DateTime();
        $formattedDate = $date->format('Y-m-d H:i:s');
        $logContent = "Execution time: $formattedDate\n" . "Successes: " . json_encode($successes) . "\nErrors: " . json_encode($errors) . "\n";

        file_put_contents($filePath, $logContent, FILE_APPEND);
        return [
            'successes' => $successes,
            'errors' => $errors,
        ];
    }

    /***
     * 
     * 
     * Connection to keitaro -- postback 
     * 
     * 
     *  */
    public function fetchDealDetailsWebhook($dealId)
    {

        $response = $this->httpClient->request('GET', '', [
            'query' => [
                'ID' => $dealId
            ]
        ]);

        // Utiliser getContent() pour obtenir le contenu de la réponse
        return json_decode($response->getContent(), true);
    }


    public function sendPostbackToClient($dealDetails)
    {
        try {
            // Log offer ID for debugging
            file_put_contents('../var/log/client_postback_offerID_' . date('Y-m-d') . '.log', print_r($dealDetails['result']['UF_CRM_1707164894196'], true), FILE_APPEND);

            // Split the combinedId into tag and userId and log them
            $parts = explode(':', $dealDetails['result']['UF_CRM_1707162226734']);
            if (count($parts) < 2) {
                file_put_contents('../var/log/client_postback_error_' . date('Y-m-d') . '.log', 'Invalid combinedId format: ' . $dealDetails['result']['UF_CRM_1707162226734'], FILE_APPEND);
                throw new \Exception('Invalid combinedId format');
            }
            $tag = $parts[0];
            $userId = $parts[1];
            file_put_contents('../var/log/client_postback_partner_' . date('Y-m-d') . '.log', print_r($dealDetails['result']['UF_CRM_1707162226734'], true), FILE_APPEND);

            // Extract offerId and dealId
            $offerId = $dealDetails['result']['UF_CRM_1707164894196'];
            $dealId = $dealDetails['result']['UF_CRM_1707163372336']; // SUBID
            file_put_contents('../var/log/client_postback_dealID_' . date('Y-m-d') . '.log', print_r($dealId, true), FILE_APPEND);

            // Get status and log it
            $status = $this->getStatusNamesKeitaro($dealDetails['result']['STAGE_ID']);

            file_put_contents('../var/log/client_postback_status_' . date('Y-m-d') . '.log', print_r($status, true), FILE_APPEND);
            // Check if the status is 'approve', otherwise set payout to 0
            if ($status === 'sale') {
                // Make a GET request to the internal API to fetch the payout
                $payoutResponse = $this->httpClient->request('GET', sprintf(
                    'https://plumbill.io/payout-deal/%s/%s',
                    urlencode($userId),
                    urlencode($offerId)
                ));

                // Check if the response is OK
                if ($payoutResponse->getStatusCode() !== 200) {
                    throw new \Exception('Error fetching payout: ' . $payoutResponse->getContent(false));
                }

                // Decode the JSON response
                $payoutData = json_decode($payoutResponse->getContent(), true);

                if (!isset($payoutData['payout'])) {
                    throw new \Exception('Payout not found in response');
                }

                $payout = $payoutData['payout'];

                if ($offerId == '2011' && strtolower($dealDetails['result']['UF_CRM_1707162327037']) == 'kunilin') {
                    $payout = 17;
                }
            } else {
                // If status is not 'approve', set payout to 0
                $payout = 0;
            }
            file_put_contents('../var/log/client_postback_payout_' . date('Y-m-d') . '.log', print_r($payout, true), FILE_APPEND);
            file_put_contents('../var/log/client_postback_debug_' . date('Y-m-d') . '.log', print_r([
                'dealId' => $dealId,
                'status' => $status,
                'payout' => $payout
            ], true), FILE_APPEND);

            if ($dealDetails['result']['UF_CRM_1707162226734'] === 'TrO:00021') {
                //$endpoint = '';
                $endpoint = '';
            } elseif ($dealDetails['result']['UF_CRM_1707162226734'] === 'Ily:00022') {
                $endpoint = '';
            } elseif ($dealDetails['result']['UF_CRM_1707162226734'] === 'Jig:00024') {
                $endpoint = '';
            } else {
                //$endpoint = '';
                $endpoint = '';
            }

            $clientApiUrl = sprintf(
                $endpoint . '?subid=%s&status=%s&payout=%s&currency=USD&from=PLUMBILL',
                urlencode($dealId),
                urlencode($status),
                urlencode($payout)
            );

            if ($dealDetails['result']['UF_CRM_1707162226734'] === 'Ban:00017') {
                $clientApiUrl = sprintf(
                    '',
                    urlencode($dealId),
                    urlencode($payout),
                    urlencode($status),
                );
            }

            if (!empty($dealDetails['result']['UF_CRM_1725742133013'])) {
                $clientApiUrl .= '&sub1=' . urlencode($dealDetails['result']['UF_CRM_1725742133013']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1725742147493'])) {
                $clientApiUrl .= '&sub2=' . urlencode($dealDetails['result']['UF_CRM_1725742147493']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1725742215564'])) {
                $clientApiUrl .= '&sub3=' . urlencode($dealDetails['result']['UF_CRM_1725742215564']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1725742225050'])) {
                $clientApiUrl .= '&sub4=' . urlencode($dealDetails['result']['UF_CRM_1725742225050']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1725742293109'])) {
                $clientApiUrl .= '&sub5=' . urlencode($dealDetails['result']['UF_CRM_1725742293109']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1725742509286'])) {
                $clientApiUrl .= '&AdresseIP=' . urlencode($dealDetails['result']['UF_CRM_1725742509286']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1725742687858'])) {
                $clientApiUrl .= '&userAgent=' . urlencode($dealDetails['result']['UF_CRM_1725742687858']);
            }

            if ($dealDetails['result']['UF_CRM_1707162226734'] === 'TrO:00021') {
                file_put_contents('../var/log/client_postback_trafficoff_' . date('Y-m-d') . '.log', $clientApiUrl . PHP_EOL, FILE_APPEND);
            } elseif ($dealDetails['result']['UF_CRM_1707162226734'] === 'Ily:00022') {
                file_put_contents('../var/log/client_postback_ilya_' . date('Y-m-d') . '.log', $clientApiUrl . PHP_EOL, FILE_APPEND);
            } elseif ($dealDetails['result']['UF_CRM_1707162226734'] === 'Jig:00024') {
                file_put_contents('../var/log/client_postback_jigit_' . date('Y-m-d') . '.log', $clientApiUrl . PHP_EOL, FILE_APPEND);
            } elseif ($dealDetails['result']['UF_CRM_1707162226734'] === 'Ban:00017') {
                file_put_contents('../var/log/client_postback_bankaiads_' . date('Y-m-d') . '.log', $clientApiUrl . PHP_EOL, FILE_APPEND);
            } else {
                file_put_contents('../var/log/client_postback_affera_' . date('Y-m-d') . '.log', $clientApiUrl . PHP_EOL, FILE_APPEND);
            }

            //file_put_contents('../var/log/client_postback_clientApiUrl_' . date('Y-m-d') . '.log', $clientApiUrl, FILE_APPEND);

            //$this->log($dealDetails['result']['UF_CRM_1707162226734'], $clientApiUrl);

            // Send the postback to the client
            $response = $this->httpClient->request('GET', $clientApiUrl);
            //file_put_contents('../var/log/client_postback_response_AfterClientURL_' . date('Y-m-d') . '.log', print_r($response, true), FILE_APPEND);

            // Check if the response is 200 OK
            if ($response->getStatusCode() !== 200) {
                file_put_contents('../var/log/client_postback_responseError_' . date('Y-m-d') . '.log', $response->getContent(false), FILE_APPEND);
                throw new \Exception('Error in client API response: ' . $response->getContent(false));
            }

            // Log the successful response
            file_put_contents('../var/log/client_postback_response_' . date('Y-m-d') . '.log', $response->getContent(), FILE_APPEND);
        } catch (\Exception $e) {
            // Log the error message
            error_log('Error while sending the postback: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getStatusNamesKeitaro(string $stageId): string
    {

        // Mapping of STAGE_ID to Keitaro statuses
        $statusMapping = [
            'C6:NEW' => 'lead', // Tunisie 
            'C17:NEW' => 'lead', // Kenya 
            'C21:NEW' => 'lead', // Libye
            'C25:NEW' => 'lead', // Senegal
            'C29:NEW' => 'lead', //AN

            'C6:PREPARATION' => 'lead', // Processeing TN
            'C17:PREPARATION' => 'lead', //Processeing KY
            'C21:PREPARATION' => 'lead', //Processeing LY
            'C25:PREPARATION' => 'lead', //Processeing SN
            'C29:PREPARATION' => 'lead', //AN

            'C6:UC_00EJVW' => 'lead', // call back TN
            'C17:PREPAYMENT_INVOIC' => 'lead', // call back KY
            'C21:PREPAYMENT_INVOIC' => 'lead', // call back LY
            'C25:PREPAYMENT_INVOIC' => 'lead', // call back SN
            'C29:PREPAYMENT_INVOIC' => 'lead', // call back AN


            'C6:UC_9TTZDM' => 'lead', // Verification TN
            'C17:EXECUTING' => 'lead', // Verification KY
            'C21:EXECUTING' => 'lead', // Verification LY
            'C25:EXECUTING' => 'lead', // Verification SN
            'C29:EXECUTING' => 'lead', // Verification AN

            'C6:UC_Q69K6V' => 'lead', // Nom TN
            'C25:UC_7V0B6O' => 'lead', // Nom SN

            'C6:LOSE' => 'rejected', // TN
            'C17:LOSE' => 'rejected', // kenya
            'C21:LOSE' => 'rejected', // Libye
            'C25:LOSE' => 'rejected', // Senegal
            'C29:LOSE' => 'rejected', //AN

            'C6:WON' => 'sale', //TN
            'C17:WON' => 'sale', //KY
            'C21:WON' => 'sale', //LY
            'C25:WON' => 'sale', //SN
            'C29:WON' => 'sale', //AN

            'C6:EXECUTING' => 'rejected', // Fusionner "Approvedtocg TN" avec "Cancel"
            'C17:FINAL_INVOICE' => 'rejected', // Fusionner "Approvedtocg KY" avec "Cancel"
            'C21:FINAL_INVOICE' => 'rejected', // Fusionner "Approvedtocg LY" avec "Cancel"
            'C25:FINAL_INVOICE' => 'rejected', // Fusionner "Approvedtocg SN" avec "Cancel"
            'C29:FINAL_INVOICE' => 'rejected', // Fusionner "Approvedtocg AN" avec "Cancel"

            'C6:UC_RS2SSM' => 'rejected', //TN SPAM
            'C17:UC_X0677F' => 'rejected', //KY SPAM
            'C21:1' => 'rejected', //LY SPAM
            'C21:UC_I2B1ZE' => 'rejected', //LY SPAM
            'C25:1' => 'rejected', //SN SPAM
            'C29:UC_8ZODDQ' => 'rejected', // AN Spam   
            'C29:1' => 'rejected',

            'C6:UC_KS3JD1' => 'rejected', //TN BLACK LIST
            'C17:PREPAYMENT_INVOIC' => 'rejected', //KY BLACK LIST
            'C21:4' => 'rejected', //LY BLACK LIST
            'C21:UC_J3ETBK' => 'rejected',//LY BLACK LIST
            'C25:4' => 'rejected', //SN BLACK LIST
            'C29:UC_3SQYKW' => 'rejected', // AN BLACK LIST
            'C29:3' => 'rejected',

            'C6:UC_VFCGR5' => 'rejected', // Double TN
            'C17:UC_2446OA' => 'rejected', // Double KY
            'C21:2' => 'rejected', // Double LY
            'C25:2' => 'rejected', // Double SN
            'C29:UC_6XBK1Y' => 'rejected', // Double AN
            'C29:2' => 'rejected',

            'C6:UC_9GP25Z' => 'sale', // Fusionner "Approved" avec "Approve+ TN"
            'C17:UC_23YD73' => 'sale', // Fusionner "Approved" avec "Approve+ KY"
            'C21:3' => 'sale', // Fusionner "Approved" avec "Approve+ KY"
            'C25:3' => 'sale', // Fusionner "Approved" avec "Approve+ SN"
            'C29:UC_QJZYOG' => 'sale' // Fusionner "Approved" avec "AN Approve+"

        ];

        // Return the corresponding status or a default value if the stageId is not found
        return $statusMapping[$stageId] ?? 'unknown';
    }
    public function getPayoutForUserAndOffer($user, $offer): ?int
    {

        $userOfferPayout = $this->userOfferPayoutRepository->findOneBy(['user' => $user, 'offer' => $offer]);

        if (!$userOfferPayout) {
            return null; // Or some default payout value if not found
        }

        return $userOfferPayout->getPayout();
    }
    public function sendPostbackToClientANGOLA($dealDetails)
    {
        try {
            // Log offer ID for debugging
            file_put_contents('../var/log/client_postback_offerID_ANGOLA_' . date('Y-m-d') . '.log', print_r($dealDetails['result']['UF_CRM_1711153213235'], true), FILE_APPEND);

            // Split the combinedId into tag and userId and log them
            $parts = explode(':', $dealDetails['result']['UF_CRM_1711153543876']);
            if (count($parts) < 2) {
                file_put_contents('../var/log/client_postback_error_ANGOLA_' . date('Y-m-d') . '.log', 'Invalid combinedId format: ' . $dealDetails['result']['UF_CRM_1711153543876'], FILE_APPEND);
                throw new \Exception('Invalid combinedId format');
            }
            $tag = $parts[0];
            $userId = $parts[1];
            file_put_contents('../var/log/client_postback_partner_ANGOLA_' . date('Y-m-d') . '.log', print_r($dealDetails['result']['UF_CRM_1711153543876'], true), FILE_APPEND);

            // Extract offerId and dealId
            $offerId = $dealDetails['result']['UF_CRM_1711153213235'];
            $dealId = $dealDetails['result']['UF_CRM_1711153623306']; // SUBID
            file_put_contents('../var/log/client_postback_dealID_ANGOLA_' . date('Y-m-d') . '.log', print_r($dealId, true), FILE_APPEND);

            // Get status and log it
            $status = $this->getStatusNamesKeitaroAngola($dealDetails['result']['STAGE_ID']);
            file_put_contents('../var/log/client_postback_status_before_ANGOLA_' . date('Y-m-d') . '.log', print_r($dealDetails['result']['STAGE_ID'], true), FILE_APPEND);

            file_put_contents('../var/log/client_postback_status_ANGOLA_' . date('Y-m-d') . '.log', print_r($status, true), FILE_APPEND);
            // Check if the status is 'approve', otherwise set payout to 0
            if ($status === 'sale') {
                // Make a GET request to the internal API to fetch the payout
                $payoutResponse = $this->httpClient->request('GET', sprintf(
                    'https://plumbill.io/payout-deal/%s/%s',
                    urlencode($userId),
                    urlencode($offerId)
                ));

                // Check if the response is OK
                if ($payoutResponse->getStatusCode() !== 200) {
                    throw new \Exception('Error fetching payout: ' . $payoutResponse->getContent(false));
                }

                // Decode the JSON response
                $payoutData = json_decode($payoutResponse->getContent(), true);

                if (!isset($payoutData['payout'])) {
                    throw new \Exception('Payout not found in response');
                }

                $payout = $payoutData['payout'];
            } else {
                // If status is not 'approve', set payout to 0
                $payout = 0;
            }
            // $stageId = $dealDetails['result']['STAGE_ID'];
            // if( $stageId === "UC_K0GLPN" ){
            //     $payout = 0;
            // }else{
            //     $payout = 2;
            // }
            file_put_contents('../var/log/client_postback_payout_ANGOLA_' . date('Y-m-d') . '.log', print_r($payout, true), FILE_APPEND);

            file_put_contents('../var/log/client_postback_debug_ANGOLA_' . date('Y-m-d') . '.log', print_r([
                'dealId' => $dealId,
                'status' => $status,
                'payout' => $payout
            ], true), FILE_APPEND);

            $clientApiUrl = sprintf(
                //'',
                '',
                urlencode($dealId),
                urlencode($status),
                urlencode($payout)
            );
            // Check for optional sub1, sub2, sub3 fields and append them to the URL if they exist
            if (!empty($dealDetails['result']['UF_CRM_1726852312218'])) {
                $clientApiUrl .= '&sub1=' . urlencode($dealDetails['result']['UF_CRM_1726852312218']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1726852335223'])) {
                $clientApiUrl .= '&sub2=' . urlencode($dealDetails['result']['UF_CRM_1726852335223']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1726852357112'])) {
                $clientApiUrl .= '&sub3=' . urlencode($dealDetails['result']['UF_CRM_1726852357112']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1726852365691'])) {
                $clientApiUrl .= '&sub4=' . urlencode($dealDetails['result']['UF_CRM_1726852365691']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1726852380221'])) {
                $clientApiUrl .= '&sub5=' . urlencode($dealDetails['result']['UF_CRM_1726852380221']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1726852403535'])) {
                $clientApiUrl .= '&AdresseIP=' . urlencode($dealDetails['result']['UF_CRM_1726852403535']);
            }
            if (!empty($dealDetails['result']['UF_CRM_1726852418089'])) {
                $clientApiUrl .= '&userAgent=' . urlencode($dealDetails['result']['UF_CRM_1726852418089']);
            }

            file_put_contents('../var/log/client_postback_clientApiUrl_ANGOLA_' . date('Y-m-d') . '.log', $clientApiUrl, FILE_APPEND);
            // Send the postback to the client
            $response = $this->httpClient->request('GET', $clientApiUrl);
            file_put_contents('../var/log/client_postback_response_AfterClientURL_ANGOLA_' . date('Y-m-d') . '.log', print_r($response, true), FILE_APPEND);

            // Check if the response is 200 OK
            if ($response->getStatusCode() !== 200) {
                file_put_contents('../var/log/client_postback_responseError_ANGOLA_' . date('Y-m-d') . '.log', $response->getContent(false), FILE_APPEND);
                throw new \Exception('Error in client API response: ' . $response->getContent(false));
            }

            // Log the successful response
            file_put_contents('../var/log/client_postback_response_ANGOLA_' . date('Y-m-d') . '.log', $response->getContent(), FILE_APPEND);
        } catch (\Exception $e) {
            // Log the error message
            error_log('Error while sending the postback ANGOLA: ' . $e->getMessage());
            throw $e;
        }
    }



    public function  getStatusNamesKeitaroAngola(string $stageId): string
    {
        // Mapping of STAGE_ID to Keitaro statuses
        $statusMapping = [
            'NEW' => 'lead', // 'lead'--> Angola
            'PREPARATION' => 'lead', //lead
            'UC_ROPKBH' => 'lead', // 'lead'--> call back AN
            'UC_11IBWS' => 'lead', //'lead' --> Verification AN
            'LOSE' => 'rejected', // rejected
            'WON' => 'sale', //
            'PREPAYMENT_INVOICE' => 'rejected', // rejected --> Fusionner "Approvedtocg AN" avec "Cancel"
            'EXECUTING' => 'rejected', // rejected
            'UC_7YUT0F' => 'rejected', // rejected --> AN BLACK LIST
            'UC_K0GLPN' => 'rejected', // Double AN
            'FINAL_INVOICE' => 'sale', // Fusionner "Approved" avec "Approve+ AN"
        ];

        // Return the corresponding status or a default value if the stageId is not found
        return $statusMapping[$stageId] ?? 'unknown';
    }
    /***
     * 
     * 
     * Connection to bitrix Angola to fetch details -- postback 
     * 
     * 
     *  */
    public function fetchDealDetailsWebhookAngola($dealId)
    {

        $response = $this->httpClient->request('GET', '', [
            'query' => [
                'ID' => $dealId
            ]
        ]);

        // Utiliser getContent() pour obtenir le contenu de la réponse
        return json_decode($response->getContent(), true);
    }

    /***************************************************
     * 
     * To update deal stages according to firt delivery 
     * 
     ***************************************************/

    public function processDealsByStatus(): array
    {
        // Initialiser les variables pour stocker les résultats
        $allItemsMail = [];

        // Récupérer le mapping des statuts
        $mapping = $this->mappingState();  // Cette méthode doit être définie dans votre service

        // Filtrer les deals ayant un statut parmi ceux spécifiés
        $stagesToFilter = ['C8:PREPARATION', 'C8:PREPAYMENT_INVOICE', 'C8:EXECUTING', 'C8:FINAL_INVOICE'];

        // Récupérer les deals avec ces statuts
        $filters = [
            'CATEGORY_ID' => [8],
            'STAGE_ID' => $stagesToFilter
        ];

        // Champs à sélectionner dans la requête
        $selectFields = ['UF_CRM_1719699229165', 'DATE_CREATE', 'STAGE_ID'];
        $deals = $this->getPaginatedData("", $filters, $selectFields, 145207, '');
        if (empty($deals)) {
            return $allItemsMail;  // Retourner une liste vide si aucun deal n'est récupéré
        }
        $statusFD = [];
        // Parcourir les deals récupérés
        foreach ($deals as $deal) {
            if (isset($deal['UF_CRM_1719699229165'])) {
                try {
                    $dealID = $deal['ID'];  // Initialiser le deal ID
                    $currentStateFD = $this->fetchDeliveryFDAndCheckState($deal['UF_CRM_1719699229165'], $statusFD);

                    // Initialiser la variable de statut Bitrix à mettre à jour
                    $newStageBitrix = null;

                    // Vérifier le code FD du deal par rapport au mapping
                    foreach ($mapping as $bitrixStage => $fdCodes) {
                        if (in_array($currentStateFD['code'], $fdCodes)) {
                            $newStageBitrix = $bitrixStage;
                            break;  // Une fois le stage trouvé, on sort de la boucle
                        }
                    }

                    // Si un nouveau stage est trouvé, mettre à jour le deal
                    if (($newStageBitrix != $deal['STAGE_ID']) && ($newStageBitrix != null)) {
                        $this->updateDealBitrix($dealID, $newStageBitrix);
                        $allItemsMail[] = [
                            'barCode' => $deal['UF_CRM_1719699229165'],
                            'createdAt' => $deal['DATE_CREATE'],
                            'stateFirstDelivery' => $currentStateFD['status'],
                            'stateCodeFirstDelivery' => $currentStateFD['code'],
                            'stateBitrixBeforeUpdate' => $this->getStatusNameById($deal['STAGE_ID']),
                            'stateBitrixAfterUpdate' => $this->getStatusNameById($newStageBitrix),
                            'stateBitrixCodeAfterUpdate' => $newStageBitrix,
                            'dealID' => $dealID,
                            'status' => 'Updated',
                            'message' => 'pas en erreur'

                        ];
                    } else {
                        // Si aucun changement de stage n'est nécessaire
                        $allItemsMail[] = [
                            'barCode' => $deal['UF_CRM_1719699229165'],
                            'createdAt' => $deal['DATE_CREATE'],
                            'stateFirstDelivery' => $currentStateFD['status'],
                            'stateCodeFirstDelivery' => $currentStateFD['code'],
                            'stateBitrixBeforeUpdate' => $this->getStatusNameById($deal['STAGE_ID']),
                            'stateBitrixAfterUpdate' => $this->getStatusNameById($deal['STAGE_ID']),
                            'stateBitrixCodeAfterUpdate' => $deal['STAGE_ID'],
                            'dealID' => $dealID,
                            'status' => 'Not updated (no matching FD code)',
                            'message' => 'pas en erreur'
                        ];
                    }
                } catch (Exception $e) {
                    // Enregistrer les erreurs rencontrées pendant le traitement
                    $allItemsMail[] = [
                        'barCode' => $deal['UF_CRM_1719699229165'],
                        'createdAt' => $deal['DATE_CREATE'],
                        'stateFirstDelivery' => $currentStateFD['status'] ?? 'unknown',
                        'stateCodeFirstDelivery' => $currentStateFD['code'],
                        'stateBitrixBeforeUpdate' => $this->getStatusNameById($deal['STAGE_ID']),
                        'stateBitrixAfterUpdate' => $this->getStatusNameById($deal['STAGE_ID']),
                        'stateBitrixCodeAfterUpdate' => $deal['STAGE_ID'],
                        'dealID' => $dealID,
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }
        }

        // Retourner la liste complète des items mis à jour ou non
        return $allItemsMail;
    }

    public function fetchDeliveryFDAndCheckState($barCode, $statusCodeFD)
    {
        try {

            $response = $this->httpClient->request('POST', 'https://www.firstdeliverygroup.com/api/v2/histories', [
                'headers' => [
                    'Authorization' => 'Bearer 42f9befe-82dc-491b-ad1f-7fa0cf2ab971',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => [
                    'barCodes' => [$barCode],
                ],
            ]);

            if ($response->getStatusCode() === 429) {
                sleep(10); // Attendre 10 secondes avant de réessayer
                return $this->fetchDeliveryFDAndCheckState($barCode, $statusFD);
            }

            $response = $response->toArray();
            return $this->findHistoriesWithStatus($response, $statusCodeFD);
        } catch (\Exception $e) {
            // Gérer les exceptions comme les erreurs de connexion ou autres ici
            return null;
        }
    }

    private function findHistoriesWithStatus($data, array $targetStatuses)
    {
        if (isset($data['result'])) {
            foreach ($data['result'] as $product) {
                if (isset($product['histories']) && is_array($product['histories'])) {
                    // Prioritize returning the history with status 32 or 30 if they exist
                    foreach ($product['histories'] as $history) {
                        if (in_array($history['status'], [32, 30])) {
                            return [
                                'found' => true,
                                'code' => $history['status'],
                                'status' => $history['state'],
                            ];
                        }
                    }

                    // If status 32 or 30 is not found, return the last history that matches the targetStatuses
                    $lastHistory = end($product['histories']);

                    if (in_array($lastHistory['status'], $targetStatuses)) {
                        return [
                            'found' => true,
                            'code' => $lastHistory['status'],
                            'status' => $lastHistory['state'],
                        ];
                    } else {
                        // Return the last history but indicate it doesn't match targetStatuses
                        return [
                            'found' => false,
                            'code' => $lastHistory['status'],
                            'status' => $lastHistory['state'],
                        ];
                    }
                }
            }
        }

        return null; // Return null if no history is found
    }

    public function updateDealBitrix($dealID, $bitrixStatus)
    {

        $response = $this->httpClient->request('POST', '', [
            'json' => [
                'id' => $dealID,
                'fields' => ['STAGE_ID' => $bitrixStatus]
            ]
        ]);

        return $bitrixStatus;
        //return $response;
    }

    public function mappingState()
    {
        $mapping = [
            // Si le code FD est dans [30, 32], mettre à jour le stage Bitrix à 'C8:LOSE' --> closed - Lost
            'C8:LOSE' => [30, 32],

            // Si le code FD est 10, mettre à jour le stage Bitrix à 'C8:WON' --> closed - Won
            'C8:WON' => [10],

            // Si le code FD est dans [5, 7, 31, 201, 202, 203], mettre à jour le stage Bitrix à 'C8:FINAL_INVOICE' --> returned 
            'C8:FINAL_INVOICE' => [5, 7, 31, 201, 202, 203],

            // Si le code FD est 2, mettre à jour le stage Bitrix à 'C8:EXECUTING' --> delivered
            'C8:EXECUTING' => [2],

            // Si le code FD est dans [1, 3, 11, 20], mettre à jour le stage Bitrix à 'C8:PREPAYMENT_INVOICE' -> shipping
            'C8:PREPAYMENT_INVOICE' => [1, 3, 11, 20]
        ];

        return $mapping;
    }

    public function getStatusNameById($statusId)
    {
        $statusMapping = [
            "C8:NEW" => "Confirm",
            "C8:PREPARATION" => "Send",
            "C8:PREPAYMENT_INVOICE" => "Shipping",
            "C8:EXECUTING" => "Delivered",
            "C8:FINAL_INVOICE" => "Returned",
            "C8:WON" => "Closed - won",
            "C8:LOSE" => "Closed - lost",
        ];

        return $statusMapping[$statusId] ?? null;
    }

    /**
     * 
     * Send command Delivero 
     * 
     */
    public function SendCommandDeliverooKY(): array
    {
        $errors = [];
        $successes = [];

        $filters['CATEGORY_ID'] = [19];
        $filters['STAGE_ID'] = 'C19:NEW';

        $selectFields = [
            'ID',
            'DATE_CREATE',
            'CONTACT_ID',
            'UF_CRM_1715945258',
            'STAGE_ID',
            'CATEGORY_ID',
            'UF_CRM_1730802536177',
            'UF_CRM_1730803983980',
            'UF_CRM_1705750644478',
            'UF_CRM_1715939080',
            'UF_CRM_1705750486330',
            'UF_CRM_1716205428',
            'UF_CRM_1705749715974',
            'UF_CRM_1715945337'
        ];

        $responseT = $this->getPaginatedData("", $filters, $selectFields, 145207, '');

        if (empty($responseT)) {
            $errors[] = ['error' => 'Failed to fetch deals from API', 'details' => $responseT['error'] ?? 'No confirmed deals'];
        }

        $dealsFormatted = [];
        foreach ($responseT as $deal) {
            if ($deal['CONTACT_ID']) {
                $clientData = $this->getContactDetails($deal['CONTACT_ID']);
                $dealsFormatted = [
                    "api_key" => "023b5082f74e3f9a4e415e3ad6d55fedaa23ab53VWh",
                    "username" => "0113989554",
                    "packages" => [
                        [
                            "order_number" => $deal['ID'],
                            "description" => $this->getIdValue('UF_CRM_1715945258', $deal['UF_CRM_1715945258']),
                            "recipient_name" => $clientData['NAME'] . ' ' . $clientData['LAST_NAME'],
                            "scheduled_date" => $deal['UF_CRM_1705750644478'],
                            "delivery_instructions" => " ",
                            "product_payment" => "NOT PAID",
                            "package_value" => $this->getIdValue('UF_CRM_1715939080', $deal['UF_CRM_1715939080']),
                            "recipient_phone" => $clientData['PHONE'][0]['VALUE'],
                            "detailed_description" => $deal['UF_CRM_1705750486330'],
                            "destination_location" => $deal['UF_CRM_1730803983980'] . ' ' . $this->getIdValue('UF_CRM_1716205428', $deal['UF_CRM_1716205428']) . ' ' . $this->getIdValue('UF_CRM_1730802536177', $deal['UF_CRM_1730802536177']),
                            "quantity" => $this->getIdValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974'])
                        ]
                    ]
                ];
                $reponseDKY = $this->AddCommandDeliveryCompanyKY($dealsFormatted);
                $reponseDKY = json_decode($reponseDKY, true);
                if ($reponseDKY['response'] === "success") {
                    // Successful response from the delivery company
                    if (!empty($reponseDKY['success'])) {
                        $successes[] = ['order_ID' => $deal['ID'], 'message' => 'Deal successfully sent to delivery company'];

                        $rep = $this->updateDealStageKY($deal['ID']);
                        if ($rep->getStatusCode() === 200) {
                            $successes[] = ['order_ID' => $deal['ID'], 'message' => 'Deal successfully updated'];
                        } else {
                            $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Error updating deal'];
                        }
                    }

                    // Handle duplicates
                    if (!empty($reponseDKY['duplicates'])) {
                        foreach ($reponseDKY['duplicates'] as $duplicateID) {
                            $errors[] = [
                                'order_ID' => $deal['ID'],
                                'duplicate_ID' => $duplicateID,
                                'error' => 'Duplicate deal detected'
                            ];
                        }
                    }

                    // Handle failures
                    if (!empty($reponseDKY['failure'])) {
                        foreach ($reponseDKY['failure'] as $failureID) {
                            $errors[] = [
                                'order_ID' => $deal['ID'],
                                'failed_ID' => $failureID,
                                'error' => 'Failed to send deal to delivery company'
                            ];
                        }
                    }
                } else {
                    // General error if response is not successful
                    $errors[] = ['order_ID' => $deal['ID'], 'error' => 'Error sending deal to Deliveroo'];
                }
            }
        }

        return [
            'successes' => $successes,
            'errors' => $errors,
        ];
    }

    private function AddCommandDeliveryCompanyKY($command)
    {
        $url = 'http://deliveroofulfilment.co.ke/client_api/';
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => $command
            ]);

            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                return $content;
            } else {
                return $response->getContent(false);
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    private function updateDealStageKY($dealID)
    {
        $response = $this->httpClient->request('POST', '', [
            'json' => [
                'id' => $dealID,
                'fields' => ['STAGE_ID' => 'C19:PREPARATION']
            ]
        ]);
        return $response;
    }


    public function postbackUpdateDataBaseTN($dealDetails)
    {
        try {
            // Log the input deal details
            file_put_contents('../var/log/deal_update_BD_tunisie_postbackUpdateDataBaseTN_' . date('Y-m-d') . '.log', 'postbackUpdateDataBaseTN dealDetails: ' . print_r($dealDetails, true) . PHP_EOL, FILE_APPEND);

            // Find the deal in the database
            $this->processDealTN($dealDetails['result']);

            // Log successful update
            file_put_contents('../var/log/deal_update_BD_tunisie_postbackUpdateDataBaseTN_' . date('Y-m-d') . '.log', 'Successfully updated deal: ID = ' . $dealDetails['result']['ID'] . ', Stage = ' . $dealDetails['result']['STAGE_ID'] . PHP_EOL, FILE_APPEND);
        } catch (\Exception $e) {
            // Log the exception details
            file_put_contents('../var/log/deal_update_BD_tunisie_postbackUpdateDataBaseTN_' . date('Y-m-d') . '.log', 'Error occurred: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            file_put_contents('../var/log/deal_update_BD_tunisie_postbackUpdateDataBaseTN_' . date('Y-m-d') . '.log', 'Stack trace: ' . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);

            // Rethrow the exception
            throw $e;
        }
    }

    public function postbackUpdateDataBaseSN($dealDetails)
    {
        try {
            // Log the input deal details
            file_put_contents('../var/log/SN_deal_update_BD_postbackUpdateDataBaseSN_' . date('Y-m-d') . '.log', 'postbackUpdateDataBaseSN dealDetails: ' . print_r($dealDetails, true) . PHP_EOL, FILE_APPEND);

            // Find the deal in the database
            $this->processDealSN($dealDetails['result']);

            // Log successful update
            file_put_contents('../var/log/SN_deal_update_BD_postbackUpdateDataBaseSN_' . date('Y-m-d') . '.log', 'Successfully updated deal: ID = ' . $dealDetails['result']['ID'] . ', Stage = ' . $dealDetails['result']['STAGE_ID'] . PHP_EOL, FILE_APPEND);
        } catch (\Exception $e) {
            // Log the exception details
            file_put_contents('../var/log/SN_deal_update_BD_postbackUpdateDataBaseSN_' . date('Y-m-d') . '.log', 'Error occurred: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            file_put_contents('../var/log/SN_deal_update_BD_postbackUpdateDataBaseSN_' . date('Y-m-d') . '.log', 'Stack trace: ' . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);

            // Rethrow the exception
            throw $e;
        }
    }
    public function postbackUpdateDataBaseAN($dealDetails)
    {
        try {
            // Log the input deal details
            file_put_contents('../var/log/deal_update_BD_ANGOLA_postbackUpdateDataBaseAN_migration_' . date('Y-m-d') . '.log', 'postbackUpdateDataBaseTN dealDetails: ' . print_r($dealDetails, true) . PHP_EOL, FILE_APPEND);

            // Find the deal in the database
            $this->processDealAN($dealDetails);

            // Log successful update
            file_put_contents('../var/log/deal_update_BD_ANGOLA_postbackUpdateDataBaseAN_migration_' . date('Y-m-d') . '.log', 'Successfully updated deal: ID = ' . $dealDetails['ID'] . ', Stage = ' . $dealDetails['new_status'] . PHP_EOL, FILE_APPEND);
        } catch (\Exception $e) {
            // Log the exception details
            file_put_contents('../var/log/deal_update_BD_ANGOLA_postbackUpdateDataBaseAN_migration_' . date('Y-m-d') . '.log', 'Error occurred: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            file_put_contents('../var/log/deal_update_BD_ANGOLA_postbackUpdateDataBaseAN_migration_' . date('Y-m-d') . '.log', 'Stack trace: ' . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);

            // Rethrow the exception
            throw $e;
        }
    }

    public function getDealsForBD(string $monthStart, string $monthEnd): array
    {
        ini_set('memory_limit', '2G'); // Augmentation de la limite de mémoire pour les grands volumes

        $batchSize = 10; // Nombre de requêtes par batch
        $limit = 10; // Nombre de résultats par requête
        $currentOffset = 0; // Offset initial
        $baseUrl = '';
        $categoryTable = [0, 6, 17, 21, 25]; // Catégories ciblées
        $maxRetries = 5; // Nombre maximum de tentatives en cas d'erreur
        $retryDelay = 5; // Pause (en secondes) entre les tentatives

        try {
            while (true) {
                $batchPayload = [];

                // Préparation des requêtes pour le batch
                for ($i = 0; $i < $batchSize; $i++) {
                    $batchPayload["cmd$i"] = sprintf(
                        'crm.deal.list?filter[>=DATE_CREATE]=%s&filter[<=DATE_CREATE]=%s&filter[@CATEGORY_ID]=%s&select[]=ID&select[]=TITLE&select[]=DATE_CREATE&select[]=STAGE_ID&select[]=CATEGORY_ID&select[]=DATE_MODIFY&start=%d&limit=%d',
                        urlencode($monthStart),
                        urlencode($monthEnd),
                        implode(',', $categoryTable),
                        $currentOffset,
                        $limit
                    );
                    $currentOffset += $limit;
                }

                $responseData = $this->executeBatchRequest($baseUrl, $batchPayload, $maxRetries, $retryDelay);

                if (!isset($responseData['result']['result'])) {
                    throw new \Exception('Structure de réponse inattendue : ' . json_encode($responseData));
                }

                $isEmpty = true;

                // Traitement des données reçues
                foreach ($responseData['result']['result'] as $cmdResult) {
                    if (!empty($cmdResult)) {
                        foreach ($cmdResult as $deal) {
                            $this->processDeal($deal); // Traitement individuel du deal
                        }
                        $isEmpty = false;
                    }
                }

                // Si aucune donnée n'est retournée, on arrête la boucle
                if ($isEmpty) {
                    break;
                }
            }
        } catch (\Exception $e) {
            // Log global des erreurs
            $this->logError('Fatal Error', $e->getMessage());
            return [];
        }

        return [];
    }

    /**
     * Exécute une requête batch avec gestion des tentatives.
     *
     * @param string $baseUrl URL de base pour l'API
     * @param array $batchPayload Charge utile pour le batch
     * @param int $maxRetries Nombre maximum de tentatives
     * @param int $retryDelay Délai entre les tentatives
     * @return array Réponse de l'API
     */
    private function executeBatchRequest(string $baseUrl, array $batchPayload, int $maxRetries, int $retryDelay): array
    {
        $retries = 0;

        while ($retries < $maxRetries) {
            try {
                $response = $this->httpClient->request('POST', $baseUrl . 'batch.json', [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => ['cmd' => $batchPayload],
                    'timeout' => 120, // Timeout pour éviter les blocages
                ]);

                return $response->toArray(); // Conversion en tableau PHP
            } catch (\Exception $e) {
                $retries++;
                $this->logError("Retry $retries/$maxRetries failed", $e->getMessage());

                if ($retries < $maxRetries) {
                    sleep($retryDelay); // Pause avant de réessayer
                } else {
                    throw new \Exception('Maximum retries reached. Last error: ' . $e->getMessage());
                }
            }
        }

        return [];
    }

    /**
     * Traite un deal individuel Tunisie.
     *
     * @param array $deal Les données du deal à traiter
     */
    private function processDealTN(array $deal): void
    {
        try {
            // Rechercher le deal dans la base de données
            $existingDeal = $this->dealTnRepository->findOneBy(['bitrix_Id' => $deal['ID']]);

            if ($existingDeal) {
                // Mise à jour du deal existant
                $this->dealTnRepository->updateStageTn($existingDeal, $deal['STAGE_ID']);
            } else {
                // Création d'un nouveau deal si non existant
                $this->dealTnRepository->createDealTNFromBitrix(
                    $deal['UF_CRM_1707162226734'] ?? ' ', // Champ optionnel partner ID
                    $deal['STAGE_ID'] ?? null, // Champ optionnel Stage
                    $deal['ID'] ?? null, //bitrix ID 
                    $deal['UF_CRM_1707164894196'] ?? ' ', // Champ optionnel Offer ID 
                    $deal['DATE_CREATE'] ?? null,
                    $deal['DATE_MODIFY'] ?? '',
                    $deal['UF_CRM_1707163372336'] ?? ' ', //Lead ID
                    $deal['UF_CRM_1731502147567'] ?? ' ' // Order ID 
                );
            }
        } catch (\Exception $e) {
            // Log des erreurs spécifiques à un deal
            $this->logError("Error processing deal ID {$deal['ID']}", $e->getMessage());
        }
    }
    /**
     * Traite un deal individuel Senegal.
     *
     * @param array $deal Les données du deal à traiter
     */
    private function processDealSN(array $deal): void
    {
        try {
            // Rechercher le deal dans la base de données
            $existingDeal = $this->dealSnRepository->findOneBy(['bitrix_Id' => $deal['ID']]);

            if ($existingDeal) {
                file_put_contents('../var/log/SN_deal_update_BD_postbackUpdateDataBaseSN_' . date('Y-m-d') . '.log', '$existingDeal: ID = ' . $deal['ID'] . PHP_EOL, FILE_APPEND);

                // Mise à jour du deal existant
                $this->dealSnRepository->updateStageSn($existingDeal, $deal['STAGE_ID']);
            } else {
                file_put_contents('../var/log/SN_deal_update_BD_postbackUpdateDataBaseSN_' . date('Y-m-d') . '.log', '$createDealSNFromBitrix: ID = ' .  $deal['ID']  . PHP_EOL, FILE_APPEND);

                // Création d'un nouveau deal si non existant
                $this->dealSnRepository->createDealSNFromBitrix(
                    $deal['UF_CRM_1707162226734'] ?? ' ', // Champ optionnel partner ID
                    $deal['STAGE_ID'] ?? null, // Champ optionnel Stage
                    $deal['ID'] ?? null, //bitrix ID 
                    $deal['UF_CRM_1707164894196'] ?? ' ', // Champ optionnel Offer ID 
                    $deal['DATE_CREATE'] ?? null,
                    $deal['DATE_MODIFY'] ?? '',
                    $deal['UF_CRM_1707163372336'] ?? ' ', //Lead ID
                    $deal['UF_CRM_1731502147567'] ?? ' ' // Order ID 
                );
            }
        } catch (\Exception $e) {
            file_put_contents('../var/log/SN_deal_update_BD_postbackUpdateDataBaseSN_' . date('Y-m-d') . '.log', 'Error processing deal: ID = ' . $deal['ID'] . ', message = ' . $e->getMessage() . PHP_EOL, FILE_APPEND);

            // Log des erreurs spécifiques à un deal
            $this->logError("Error processing deal ID {$deal['ID']}", $e->getMessage());
        }
    }
    /**
     * Traite un deal individuel.
     *
     * @param array $deal Les données du deal à traiter
     */
    private function processDealAN(array $deal): void
    {


        try {
            $leadId = $deal['lead_ID'];
            $orderId = $deal['order_ID'];
            $newStatus = $deal['new_status'];
            file_put_contents('../var/log/deal_update_BD_ANGOLA_processDealAN_migration_' . date('Y-m-d') . '.log', 'New status from postback : ' . $newStatus . PHP_EOL, FILE_APPEND);

            $statusNames = $this->getStatusKeyBYName();
            $newStatus = $statusNames[$newStatus] ?? 'Unknown';

            file_put_contents('../var/log/deal_update_BD_ANGOLA_processDealAN_migration_' . date('Y-m-d') . '.log', 'New status : ' . $newStatus . PHP_EOL, FILE_APPEND);


            // Rechercher le deal dans la base de données
            // Vérifiez que les champs existent avant d'appeler le repository
            if (!empty($leadId) && !empty($orderId)) {
                $existingDeal = $this->dealAnRepository->findByLeadAndOrder($leadId, $orderId);


                if ($existingDeal) {
                    // Mise à jour du deal existant
                    $this->dealAnRepository->updateStageAn($existingDeal, $newStatus, $deal['ID']);
                } else {
                    // Création d'un nouveau deal si non existant
                    $this->dealAnRepository->createDealAnFromBitrix(
                        $deal['partner_ID'] ?? ' ', // Champ optionnel partner ID
                        $newStatus ?? null, // Champ optionnel Stage
                        $deal['ID'] ?? null, //bitrix ID 
                        $deal['offer_ID'] ?? ' ', // Champ optionnel Offer ID 
                        $deal['created_AT'] ?? null,
                        $deal['DATE_MODIFY'] ?? date('Y-m-d H:i:s'),
                        $deal['lead_ID'] ?? ' ', //Lead ID
                        $deal['order_ID'] ?? ' ' // Order ID 
                    );
                }
            }
        } catch (\Exception $e) {
            // Log des erreurs spécifiques à un deal
            $this->logError("Error processing deal ID {$deal['ID']}", $e->getMessage());
        }
    }

    /**
     * Enregistre les erreurs dans un fichier de log.
     *
     * @param string $context Contexte de l'erreur
     * @param string $message Message d'erreur
     */
    private function logError(string $context, string $message): void
    {
        file_put_contents('../var/log/error_log.txt', sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            $context,
            $message
        ), FILE_APPEND);
    }


    public function getStatusNamesBYName(): array
    {
        return
            [
                'New lead' => 'New lead',
                'Processing' => 'Processing',
                'Call back' => 'Processing', // Call back pour tous les pays
                'Verification' => 'Processing', // Verification pour tous les pays
                'Cancel' => 'Cancel', // Cancel pour tous les pays
                'Approvedtocg' => 'Cancel', // Fusionné avec Cancel
                'SPAM' => 'SPAM', // Spam pour tous les pays
                'Double' => 'DOUBLE', // Double pour tous les pays
                'TN Approve ' => 'Approved', // Fusionné avec Approved
                'APPROVE' => 'Approved', // Approved pour tous les pays
                'Approve' => 'Approved', // Approved pour tous les pays
                'Black list' => 'SPAM', // Black list comme SPAM
                'AN Approve ' => 'Approved',
                'SN Approve ' => 'Approved',
            ];
    }



    /**
     * Relays the transformed data to the partner's endpoint.
     */
    public function relayToClientsTn(array $data): void
    {
        try {
            // Associate each partner_ID with specific configurations
            $partnerEndpoints = [
                'Ts:999999995' => [
                    'url' => '',
                    'method' => 'POST',
                    'dataMapping' => [
                        'status' => 'new_status',
                        'uid' => 'lead_ID',
                    ],
                ],
                'Ez:00009' => [
                    'url' => '',
                    'method' => 'POST',
                    'dataMapping' => [
                        'Status' => 'new_status',
                        'Lead_ID' => 'lead_ID',
                    ],
                ],
                'Lb:00004' => [
                    'url' => '',
                    'method' => 'GET',
                    'dataMapping' => [
                        'status' => 'new_status',
                        'uid' => 'lead_ID',
                    ],
                ],
                'Sn:00019' => [
                    'url' => '',
                    'method' => 'POST',
                    'dataMapping' => [
                        'cnv_status' => 'new_status',
                        'cnv_id' => 'lead_ID',
                        'payout' => 'payout',
                    ],
                ],
            ];
            // Get the partner_ID
            $partnerId = $data['partner_ID'];
            if ($partnerId === 'Sn:00019') {
                $payout = $this->calculatePayout($partnerId, $data['offer_ID']);
                $data['payout'] = $payout;

                // Add mapping for the payout if needed
                $dataMapping['payout'] = 'payout';
            }


            if (!isset($partnerEndpoints[$partnerId])) {
                throw new \Exception("Invalid partner_ID_tn: $partnerId");
            }

            // Retrieve the client configuration
            $clientConfig = $partnerEndpoints[$partnerId];
            $clientUrl = $clientConfig['url'];
            $httpMethod = $clientConfig['method'];
            $dataMapping = $clientConfig['dataMapping'];

            // Transform data based on the dataMapping
            $transformedData = [];
            foreach ($dataMapping as $clientKey => $sourceKey) {
                $transformedData[$clientKey] = $data[$sourceKey] ?? null;
            }

            // Adjust the request options based on the HTTP method
            $requestOptions = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ];

            if ($httpMethod === 'POST') {
                $requestOptions['body'] = $transformedData;
            } elseif ($httpMethod === 'GET') {
                $clientUrl .= '?' . http_build_query($transformedData);
            }

            // Send the request
            $response = $this->httpClient->request($httpMethod, $clientUrl, $requestOptions);

            // Log the information
            file_put_contents(
                '../var/log/http_request_tn_' . date('Y-m-d') . '.log',
                sprintf(
                    "[%s] Method: %s\nURL: %s\nData (JSON): %s\n\n",
                    date('Y-m-d H:i:s'),
                    $httpMethod,
                    $clientUrl,
                    json_encode($transformedData, JSON_PRETTY_PRINT)
                ),
                FILE_APPEND
            );

            // Check the response
            if ($response->getStatusCode() !== 200) {
                // Log an error if the HTTP status is not 200
                file_put_contents(
                    '../var/log/client_postback_error_tn_' . date('Y-m-d') . '.log',
                    sprintf(
                        "[%s] Error relaying data to client: %s\nMethod: %s\nURL: %s\nStatus Code: %d\nResponse: %s\n\n",
                        date('Y-m-d H:i:s'),
                        json_encode($transformedData, JSON_PRETTY_PRINT),
                        $httpMethod,
                        $clientUrl,
                        $response->getStatusCode(),
                        $response->getContent(false)
                    ),
                    FILE_APPEND
                );
            } else {
                // Log a success if everything went well
                file_put_contents(
                    '../var/log/client_postback_success_tn_' . date('Y-m-d') . '.log',
                    sprintf(
                        "[%s] Successfully relayed data to client: %s\nMethod: %s\nURL: %s\nResponse: %s\n\n",
                        date('Y-m-d H:i:s'),
                        json_encode($transformedData, JSON_PRETTY_PRINT),
                        $httpMethod,
                        $clientUrl,
                        $response->getContent(false)
                    ),
                    FILE_APPEND
                );
            }
        } catch (\Exception $e) {
            // Log an exception in case of error
            file_put_contents(
                '../var/log/client_postback_error_tn_' . date('Y-m-d') . '.log',
                sprintf(
                    "[%s] Exception: %s\nTrace: %s\n\n",
                    date('Y-m-d H:i:s'),
                    $e->getMessage(),
                    $e->getTraceAsString()
                ),
                FILE_APPEND
            );
        }
    }


    /**
     * Relaye les données transformées vers l'endpoint des clients.
     */
    public function relayToClientsAn(array $data): void
    {
        try {
            // Associate each partner_ID with specific configurations
            $partnerEndpoints = [
                'Ts:999999995' => [
                    'url' => '',
                    'method' => 'POST',
                    'dataMapping' => [
                        'status' => 'new_status',
                        'uid' => 'lead_ID',
                        'payout' => 'payout',
                    ],
                ],
                'Ez:00009' => [
                    'url' => '',
                    'method' => 'POST',
                    'dataMapping' => [
                        'Status' => 'new_status',
                        'Lead_ID' => 'lead_ID',
                    ],
                ],
                'Lb:00004' => [
                    'url' => '',
                    'method' => 'GET',
                    'dataMapping' => [
                        'status' => 'new_status',
                        'uid' => 'lead_ID',
                    ],
                ],
                'Sn:00019' => [
                    'url' => '',
                    'method' => 'POST',
                    'dataMapping' => [
                        'cnv_status' => 'new_status',
                        'cnv_id' => 'lead_ID',
                        'payout' => 'payout',
                    ],
                ],
            ];
            // Get the partner_ID
            $partnerId = $data['partner_ID'];
            if ($partnerId === 'Sn:00019') {
                $payout = $this->calculatePayout($partnerId, $data['offer_ID']);
                $data['payout'] = $payout;

                // Add mapping for the payout if needed
                $dataMapping['payout'] = 'payout';
            }


            if (!isset($partnerEndpoints[$partnerId])) {
                throw new \Exception("Invalid partner_ID_an: $partnerId");
            }

            // Retrieve the client configuration
            $clientConfig = $partnerEndpoints[$partnerId];
            $clientUrl = $clientConfig['url'];
            $httpMethod = $clientConfig['method'];
            $dataMapping = $clientConfig['dataMapping'];

            // Transform data based on the dataMapping
            $transformedData = [];
            foreach ($dataMapping as $clientKey => $sourceKey) {
                $transformedData[$clientKey] = $data[$sourceKey] ?? null;
            }

            // Adjust the request options based on the HTTP method
            $requestOptions = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ];

            if ($httpMethod === 'POST') {
                $requestOptions['body'] = $transformedData;
            } elseif ($httpMethod === 'GET') {
                $clientUrl .= '?' . http_build_query($transformedData);
            }

            // Send the request
            $response = $this->httpClient->request($httpMethod, $clientUrl, $requestOptions);

            // Log the information
            file_put_contents(
                '../var/log/http_request_an_' . date('Y-m-d') . '.log',
                sprintf(
                    "[%s] Method: %s\nURL: %s\nData (JSON): %s\n\n",
                    date('Y-m-d H:i:s'),
                    $httpMethod,
                    $clientUrl,
                    json_encode($transformedData, JSON_PRETTY_PRINT)
                ),
                FILE_APPEND
            );

            // Check the response
            if ($response->getStatusCode() !== 200) {
                // Log an error if the HTTP status is not 200
                file_put_contents(
                    '../var/log/client_postback_error_an_' . date('Y-m-d') . '.log',
                    sprintf(
                        "[%s] Error relaying data to client: %s\nMethod: %s\nURL: %s\nStatus Code: %d\nResponse: %s\n\n",
                        date('Y-m-d H:i:s'),
                        json_encode($transformedData, JSON_PRETTY_PRINT),
                        $httpMethod,
                        $clientUrl,
                        $response->getStatusCode(),
                        $response->getContent(false)
                    ),
                    FILE_APPEND
                );
            } else {
                // Log a success if everything went well
                file_put_contents(
                    '../var/log/client_postback_success_an_' . date('Y-m-d') . '.log',
                    sprintf(
                        "[%s] Successfully relayed data to client: %s\nMethod: %s\nURL: %s\nResponse: %s\n\n",
                        date('Y-m-d H:i:s'),
                        json_encode($transformedData, JSON_PRETTY_PRINT),
                        $httpMethod,
                        $clientUrl,
                        $response->getContent(false)
                    ),
                    FILE_APPEND
                );
            }
        } catch (\Exception $e) {
            // Log an exception in case of error
            file_put_contents(
                '../var/log/client_postback_error_an_' . date('Y-m-d') . '.log',
                sprintf(
                    "[%s] Exception: %s\nTrace: %s\n\n",
                    date('Y-m-d H:i:s'),
                    $e->getMessage(),
                    $e->getTraceAsString()
                ),
                FILE_APPEND
            );
        }
    }

    /**
     * addDealsToBitrixSN Senegal 
     */
    public function addDealsToBitrixSN(): array
    {
        $successes = [];
        $errors = [];
        $batchCommands = [];
        $batchSize = 36; // Bitrix batch limit for commands (deals and contacts)
        $dealIndexMap = []; // Pour mapper les commandes deal_add avec les deals
        $dealCount = 0;

        // Retrieve all unsent deals
        do {
            // Retrieve unsent deals from the database
            $unsentDeals = $this->dealRepository->findUnsentDealsSN(); // Each deal includes a contact command, a deal command, and an update command, so divide by 3

            foreach ($unsentDeals as $index => $deal) {
                $dealData = $deal->getJsonValue();

                $offerValue = $dealData['offer'];
                $webmasterId = $dealData['webmasterID'];
                $leadId = $dealData['leadID'];
                $contactData = $dealData['contact'] ?? [];

                $offer = $this->offerRepository->findOneBy(['offerId' => $offerValue]);
                if (!$offer) {
                    $errors[] = ['leadID' => $leadId, 'offer' => $offerValue, 'error' => 'Offer not found'];
                    continue;
                }

                // Determine country-specific parameters
                $offerName = $offer->getName();
                $offerCountry = $offer->getCountry();
                $hasRecentDeal = $this->dealRepository->hasRecentDeal($contactData["telephone"], $offerValue);

                // Define unique command keys for contact, deal, and update in the batch
                $contactCommandKey = "contact_add_{$dealCount}";
                $dealCommandKey = "deal_add_{$dealCount}";
                $dealUpdateCommandKey = "deal_update_{$dealCount}";
                // Initialize deal fields
                $dealFields = [];

                if ($offerCountry != "Angola") {
                    // Add deal creation command to the batch
                    $dealFields = $this->createDealMATN(
                        ['ID' => '$result[' . $contactCommandKey . ']'], // Passing reference to created contact ID
                        $offerValue,
                        $webmasterId,
                        $leadId,
                        $offerName,
                        $dealData,
                        $deal->getClientId(),
                        $offerCountry,
                        $deal->getStatus(),
                        $deal->getOrderId()
                    );


                    // Map the deal ID to the command key for future reference
                    $dealIndexMap[$dealCommandKey] = $deal;
                    $dealCount++;

                    // Add contact creation command to the batch
                    $batchCommands[$contactCommandKey] = "crm.contact.add?" . http_build_query([
                        'fields' => [
                            'NAME' => $contactData['name'],
                            'PHONE' => [['VALUE' => $contactData['telephone'], 'VALUE_TYPE' => 'WORK']],
                        ],
                        'params' => ["REGISTER_SONET_EVENT" => "Y"]
                    ]);


                    // Add deal creation command to the batch using generated deal data
                    $batchCommands[$dealCommandKey] = "crm.deal.add?" . http_build_query($dealFields);

                    // Add deal update command to link the contact to the deal
                    $batchCommands[$dealUpdateCommandKey] = "crm.deal.update?" . http_build_query([
                        'id' => '$result[' . $dealCommandKey . ']',
                        'fields' => [
                            'CONTACT_ID' => '$result[' . $contactCommandKey . ']'
                        ]
                    ]);

                    // Check if batch size limit is reached, then execute the batch
                    if (count($batchCommands) >= $batchSize) {
                        $this->executeBatchTN($batchCommands, $successes, $errors, $dealIndexMap);
                        $batchCommands = []; // Reset batch commands
                        $dealIndexMap = []; // Reset the mapping for each batch execution

                    }
                }
            }

            // Execute any remaining commands in the final batch
            if (!empty($batchCommands)) {
                $this->executeBatchTN($batchCommands, $successes, $errors, $dealIndexMap);
                $batchCommands = [];
                $dealIndexMap = [];
            }
        } while (!empty($unsentDeals)); // Continue until there are no more unsent deals

        return [
            'successes' => $successes,
            'errors' => $errors,
        ];
    }


    /**
     * addDealsToBitrixAN Angola
     * NEW 
     */
    public function addDealsToBitrixAN(): array
    {
        $successes = [];
        $errors = [];
        $batchCommands = [];
        $batchSize = 36; // Bitrix batch limit for commands (deals and contacts)
        $dealIndexMap = []; // Pour mapper les commandes deal_add avec les deals
        $dealCount = 0;

        // Retrieve all unsent deals
        do {
            // Retrieve unsent deals from the database
            $unsentDeals = $this->dealRepository->findUnsentDealsAN(); // Each deal includes a contact command, a deal command, and an update command, so divide by 3

            foreach ($unsentDeals as $index => $deal) {
                $dealData = $deal->getJsonValue();

                $offerValue = $dealData['offer'];
                $webmasterId = $dealData['webmasterID'];
                $leadId = $dealData['leadID'];
                $contactData = $dealData['contact'] ?? [];

                $offer = $this->offerRepository->findOneBy(['offerId' => $offerValue]);
                if (!$offer) {
                    $errors[] = ['leadID' => $leadId, 'offer' => $offerValue, 'error' => 'Offer not found'];
                    continue;
                }

                // Determine country-specific parameters
                $offerName = $offer->getName();
                $offerCountry = $offer->getCountry();
                $hasRecentDeal = $this->dealRepository->hasRecentDeal($contactData["telephone"], $offerValue);

                // Define unique command keys for contact, deal, and update in the batch
                $contactCommandKey = "contact_add_{$dealCount}";
                $dealCommandKey = "deal_add_{$dealCount}";
                $dealUpdateCommandKey = "deal_update_{$dealCount}";
                // Initialize deal fields
                $dealFields = [];


                // Add deal creation command to the batch
                $dealFields = $this->createDealMATN(
                    ['ID' => '$result[' . $contactCommandKey . ']'], // Passing reference to created contact ID
                    $offerValue,
                    $webmasterId,
                    $leadId,
                    $offerName,
                    $dealData,
                    $deal->getClientId(),
                    $offerCountry,
                    $deal->getStatus(),
                    $deal->getOrderId()
                );


                // Map the deal ID to the command key for future reference
                $dealIndexMap[$dealCommandKey] = $deal;
                $dealCount++;

                // Add contact creation command to the batch
                $batchCommands[$contactCommandKey] = "crm.contact.add?" . http_build_query([
                    'fields' => [
                        'NAME' => $contactData['name'],
                        'PHONE' => [['VALUE' => $contactData['telephone'], 'VALUE_TYPE' => 'WORK']],
                    ],
                    'params' => ["REGISTER_SONET_EVENT" => "Y"]
                ]);


                // Add deal creation command to the batch using generated deal data
                $batchCommands[$dealCommandKey] = "crm.deal.add?" . http_build_query($dealFields);

                // Add deal update command to link the contact to the deal
                $batchCommands[$dealUpdateCommandKey] = "crm.deal.update?" . http_build_query([
                    'id' => '$result[' . $dealCommandKey . ']',
                    'fields' => [
                        'CONTACT_ID' => '$result[' . $contactCommandKey . ']'
                    ]
                ]);

                // Check if batch size limit is reached, then execute the batch
                if (count($batchCommands) >= $batchSize) {
                    $this->executeBatchAN($batchCommands, $successes, $errors, $dealIndexMap);
                    $batchCommands = []; // Reset batch commands
                    $dealIndexMap = []; // Reset the mapping for each batch execution

                }
            }

            // Execute any remaining commands in the final batch
            if (!empty($batchCommands)) {
                $this->executeBatchAN($batchCommands, $successes, $errors, $dealIndexMap);
                $batchCommands = [];
                $dealIndexMap = [];
            }
        } while (!empty($unsentDeals)); // Continue until there are no more unsent deals

        return [
            'successes' => $successes,
            'errors' => $errors,
        ];
    }
    /**
     * Fetch user, offer, and calculate payout based on partner_ID and offer_ID.
     *
     * @param string $partnerId
     * @param string $offerId
     * @return float
     * @throws \Exception
     */
    private function calculatePayout(string $partnerId, string $offerId): float
    {
        // Split the combinedId into tag and userId
        $parts = explode(':', $partnerId);
        if (count($parts) < 2) {
            file_put_contents('../var/log/client_postback_error_postbackPayout_' . date('Y-m-d') . '.log', 'Invalid combinedId format: ' . $partnerId, FILE_APPEND);
            throw new \Exception('Invalid combinedId format');
        }

        $tag = $parts[0];
        $userId = $parts[1];

        // Fetch the user by the `userId` field
        $user = $this->userRepository->findOneBy(['userId' => $userId]);

        // Fetch the offer by the `offerId` field
        $offer = $this->offerRepository->findOneBy(['offerId' => $offerId]);

        // Log the extracted userId
        file_put_contents('../var/log/client_postback_success_tn_' . date('Y-m-d') . '.log', 'combinedId format partner_ID: ' . $userId . "\n", FILE_APPEND);

        // Calculate the payout
        $payout = $this->getPayoutForUserAndOffer($user, $offer);

        // Log the payout
        file_put_contents('../var/log/client_postback_success_tn_' . date('Y-m-d') . '.log', 'payout: ' . $payout . "\n", FILE_APPEND);

        return $payout;
    }


    /**
     * Relays the transformed data to the partner's endpoint.
     */

    public function relayTOnePartnersTn(array $data): string
    {
        // Log the input data
        file_put_contents(
            '../var/log/http_request_op_tn_' . date('Y-m-d') . '.log',
            sprintf(
                "[%s] Input Data: %s\n\n",
                date('Y-m-d H:i:s'),
                json_encode($data, JSON_PRETTY_PRINT)
            ),
            FILE_APPEND
        );

        $statusMap = [
            'New lead'      => 'pending',
            'Processing'    => 'pending',
            'Cancel'        => 'rejected',
            'SPAM'          => 'trash',
            'DOUBLE'        => 'trash',
            'Approved'      => 'approved'
        ];

        $originalStatus = $data['new_status'] ?? null;

        // Log the original status
        file_put_contents(
            '../var/log/http_request_tn_' . date('Y-m-d') . '.log',
            sprintf(
                "[%s] Original Status: %s\n\n",
                date('Y-m-d H:i:s'),
                $originalStatus ?? 'null'
            ),
            FILE_APPEND
        );

        $normalizedStatus = $statusMap[$originalStatus] ?? 'unknown';

        // Log the normalized status
        file_put_contents(
            '../var/log/http_request_tn_' . date('Y-m-d') . '.log',
            sprintf(
                "[%s] Normalized Status: %s\n\n",
                date('Y-m-d H:i:s'),
                $normalizedStatus
            ),
            FILE_APPEND
        );

        // Base URL
        $baseUrl = 'https://tracking.onepartners.io/track/conv-addchange';
        $token = '';
        $goalAlias = 'status';

        if ($normalizedStatus === 'unknown') {
            $errorMessage = 'Unknown status: ' . $originalStatus;

            // Log the error
            file_put_contents(
                '../var/log/http_request_op_tn_' . date('Y-m-d') . '.log',
                sprintf(
                    "[%s] Error: %s\n\n",
                    date('Y-m-d H:i:s'),
                    $errorMessage
                ),
                FILE_APPEND
            );

            throw new Exception($errorMessage);
        }

        $postbackUrl = sprintf(
            '%s?token=%s&goal_alias=%s&click_id=%s&conv_status=%s',
            $baseUrl,
            $token,
            $goalAlias,
            $data['lead_ID'] ?? 'unknown',
            $normalizedStatus
        );

        // Log the generated Postback URL
        file_put_contents(
            '../var/log/http_request_op_tn_' . date('Y-m-d') . '.log',
            sprintf(
                "[%s] Generated Postback URL: %s\n\n",
                date('Y-m-d H:i:s'),
                $postbackUrl
            ),
            FILE_APPEND
        );
        $response = file_get_contents($postbackUrl);

        // Log the response
        file_put_contents(
            '../var/log/http_request_op_tn_' . date('Y-m-d') . '.log',
            sprintf(
                "[%s] Response from Postback URL: %s\n\n",
                date('Y-m-d H:i:s'),
                $response
            ),
            FILE_APPEND
        );
        return $postbackUrl;
    }

    /**
     * Relays the transformed data to the partner's endpoint.
     */

    public function relayTOnePartnersAn(array $data): string
    {
        // Log the input data
        file_put_contents(
            '../var/log/http_request_op_an_' . date('Y-m-d') . '.log',
            sprintf(
                "[%s] Input Data: %s\n\n",
                date('Y-m-d H:i:s'),
                json_encode($data, JSON_PRETTY_PRINT)
            ),
            FILE_APPEND
        );

        $statusMap = [
            'New lead'      => 'pending',
            'Processing'    => 'pending',
            'Cancel'        => 'rejected',
            'SPAM'          => 'trash',
            'DOUBLE'        => 'trash',
            'Approved'      => 'approved'
        ];


        $originalStatus = $data['new_status'] ?? null;

        // Log the original status
        file_put_contents(
            '../var/log/http_request_op_an_' . date('Y-m-d') . '.log',
            sprintf(
                "[%s] Original Status: %s\n\n",
                date('Y-m-d H:i:s'),
                $originalStatus ?? 'null'
            ),
            FILE_APPEND
        );

        $normalizedStatus = $statusMap[$originalStatus] ?? 'unknown';

        // Log the normalized status
        file_put_contents(
            '../var/log/http_request_op_an_' . date('Y-m-d') . '.log',
            sprintf(
                "[%s] Normalized Status: %s\n\n",
                date('Y-m-d H:i:s'),
                $normalizedStatus
            ),
            FILE_APPEND
        );

        // Base URL
        $baseUrl = 'https://tracking.onepartners.io/track/conv-addchange';
        $token = '';
        $goalAlias = 'status';

        if ($normalizedStatus === 'unknown') {
            $errorMessage = 'Unknown status: ' . $originalStatus;

            // Log the error
            file_put_contents(
                '../var/log/http_request_op_an_' . date('Y-m-d') . '.log',
                sprintf(
                    "[%s] Error: %s\n\n",
                    date('Y-m-d H:i:s'),
                    $errorMessage
                ),
                FILE_APPEND
            );

            throw new Exception($errorMessage);
        }

        $postbackUrl = sprintf(
            '%s?token=%s&goal_alias=%s&click_id=%s&conv_status=%s',
            $baseUrl,
            $token,
            $goalAlias,
            $data['lead_ID'] ?? 'unknown',
            $normalizedStatus
        );

        // Log the generated Postback URL
        file_put_contents(
            '../var/log/http_request_op_an_' . date('Y-m-d') . '.log',
            sprintf(
                "[%s] Generated Postback URL: %s\n\n",
                date('Y-m-d H:i:s'),
                $postbackUrl
            ),
            FILE_APPEND
        );

        return $postbackUrl;
    }


    public function getStatusKeyBYName(): array
    {
        return
            [
                'New lead' => 'C29:NEW',
                'Processing' => 'C29:PREPARATION',
                'Call back' => 'C29:PREPAYMENT_INVOIC', // Call back pour tous les pays
                'Verification' => 'C29:EXECUTING', // Verification pour tous les pays
                'Cancel' => 'C29:LOSE', // Cancel pour tous les pays
                'Approvedtocg' => 'C29:FINAL_INVOICE', // Fusionné avec Cancel
                'Spam' => 'C29:1', //'C29:UC_8ZODDQ', // Spam pour tous les pays
                'Double' => 'C29:2', //'C29:UC_6XBK1Y', // Double pour tous les pays
                'Approve' => 'C29:WON', // Approved pour tous les pays
                'Black list' => 'C29:UC_3SQYKW', // Black list comme SPAM
                'Approve+' => 'C29:UC_QJZYOG',
                'Blacklist' => 'C29:3'
            ];
    }

    /**
     * Enregistre les logs dans un fichier.
     *
     * @param string $context Contexte de l'erreur
     * @param string $message Message d'erreur
     */
    private function log(string $context, string $message): void
    {
        file_put_contents(__DIR__ . '/send_postback_client_log_' . date('Y-m-d') . '.log', sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            $context,
            $message
        ), FILE_APPEND);
    }
}
