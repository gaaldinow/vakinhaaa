<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';

function responder($dados, $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function cpfAleatorio()
{
    $n = [];
    for ($i = 0; $i < 9; $i++) $n[] = random_int(0, 9);
    for ($d = 0; $d < 2; $d++) {
        $soma = 0;
        $peso = count($n) + 1;
        foreach ($n as $i => $v) $soma += $v * ($peso - $i);
        $r = $soma % 11;
        $n[] = $r < 2 ? 0 : 11 - $r;
    }
    return implode('', $n);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['ok' => false, 'error' => 'Método inválido'], 405);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) responder(['ok' => false, 'error' => 'Payload inválido'], 400);

$centavos = (int) round(((float) ($in['value'] ?? 0)) * 100);
if ($centavos < 100) responder(['ok' => false, 'error' => 'Valor mínimo não atingido'], 400);

$c = is_array($in['customer'] ?? null) ? $in['customer'] : [];
$nome = trim((string) ($c['name'] ?? '')) ?: 'Doador Anônimo';
$email = trim((string) ($c['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = 'doador' . random_int(1000, 99999) . '@gmail.com';

$doc = preg_replace('/\D/', '', (string) ($c['taxID'] ?? $c['document'] ?? ''));
if (strlen($doc) !== 11 && strlen($doc) !== 14) $doc = cpfAleatorio();

$tel = preg_replace('/\D/', '', (string) ($c['phone'] ?? ''));
if (strlen($tel) > 11 && str_starts_with($tel, '55')) $tel = substr($tel, 2);
if (strlen($tel) < 10 || strlen($tel) > 11) $tel = '119' . random_int(10000000, 99999999);

$payload = [
    'amount' => $centavos,
    'customer' => ['name' => $nome, 'document' => $doc, 'email' => $email, 'phone' => $tel],
    'item' => ['title' => PIX_ITEM_TITLE, 'price' => $centavos, 'quantity' => 1],
    'paymentMethod' => 'PIX',
];
$utm = trim((string) ($in['utm'] ?? ''));
if ($utm !== '') $payload['utm'] = $utm;

$ch = curl_init(DUTTYFY_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30,
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode((string) $resp, true);
if ($resp === false || $http < 200 || $http >= 300 || empty($data['pixCode']) || empty($data['transactionId'])) {
    responder(['ok' => false, 'error' => 'Falha ao gerar Pix'], 502);
}

responder([
    'ok' => true,
    'transaction_id' => $data['transactionId'],
    'pix_code' => $data['pixCode'],
]);
