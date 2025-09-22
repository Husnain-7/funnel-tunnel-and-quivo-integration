<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection
$mysqli = new mysqli("localhost", "u534957383_husnain7z", "4876246@Hostinger", "u534957383_funnel_tunnel");
if ($mysqli->connect_error) {
    http_response_code(500);
    die("DB Connection failed: " . $mysqli->connect_error);
}

// Get raw webhook payload
$rawPayload = file_get_contents("php://input");
$data = json_decode($rawPayload, true);
if (!$data) {
    http_response_code(400);
    die(json_encode(["status"=>"error","message"=>"Invalid JSON"]));
}


// Save raw payload into DB
$stmt = $mysqli->prepare("INSERT INTO shipment_data (payload) VALUES (?)");
$stmt->bind_param("s", $rawPayload);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $stmt->error]);
    exit;
}

$stmt->close();
$mysqli->close();

// Respond to Quivo
http_response_code(200);
echo json_encode(["status" => "ok"]);
