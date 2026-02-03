<?php

namespace App\Service\Delivery;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AramexService
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
        $this->config = $params->get('delivery')['aramex'];

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
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($this->payload($deal)),
            ];

            $response = $this->httpClient->request('POST', $this->config['endpoint'] . 'CreateShipments', $options);

            if ($response->getStatusCode() === 200) {
                $result = json_decode($response->getContent(), true);

                if (isset($result['HasErrors']) && $result['HasErrors']) {
                    if (isset($result['Notifications'][0]['Message'])) {
                        return [
                            'success' => false,
                            'error' => $result['Notifications'][0]['Message'],
                            'company' => 'ARAMEX'
                        ];
                    } elseif (isset($result['Shipments'][0]['Notifications'])) {
                        $errors = " ";
                        foreach ($result['Shipments'][0]['Notifications'] as $notification) {
                            if (isset($notification['Code']) && isset($notification['Message'])) {
                                $errors .= $notification['Message'];
                            }
                        }

                        return [
                            'success' => false,
                            'error' => $errors,
                            'company' => 'ARAMEX'
                        ];
                    } else {
                        return [
                            'success' => false,
                            'error' => 'Erreur de livraison.',
                            'company' => 'ARAMEX'
                        ];
                    }
                }

                return [
                    'success' => true,
                    'id' => $result['Shipments'][0]['ID'],
                    'label' => $result['Shipments'][0]['ShipmentLabel']['LabelURL'],
                    'company' => 'ARAMEX'
                ];
            }

            return [
                'success' => false,
                'error' => $response->getContent(false),
                'company' => 'ARAMEX'
            ];
        } catch (\Exception $e) {
            $this->logError("Error processing AramexService {$deal['id']} - ", $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'company' => 'ARAMEX'
            ];
        }
    }

    /**
     * Track Shipments Status.
     *
     * @param mixed $ids
     * @return string|bool
     */
    public function track(mixed $ids): string|bool
    {
        try {
            $body = [
                'Shipments' => is_array($ids) ? $ids : [$ids],
                'ClientInfo' => [
                    'UserName' => $this->config['username'],
                    'Password' => $this->config['password'],
                    'Version' => $this->config['version'],
                    'AccountNumber' => $this->config['account_number'],
                    'AccountPin' => $this->config['account_pin'],
                    'AccountEntity' => $this->config['account_entity'],
                    'AccountCountryCode' => $this->config['account_country_code'],
                ],
                'Transaction' => [
                    'Reference1' => '',
                    'Reference2' => '',
                    'Reference3' => '',
                    'Reference4' => '',
                    'Reference5' => ''
                ],
                'GetLastTrackingUpdateOnly' => true
            ];

            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($body),
            ];

            $response = $this->httpClient->request('POST', $this->config['tracking_endpoint'], $options);

            if ($response->getStatusCode() === 200) {
                $result = json_decode($response->getContent(), true);

                if (isset($result['HasErrors']) && $result['HasErrors']) {
                    if (isset($result['Notifications'][0]['Message'])) {
                        $this->logError("Error processing AramexService Track {$ids} - ", $result['Notifications'][0]['Message']);
                        return false;
                    } elseif (isset($result['Shipments'][0]['Notifications'])) {
                        $errors = " ";
                        foreach ($result['Shipments'][0]['Notifications'] as $notification) {
                            if (isset($notification['Code']) && isset($notification['Message'])) {
                                $errors .= $notification['Message'];
                            }
                        }

                        $this->logError("Error processing AramexService Track {$ids} - ", $errors);

                        return false;
                    } else {
                        $this->logError("Error processing AramexService Track {$ids} - ", 'Erreur de livraison.');

                        return false;
                    }
                }

                if (isset($result['TrackingResults'][0]['Value'])) {
                    $code = $result['TrackingResults'][0]['Value'][0]['ProblemCode'];
                    $status = $result['TrackingResults'][0]['Value'][0]['UpdateCode'];
                    switch ($status) {
                        case 'SH014':
                        case 'SH017':
                        case 'SH024':
                        case 'SH030':
                            return false;
                        case 'SH071':
                        case 'SH069':
                            $status = 'C8:LOSE';
                            break;
                        case 'SH005':
                            $status = 'C8:WON';
                            break;
                        case 'SH006':
                        case 'SH007':
                        case 'SH234':
                        case 'SH496':
                        case 'SH597':
                        case 'SH534':
                            $status = 'C8:EXECUTING'; //Delivered
                            break;
                        case 'SH070':
                        case 'SH380':
                        case 'SH539':
                        case 'SH514':
                            $status = 'C8:FINAL_INVOICE'; //Returned
                            break;
                        default:
                            $status = 'C8:PREPAYMENT_INVOICE'; //Shipping
                            break;
                    }

                    return $status;
                }
            }

            $this->logError("Error processing AramexService Track {$ids} - ", $response->getContent(false));
        } catch (\Exception $e) {
            $this->logError("Error processing AramexService Track {$ids} - ", $e->getMessage());
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
        $timestamp = time();

        return [
            'Shipments' => [
                [
                    'Reference1' => $deal['order'],
                    'Reference2' => null,
                    'Reference3' => null,
                    'Shipper' => [
                        'Reference1' => null,
                        'Reference2' => null,
                        'AccountNumber' => $this->config['account_number'],
                        'PartyAddress' => [
                            'Line1' => 'Avenue Kuwait',
                            'Line2' => '',
                            'Line3' => null,
                            'City' => 'Nabeul',
                            'StateOrProvinceCode' => '8050',
                            'PostCode' => '8050',
                            'CountryCode' => $this->config['account_country_code'],
                            'Longitude' => 0,
                            'Latitude' => 0,
                        ],
                        'Contact' => [
                            'Department' => null,
                            'PersonName' => 'MC Shopy',
                            'Title' => null,
                            'CompanyName' => 'Shopywall-sw',
                            'PhoneNumber1' => '99112219',
                            'PhoneNumber1Ext' => null,
                            'PhoneNumber2' => null,
                            'PhoneNumber2Ext' => null,
                            'FaxNumber' => null,
                            'CellPhone' => '99112219',
                            'EmailAddress' => 'ms.shopy.shop@gmail.com',
                            'Type' => null
                        ]
                    ],
                    'Consignee' => [
                        'Reference1' => null,
                        'Reference2' => null,
                        'AccountNumber' => null,
                        'PartyAddress' => [
                            'Line1' => $deal['contact']['address'],
                            'Line2' => null,
                            'Line3' => null,
                            'City' => $deal['contact']['city'],//$deal['contact']['region'],
                            'StateOrProvinceCode' => null,
                            'PostCode' => null,
                            'CountryCode' => $this->config['account_country_code'],
                            'Longitude' => 0,
                            'Latitude' => 0,
                            'BuildingNumber' => null,
                            'BuildingName' => null,
                            'Floor' => null,
                            'Apartment' => null,
                            'POBox' => null,
                            'Description' => null
                        ],
                        'Contact' => [
                            'Department' => null,
                            'PersonName' => $deal['contact']['full_name'],
                            'Title' => null,
                            'CompanyName' => $deal['contact']['full_name'],
                            'PhoneNumber1' => $deal['contact']['phone'],
                            'PhoneNumber1Ext' => null,
                            'PhoneNumber2' => null,
                            'PhoneNumber2Ext' => null,
                            'FaxNumber' => null,
                            'CellPhone' => $deal['contact']['phone'],
                            'EmailAddress' => $deal['contact']['email'],
                            'Type' => null
                        ]
                    ],
                    'ThirdParty' => null,
                    'ShippingDateTime' => "/Date({$timestamp}000)/",
                    'DueDate' => "/Date({$timestamp}000)/",
                    'Comments' => null,
                    'PickupLocation' => null,
                    'OperationsInstructions' => null,
                    'AccountingInstrcutions' => null,
                    'Details' => [
                        'Dimensions' => null,
                        'ActualWeight' => [
                            'Unit' => 'KG',
                            'Value' => 0.5
                        ],
                        'ChargeableWeight' => null,
                        'DescriptionOfGoods' => $deal['offer']['name'], // . ' QTE : ' . $deal['offer']['quantity'],
                        'GoodsOriginCountry' => $this->config['account_country_code'],
                        'NumberOfPieces' => $deal['offer']['quantity'],
                        'ProductGroup' => 'DOM',
                        'ProductType' => 'ONP',
                        'PaymentType' => 'P',
                        'PaymentOptions' => null,
                        'CustomsValueAmount' => null,
                        'CashOnDeliveryAmount' =>  [
                            'CurrencyCode' => 'TND',
                            'Value' => $deal['offer']['price']
                        ],
                        'InsuranceAmount' => null,
                        'CashAdditionalAmount' => null,
                        'CashAdditionalAmountDescription' => null,
                        'CollectAmount' => null,
                        'Services' => 'CODS',
                        'Items' => [
                            'PackageType' => 'Box',
                            'Quantity' => $deal['offer']['quantity'],
                            'Weight' => [
                                'Value' => 0.5,
                                'Unit' => 'Kg',
                            ],
                            'Comments' => $deal['offer']['name'] . ' QTE : ' . $deal['offer']['quantity'],
                            'Reference' => null
                        ],
                        'DeliveryInstructions' => null,
                        'AdditionalProperties' => null,
                        'ContainsDangerousGoods' => false
                    ],
                    'Attachments' => null,
                    'ForeignHAWB' => '',
                    'TransportType ' => 0,
                    'PickupGUID' => '',
                    'Number' => '',
                    'ScheduledDelivery' => null
                ]
            ],
            'LabelInfo' => [
                'ReportID' => 9824,
                'ReportType' => 'URL'
            ],
            'ClientInfo' => [
                'UserName' => $this->config['username'],
                'Password' => $this->config['password'],
                'Version' => $this->config['version'],
                'AccountNumber' => $this->config['account_number'],
                'AccountPin' => $this->config['account_pin'],
                'AccountEntity' => $this->config['account_entity'],
                'AccountCountryCode' => $this->config['account_country_code'],
            ],
            'Transaction' => [
                'Reference1' => '',
                'Reference2' => '',
                'Reference3' => '',
                'Reference4' => '',
                'Reference5' => ''
            ]
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
