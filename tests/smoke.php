<?php
// End-to-end check of the API. Run it against a running server:
//   php tests/smoke.php http://127.0.0.1:8080 admin@example.com "admin-password"
// Needs: config.php with flutterwave.base_url pointing at tests/mock-flutterwave.php and simulate_payments=false.
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$adminEmail = $argv[2] ?? 'admin@test.com';
$adminPass = $argv[3] ?? 'Passw0rd!x';
$origin = $argv[4] ?? 'http://localhost:5173';
$pass = 0; $fail = 0;

function call(string $method, string $path, ?array $body = null, string $token = '', array $headers = []): array
{
    global $base, $origin;
    $ch = curl_init($base . $path);
    $h = ['Accept: application/json', 'Origin: ' . $origin];
    if ($token !== '') $h[] = 'X-Auth-Token: ' . $token;
    foreach ($headers as $x) $h[] = $x;
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $opts[CURLOPT_POSTFIELDS] = json_encode($body); }
    $opts[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $rawBody = substr($raw, $headerSize);
    return ['status' => $status, 'json' => json_decode($rawBody, true), 'raw' => $rawBody, 'headers' => substr($raw, 0, $headerSize)];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok   $label\n"; } else { $fail++; echo "  FAIL $label" . ($detail !== '' ? "  -> $detail" : '') . "\n"; }
}
function section(string $s): void { echo "\n$s\n"; }
function d(array $r): string { return $r['status'] . ' ' . substr($r['raw'], 0, 220); }

function order(array $items, array $extra = []): array
{
    global $origin;
    return array_replace_recursive([
        'items' => $items, 'method' => 'pickup',
        'customer' => ['name' => 'Ada Obi', 'phone' => '08031234567', 'email' => 'ada@example.com'],
        'preferredAt' => '2026-11-02T13:00:00+01:00', 'address' => '', 'hub' => 'Awka Food Hub', 'zoneId' => null, 'notes' => 'No pepper', 'origin' => $origin,
    ], $extra);
}
function line(string $id, array $options = [], array $extras = [], int $qty = 1): array
{
    return ['productId' => $id, 'quantity' => $qty, 'options' => (object) $options, 'extras' => (object) $extras];
}

section('Public');
$r = call('GET', '/api/config');
check('config has public values only', $r['status'] === 200 && isset($r['json']['currency']) && !str_contains($r['raw'], 'secret'));
$r = call('GET', '/api/menu');
check('menu has sections, items and prices', $r['status'] === 200 && count($r['json']['sections']) >= 3 && count($r['json']['items']) >= 12 && count($r['json']['prices']) >= 40, d($r));
check('menu item shape (booleans, ints)', is_bool($r['json']['items'][0]['available']) && is_int($r['json']['items'][0]['price']));
check('CORS allows the website origin', str_contains(strtolower($r['headers']), 'access-control-allow-origin: ' . $origin));
$r = call('GET', '/api/menu', null, '', ['Origin: https://evil.example']);
check('CORS refuses other origins', !str_contains(strtolower($r['headers']), 'access-control-allow-origin: https://evil.example'));
$r = call('GET', '/api/promo');
check('promo returns the banner', $r['status'] === 200 && isset($r['json']['title']) && is_bool($r['json']['active']), d($r));
$r = call('GET', '/api/delivery-zones');
check('delivery zones list', $r['status'] === 200 && is_array($r['json']['zones']));
check('unknown route is 404', call('GET', '/api/nope')['status'] === 404);

