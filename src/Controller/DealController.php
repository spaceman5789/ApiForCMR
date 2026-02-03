<?php

namespace App\Controller;

use App\Repository\OfferRepository;
use App\Service\ContactService;
use App\Service\DealService;
use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Offer;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SmsService;
use App\Service\FirstDeliveryService;

class DealController extends AbstractController
{
    private $httpClient;
    private string $bitrixWebhookUrl;
    private $offerRepository;
    private $contactService;
    private $dealService;
    private $mailService;
    private $security;
    private $userRepository;
    private $smsService;

    public function __construct(
        HttpClientInterface $httpClient,
        OfferRepository $offerRepository,
        ContactService $contactService,
        DealService $dealService,
        EmailService $mailService,
        Security $security,
        UserRepository $userRepository,
        SmsService $smsService
    ) {
        $this->httpClient = $httpClient;
        $this->bitrixWebhookUrl = '';
        $this->offerRepository = $offerRepository;
        $this->contactService = $contactService;
        $this->dealService = $dealService;
        $this->mailService = $mailService;
        $this->security = $security;
        $this->userRepository = $userRepository;
        $this->smsService = $smsService;
    }


    /**
     * @Route(
     *     name="add_deals",
     *     path="/api/add-deals",
     *     methods={"POST"}
     * )
     */
    public function addDeals(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createNotFoundException('Token Invalid !! ');
        }

