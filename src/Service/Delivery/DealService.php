<?php

namespace App\Service\Delivery;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DealService
{

    /**
     *
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     *
     * @var array
     */
    private $fields;

    /**
     * Create a new instance.
     *
     * @param ParameterBagInterface $params
     * @return void
     */
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        $this->fields = $this->fields();
    }

    /**
     * Get all deals
     *
     * @param array $deals
     * @param array $filter
     * @param array $fields
     * @param iint $start
     * 
     * @return array
     */
    public function all($deals = [], $filter = [], $fields = ["*", "UF_*"], $start = 0): array|bool
    {
        try {
            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'filter' => $filter,
                    'order' => ['ID' => 'ASC'],
                    'select' => $fields,
                    'start' => $start
                ]
            ];

            $response = $this->httpClient->request('POST', '', $options);

            if ($response->getStatusCode() !== 200) {
                $this->logError("Error processing DealService all - ", 'Erreur lors de la requête list à Bitrix');
                return false;
            }

            $result = json_decode($response->getContent(), true);

            $ids = array_column($result['result'], 'CONTACT_ID');

            $contacts = $ids ? $this->contacts(["@ID" => $ids]) : [];

            foreach ($result['result'] as $deal) {

                $deal['contact'] = array_values(array_filter($contacts, function ($contact) use ($deal) {
                    return ($contact['ID'] == $deal['CONTACT_ID']);
                }));

                $deals[] = $this->normalize($deal);
            }

            if (isset($result['next'])) {
                $deals = $this->all($deals, $filter, $fields, (int)$result['next']);
            }

            return $deals;
        } catch (\Exception $e) {
            $this->logError("Error processing DealService all - ", $e->getMessage());
        }

        return false;
    }

    /**
     * Update deal
     *
     * @param int $deal
     * @param array $fields
     * 
     * @return bool
     */
    public function update(int $deal, array $fields, array $params = []): bool
    {
        try {
            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'id' => $deal,
                    'fields' => $fields,
                    'params' => $params
                ]
            ];

            $response = $this->httpClient->request('POST', '', $options);

            if ($response->getStatusCode() !== 200) {
                $this->logError("Error processing DealService update - ", 'Erreur lors de la requête update à Bitrix');
                return false;
            }

            $result = json_decode($response->getContent(), true);

            return $result['result'];
        } catch (\Exception $e) {
            $this->logError("Error processing DealService update - ", $e->getMessage());
        }

        return false;
    }

    /**
     * ExecuteBatch
     *
     * @param array $commands
     * @param int $size
     * 
     * @return bool
     */
    public function executeBatch(array $commands, $size = 10): bool
    {
        try {
            $batched = array_chunk($commands, $size);

            foreach ($batched as $batch) {
                $payload = [];

                foreach ($batch as $index => $cmd) {
                    $payload["cmd$index"] = $cmd;
                }

                $options = [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'cmd' => $payload
                    ]
                ];

                $response = $this->httpClient->request('POST', '', $options);

                if ($response->getStatusCode() !== 200) {
                    $this->logError("Error processing DealService executeBatch - ", 'Erreur lors de la requête batch à Bitrix');
                    return false;
                }

                sleep(1);
            }

            return true;
        } catch (\Exception $e) {
            $this->logError("Error processing DealService executeBatch - ", $e->getMessage());
        }

        return false;
    }


    /**
     * Get all fields
     *
     * 
     * @return array
     */
    public function fields(): array
    {
        try {
            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ];

            $response = $this->httpClient->request('POST', '', $options);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $result = json_decode($response->getContent(), true);

            return $result['result'];
        } catch (\Exception $e) {
            $this->logError("Error processing DealService - ", $e->getMessage());
        }

        return [];
    }

    /**
     * Get all contacts
     *
     * @param array $filter
     * @param array $fields
     * @param iint $start
     * 
     * @return array
     */
    public function contacts($filter = [], $fields = ["EMAIL", "PHONE", "NAME", "LAST_NAME"], $start = 0): array
    {
        try {
            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'filter' => $filter,
                    'order' => ['ID' => 'ASC'],
                    'select' => $fields,
                    'start' => $start
                ]
            ];

            $response = $this->httpClient->request('POST', '', $options);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $result = json_decode($response->getContent(), true);

            return $result['result'];
        } catch (\Exception $e) {
            $this->logError("Error processing DealService - ", $e->getMessage());
        }

        return [];
    }

    /**
     * Normalized Deal format
     *
     * @param array $deal
     * 
     * @return array
     */
    private function normalize(array $deal): array
    {
        $normalized = [
            'id' => $deal['ID'],
            'title' => $deal['TITLE'],
            'stage' => $deal['STAGE_ID'],
            'category' => $deal['CATEGORY_ID'],
            'order' => $deal['UF_CRM_1731502147567'],
            'lead' => $deal['UF_CRM_1707163372336'],
            'webmaster' => $deal['UF_CRM_1707162327037'],
            'trackNumber' => $deal['UF_CRM_1719699229165'],
            'company' => $deal['UF_CRM_1738157700839'],
            'created_at' => $deal['DATE_CREATE'],
            'contact' => [
                'id' => $deal['CONTACT_ID'],
                'full_name' => $deal['TITLE'],
                'first_name' => $deal['contact'][0]['NAME'],
                'last_name' => $deal['contact'][0]['LAST_NAME'],
                'phone' => $deal['contact'][0]['PHONE'][0]['VALUE'] ?? null,
                'email' => $deal['contact'][0]['EMAIL'][0]['VALUE'] ?? null,
                'address' => $deal['UF_CRM_1705750486330'],
                // 'city' => $this->getFieldValue('UF_CRM_1705750299634', $deal['UF_CRM_1705750299634']),
                'city' => $this->getFieldValue('UF_CRM_1741263417', $deal['UF_CRM_1741263417']),
                'region' => $this->getFieldValue('UF_CRM_1705750225691', $deal['UF_CRM_1705750225691']),
                'apartment' => $deal['UF_CRM_1705750501722'],
                'zip_code' => ''
            ],
            'offer' => [
                'id' => $deal['UF_CRM_1707164894196'],
                'name' => str_replace('TN ', '', $this->getFieldValue('UF_CRM_1705749663304', $deal['UF_CRM_1705749663304'])),
                'quantity' => $this->getFieldValue('UF_CRM_1705749715974', $deal['UF_CRM_1705749715974']),
                'price' => $this->getFieldValue('UF_CRM_1705750463237', $deal['UF_CRM_1705750463237']),
            ],
        ];

        return $normalized;
    }

    /**
     * Get field value
     *
     * @param string $name
     * @param string $mvalue
     */
    private function getFieldValue(string $name, $value): ?string
    {
        foreach ($this->fields as $fieldName => $field) {
            if ($name === $fieldName) {
                foreach ($field['items'] as $item) {
                    if ($item['ID'] === $value) {
                        return $item['VALUE'];
                    }
                }
            }
        }

        return null;
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