section('Accounts');
$email = 'cust' . bin2hex(random_bytes(3)) . '@example.com';
check('signup rejects a short password', call('POST', '/api/auth/signup', ['email' => $email, 'password' => 'short', 'full_name' => 'Ada'])['status'] === 422);
check('signup rejects a bad email', call('POST', '/api/auth/signup', ['email' => 'nope', 'password' => 'longenough1'])['status'] === 422);
$r = call('POST', '/api/auth/signup', ['email' => $email, 'password' => 'longenough1', 'full_name' => 'Ada Obi']);
$custToken = (string) ($r['json']['token'] ?? '');
check('signup signs the customer in', $r['status'] === 201 && strlen($custToken) === 64 && $r['json']['user']['email'] === $email && $r['json']['user']['roles'] === [], d($r));
check('duplicate email is refused', call('POST', '/api/auth/signup', ['email' => strtoupper($email), 'password' => 'longenough1'])['status'] === 409);
$r = call('GET', '/api/auth/me', null, $custToken);
check('me returns the profile', $r['status'] === 200 && $r['json']['user']['full_name'] === 'Ada Obi');
check('me without a token is 401', call('GET', '/api/auth/me')['status'] === 401);
check('me with a made-up token is 401', call('GET', '/api/auth/me', null, str_repeat('a', 64))['status'] === 401);
$r = call('POST', '/api/auth/profile', ['full_name' => 'Ada N. Obi', 'phone' => '08031234567'], $custToken);
check('profile update saves', $r['status'] === 200 && $r['json']['user']['phone'] === '08031234567', d($r));
check('profile update needs sign-in', call('POST', '/api/auth/profile', ['full_name' => 'x'])['status'] === 401);
check('wrong password is refused', call('POST', '/api/auth/login', ['email' => $email, 'password' => 'wrong-password'])['status'] === 401);
check('unknown email is refused the same way', call('POST', '/api/auth/login', ['email' => 'nobody' . bin2hex(random_bytes(2)) . '@example.com', 'password' => 'whatever12'])['status'] === 401);
$r = call('POST', '/api/auth/login', ['email' => $email, 'password' => 'longenough1']);
check('login works and is case-insensitive on email', $r['status'] === 200 && isset($r['json']['token']), d($r));
$loginToken = (string) $r['json']['token'];
check('logout ends the session', call('POST', '/api/auth/logout', null, $loginToken)['status'] === 200 && call('GET', '/api/auth/me', null, $loginToken)['status'] === 401);

$r = call('POST', '/api/auth/forgot', ['email' => $email, 'origin' => $origin]);
check('forgot password answers ok with a dev link', $r['status'] === 200 && ($r['json']['ok'] ?? false) && str_contains((string) ($r['json']['dev_link'] ?? ''), '/reset-password?token='), d($r));
$resetToken = (string) preg_replace('/^.*token=/', '', (string) ($r['json']['dev_link'] ?? ''));
$r2 = call('POST', '/api/auth/forgot', ['email' => 'nobody' . bin2hex(random_bytes(2)) . '@example.com', 'origin' => $origin]);
check('forgot password does not reveal unknown emails', $r2['status'] === 200 && !isset($r2['json']['dev_link']));
check('forgot password refuses unknown origins', call('POST', '/api/auth/forgot', ['email' => $email, 'origin' => 'https://evil.example'])['status'] === 422);
check('reset rejects a bad token', call('POST', '/api/auth/reset', ['token' => 'bad', 'password' => 'brandnewpass1'])['status'] === 400);
check('reset rejects a weak password', call('POST', '/api/auth/reset', ['token' => $resetToken, 'password' => 'short'])['status'] === 422);
$r = call('POST', '/api/auth/reset', ['token' => $resetToken, 'password' => 'brandnewpass1']);
check('reset sets the password and signs in', $r['status'] === 200 && isset($r['json']['token']), d($r));
check('old password no longer works', call('POST', '/api/auth/login', ['email' => $email, 'password' => 'longenough1'])['status'] === 401);
check('new password works', call('POST', '/api/auth/login', ['email' => $email, 'password' => 'brandnewpass1'])['status'] === 200);
check('reset link works only once', call('POST', '/api/auth/reset', ['token' => $resetToken, 'password' => 'anotherpass12'])['status'] === 400);
check('old sessions end after a reset', call('GET', '/api/auth/me', null, $custToken)['status'] === 401);
$custToken = (string) call('POST', '/api/auth/login', ['email' => $email, 'password' => 'brandnewpass1'])['json']['token'];
$spam = 'spam' . bin2hex(random_bytes(3)) . '@example.com'; $last = 0;
for ($i = 0; $i < 10; $i++) $last = call('POST', '/api/auth/login', ['email' => $spam, 'password' => 'wrongwrong1'])['status'];
check('repeated bad logins are throttled', $last === 429, (string) $last);
check('google sign-in is off until configured', call('POST', '/api/auth/google', ['credential' => 'x'])['status'] === 503);

