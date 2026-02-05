<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GetFormController extends AbstractController
{
    private const API_URL = 'http://nginx/api/add-deals';
    private const API_KEY = 'e413b019ef8c2cbaa8dfb2c522c5bcb6';
    private const OFFER_ID = '2006';
    private const WEBMASTER_ID = '00002';

    /**
     * @Route("/", name="getform_root", methods={"GET","POST"})
     * @Route("/getform", name="getform", methods={"GET","POST"})
     */
    public function handle(Request $request, HttpClientInterface $httpClient): Response
    {
        $name = (string) $request->request->get('name', '');
        $phone = (string) $request->request->get('phone', '');

        $result = null;
        $error = null;
        $statusCode = null;

        if ($request->isMethod('POST')) {
            $payload = [
                'deals' => [
                    [
                        'offer' => self::OFFER_ID,
                        'contact' => [
                            'name' => $name !== '' ? $name : ' ',
                            'telephone' => $phone !== '' ? $phone : ' ',
                        ],
                        'webmasterID' => self::WEBMASTER_ID,
                        'leadID' => random_int(1, PHP_INT_MAX),
                    ],
                ],
            ];

            try {
                $response = $httpClient->request('POST', self::API_URL, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Api-Key' => self::API_KEY,
                    ],
                    'json' => $payload,
                ]);

                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
                $decoded = json_decode($content, true);

                if ($statusCode !== 200) {
                    $error = 'HTTP Error: ' . $statusCode;
                    $result = $decoded ?? $content;
                } elseif ($decoded === null) {
                    $error = 'Error decoding JSON: ' . json_last_error_msg();
                } else {
                    $result = $decoded;
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('getform.html.twig', [
            'name' => $name,
            'phone' => $phone,
            'result' => $result,
            'error' => $error,
            'statusCode' => $statusCode,
        ]);
    }
}
