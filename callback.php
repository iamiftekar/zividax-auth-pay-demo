<?php require __DIR__ . '/config.php';
if (isset($_GET['error'])) exit('Zividax sign-in was denied. <a href="index.php">Back</a>');
if (!hash_equals((string)($_SESSION['oauth_state'] ?? ''), (string)($_GET['state'] ?? ''))) exit('Invalid OAuth state. <a href="index.php">Back</a>');
unset($_SESSION['oauth_state']);
$code = trim((string)($_GET['code'] ?? '')); if ($code==='') exit('Missing authorization code. <a href="index.php">Back</a>');
$token = exchange_oauth_code($code); if ($token['status'] < 200 || $token['status'] >= 300 || empty($token['body']['access_token'])) { error_log('Zividax OAuth token exchange failed: ' . json_encode($token, JSON_UNESCAPED_SLASHES)); $message=trim((string)($token['body']['error_description'] ?? $token['body']['message'] ?? $token['body']['error'] ?? $token['error'] ?? 'HTTP '.$token['status'])); exit('Could not exchange the Zividax code. ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); }
$profile = fetch_oauth_profile((string)$token['body']['access_token']); if ($profile['status'] < 200 || $profile['status'] >= 300 || empty($profile['body']['username'])) { error_log('Zividax OAuth profile failed: ' . json_encode($profile, JSON_UNESCAPED_SLASHES)); $message=trim((string)($profile['body']['message'] ?? $profile['body']['error'] ?? $profile['error'] ?? 'HTTP '.$profile['status'])); exit('Could not read the Zividax profile. ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); }
save_user([
  'id'=>(int)($profile['body']['id'] ?? 0),
  'username'=>(string)($profile['body']['username'] ?? ''),
  'first_name'=>(string)($profile['body']['first_name'] ?? ''),
  'last_name'=>(string)($profile['body']['last_name'] ?? ''),
  'email'=>(string)($profile['body']['email'] ?? ''),
]);
header('Location: ' . app_url() . '/dashboard.php'); exit;