section('Checkout and pricing');
$egg = line('mcrelish-jollof', ['meal' => 'egg'], ['rice' => 1], 2);
$r = call('POST', '/api/orders', order([$egg, line('solo-chicken')]), $custToken);
check('order is created with a payment link', $r['status'] === 201 && str_starts_with((string) $r['json']['link'], 'https://checkout.flutterwave.com/mock/') && str_starts_with((string) $r['json']['reference'], 'MCR-'), d($r));
check('prices are recomputed on the server (2 x 2800 + 2000)', ($r['json']['total'] ?? 0) === 7600, d($r));
$orderA = $r['json']; preg_match('#/mock/(\d+)$#', $orderA['link'], $m); $txA = $m[1];
$bad = [
  'empty cart' => order([]), 'unknown item' => order([line('nope')]), 'unavailable item' => order([line('special-fried-rice', ['meal' => 'egg'])]),
  'missing meal' => order([line('mcrelish-jollof')]), 'unknown meal' => order([line('mcrelish-jollof', ['meal' => 'zzz'])]),
  'meal none without extras' => order([line('mcrelish-jollof', ['meal' => 'none'])]), 'unknown extra' => order([line('mcrelish-jollof', ['meal' => 'egg'], ['zzz' => 1])]),
  'quantity 0' => order([line('solo-chicken', [], [], 0)]), 'quantity 51' => order([line('solo-chicken', [], [], 51)]),
  'bad phone' => order([line('solo-chicken')], ['customer' => ['phone' => '123']]), 'bad email' => order([line('solo-chicken')], ['customer' => ['email' => 'x']]),
  'unknown origin' => order([line('solo-chicken')], ['origin' => 'https://evil.example']), 'unknown hub' => order([line('solo-chicken')], ['hub' => 'Moon Hub']),
  'delivery without area' => order([line('solo-chicken')], ['method' => 'delivery', 'address' => '12 Zik Avenue, Awka']),
];
foreach ($bad as $label => $payload) { $r = call('POST', '/api/orders', $payload); check("refuses: $label", $r['status'] === 422, d($r)); }
$r = call('POST', '/api/orders', order([line('mcrelish-jollof', ['meal' => 'none'], ['chicken' => 1])]));
check('extras-only plate uses the on-its-own price', ($r['json']['total'] ?? 0) === 2000, d($r));
$r = call('POST', '/api/orders', order([line('mcrelish-jollof', ['meal' => 'none'], ['chicken' => 1, 'coleslaw' => 1])]));
check('two extras without rice add up (1500 + 500)', ($r['json']['total'] ?? 0) === 2000, d($r));
$r = call('POST', '/api/orders', order([line('mcrelish-jollof', ['meal' => 'chicken'], ['rice' => 3])]));
check('extra rice is capped at one per plate (3000 + 1000)', ($r['json']['total'] ?? 0) === 4000, d($r));
$r = call('POST', '/api/orders', order([line('family-jollof-pack', [], [], 1)]));
check('protein group defaults to the first choice', ($r['json']['total'] ?? 0) === 7500, d($r));
$r = call('POST', '/api/orders', order([line('family-jollof-pack', ['protein' => 'turkey'])]));
$tk = $r['json']['token'] ?? ''; $rf = $r['json']['reference'] ?? '';
$t = call('GET', "/api/orders/$rf?token=$tk");
check('chosen protein appears on the ticket', $t['status'] === 200 && in_array('Choice of protein: Turkey', $t['json']['items'][0]['details'] ?? [], true), d($t));

