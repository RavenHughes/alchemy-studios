<?php
// Begin a project: the short request form on the site posts here and it is emailed to the studio.
// The address stays on the server so it never appears in the page.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const TO = 'ravenhughes@proton.me';
const FROM = 'noreply@alchemystudiosorlando.com';

function out(int $code, array $body): void { http_response_code($code); echo json_encode($body); exit; }

// the brass puzzle: the server picks where the pointer sits and signs it; the visitor turns the gear to meet it
function secret(): string {
    $f = sys_get_temp_dir() . '/as-begin-secret';
    $k = @file_get_contents($f);
    if (!$k || strlen($k) < 32) { $k = bin2hex(random_bytes(32)); @file_put_contents($f, $k); @chmod($f, 0600); }
    return $k;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['puzzle'])) {
    $a = random_int(40, 320); $exp = time() + 1800;
    out(200, ['a' => $a, 'k' => $exp . '.' . hash_hmac('sha256', $a . '|' . $exp, secret())]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'error' => 'Send the form to begin a project.']);

$f = static fn (string $k, int $max = 200): string => trim(mb_substr(str_replace(["\r", "\0"], '', (string) ($_POST[$k] ?? '')), 0, $max));

// spam traps: a field people never see, and a form sent faster than a person could fill it
if ($f('website') !== '') out(200, ['ok' => true]);
$t = (int) ($_POST['t'] ?? 0);
if ($t && (time() - $t) < 3) out(200, ['ok' => true]);

$parts = explode('.', (string) ($_POST['pk'] ?? ''), 2); $exp = (int) ($parts[0] ?? 0);
$solved = false;
if (count($parts) === 2 && $exp > time()) for ($a = 40; $a <= 320; $a++) {
    if (hash_equals(hash_hmac('sha256', $a . '|' . $exp, secret()), $parts[1])) { $d = abs(((int) ($_POST['pa'] ?? -999) - $a + 540) % 360 - 180); $solved = $d <= 8; break; }
}
if (!$solved) out(422, ['ok' => false, 'error' => 'Turn the brass gear until its gold tooth meets the pointer.']);

$name = $f('name', 120); $email = $f('email', 190); $phone = $f('phone', 40); $org = $f('org', 160);
$kind = $f('kind', 80); $stage = $f('stage', 80); $when = $f('when', 80); $desc = $f('desc', 4000);

if ($name === '') out(422, ['ok' => false, 'error' => 'Add your name.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(422, ['ok' => false, 'error' => 'Add an email we can reply to.']);
if (mb_strlen($desc) < 10) out(422, ['ok' => false, 'error' => 'Tell us a little about the project.']);

// simple rate limit: 5 requests per address per hour
$dir = sys_get_temp_dir() . '/as-begin'; @mkdir($dir, 0700, true);
$key = $dir . '/' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
$hits = array_filter(array_map('intval', @file($key, FILE_IGNORE_NEW_LINES) ?: []), fn ($x) => $x > time() - 3600);
if (count($hits) >= 5) out(429, ['ok' => false, 'error' => 'We have your requests. Please try again in an hour.']);
$hits[] = time(); @file_put_contents($key, implode("\n", $hits));

$clean = static fn (string $s): string => preg_replace('/[\r\n]+/', ' ', $s);
$subject = 'Begin a project: ' . $clean($name) . ($kind !== '' ? ' · ' . $clean($kind) : '');
$body = "A new project request from alchemystudiosorlando.com\n\n"
    . "Name:          $name\n"
    . "Email:         $email\n"
    . ($phone !== '' ? "Phone:         $phone\n" : '')
    . ($org !== '' ? "Organization:  $org\n" : '')
    . ($kind !== '' ? "Discipline:    $kind\n" : '')
    . ($stage !== '' ? "Stage:         $stage\n" : '')
    . ($when !== '' ? "Timeline:      $when\n" : '')
    . "\nWhat they want to create:\n$desc\n\n"
    . '— Sent ' . gmdate('Y-m-d H:i') . " UTC\n";
$headers = implode("\r\n", [
    'From: Alchemy Studios <' . FROM . '>',
    'Reply-To: ' . $clean($name) . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);
$ok = @mail(TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . FROM);
if (!$ok) out(500, ['ok' => false, 'error' => 'The message didn’t send. Please write to info@alchemystudiosorlando.com.']);
out(200, ['ok' => true]);
