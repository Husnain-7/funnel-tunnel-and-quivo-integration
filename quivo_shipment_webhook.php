<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection
$mysqli = new mysqli("localhost", "apidoctorz_bclix", "bclix@768", "apidoctorz_funnel-tunnel");
if ($mysqli->connect_error) {
    http_response_code(500);
    die("DB Connection failed: " . $mysqli->connect_error);
}

// Get raw webhook payload
$rawPayload = file_get_contents("php://input");

// Save raw payload into DB
$stmt = $mysqli->prepare("INSERT INTO shipment_data (payload) VALUES (?)");
$stmt->bind_param("s", $rawPayload);
$stmt->execute();
$stmt->close();
$mysqli->close();

// Respond to Quivo
http_response_code(200);
echo json_encode(["status" => "ok"]);