section('Tickets and payment');
$t = call('GET', "/api/orders/{$orderA['reference']}?token={$orderA['token']}");
check('ticket loads with the token', $t['status'] === 200 && $t['json']['total'] === 7600 && $t['json']['payment_status'] === 'pending' && $t['json']['status'] === 'new' && $t['json']['hub'] === 'Awka Food Hub', d($t));
check('ticket keeps customer details and details lines', $t['json']['customer_name'] === 'Ada Obi' && $t['json']['notes'] === 'No pepper' && count($t['json']['items'][0]['details']) === 2);
check('ticket refuses a wrong token', call('GET', "/api/orders/{$orderA['reference']}?token=" . '00000000-0000-4000-8000-000000000000')['status'] === 404);
check('ticket refuses a missing token', call('GET', "/api/orders/{$orderA['reference']}")['status'] === 422);
$r = call('POST', '/api/orders/confirm', ['reference' => $orderA['reference'], 'token' => $orderA['token']]);
check('confirm without a transaction reports pending', $r['status'] === 200 && $r['json']['status'] === 'pending', d($r));
check('confirm with a wrong token is 404', call('POST', '/api/orders/confirm', ['reference' => $orderA['reference'], 'token' => '00000000-0000-4000-8000-000000000000'])['status'] === 404);
$r = call('POST', '/api/orders', order([line('solo-chicken')], ['customer' => ['name' => 'Bola Ade']]));
preg_match('#/mock/(\d+)$#', $r['json']['link'], $m); $orderB = $r['json']; $txB = $m[1];
$r = call('POST', '/api/orders/confirm', ['reference' => $orderA['reference'], 'token' => $orderA['token'], 'transactionId' => $txB]);
check("another order's payment is refused", $r['status'] === 409, d($r));
$r = call('POST', '/api/orders/confirm', ['reference' => $orderA['reference'], 'token' => $orderA['token'], 'transactionId' => 'abc']);
check('non-numeric transaction id is refused', $r['status'] === 422, d($r));
$r = call('POST', '/api/orders/confirm', ['reference' => $orderA['reference'], 'token' => $orderA['token'], 'transactionId' => $txA]);
check('verified payment marks the order paid', $r['status'] === 200 && $r['json']['status'] === 'paid', d($r));
$t = call('GET', "/api/orders/{$orderA['reference']}?token={$orderA['token']}");
check('ticket shows paid with time and transaction id', $t['json']['payment_status'] === 'paid' && str_ends_with((string) $t['json']['paid_at'], 'Z') && $t['json']['flw_transaction_id'] === $txA, d($t));
check('confirming twice stays paid', call('POST', '/api/orders/confirm', ['reference' => $orderA['reference'], 'token' => $orderA['token'], 'transactionId' => $txA])['json']['status'] === 'paid');
$r = call('POST', '/api/orders', order([line('solo-chicken')], ['customer' => ['name' => 'Underpay Test']]));
preg_match('#/mock/(\d+)$#', $r['json']['link'], $m);
$u = call('POST', '/api/orders/confirm', ['reference' => $r['json']['reference'], 'token' => $r['json']['token'], 'transactionId' => $m[1]]);
check('an underpayment is not accepted', $u['json']['status'] === 'pending', d($u));
$r = call('POST', '/api/orders', order([line('solo-chicken')], ['customer' => ['name' => 'Declined Test']]));
preg_match('#/mock/(\d+)$#', $r['json']['link'], $m);
$u = call('POST', '/api/orders/confirm', ['reference' => $r['json']['reference'], 'token' => $r['json']['token'], 'transactionId' => $m[1]]);
check('a failed payment is recorded as failed', $u['json']['status'] === 'failed', d($u));
check('webhook rejects a bad signature', call('POST', '/api/payments/webhook', ['data' => ['id' => (int) $txB]], '', ['verif-hash: wrong'])['status'] === 401);
$r = call('POST', '/api/payments/webhook', ['data' => ['id' => (int) $txB]], '', ['verif-hash: whsecret']);
$t = call('GET', "/api/orders/{$orderB['reference']}?token={$orderB['token']}");
check('webhook with the right signature settles the order', $r['status'] === 200 && $t['json']['payment_status'] === 'paid', d($t));

section('Dashboard access rules');
check('orders need sign-in', call('GET', '/api/admin/orders')['status'] === 401);
check('customers cannot open the dashboard', call('GET', '/api/admin/orders', null, $custToken)['status'] === 403);
check('customers cannot change the menu', call('POST', '/api/admin/menu/sections', ['id' => 'x', 'name' => 'x'], $custToken)['status'] === 403);
$r = call('POST', '/api/auth/login', ['email' => $adminEmail, 'password' => $adminPass]);
$admin = (string) ($r['json']['token'] ?? '');
check('admin signs in with the admin role', $r['status'] === 200 && in_array('admin', $r['json']['user']['roles'], true), d($r));

section('Live orders');
$r = call('GET', '/api/admin/orders?limit=200', null, $admin);
$found = array_values(array_filter($r['json']['orders'] ?? [], fn ($o) => $o['reference'] === $orderA['reference']))[0] ?? null;
check('live orders lists recent orders', $r['status'] === 200 && $found && $found['payment_status'] === 'paid' && is_array($found['items']) && is_string($found['id']), d($r));
check('order keeps the board fields', $found && $found['method'] === 'pickup' && $found['hub'] === 'Awka Food Hub' && $found['total'] === 7600 && str_ends_with($found['created_at'], 'Z'));
$id = $found['id'] ?? 'x';
foreach (['preparing', 'ready', 'completed', 'cancelled', 'new'] as $s) {
    $r = call('POST', "/api/admin/orders/$id/status", ['status' => $s], $admin);
    check("status can move to $s", $r['status'] === 200 && $r['json']['status'] === $s, d($r));
}
check('invalid status is refused', call('POST', "/api/admin/orders/$id/status", ['status' => 'banana'], $admin)['status'] === 422);
check('unknown order is 404', call('POST', '/api/admin/orders/00000000-0000-4000-8000-000000000000/status', ['status' => 'ready'], $admin)['status'] === 404);

