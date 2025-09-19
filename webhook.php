<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === DB Config ===
$host   = "localhost";
$user   = "apidoctorz_bclix";
$pass   = "bclix@768";
$dbname = "apidoctorz_funnel-tunnel";

// === Get Raw Payload ===
$rawPayload = file_get_contents("php://input");


file_put_contents("webhook_debug.log", date("Y-m-d H:i:s") . " | Payload: " . $rawPayload . "\n", FILE_APPEND);

// === Verify Webhook Signature ===
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$secret    = "4876246@funnel_tunnel"; 

$calculated = hash_hmac('sha512', $rawPayload, $secret);
// if (!hash_equals($calculated, $signature)) {
//     http_response_code(401);
//     die("Invalid signature");
//}

// === DB Connection ===
$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    http_response_code(500);
    die(json_encode(["status"=>"error","message"=>"DB Connection Failed: ".$mysqli->connect_error]));
}
$event = "ft_response";
$logSql = "INSERT INTO webhook_logs (event, payload) VALUES (?, ?)";
$logStmt = $mysqli->prepare($logSql);
$logStmt->bind_param("ss", $event, $rawPayload);
$logStmt->execute();
$logStmt->close();
// === Decode JSON ===
$data = json_decode($rawPayload, true);
if (!$data) {
    http_response_code(400);
    die(json_encode(["status"=>"error","message"=>"Invalid JSON"]));
}
// die();
// Extract token (assuming payload structure has it at ["token"])
$ft_token = isset($data['token']) ? $data['token'] : null;

