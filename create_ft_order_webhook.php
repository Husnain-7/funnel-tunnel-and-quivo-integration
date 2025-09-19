<?php
$ch = curl_init();

$payload = [
  "target" => "https://apidoctorz.com/funnel_tunnel/webhook.php",
  "secret" => "4876246@funnel_tunnel",
  "events" => ["order_created", "order_updated"]
];

curl_setopt($ch, CURLOPT_URL, "https://husnainbinashraf7001341.funnel-tunnel.com/api/site/webhooks");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);

curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json",
  "Authorization: Bearer w0238a36394c44f209b8663b038d4541d"
]);

$response = curl_exec($ch);

if(curl_errno($ch)){
    echo "cURL error: " . curl_error($ch);
}
curl_close($ch);

var_dump($response);