section('Staff accounts and roles');
$r = call('GET', '/api/admin/staff', null, $admin);
check('admin can list staff', $r['status'] === 200 && count($r['json']['staff']) >= 1, d($r));
check('adding an unknown email explains why', call('POST', '/api/admin/staff', ['email' => 'ghost' . bin2hex(random_bytes(2)) . '@example.com', 'role' => 'staff'], $admin)['status'] === 404);
check('invalid role refused', call('POST', '/api/admin/staff', ['email' => $email, 'role' => 'owner'], $admin)['status'] === 422);
check('admin can give a customer the dispatcher role', call('POST', '/api/admin/staff', ['email' => $email, 'role' => 'dispatcher'], $admin)['status'] === 201);
check('same role twice is refused', call('POST', '/api/admin/staff', ['email' => $email, 'role' => 'dispatcher'], $admin)['status'] === 409);
$disp = (string) call('POST', '/api/auth/login', ['email' => $email, 'password' => 'brandnewpass1'])['json']['token'];
check('login shows the new role', in_array('dispatcher', call('GET', '/api/auth/me', null, $disp)['json']['user']['roles'], true));
check('dispatcher can see orders', call('GET', '/api/admin/orders', null, $disp)['status'] === 200);
check('dispatcher can move an order', call('POST', "/api/admin/orders/$id/status", ['status' => 'preparing'], $disp)['status'] === 200);
check('dispatcher cannot see the menu manager', call('GET', '/api/admin/menu', null, $disp)['status'] === 403);
check('dispatcher cannot see sales', call('GET', '/api/admin/sales', null, $disp)['status'] === 403);
check('dispatcher cannot manage staff', call('GET', '/api/admin/staff', null, $disp)['status'] === 403);
check('dispatcher can write an activity note', call('POST', '/api/admin/audit', ['action' => 'order_status', 'details' => ['reference' => 'X']], $disp)['status'] === 201);
check('dispatcher cannot read the activity log', call('GET', '/api/admin/audit', null, $disp)['status'] === 403);
check('admin can add the staff role too', call('POST', '/api/admin/staff', ['email' => $email, 'role' => 'staff'], $admin)['status'] === 201);
$staff = (string) call('POST', '/api/auth/login', ['email' => $email, 'password' => 'brandnewpass1'])['json']['token'];
check('staff can read the menu manager', call('GET', '/api/admin/menu', null, $staff)['status'] === 200);
check('staff cannot edit the menu', call('POST', '/api/admin/menu/prices', ['key' => 'e:mcrelish-jollof:beef', 'price' => 1], $staff)['status'] === 403);
$userId = '';
foreach (call('GET', '/api/admin/staff', null, $admin)['json']['staff'] as $s) if ($s['email'] === $email) $userId = $s['user_id'];
check('staff list shows both roles', $userId !== '');
check('roles can be removed', call('POST', '/api/admin/staff/remove', ['userId' => $userId, 'role' => 'dispatcher'], $admin)['status'] === 200 && call('POST', '/api/admin/staff/remove', ['userId' => $userId, 'role' => 'staff'], $admin)['status'] === 200);
check('removed access takes effect immediately', call('GET', '/api/admin/orders', null, $staff)['status'] === 403);
$adminId = call('GET', '/api/auth/me', null, $admin)['json']['user']['id'];
check("admin cannot remove their own admin role", call('POST', '/api/admin/staff/remove', ['userId' => $adminId, 'role' => 'admin'], $admin)['status'] === 409);

