<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    session_start();
}

// PHP-only configuration. Do not put these values in browser JavaScript.
const APP_URL = 'https://YOUR-DOMAIN.example/demo';
const ZIVIDAX_CONSOLE_BASE = 'https://console.zividax.uk/public';
const ZIVIDAX_CLIENT_ID = 'zxc_REPLACE_WITH_YOUR_CLIENT_ID';
const ZIVIDAX_CLIENT_SECRET = 'REPLACE_WITH_YOUR_OAUTH_CLIENT_SECRET';
const ZIVIDAX_API_KEY = 'zx_live_REPLACE_WITH_YOUR_ACTIVE_API_KEY';
const SUBSCRIPTION_NAME = 'Demo Premium';
const SUBSCRIPTION_PRICE = 1.00;
const SUBSCRIPTION_DAYS = 30;
const APP_DEBUG = false;

function app_url(): string { return rtrim(APP_URL, '/'); }
function console_base(): string { return rtrim(ZIVIDAX_CONSOLE_BASE, '/'); }
function client_id(): string { return ZIVIDAX_CLIENT_ID; }
function client_secret(): string { return ZIVIDAX_CLIENT_SECRET; }
function api_key(): string { return ZIVIDAX_API_KEY; }
function subscription_name(): string { return SUBSCRIPTION_NAME; }
function subscription_price(): float { return round((float)SUBSCRIPTION_PRICE, 2); }
function subscription_days(): int { return max(1, (int)SUBSCRIPTION_DAYS); }
function debug_mode(): bool { return APP_DEBUG; }

const DEMO_STORAGE_DIR = __DIR__ . '/storage';
const DEMO_USERS_FILE = DEMO_STORAGE_DIR . '/users.json';
const DEMO_SUBS_FILE = DEMO_STORAGE_DIR . '/subscriptions.json';
const DEMO_ORDERS_FILE = DEMO_STORAGE_DIR . '/orders.json';


function ensure_storage(): void {
    if (!is_dir(DEMO_STORAGE_DIR)) mkdir(DEMO_STORAGE_DIR, 0700, true);
    foreach ([DEMO_USERS_FILE, DEMO_SUBS_FILE, DEMO_ORDERS_FILE] as $file) {
        if (!file_exists($file)) file_put_contents($file, "[]", LOCK_EX);
    }
}
ensure_storage();

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

function json_read(string $file): array {
    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function json_write(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function require_csrf(): void {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Invalid request token.');
    }
}

function current_user(): ?array {
    $id = (int)($_SESSION['zividax_user_id'] ?? 0);
    if (!$id) return null;
    foreach (json_read(DEMO_USERS_FILE) as $u) if ((int)($u['id'] ?? 0) === $id) return $u;
    return null;
}
function require_login(): array {
    $u = current_user();
    if (!$u) { header('Location: ' . app_url() . '/login.php'); exit; }
    return $u;
}
function save_user(array $profile): array {
    $users = json_read(DEMO_USERS_FILE);
    $id = (int)($profile['id'] ?? 0);
    $found = false;
    foreach ($users as &$u) {
        if ((int)($u['id'] ?? 0) === $id) {
            $u = array_merge($u, $profile, ['updated_at' => gmdate('c')]);
            $found = true; break;
        }
    }
    unset($u);
    if (!$found) { $profile['updated_at'] = gmdate('c'); $users[] = $profile; }
    json_write(DEMO_USERS_FILE, $users);
    $_SESSION['zividax_user_id'] = $id;
    return $profile;
}

function http_json(string $method, string $url, array $headers = [], ?array $form = null, ?array $json = null): array {
    $ch = curl_init($url);
    $headers[] = 'Accept: application/json';
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_SLASHES);
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($form ?? []);
        }
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string)$body, true);
    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null, 'raw' => (string)$body, 'error' => $err];
}

function oauth_authorize_url(): string {
    $state = bin2hex(random_bytes(24));
    $_SESSION['oauth_state'] = $state;
    $query = http_build_query([
        'client_id' => client_id(),
        'redirect_uri' => app_url() . '/callback.php',
        'state' => $state,
    ]);
    return console_base() . '/oauth/authorize?' . $query;
}

function exchange_oauth_code(string $code): array {
    return http_json('POST', console_base() . '/oauth/token', [], [
        'client_id' => client_id(),
        'client_secret' => client_secret(),
        'code' => $code,
        'redirect_uri' => app_url() . '/callback.php',
    ]);
}

function fetch_oauth_profile(string $accessToken): array {
    return http_json('GET', console_base() . '/oauth/userinfo', ['Authorization: Bearer ' . $accessToken]);
}

function create_payment_request(array $customer, string $idempotency, string $returnUrl): array {
    $payload = [
        'customer_username' => (string)$customer['username'],
        'company_name' => 'Zividax Demo Store',
        'service_name' => subscription_name(),
        'request_type' => 'subscription',
        'amount' => subscription_price(),
        'description' => 'Monthly ' . subscription_name() . ' subscription for the Zividax Demo Store.',
        'reference' => 'DEMO-SUB-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)),
        'return_url' => $returnUrl,
    ];
    return http_json('POST', console_base() . '/api/v1/payments/request', [
        'Authorization: Bearer ' . api_key(),
        'Idempotency-Key: ' . $idempotency,
        'Content-Type: application/json',
    ], null, $payload);
}

function fetch_payment_status(string $requestId): array {
    $url = console_base() . '/api/v1/payments/status?request_id=' . rawurlencode($requestId);
    return http_json('GET', $url, ['Authorization: Bearer ' . api_key()]);
}

function find_order(string $requestId): ?array {
    foreach (json_read(DEMO_ORDERS_FILE) as $o) if (($o['request_id'] ?? '') === $requestId) return $o;
    return null;
}

function save_order(array $order): void {
    $orders = json_read(DEMO_ORDERS_FILE);
    $replaced = false;
    foreach ($orders as $i => $o) {
        if (($o['order_id'] ?? '') === ($order['order_id'] ?? '')) { $orders[$i] = $order; $replaced = true; break; }
    }
    if (!$replaced) $orders[] = $order;
    json_write(DEMO_ORDERS_FILE, $orders);
}

function active_subscription(int $userId): ?array {
    foreach (json_read(DEMO_SUBS_FILE) as $s) {
        if ((int)($s['user_id'] ?? 0) === $userId && ($s['status'] ?? '') === 'active' && strtotime((string)$s['ends_at']) > time()) return $s;
    }
    return null;
}

function activate_subscription(array $user, array $order, array $payment): array {
    $subs = json_read(DEMO_SUBS_FILE);
    $now = time();
    $existing = active_subscription((int)$user['id']);
    $start = $existing ? strtotime((string)$existing['ends_at']) : $now;
    $end = $start + subscription_days() * 86400;
    $record = [
        'subscription_id' => 'SUB-' . strtoupper(bin2hex(random_bytes(6))),
        'user_id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'plan' => subscription_name(),
        'price' => subscription_price(),
        'status' => 'active',
        'payment_request_id' => (string)$order['request_id'],
        'transaction_id' => (string)($payment['transaction_id'] ?? ''),
        'starts_at' => gmdate('c', $start),
        'ends_at' => gmdate('c', $end),
        'activated_at' => gmdate('c'),
    ];
    $subs[] = $record;
    json_write(DEMO_SUBS_FILE, $subs);
    return $record;
}
