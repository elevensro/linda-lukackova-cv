<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Fixed recipient, server-side validation and no visitor-controlled mail headers.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
function respond(int $code, string $message, array $extra = []): never {
    http_response_code($code);
    echo json_encode(['ok' => $code === 200, 'message' => $message] + $extra);
    exit;
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16000) respond(413, 'Your message is too long.');
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) respond(405, 'Method not allowed.');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['https://www.e11.consulting', 'https://e11.consulting'];
if (PHP_SAPI === 'cli-server') $allowedOrigins[] = 'http://127.0.0.1:8089';
if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) respond(403, 'Please send the form from e11.consulting.');
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') respond(403, 'Please send the form from e11.consulting.');
ini_set('session.use_strict_mode', '1');
session_name('e11_contact');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if ($method === 'GET') {
    $_SESSION['form'] = ['token' => bin2hex(random_bytes(32)), 'challenge' => bin2hex(random_bytes(24)), 'issued' => time()];
    respond(200, 'Ready', ['token' => $_SESSION['form']['token'], 'challenge' => $_SESSION['form']['challenge']]);
}
$form = $_SESSION['form'] ?? [];
unset($_SESSION['form']); // Every attempt consumes the challenge.
if (!is_string($_POST['token'] ?? null) || !hash_equals($form['token'] ?? '', $_POST['token']) || empty($form['token'])) respond(403, 'Please try again. Your security check expired.');
$age = time() - (int)$form['issued'];
if ($age < 2 || $age > 1800) respond(403, 'Please wait a moment and try again.');
if (!empty($_POST['website'])) respond(400, 'Your message could not be sent.');
$nonce = $_POST['nonce'] ?? '';
if (!is_string($nonce) || !preg_match('/^\d{1,7}$/D', $nonce) || !str_starts_with(hash('sha256', $form['challenge'].':'.$nonce), '000')) respond(403, 'Security verification failed. Please try again.');
function field(string $name, int $limit): string {
    $value = $_POST[$name] ?? '';
    if (!is_string($value) || strlen($value) > $limit || str_contains($value, "\0") || !preg_match('//u', $value)) respond(400, 'Please check your form details.');
    return trim($value);
}
$name = field('name', 400);
$phone = field('phone', 35);
$email = field('email', 254);
$message = field('message', 10000);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email.$phone.$name)) respond(400, 'Please enter a valid email address and contact details.');
$digits = preg_replace('/\D/', '', $phone);
if (!preg_match('/^\+?[0-9 ()\-.]+$/D', $phone) || strlen($digits) < 7 || strlen($digits) > 15) respond(400, 'Please enter a valid phone number, including country code.');
if (mb_strlen($message) < 10 || mb_strlen($message) > 5000 || mb_strlen($name) > 100 || ($_POST['consent'] ?? '') !== 'yes') respond(400, 'Please add a message and confirm your consent.');
// A single locked daily file bounds storage and enforces per-IP and global limits.
// Only hashes/counters are stored, never enquiry text or raw IP addresses.
$directory = sys_get_temp_dir().'/e11-contact-'.hash('sha256', __DIR__);
if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) respond(503, 'The form is temporarily unavailable. Please email linda@e11.consulting.');
$today = gmdate('Y-m-d');
foreach (glob($directory.'/*.json') ?: [] as $old) {
    if (basename($old) !== $today.'.json') @unlink($old);
}
$path = $directory.'/'.$today.'.json';
$file = @fopen($path, 'c+');
if (!$file || !flock($file, LOCK_EX)) respond(503, 'The form is temporarily unavailable. Please email linda@e11.consulting.');
@chmod($path, 0600);
$raw = stream_get_contents($file);
$state = $raw === '' ? ['salt' => bin2hex(random_bytes(32)), 'ips' => [], 'total' => []] : json_decode($raw, true);
if (!is_array($state) || !isset($state['salt'], $state['ips'], $state['total'])) { flock($file, LOCK_UN); fclose($file); respond(503, 'The form is temporarily unavailable.'); }
$ip = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $state['salt']);
$now = time();
$state['total'] = array_values(array_filter($state['total'], fn($t) => $t > $now - 3600));
$state['ips'][$ip] = array_values(array_filter($state['ips'][$ip] ?? [], fn($t) => $t > $now - 3600));
if (count($state['total']) >= 40 || count($state['ips'][$ip]) >= 5) {
    flock($file, LOCK_UN); fclose($file);
    respond(429, 'Too many messages. Please try later or email linda@e11.consulting.');
}
$state['total'][] = $now;
$state['ips'][$ip][] = $now;
rewind($file); ftruncate($file, 0); fwrite($file, json_encode($state)); fflush($file); flock($file, LOCK_UN); fclose($file);
session_write_close();
$body = "New enquiry from e11.consulting\n\nName: ".($name ?: 'Not provided')."\nPhone: $phone\nEmail: $email\n\nMessage:\n$message\n\nConsent: explicitly given for responding to this enquiry.\nPrivacy notice version: 2026-10-01\nReceived: ".gmdate('c');
$headers = ['From' => 'Linda website <linda@e11.consulting>', 'Reply-To' => $email, 'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8'];
if (!mail('linda@e11.consulting', 'Website enquiry - e11.consulting', $body, $headers, '-flinda@e11.consulting')) respond(503, 'Your message could not be sent. Please email linda@e11.consulting directly.');
respond(200, 'Thank you. Your message has been submitted. I’ll be in touch.');