section('Menu manager');
$r = call('GET', '/api/admin/menu', null, $admin);
check('admin menu returns sections, items and every price row', $r['status'] === 200 && count($r['json']['prices']) >= 40 && isset($r['json']['prices'][0]['low_stock']), d($r));
$sid = 'sec-' . bin2hex(random_bytes(3));
$r = call('POST', '/api/admin/menu/sections', ['id' => $sid, 'name' => 'Test Specials', 'subtitle' => 'Just for tests', 'image_path' => '', 'sort_order' => 50, 'visible' => true], $admin);
check('a section can be created', $r['status'] === 200 && $r['json']['name'] === 'Test Specials', d($r));
$iid = 'item-' . bin2hex(random_bytes(3));
$item = ['id' => $iid, 'section_id' => $sid, 'name' => 'Test Soup', 'description' => 'A test soup', 'image_path' => '', 'price' => 2500, 'available' => true, 'visible' => true, 'sort_order' => 0];
check('an item needs a real section', call('POST', '/api/admin/menu/items', ['section_id' => 'missing'] + $item, $admin)['status'] === 422);
check('an item needs a valid price', call('POST', '/api/admin/menu/items', ['price' => -5] + $item, $admin)['status'] === 422);
$r = call('POST', '/api/admin/menu/items', $item, $admin);
check('an item can be created', $r['status'] === 200 && $r['json']['price'] === 2500, d($r));
$pub = call('GET', '/api/menu')['json'];
check('new item shows on the public menu', in_array($iid, array_column($pub['items'], 'id'), true) && in_array($sid, array_column($pub['sections'], 'id'), true));
check('new item has its own price row', in_array("p:$iid", array_column($pub['prices'], 'key'), true));
$r = call('POST', '/api/orders', order([line($iid, [], [], 2)]));
check('a customer can order the new item', ($r['json']['total'] ?? 0) === 5000, d($r));
$r = call('POST', '/api/admin/menu/items', ['price' => 3000, 'name' => 'Test Soup Deluxe'] + $item, $admin);
check('price and name edits save', $r['json']['price'] === 3000 && $r['json']['name'] === 'Test Soup Deluxe');
check('new price applies to orders', (call('POST', '/api/orders', order([line($iid)]))['json']['total'] ?? 0) === 3000);
$pr = array_values(array_filter(call('GET', '/api/admin/menu', null, $admin)['json']['prices'], fn ($p) => $p['key'] === "p:$iid"))[0] ?? [];
check('item price row follows the item', ($pr['price'] ?? 0) === 3000 && ($pr['label'] ?? '') === 'Test Soup Deluxe');
$r = call('POST', '/api/admin/menu/prices', ['key' => "p:$iid", 'low_stock' => true, 'price' => 1], $admin);
check('low stock can be flagged and the item price row cannot be repriced', $r['status'] === 200 && $r['json']['low_stock'] === true && $r['json']['price'] === 3000, d($r));
$r = call('POST', '/api/admin/menu/items', ['available' => false] + $item, $admin);
check('an unavailable item cannot be ordered', call('POST', '/api/orders', order([line($iid)]))['status'] === 422 && $r['json']['available'] === false);
$r = call('POST', '/api/admin/menu/items', ['visible' => false] + $item, $admin);
$pub = call('GET', '/api/menu')['json'];
check('a hidden item disappears from the public menu', !in_array($iid, array_column($pub['items'], 'id'), true));
$other = 'item-' . bin2hex(random_bytes(3));
call('POST', '/api/admin/menu/items', ['id' => $other, 'name' => 'Second Dish', 'sort_order' => 1] + $item, $admin);
$r = call('POST', '/api/admin/menu/reorder', ['kind' => 'item', 'ids' => [$other, $iid]], $admin);
$items = array_values(array_filter(call('GET', '/api/admin/menu', null, $admin)['json']['items'], fn ($i) => $i['section_id'] === $sid));
check('items can be reordered', $r['status'] === 200 && $items[0]['id'] === $other && $items[1]['id'] === $iid);
check('sections can be reordered', call('POST', '/api/admin/menu/reorder', ['kind' => 'section', 'ids' => [$sid]], $admin)['status'] === 200);
check('bad reorder is refused', call('POST', '/api/admin/menu/reorder', ['kind' => 'nope', 'ids' => []], $admin)['status'] === 422);
$r = call('POST', '/api/admin/menu/prices', ['key' => 'e:mcrelish-jollof:beef', 'price' => 600], $admin);
check('a meal add-on price can change', $r['status'] === 200 && $r['json']['price'] === 600, d($r));
check('the changed add-on price applies to orders (1800 + 600)', (call('POST', '/api/orders', order([line('mcrelish-jollof', ['meal' => 'egg'], ['beef' => 1])]))['json']['total'] ?? 0) === 2400);
call('POST', '/api/admin/menu/prices', ['key' => 'e:mcrelish-jollof:beef', 'price' => 500], $admin);
$r = call('POST', '/api/admin/menu/prices', ['key' => 'm:mcrelish-jollof:turkey', 'available' => false], $admin);
check('a meal can be switched off', $r['status'] === 200 && call('POST', '/api/orders', order([line('mcrelish-jollof', ['meal' => 'turkey'])]))['status'] === 422);
call('POST', '/api/admin/menu/prices', ['key' => 'm:mcrelish-jollof:turkey', 'available' => true], $admin);
check('a switched-on meal works again', call('POST', '/api/orders', order([line('mcrelish-jollof', ['meal' => 'turkey'])]))['status'] === 201);
check('negative price refused', call('POST', '/api/admin/menu/prices', ['key' => 'e:mcrelish-jollof:beef', 'price' => -1], $admin)['status'] === 422);
check('unknown price key is 404', call('POST', '/api/admin/menu/prices', ['key' => 'e:nothing:here', 'price' => 1], $admin)['status'] === 404);

