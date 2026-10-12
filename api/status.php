<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/config.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    echo json_encode(['ok' => false, 'status' => 'pendente']);
    exit;
}

$ch = curl_init(DUTTYFY_URL . '?transactionId=' . urlencode($id));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode((string) $resp, true);
$raw = strtolower((string) ($data['status'] ?? ''));
$pago = $http >= 200 && $http < 300 && in_array($raw, ['completed', 'paid', 'approved', 'confirmed'], true);

echo json_encode(['ok' => $http >= 200 && $http < 300, 'status' => $pago ? 'pago' : 'pendente']);