// === Save to DB ===
// === Extract DB fields ===
$orderId       = $data['id'] ?? null;
$invoiceNo     = $data['invoiceNo'] ?? null;
$created       = !empty($data['created']) ? date("Y-m-d H:i:s", intval($data['created']/1000)) : null;
$customerName  = $data['customerName'] ?? null;
$customerEmail = $data['customerEmail'] ?? null;
$subtotal      = $data['subTotal'] ?? 0;
$total         = $data['total'] ?? 0;
$paymentMethod = $data['paymentMethod'] ?? null;
$paid          = !empty($data['paid']) && $data['paid'] === true ? 1 : 0;
$status        = $data['status'] ?? "pending";
$itemsJson     = json_encode($data['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$rawJson       = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// New columns
$ft_token  = $data['token'] ?? null;
$quivo_id  = "";
$remarks   = "Webhook received";
$createdDb = date("Y-m-d H:i:s");

// === Save to DB (Insert or Update) ===
$stmt = $mysqli->prepare("
    INSERT INTO orders 
    (ft_invoice_no, ft_id, ft_token, quivo_id, remarks, created_at, customer_name, customer_email, subtotal, total, payment_method, paid, status, items, raw_payload) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        ft_invoice_no = VALUES(ft_invoice_no),
        ft_id = VALUES(ft_id),
        ft_token = VALUES(ft_token),
        quivo_id = VALUES(quivo_id),
        remarks = VALUES(remarks),
        created_at = VALUES(created_at),
        customer_name = VALUES(customer_name),
        customer_email = VALUES(customer_email),
        subtotal = VALUES(subtotal),
        total = VALUES(total),
        payment_method = VALUES(payment_method),
        paid = VALUES(paid),
        status = VALUES(status),
        items = VALUES(items),
        raw_payload = VALUES(raw_payload)
");
$stmt->bind_param(
    "ssssssssddsisss", 
    $invoiceNo,     // string
    $orderId,       // string
    $ft_token,      // string
    $quivo_id,      // string
    $remarks,       // string
    $created,       // string (datetime)
    $customerName,  // string
    $customerEmail, // string
    $subtotal,      // double
    $total,         // double
    $paymentMethod, // string
    $paid,          // integer (tinyint)
    $status,        // string
    $itemsJson,     // string
    $rawJson        // string
);

$stmt->execute();
if ($stmt->error) {
    file_put_contents("webhook_debug.log", "DB Insert Error: " . $stmt->error . "\n", FILE_APPEND);
}

$stmt->close();

// === Map to Quivo Format ===
$orderDate = isset($data['created']) ? date('c', intval($data['created']/1000)) : date('c');
$quivoOrder = [
    "sellerId" => 768,
    // "warehouseId" => 5,
    "orderIdentifier" => (string)($data['id'] ?? uniqid("FT-")),
    "orderReference" => ($data['invoiceNo'] ?? "NA"),
    // "orderDate" => $orderDate,
    "currencyCode" => "USD",
    "deliveryAddress" => [
        "name"    => !empty($data['customerName']) ?$data['customerName'] : "Guest Customer",
        "company" => $data['shippingAddress']['companyName'] ?? "",
        "street"    => $data['shippingAddress']['address'] ?? "",
        "city"      => $data['shippingAddress']['city'] ?? "",
        "zip"=> $data['shippingAddress']['zipCode'] ?? "",
        "countryIso2"   => "AT",//$data['shippingAddress']['country'] ?? '',
        "email"     => $data['customerEmail'] ?? "",
        "phone"     => $data['shippingAddress']['phone'] ?? ""
    ],
    "positions" => array_map(function($item){
        return [
            "sku" => '2233456',//$item['sku'] ?? (string)($item['productId'] ?? ""),
            "name"       => 'Beclix Software' ?? "",
            "quantity"          => $item['quantity'] ?? 0
        ];
    }, $data['items'] ?? [])
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

// === Send to Quivo if token exists ===
$newStatus = "pending";
$response = null;
$httpCode = 0;
if (!$token) {
    $remarks = "Quivo login failed";
} elseif ($response) {
    // existing logic
} else {
    $remarks = "No response from Quivo API";
}

// echo $token;
if(isset($token)){
    
    // echo json_encode($quivoOrder);
    $ch = curl_init("https://api-sandbox.quivo.co/orders");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Api-Key: AR40So8PKE5GT8ou9k99157hiKSfTMTK9Sol9F5z",
        "Authorization: $token"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($quivoOrder));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $newStatus = ($httpCode == 200) ? "synced" : "pending";
}
// === Prepare vars ===
$quivoOrderId = null; // default
$remarks = "Webhook received";

// After Quivo API call
if ($response) {
    $decodedResponse = json_decode($response, true);

    if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'ok') {
        $remarks = "Order created successfully in Quivo";
        $quivoOrderId = $decodedResponse['orderId'] ?? null;
    } elseif (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
        $remarks = "Quivo Error: " . ($decodedResponse['message'] ?? "Unknown error");
    } else {
        $remarks = "Unexpected Quivo response: " . substr($response, 0, 255);
    }
} else {
    $remarks = "No response from Quivo API";
}

// === Update order in DB ===
$updateStmt = $mysqli->prepare("UPDATE orders SET status = ?, quivo_id = ?, remarks = ? WHERE ft_id = ?");
$updateStmt->bind_param("ssss", $newStatus, $quivoOrderId, $remarks, $orderId);
$updateStmt->execute();
$updateStmt->close();

// === Log Quivo response in webhook_logs ===
$event = "quivo_response";
$logSql = "INSERT INTO webhook_logs (event, payload) VALUES (?, ?)";
$logStmt = $mysqli->prepare($logSql);
$logStmt->bind_param("ss", $event, $response);
$logStmt->execute();
$logStmt->close();

// === Log Quivo response also in file ===
file_put_contents("quivo_response.log", date("Y-m-d H:i:s") . " | HTTP $httpCode | $response\n", FILE_APPEND);

// === Final response to Funnel Tunnel ===
http_response_code(200);
echo json_encode([
    "status" => "success",
    "db_status" => "saved",
    "sync_status" => $newStatus,
    "quivo_response" => $response
]);

$mysqli->close();
