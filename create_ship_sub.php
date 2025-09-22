<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === DB Config ===
$host   = "localhost";
$user   = "u534957383_husnain7z";
$pass   = "4876246@Hostinger";
$dbname = "u534957383_funnel_tunnel";

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die("DB Connection failed: " . $mysqli->connect_error);
}

$ship_payload = [
    "sellerId"=> 768,
    "entity"=> "SHIPMENTS",
    "endpoint"=> [
        "type" => "WEBHOOK",
        "url"  => "https://bclix.tech/funnel_tunnel/quivo_shipment_webhook.php"
    ]
];

// === Quivo Login ===
$loginPayload = [
    "username" => "thomas.feuerstein",
    "password" => "!QUIconnFEUSTO2025*"
];

$ch = curl_init("https://api-sandbox.quivo.co/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "X-Api-Key: AR40So8PKE5GT8ou9k99157hiKSfTMTK9Sol9F5z"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginPayload));
$loginResponse = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


curl_close($ch);

if ($loginHttpCode !== 200) {
    file_put_contents("quivo_response.log", date("Y-m-d H:i:s")." | LOGIN FAILED | $loginResponse\n", FILE_APPEND);
    $token = null;
} else {
    $loginData = json_decode($loginResponse,true);
    $token = $loginData["Token"] ?? null;
}

$response = null;


if(isset($token)){
    $ch = curl_init("https://api-sandbox.quivo.co/subscriptions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Api-Key: AR40So8PKE5GT8ou9k99157hiKSfTMTK9Sol9F5z",
        "Authorization: $token"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ship_payload));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Response: " . $response;  // This will show the response on screen
}

$event = "shipment_subscription_booked";
$responseStr = (string)$response;

// Only log if the webhook creation was successful (HTTP 200 or 201)
if ($httpCode == 200 || $httpCode == 201) {
    $logSql = "INSERT INTO webhook_logs (event, payload) VALUES (?, ?)";
    
    if ($logStmt = $mysqli->prepare($logSql)) {
    $logStmt->bind_param("ss", $event, $responseStr);
    $logStmt->execute();
        $logStmt->close();
    } else {
        echo "DB log failed: " . $mysqli->error;
    }
} else {
    echo "Webhook creation failed with HTTP code: " . $httpCode;
}

$mysqli->close();
