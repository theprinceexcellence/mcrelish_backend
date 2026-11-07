<?php
// A tiny stand-in for the Flutterwave API, used only by tests/smoke.php.
//   php -S 127.0.0.1:9100 tests/mock-flutterwave.php
// Point flutterwave.base_url at http://127.0.0.1:9100/v3 while testing.
$file = sys_get_temp_dir() . '/mock-flw.json';
$db = is_file($file) ? json_decode((string) file_get_contents($file), true) : ['next' => 1000, 'tx' => []];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') === '') { http_response_code(401); exit('{"status":"error"}'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/v3/payments') {
    $in = json_decode(file_get_contents('php://input'), true);
    $id = $db['next']++;
    $name = (string) ($in['customer']['name'] ?? '');
    $db['tx'][$id] = [
        'id' => $id, 'tx_ref' => $in['tx_ref'], 'amount' => stripos($name, 'underpay') === 0 ? $in['amount'] - 100 : $in['amount'],
        'currency' => $in['currency'], 'status' => stripos($name, 'declined') === 0 ? 'failed' : 'successful',
    ];
    file_put_contents($file, json_encode($db));
    echo json_encode(['status' => 'success', 'data' => ['link' => "https://checkout.flutterwave.com/mock/$id"]]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^/v3/transactions/(\d+)/verify$#', $path, $m) && isset($db['tx'][(int) $m[1]])) {
    echo json_encode(['status' => 'success', 'data' => $db['tx'][(int) $m[1]]]);
} else {
    http_response_code(404);
    echo '{"status":"error","message":"not found"}';
}