section('Photos');
function upload(string $kind, string $name, string $bytes, string $mime, string $token): array
{
    global $base, $origin;
    $tmp = tempnam(sys_get_temp_dir(), 'up'); file_put_contents($tmp, $bytes);
    $ch = curl_init("$base/api/admin/uploads/$kind");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['file' => new CURLFile($tmp, $mime, $name)],
        CURLOPT_HTTPHEADER => ['X-Auth-Token: ' . $token, 'Origin: ' . $origin]]);
    $raw = (string) curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); unlink($tmp);
    return ['status' => $status, 'json' => json_decode($raw, true), 'raw' => $raw];
}
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$r = upload('menu', 'dish.png', $png, 'image/png', $admin);
check('admin can upload a menu photo', $r['status'] === 201 && preg_match('/^[0-9a-f-]{36}\.png$/', (string) $r['json']['path']) === 1, d($r));
$img = call('GET', '/uploads/menu/' . $r['json']['path']);
check('the uploaded photo is served', $img['status'] === 200 && str_contains(strtolower($img['headers']), 'image/png'));
check('a fake image is refused', upload('menu', 'evil.php', '<?php echo 1;', 'image/png', $admin)['status'] === 422);
check('a text file named .png is refused', upload('menu', 'a.png', 'just text', 'image/png', $admin)['status'] === 422);
check('uploads need the admin role', upload('menu', 'dish.png', $png, 'image/png', $custToken)['status'] === 403);
check('unknown upload folder is refused', upload('secrets', 'dish.png', $png, 'image/png', $admin)['status'] === 404);
$r2 = call('POST', '/api/admin/menu/items', ['image_path' => $r['json']['path'], 'visible' => true, 'available' => true] + $item, $admin);
check('an item can use the uploaded photo', $r2['status'] === 200 && $r2['json']['image_path'] === $r['json']['path']);
check('image paths cannot climb folders', call('POST', '/api/admin/menu/items', ['image_path' => '../../config/config.php'] + $item, $admin)['status'] === 422);

section('Home banner');
$r = call('POST', '/api/admin/promo', ['title' => 'Christmas Feast', 'label' => 'December Special', 'subtitle' => 'Jollof and more', 'product_id' => 'jolly-fest', 'active' => true], $admin);
check('banner text saves', $r['status'] === 200 && $r['json']['title'] === 'Christmas Feast', d($r));
$up = upload('promo', 'flyer.png', $png, 'image/png', $admin);
$r = call('POST', '/api/admin/promo', ['image_url' => $up['json']['path']], $admin);
check('banner flyer saves', $r['status'] === 200 && $r['json']['image_url'] === $up['json']['path'] && $r['json']['title'] === 'Christmas Feast', d($r));
check('banner flyer is served', call('GET', '/uploads/promo/' . $up['json']['path'])['status'] === 200);
check('public banner reflects the change', call('GET', '/api/promo')['json']['title'] === 'Christmas Feast');
call('POST', '/api/admin/promo', ['active' => false, 'image_url' => ''], $admin);
check('banner can be switched off', call('GET', '/api/promo')['json']['active'] === false);
check('banner needs the admin role', call('POST', '/api/admin/promo', ['title' => 'x'], $custToken)['status'] === 403);
call('POST', '/api/admin/promo', ['label' => 'November Jolly Fest', 'title' => 'Jolly Fest Feast', 'active' => true], $admin);

