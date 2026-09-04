<?php require __DIR__ . '/config.php'; $user=require_login();
if (active_subscription((int)$user['id'])) { header('Location: ' . app_url() . '/dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $token=csrf_token();
  ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Subscribe · Zividax Demo</title><style>body{margin:0;background:#070a12;color:#fff;font-family:Inter,system-ui}.wrap{max-width:620px;margin:auto;padding:60px 18px}.card{background:#101725;border:1px solid #26314a;border-radius:22px;padding:28px}.btn{width:100%;padding:15px;border:0;border-radius:14px;background:linear-gradient(135deg,#6d7cff,#8b72ff);color:#fff;font-weight:850;font-size:15px}.muted{color:#97a3b8;line-height:1.6}.price{font-size:46px;font-weight:900}</style></head><body><div class="wrap"><div class="card"><div class="muted">ZIVIDAX PAY CHECKOUT</div><h1><?=h(subscription_name())?></h1><div class="price">$<?=number_format(subscription_price(),2)?></div><p class="muted">You will be redirected to Zividax Pay to review the merchant, service and amount. Acceptance requires a fresh email MFA code valid for 5 minutes.</p><form method="post"><input type="hidden" name="csrf" value="<?=h($token)?>"><button class="btn">Create payment request</button></form></div></div></body></html><?php exit;
}
require_csrf();
if (api_key()==='' || str_contains(api_key(), 'REPLACE_WITH_YOUR_ACTIVE_API_KEY')) exit('Demo is not configured. Open config.php and set ZIVIDAX_API_KEY to your active zx_live_... key.');
$idempotency='demo-subscription-' . (int)$user['id'] . '-' . date('Ymd');
$return=app_url() . '/payment-complete.php';
$result=create_payment_request($user,$idempotency,$return);
if ($result['status'] < 200 || $result['status'] >= 300 || empty($result['body']['success']) || empty($result['body']['payment_url']) || empty($result['body']['request']['request_id'])) {
  error_log('Zividax payment request failed: ' . json_encode($result, JSON_UNESCAPED_SLASHES));
  $apiMessage = trim((string)($result['body']['message'] ?? $result['body']['error'] ?? ''));
  if ($result['error'] !== '') $apiMessage = ($apiMessage !== '' ? $apiMessage . ' — ' : '') . $result['error'];
  if ($apiMessage === '') {
      $raw = trim((string)($result['raw'] ?? ''));
      $apiMessage = 'HTTP ' . $result['status'] . ($raw !== '' ? ' — ' . substr($raw, 0, 500) : '');
  } else {
      $apiMessage = 'HTTP ' . $result['status'] . ' — ' . $apiMessage;
  }
  exit('Zividax Pay could not create the payment request. ' . htmlspecialchars($apiMessage, ENT_QUOTES, 'UTF-8'));
}
$request=$result['body']['request'];
$order=[
 'order_id'=>'ORD-' . strtoupper(bin2hex(random_bytes(6))),
 'user_id'=>(int)$user['id'],
 'username'=>$user['username'],
 'request_id'=>(string)$request['request_id'],
 'payment_url'=>(string)$result['body']['payment_url'],
 'amount'=>(float)($request['amount'] ?? subscription_price()),
 'status'=>'pending',
 'created_at'=>gmdate('c'),
];
save_order($order);
header('Location: ' . $order['payment_url']); exit;
