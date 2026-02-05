<?php
const API_URL = "https://themostaffiliate.com/api/add-deals";
const API_KEY = "e413b019ef8c2cbaa8dfb2c522c5bcb6";


$data = [
    'deals' => [
        [
            'offer' => '2006',
            'contact' => [
                'name' => isset($_POST["name"]) ? $_POST["name"] : " ",
                'telephone' => isset($_POST["phone"]) ? $_POST["phone"] : " "  
            ],
            'webmasterID' => '00002',
            'leadID' => rand()
        ]
    ]
];

$headers = [
    'Content-Type: application/json',
    'X-Api-Key: ' . API_KEY,
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => API_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => $headers,
]);

try {
    $res = curl_exec($curl);

    if ($res === false) {
        throw new Exception('Curl error: ' . curl_error($curl));
    }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($httpCode != 200) {
        throw new Exception('HTTP Error: ' . $httpCode);
    }

    $decodedRes = json_decode($res, true);

    if ($decodedRes === null) {
        throw new Exception('Error decoding JSON: ' . json_last_error_msg());
    }

    if (isset($decodedRes['successes'][0]['lead_ID'])) {
        header("Location: ok.php?id=" . $decodedRes['successes'][0]['lead_ID']);
    } else {
        var_dump($decodedRes);
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
} finally {
    curl_close($curl);
}  

?>