section('Delivery and store');
$r = call('GET', '/api/admin/delivery', null, $admin);
check('delivery settings load', $r['status'] === 200 && $r['json']['store']['store_open'] === true && is_array($r['json']['zones']), d($r));
check('a zone needs a name and a fee', call('POST', '/api/admin/zones', ['name' => '', 'fee' => 500], $admin)['status'] === 422 && call('POST', '/api/admin/zones', ['name' => 'X', 'fee' => -1], $admin)['status'] === 422);
$r = call('POST', '/api/admin/zones', ['name' => 'Test Zone ' . bin2hex(random_bytes(2)), 'fee' => 1500], $admin);
$zone = $r['json'];
check('a delivery area can be added', $r['status'] === 201 && $zone['fee'] === 1500 && $zone['active'] === true, d($r));
check('the area shows for customers', in_array($zone['id'], array_column(call('GET', '/api/delivery-zones')['json']['zones'], 'id'), true));
$r = call('POST', '/api/orders', order([line('solo-chicken')], ['method' => 'delivery', 'zoneId' => $zone['id'], 'address' => '12 Zik Avenue, Awka', 'hub' => '']));
check('a delivery order adds the area fee (2000 + 1500)', $r['status'] === 201 && $r['json']['total'] === 3500, d($r));
$dt = call('GET', "/api/orders/{$r['json']['reference']}?token={$r['json']['token']}")['json'];
check('delivery ticket shows area, address, fee', $dt['method'] === 'delivery' && $dt['zone_name'] === $zone['name'] && $dt['address'] === '12 Zik Avenue, Awka' && $dt['delivery_fee'] === 1500 && $dt['subtotal'] === 2000 && $dt['hub'] === null);
check('area fee can change', call('PATCH', "/api/admin/zones/{$zone['id']}", ['fee' => 2000], $admin)['json']['fee'] === 2000);
check('the fee change applies to new orders', (call('POST', '/api/orders', order([line('solo-chicken')], ['method' => 'delivery', 'zoneId' => $zone['id'], 'address' => '12 Zik Avenue, Awka']))['json']['total'] ?? 0) === 4000);
check('an area can be switched off', call('PATCH', "/api/admin/zones/{$zone['id']}", ['active' => false], $admin)['json']['active'] === false);
check('a switched-off area is hidden and refused', !in_array($zone['id'], array_column(call('GET', '/api/delivery-zones')['json']['zones'], 'id'), true)
    && call('POST', '/api/orders', order([line('solo-chicken')], ['method' => 'delivery', 'zoneId' => $zone['id'], 'address' => '12 Zik Avenue, Awka']))['status'] === 422);
$r = call('POST', '/api/admin/store', ['store_open' => false, 'note' => 'Closed for a private event.'], $admin);
check('the store can be closed with a note', $r['status'] === 200 && $r['json']['store_open'] === false && $r['json']['note'] === 'Closed for a private event.', d($r));
$r = call('POST', '/api/orders', order([line('solo-chicken')]));
check('a closed store refuses new orders with the note', $r['status'] === 503 && $r['json']['error'] === 'Closed for a private event.', d($r));
check('the store can reopen', call('POST', '/api/admin/store', ['store_open' => true, 'note' => ''], $admin)['json']['store_open'] === true && call('POST', '/api/orders', order([line('solo-chicken')]))['status'] === 201);
check('an area can be deleted', call('DELETE', "/api/admin/zones/{$zone['id']}", null, $admin)['status'] === 200 && call('DELETE', "/api/admin/zones/{$zone['id']}", null, $admin)['status'] === 404);
check('settings need the admin role', call('POST', '/api/admin/store', ['store_open' => false], $custToken)['status'] === 403);

section('Sales and activity');
$r = call('GET', '/api/admin/sales', null, $admin);
check('sales rows carry what the charts use', $r['status'] === 200 && count($r['json']['orders']) > 5 && array_key_exists('paid_at', $r['json']['orders'][0]) && is_array($r['json']['orders'][0]['items']) && array_key_exists('price', $r['json']['orders'][0]['items'][0]), d($r));
check('activity log records and lists', call('POST', '/api/admin/audit', ['action' => 'price_change', 'details' => ['label' => 'Beef', 'from' => 500, 'to' => 600]], $admin)['status'] === 201);
$r = call('GET', '/api/admin/audit?limit=50', null, $admin);
check('activity log returns newest first with actor', $r['status'] === 200 && $r['json']['logs'][0]['action'] === 'price_change' && $r['json']['logs'][0]['actor_email'] === $adminEmail && $r['json']['logs'][0]['details']['to'] === 600, d($r));

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