        $partnerID = $user->getTag() . ':' . $user->getUserId();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['deals'])) {
            $errors[] = ['error' => 'No data foud'];

            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $deals = $data['deals'];
        //$result = $this->dealService->addDealsToBitrixTN();

        $result = $this->dealService->addDeals($deals, $partnerID);

        return $this->json($result, Response::HTTP_OK);
    }

    /**
     * @Route(
     *     name="create-commande-fd",
     *     path="/create-commande-fd",
     *     methods={"GET"}
     * )

     * @return Response
     */
    public function SendCommandFirstDelivery(Request $request): Response
    {

        try {
            $deals = $this->dealService->SendCommandFirstDelivery();

            // Envoyer un email avec les détails de la commande
            $this->mailService->sendEmailSendDealsToFD('Success', $deals);

            return $this->json([
                'success' => true,
                'result' => $deals
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, envoyer un email d'erreur
            $this->mailService->sendEmailSendDealsToFD($e->getMessage(), [], true);

            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route(
     *     name="get_deal_by",
     *     path="/get_deal_by/{idContact}/{idOffer}",
     *     methods={"GET"}
     * )

     * @return Response
     */
    public function getTotalExistingDealsBy(string $idContact, string $idOffer): Response
    {

        // Call the service method
        $deals = $this->dealService->getTotalExistingDealsBy($idContact, $idOffer);
        return $this->json([
            'success' => true,
            'result' => $deals
        ]);
    }
    /**
     * @Route(
     *     name="fetch-commande-fd",
     *     path="/fetch-commande-fd",
     *     methods={"GET"}
     * )

     * @return Response
     */
    public function fetchCommandFirstDelivery(Request $request): Response
    {

        $limit = 500;

        $items = $this->dealService->fetchAllDeliveries($limit);

        return new JsonResponse([
            'status' => 200,
            'isError' => false,
            'message' => 'Deliveries fetched successfully',
            'totalItem' => count($items),
            'result' => $items,
        ]);
    }

    /**
     * @Route(
     *     name="create-specifique-commande-fd",
     *     path="/create-specifique-commande-fd",
     *     methods={"GET"}
     * )
     *
     * @return Response
     */
    public function SendSpecifiqueCommandFirstDelivery(Request $request): Response
    {
        try {
            $dealsId = $request->get('dealsId');
            $deals = $this->dealService->SendSpecifiqueCommandFirstDelivery($dealsId);
            // Envoyer un email avec les détails de la commande
            $this->mailService->sendEmailSendDealsToFD('Success', $deals);
            return $this->json([
                'success' => true,
                'result' => $deals,
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, envoyer un email d'erreur
            $this->mailService->sendEmailSendDealsToFD($e->getMessage(), [], true);
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route("/payout-deal/{userId}/{offerId}", name="get_payout")
     */

    public function getPayout(string $userId, string $offerId): JsonResponse
    {
        // Fetch the user by the `userId` field
        $user = $this->userRepository->findOneBy(['userId' => $userId]);
        // Fetch the offer by the `offerId` field
        $offer = $this->offerRepository->findOneBy(['offerId' => $offerId]);

        if (!$user || !$offer) {
            return new JsonResponse(['error' => 'User or Offer not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Get the payout for the user and offer using the deal service
        $payout = $this->dealService->getPayoutForUserAndOffer($user, $offer);

        if ($payout === null) {
            return new JsonResponse(['error' => 'Payout not found for this user and offer'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'userId' => $userId,
            'offerId' => $offerId,
            'payout' => $payout,
        ]);
    }

    /**
     * @Route("/deal_postback", name="deal_postback_plumbill", methods={"POST"})
     */
    public function handleBitrix24Webhook(Request $request): Response
    {
        // Récupérer les données brutes du webhook Bitrix24
        $rawData = $request->getContent();
        parse_str($rawData, $parsedData);
        file_put_contents('../var/log/bitrix24_webhook_' . date('Y-m-d') . '.log', print_r($parsedData, true), FILE_APPEND);
        // Vérifier si la clé 'data' existe avant de l'utiliser
        if (isset($parsedData['data']['FIELDS']['ID'])) {
            $dealId = $parsedData['data']['FIELDS']['ID'];
            // Récupérer les détails complets du deal en utilisant l'ID du deal
            $dealDetails = $this->dealService->fetchDealDetailsWebhook($dealId);
            // Journaliser les détails du deal pour le débogage
            file_put_contents('../var/log/bitrix24_webhook_details_' . date('Y-m-d') . '.log', print_r($dealDetails, true), FILE_APPEND);
            //partner ID
            if (
                $dealDetails['result']['UF_CRM_1707162226734'] === 'Afra:00014' &&
                (
                    $dealDetails['result']['CATEGORY_ID'] === '6' ||
                    $dealDetails['result']['CATEGORY_ID'] === '17' ||
                    $dealDetails['result']['CATEGORY_ID'] === '21' ||
                    $dealDetails['result']['CATEGORY_ID'] === '25' ||
                    $dealDetails['result']['CATEGORY_ID'] === '29'
                )
            ) {
                file_put_contents('../var/log/bitrix24_webhook_affera_' . date('Y-m-d') . '.log', json_encode($dealDetails) . PHP_EOL, FILE_APPEND);
                // Envoyer un postback à l'API du client
                $this->dealService->sendPostbackToClient($dealDetails);
            }

            if (
                $dealDetails['result']['UF_CRM_1707162226734'] === 'TrO:00021' &&
                (
                    $dealDetails['result']['CATEGORY_ID'] === '6' ||
                    $dealDetails['result']['CATEGORY_ID'] === '29'
                )
            ) {
                file_put_contents('../var/log/bitrix24_webhook_trafficoff_' . date('Y-m-d') . '.log', json_encode($dealDetails) . PHP_EOL, FILE_APPEND);
                // Envoyer un postback à l'API du client
                $this->dealService->sendPostbackToClient($dealDetails);
            }

            if ($dealDetails['result']['UF_CRM_1707162226734'] === 'Ily:00022' && $dealDetails['result']['CATEGORY_ID'] === '6') {
                file_put_contents('../var/log/bitrix24_webhook_ilya_' . date('Y-m-d') . '.log', json_encode($dealDetails) . PHP_EOL, FILE_APPEND);
                // Envoyer un postback à l'API du client
                $this->dealService->sendPostbackToClient($dealDetails);
            }

            if ($dealDetails['result']['UF_CRM_1707162226734'] === 'Jig:00024' && $dealDetails['result']['CATEGORY_ID'] === '6') {
                file_put_contents('../var/log/bitrix24_webhook_jigit_' . date('Y-m-d') . '.log', json_encode($dealDetails) . PHP_EOL, FILE_APPEND);
                // Envoyer un postback à l'API du client
                $this->dealService->sendPostbackToClient($dealDetails);
            }

            if ($dealDetails['result']['UF_CRM_1707162226734'] === 'Ban:00017' && $dealDetails['result']['CATEGORY_ID'] === '29') {
                file_put_contents('../var/log/bitrix24_webhook_bankaiads_' . date('Y-m-d') . '.log', json_encode($dealDetails) . PHP_EOL, FILE_APPEND);
                // Envoyer un postback à l'API du client
                $this->dealService->sendPostbackToClient($dealDetails);
            }

            //$this->log($dealDetails['result']['UF_CRM_1707162226734'] . ' => handleBitrix24Webhook', json_encode($dealDetails));
        }

        return new Response('Webhook received and processed', 200);
    }

    /**
     * @Route("/deal_postback_angola", name="deal_postback_plumbill_angola", methods={"POST"})
     */
    public function handleBitrix24WebhookAngola(Request $request): Response
    {
        // Récupérer les données brutes du webhook Bitrix24
        $rawData = $request->getContent();
        parse_str($rawData, $parsedData);
        file_put_contents('../var/log/bitrix24_webhook_angola_' . date('Y-m-d') . '.log', print_r($parsedData, true), FILE_APPEND);
        // Vérifier si la clé 'data' existe avant de l'utiliser
        if (isset($parsedData['data']['FIELDS']['ID'])) {
            $dealId = $parsedData['data']['FIELDS']['ID'];
            // Récupérer les détails complets du deal en utilisant l'ID du deal
            $dealDetails = $this->dealService->fetchDealDetailsWebhookAngola($dealId);
            // Journaliser les détails du deal pour le débogage
            file_put_contents('../var/log/bitrix24_webhook_details_angola_' . date('Y-m-d') . '.log', print_r($dealDetails, true), FILE_APPEND);
            //partner ID
            if ($dealDetails['result']['UF_CRM_1711153543876'] === 'Afra:00014' && $dealDetails['result']['CATEGORY_ID'] === '0') {
                file_put_contents('../var/log/bitrix24_webhook_dansLeIfPartnerAndCategorie_angola_' . date('Y-m-d') . '.log', print_r($dealDetails['result']['UF_CRM_1711153543876'], true), FILE_APPEND);
                // Envoyer un postback à l'API du client
                $this->dealService->sendPostbackToClientANGOLA($dealDetails);
            }
        }
        return new Response('Webhook received and processed', 200);
    }

    /**
     * @Route(
     *     name="first_delivery_update",
     *     path="/update_deals_first",
     *     methods={"GET"}
     * )
     *
     * @return Response
     */
    public function updateDealsByStatusFromBitrix(): JsonResponse
    {
        try {
            // Appel du service pour récupérer et traiter les deals par statut
            $allItemsMail = $this->dealService->processDealsByStatus();
            $this->mailService->sendEmailUpdateDeals('Success', $allItemsMail, 'Global update');

            // Si le traitement est réussi, renvoyer les résultats
            return new JsonResponse([
                'status' => 200,
                'isError' => false,
                'message' => 'Deals updated successfully',
                'totalItems' => count($allItemsMail),
                'result' => $allItemsMail,
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, capturer l'exception et renvoyer un message d'erreur
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route("/deal_postback_send_sms", name="deal_postback_send_sms", methods={"POST"})
     */
    public function handleBitrix24WebhookSendSMS(Request $request): Response
    {
        // Récupérer les données brutes du webhook Bitrix24
        $rawData = $request->getContent();
        parse_str($rawData, $parsedData);

        // Vérifier si la clé 'data' existe avant de l'utiliser
        if (isset($parsedData['data']['FIELDS']['ID'])) {
            $dealId = $parsedData['data']['FIELDS']['ID'];
            // Récupérer les détails complets du deal en utilisant l'ID du deal
            $dealDetails = $this->dealService->fetchDealDetailsWebhook($dealId);

            // Journaliser les détails du deal pour le débogage
            file_put_contents('../var/log/bitrix24_webhook_details_Send_SMS_Confirmation_' . date('Y-m-d') . '.log', print_r($dealDetails, true), FILE_APPEND);

            //if( $dealId === '24069'){
            if ($dealDetails['result']['CATEGORY_ID'] === '8' && $dealDetails['result']['STAGE_ID'] === 'C8:NEW') {

                // $offerId = $dealDetails['result']['UF_CRM_1707164894196'];
                // $offer = $this->offerRepository->findOneBy(['offerId' => $offerId]);
                // $offerName = $offer->getName();
                try {
                    // Log the input before calling getIdValue
                    file_put_contents('../var/log/getIdValue_input_' . date('Y-m-d') . '.log', print_r($dealDetails['result']['UF_CRM_1705749663304'], true), FILE_APPEND);

                    // Call the getIdValue function
                    $offerName = $this->dealService->getIdValue('UF_CRM_1705749663304', $dealDetails['result']['UF_CRM_1705749663304']);
                    file_put_contents('../var/log/getIdValue_output_' . date('Y-m-d') . '.log', print_r($offerName, true), FILE_APPEND);

                    // Log the result of getIdValue
                } catch (\Exception $e) {
                    // Log the error message and details
                    file_put_contents('../var/log/getIdValue_error_' . date('Y-m-d') . '.log', 'Error in getIdValue: ' . $e->getMessage(), FILE_APPEND);
                    return $this->json([
                        'success' => false,
                        'message' => 'Error retrieving offer name: ' . $e->getMessage(),
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                file_put_contents('../var/log/bitrix24_webhook_dansLeIf_Send_SMS_Confirmation_' . date('Y-m-d') . '.log', print_r($dealDetails['result']['CATEGORY_ID'], true), FILE_APPEND);

                // Envoyer un postback à l'API du client
                $this->smsService->sendSmsUrlConfirmation($dealDetails, $offerName);
            }
        }
        return new Response('Webhook received and processed', 200);
    }

    /**
     * @Route(
     *     name="create-commande-deliviroo",
     *     path="/create-commande-DKY",
     *     methods={"GET"}
     * )
     *
     * @return Response
     */

    public function SendCommandDeliverooKY(Request $request): Response
    {
        try {
            $deals = $this->dealService->SendCommandDeliverooKY();

            // Envoyer un email avec les détails de la commande
            $this->mailService->sendEmailSendDealsToDKY('Success', $deals);

            return $this->json([
                'success' => true,
                'result' => $deals,
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, envoyer un email d'erreur
            $this->mailService->sendEmailSendDealsToDKY($e->getMessage(), [], true);

            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * @Route(
     *     name="get_all_deals",
     *     path="/api/get-deals",
     *     methods={"GET"}
     * )

     * @return Response
     */
    public function getAllDeals(Request $request): Response
    {

        $user = $this->security->getUser();
        if (!$user) {
            throw $this->createNotFoundException('Token Invalid ! ');
        }

        $clientId = $user->getTag() . ":" . $user->getUserId();
        $page = $request->get('page');
        $pageSize = $request->get('pageSize');
        $dateStart = $request->get('dateStart');
        $dateEnd = $request->get('dateEnd');
        $offersId = $request->get('offersId');
        $leadsId = $request->get('leadsId');

        $deals = $this->dealService->getAllDealsDB($page, $pageSize, $dateStart, $dateEnd, $clientId, $offersId, $leadsId);

        return $this->json([
            'success' => true,
            'result' => $deals
        ]);
    }
    // /**
    // * @Route("/deal_update_BD_angola", name="deal_update_BD_plumbill_angola", methods={"POST"})
    // */
    // public function UpdateDealBDAngola(Request $request): Response
    // {
    //     // Récupérer les données brutes du webhook Bitrix24
    //     $rawData = $request->getContent();
    //     parse_str($rawData, $parsedData);
    //     // Vérifier si la clé 'data' existe avant de l'utiliser
    //     if (isset($parsedData['data']['FIELDS']['ID'])) {
    //         $dealId = $parsedData['data']['FIELDS']['ID'];
    //         // Récupérer les détails complets du deal en utilisant l'ID du deal
    //         $dealDetails = $this->dealService->fetchDealDetailsWebhookAngola($dealId);
    //         //partner ID
    //         if($dealDetails['result']['CATEGORY_ID'] === '0'){
    //             // Envoyer un postback à l'API du client
    //             $this->dealService->postbackUpdateDataBaseAN($dealDetails);
    //         }
    //     }
    //     return new Response('Webhook received and processed', 200);
    // }


    /**
     * @Route("/deal_update_BD_tunisie", name="deal_update_BD_plumbill_tunisie", methods={"POST"})
     */
    public function UpdateDealBDTunisie(Request $request): Response
    {
        // Récupérer les données brutes du webhook Bitrix24
        $rawData = $request->getContent();
        parse_str($rawData, $parsedData);

        // Vérifier si la clé 'data' existe avant de l'utiliser
        if (isset($parsedData['data']['FIELDS']['ID'])) {
            $dealId = $parsedData['data']['FIELDS']['ID'];
            // Récupérer les détails complets du deal en utilisant l'ID du deal
            $dealDetails = $this->dealService->fetchDealDetailsWebhook($dealId);
            //partner ID
            if (
                $dealDetails['result']['CATEGORY_ID'] === '6'
            ) {
                // Envoyer un postback à l'API du client
                $this->dealService->postbackUpdateDataBaseTN($dealDetails);
            }
            if ($dealDetails['result']['CATEGORY_ID'] === '25') {
                // Envoyer un postback à l'API du client
                $this->dealService->postbackUpdateDataBaseSN($dealDetails);
            }
        }
        return new Response('Webhook received and processed', 200);
    }

    /**
     * @Route("/deal_update_BD_tunisie_test", name="deal_update_BD_plumbill_tunisie_test", methods={"POST"})
     */
    public function UpdateDealBDTunisieTest(Request $request): Response
    {
        // Récupérer les données brutes du webhook Bitrix24
        $rawData = $request->getContent();
        parse_str($rawData, $parsedData);

        // Vérifier si la clé 'data' existe avant de l'utiliser
        if (isset($parsedData['data']['FIELDS']['ID'])) {
            $dealId = $parsedData['data']['FIELDS']['ID'];
            // Récupérer les détails complets du deal en utilisant l'ID du deal
            $dealDetails = $this->dealService->fetchDealDetailsWebhook($dealId);
            //partner ID
            if (
                $dealDetails['result']['CATEGORY_ID'] === '6'
            ) {
                // Envoyer un postback à l'API du client
                $this->dealService->postbackUpdateDataBaseTN($dealDetails);
            }
            if ($dealDetails['result']['CATEGORY_ID'] === '25') {
                // Envoyer un postback à l'API du client
                $this->dealService->postbackUpdateDataBaseSN($dealDetails);
            }
        }
        return new Response('Webhook received and processed', 200);
    }

    /**
     * @Route("/bitrix/deals", name="bitrix_deals")
     */
    public function getDealsBitrixBD(): Response
    {
        $monthStart = '2024-11-15'; // Début du mois
        $monthEnd = '2024-11-17'; // Fin du mois

        $deals = $this->dealService->getDealsForBD($monthStart, $monthEnd);

        return $this->json([
            'status' => 'success',
            'message' => 'Deals synchronized successfully!',
            'total_deals' => count($deals),
        ]);
    }


    /**
     * @Route("/bitrix/custom-postback-tn", name="bitrix_custom_postback_tn",  methods={"POST"})
     */
    public function customPostbackTn(Request $request): Response
    {

        try {
            // Étape 1: Extraire les paramètres de l'URL (GET)
            $queryParams = $request->query->all();

            // Étape 2: Récupérer les valeurs spécifiques
            $partnerId = $queryParams['partner_ID'] ?? 'N/A';
            $newStatus = $queryParams['new_status'] ?? 'N/A';
            $statusNames = $this->dealService->getStatusNamesBYName();
            $newStatus = $statusNames[$newStatus] ?? 'Unknown';
            $leadId = $queryParams['lead_ID'] ?? 'N/A';
            $offerId = $queryParams['offer_ID'] ?? 'N/A';


            if (!$partnerId) {
                return $this->json(['error' => 'Missing partner_ID_Tn'], 400);
            }

            // Étape 3: Loguer les valeurs récupérées (correctement)
            $data = [
                'partner_ID' => $partnerId,
                'new_status' => $newStatus,
                'lead_ID' => $leadId,
                'offer_ID' => $offerId
            ];
            file_put_contents(
                '../var/log/bitrix24_specific_params_tn_' . date('Y-m-d') . '.log',
                print_r($data, true) . PHP_EOL, // Utiliser print_r pour les tableaux
                FILE_APPEND
            );
            // Étape 4 : Envoi direct aux clients
            if ($data['partner_ID'] === 'Op:00020') {
                $this->dealService->relayTOnePartnersTn($data);
            } else {
                $this->dealService->relayToClientsTn($data);
            }

            return new Response('Postback relayed to clients', 200);
        } catch (\Exception $e) {
            // Log exceptions to help with error tracing
            file_put_contents(
                '../var/log/webhook_custom_postback_error_tn_' . date('Y-m-d') . '.log',
                $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL,
                FILE_APPEND
            );

            return new Response('Error processing webhook', 500);
        }
    }
    /**
     * @Route("/bitrix/custom-postback-an", name="bitrix_custom_postback_an",  methods={"POST"})
     */
    public function customPostbackAn(Request $request): Response
    {

        try {
            // Étape 1: Extraire les paramètres de l'URL (GET)
            $queryParams = $request->query->all();

            // Étape 2: Récupérer les valeurs spécifiques
            $partnerId = $queryParams['partner_ID'] ?? 'N/A';
            $newStatus = $queryParams['new_status'] ?? 'N/A';
            $statusNames = $this->dealService->getStatusNamesBYName();
            $newStatus = $statusNames[$newStatus] ?? 'Unknown';
            $leadId = $queryParams['lead_ID'] ?? 'N/A';
            $offerId = $queryParams['offer_ID'] ?? 'N/A';

            if (!$partnerId) {
                return $this->json(['error' => 'Missing partner_ID'], 400);
            }

            // Étape 3: Loguer les valeurs récupérées (correctement)
            $data = [
                'partner_ID' => $partnerId,
                'new_status' => $newStatus,
                'lead_ID' => $leadId,
                'offer_ID' => $offerId
            ];
            file_put_contents(
                '../var/log/bitrix24_specific_params_An_' . date('Y-m-d') . '.log',
                print_r($data, true) . PHP_EOL, // Utiliser print_r pour les tableaux
                FILE_APPEND
            );
            // Étape 4 : Envoi direct aux clients
            if ($data['partner_ID'] === 'Op:00020') {
                $this->dealService->relayTOnePartnersAn($data);
            } else {
                $this->dealService->relayToClientsAn($data);
            }
            return new Response('Postback relayed to clients', 200);
        } catch (\Exception $e) {
            // Log exceptions to help with error tracing
            file_put_contents(
                '../var/log/webhook_custom_postback_error_an_' . date('Y-m-d') . '.log',
                $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL,
                FILE_APPEND
            );

            return new Response('Error processing webhook', 500);
        }
    }
    /**
     * @Route("/bitrix/custom-postback-an-migration", name="bitrix_custom_postback_an",  methods={"POST"})
     */
    public function customPostbackAnMigration(Request $request): Response
    {

        try {
            // Étape 1: Extraire les paramètres de l'URL (GET)
            $queryParams = $request->query->all();

            // Étape 2: Récupérer les valeurs spécifiques
            $partnerId = $queryParams['partner_ID'] ?? 'N/A';
            $newStatus = $queryParams['new_status'] ?? 'N/A';
            $leadId = $queryParams['lead_ID'] ?? 'N/A';
            $offerId = $queryParams['offer_ID'] ?? 'N/A';
            $orderId = $queryParams['order_ID'] ?? 'N/A';
            $createAt = $queryParams['created_At'] ?? 'N/A';
            $id = $queryParams['id'] ?? 'N/A';
            if (!$partnerId) {
                return $this->json(['error' => 'Missing partner_ID'], 400);
            }

            // Étape 3: Loguer les valeurs récupérées (correctement)
            $data = [
                'partner_ID' => $partnerId,
                'new_status' => $newStatus,
                'lead_ID' => $leadId,
                'offer_ID' => $offerId,
                'order_ID' => $orderId,
                'ID' => $id,
                'created_AT' => $createAt,

            ];
            file_put_contents(
                '../var/log/bitrix24_specific_params_An_migration_' . date('Y-m-d') . '.log',
                print_r($data, true) . PHP_EOL, // Utiliser print_r pour les tableaux
                FILE_APPEND
            );
            // Étape 4 : Update la table Deal_an 
            if (isset($data['offer_ID']) && strpos($data['offer_ID'], '3') === 0) {
                $this->dealService->postbackUpdateDataBaseAN($data);
            }

            return new Response('Postback relayed to clients', 200);
        } catch (\Exception $e) {
            // Log exceptions to help with error tracing
            file_put_contents(
                '../var/log/webhook_custom_postback_error_an_migration_' . date('Y-m-d') . '.log',
                $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL,
                FILE_APPEND
            );

            return new Response('Error processing webhook', 500);
        }
    }

    /**
     * Enregistre les logs dans un fichier.
     *
     * @param string $context Contexte de l'erreur
     * @param string $message Message d'erreur
     */
    private function log(string $context, string $message): void
    {
        file_put_contents(__DIR__ . '/handle_bitrix_webhook_log_' . date('Y-m-d') . '.log', sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            $context,
            $message
        ), FILE_APPEND);
    }
}
