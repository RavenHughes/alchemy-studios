<?php
// Alchemy Studios — The Creation Lab (the Architect's private side of the site).
// One front door for everything under /lab/. Rooms: the Projects Roster (live), more to come.
// Data lives OUTSIDE the web folder (lab-data beside the site), as plain JSON: no database to install.
declare(strict_types=1);

const STATUSES = [
    'building' => ['In the build', '#4fd1e8'],
    'next'     => ['Next up', '#d8b45a'],
    'planned'  => ['Planned', '#9c86ff'],
    'live'     => ['Live', '#4ee0a0'],
    'parked'   => ['Parked', '#7b8a92'],
];
const HOMES = ['Alchemy Studios', 'The Realm', 'Digital Epitaph', 'Apex NEO Card', 'ZenChip PRIME', 'Apex VCards', 'Graffix Tools', 'Grace', 'Black Diamond', 'FlashFunded', 'Hughes Laser Lab', 'Other'];
const ROOMS = [
    ['roster', 'The Architect\'s Projects Roster', 'Every build, its stage and its steps.', true],
    ['adlab', 'The Ad Lab', 'Headers, specials, promotions and campaigns, with Optics.', true],
    ['bodyshop', 'The Body Shop', 'The avatar lab: scan, splice, walk through the portal to Hyperion.', false],
    ['workshop', 'The Workshop', 'Sound Studio, face engine and the control panel.', false],
];

// ---------- storage
function dataDir(): string {
    $site = dirname(__DIR__);
    $d = getenv('LAB_DATA') ?: dirname($site) . '/lab-data';
    if (!is_dir($d) && !@mkdir($d, 0700, true)) { http_response_code(500); exit('The lab cannot create its data folder.'); }
    return $d;
}
function load(string $name, $default) {
    $f = dataDir() . "/$name.json";
    if (!is_file($f)) return $default;
    $v = json_decode((string) file_get_contents($f), true);
    return is_array($v) ? $v : $default;
}
function save(string $name, array $v): void {
    $f = dataDir() . "/$name.json"; $tmp = $f . '.' . bin2hex(random_bytes(4));
    file_put_contents($tmp, json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($tmp, 0600); rename($tmp, $f);
    // a dated copy each day, kept 30 days: an edit can always be undone
    $bk = dataDir() . '/backups'; @mkdir($bk, 0700);
    $day = "$bk/$name-" . date('Y-m-d') . '.json'; if (!is_file($day)) { @copy($f, $day); @chmod($day, 0600); }
    foreach (glob("$bk/$name-*.json") ?: [] as $old) if (filemtime($old) < time() - 30 * 86400) @unlink($old);
}
function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function go(string $to): never { header('Location: ' . $to, true, 303); exit; }
function flash(?string $m = null): ?string { if ($m !== null) { $_SESSION['flash'] = $m; return null; } $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m; }

// ---------- session and security
$https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_name('ASLAB');
session_set_cookie_params(['lifetime' => 0, 'path' => '/lab/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_save_path((function () { $d = dataDir() . '/sessions'; @mkdir($d, 0700); return $d; })());
session_start();
header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
function csrf(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST' && !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(400); exit('The form expired. Go back and try again.'); }

$path = '/' . trim(substr((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/lab/'), PHP_URL_PATH), strlen('/lab')), '/');
require __DIR__ . '/adlab.php';   // its public parts (tracked links, finished artwork) answer here, before sign-in

// ---------- the page frame
function page(string $title, string $body, string $crumb = ''): never {
    $f = flash();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>' . e($title) . ' · Creation Lab</title>'
        . '<link rel="icon" href="/assets/brand/favicon-2027.png"><link rel="stylesheet" href="/lab/lab.css?v=2"></head><body>'
        . '<header class="top"><a class="mark" href="/lab/"><img src="/assets/brand/alchemy-emblem-2027-sm.webp" alt="" width="44" height="44"><span><b>Creation Lab</b><small>Alchemy Studios · Development House</small></span></a>'
        . (!empty($_SESSION['architect']) ? '<nav>' . $crumb . '<form method="post" action="/lab/signout">' . csrf() . '<button class="ghost">Sign out</button></form></nav>' : '') . '</header>'
        . ($f ? '<p class="flash" role="status">' . e($f) . '</p>' : '')
        . '<main>' . $body . '</main><footer>Imagination → Reality · Private to the Architect</footer><script src="/lab/lab.js?v=3"></script></body></html>';
    exit;
}

// ---------- the Architect's key: one account, set once with a code read on the server
$auth = load('auth', []);
if (!$auth) {
    $codeFile = dataDir() . '/setup.code';
    if (!is_file($codeFile)) { file_put_contents($codeFile, strtoupper(bin2hex(random_bytes(5))) . "\n"); @chmod($codeFile, 0600); }
    if ($method === 'POST' && $path === '/setup') {
        $code = trim((string) file_get_contents($codeFile)); $pw = (string) ($_POST['pw'] ?? '');
        if (!hash_equals($code, strtoupper(trim((string) ($_POST['code'] ?? ''))))) { flash('That setup code doesn\'t match.'); go('/lab/'); }
        if (strlen($pw) < 12 || $pw !== ($_POST['pw2'] ?? '')) { flash('Use at least 12 characters, typed the same twice.'); go('/lab/'); }
        save('auth', ['name' => trim((string) ($_POST['name'] ?? '')) ?: 'The Architect', 'hash' => password_hash($pw, PASSWORD_DEFAULT), 'set' => date('c')]);
        @unlink($codeFile); session_regenerate_id(true); $_SESSION['architect'] = true; flash('Your key is set. Welcome to the Creation Lab.'); go('/lab/');
    }
    page('First key', '<section class="door"><h1>Set the Architect\'s key</h1><p>This happens once. The setup code is in <code>lab-data/setup.code</code> on the server, beside the site folder. It disappears once the key is set.</p>'
        . '<form method="post" action="/lab/setup" class="stack">' . csrf() . '<label>Setup code<input name="code" required autocomplete="off"></label><label>Your name<input name="name" value="J. Daniel Hughes"></label>'
        . '<label>Password<input type="password" name="pw" required minlength="12" autocomplete="new-password"></label><label>Password again<input type="password" name="pw2" required minlength="12" autocomplete="new-password"></label><button>Set my key</button></form></section>');
}

if (empty($_SESSION['architect'])) {
    if ($method === 'POST' && $path === '/signin') {
        $tries = load('tries', []); $ip = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $mine = array_values(array_filter($tries[$ip] ?? [], fn ($t) => $t > time() - 900));
        if (count($mine) >= 6) { flash('Too many tries. Wait fifteen minutes.'); go('/lab/'); }
        if (password_verify((string) ($_POST['pw'] ?? ''), $auth['hash'])) {
            unset($tries[$ip]); save('tries', $tries); session_regenerate_id(true); $_SESSION['architect'] = true; go('/lab/');
        }
        $mine[] = time(); $tries[$ip] = $mine; save('tries', $tries); usleep(400000); flash('That key doesn\'t open the lab.'); go('/lab/');
    }
    page('Sign in', '<section class="door"><img class="seal" src="/assets/brand/alchemy-emblem-2027.webp" alt="" width="140" height="140"><h1>The Creation Lab</h1><p>Private to the Architect.</p>'
        . '<form method="post" action="/lab/signin" class="stack">' . csrf() . '<label>Password<input type="password" name="pw" required autofocus autocomplete="current-password"></label><button>Enter</button></form></section>');
}

if ($method === 'POST' && $path === '/signout') { $_SESSION = []; session_destroy(); go('/lab/'); }

// ---------- the roster
function seed(): array {
    $mk = fn ($t, $h, $s, $p, $sum, $steps, $rule = '', $notes = '') => ['id' => bin2hex(random_bytes(4)), 'title' => $t, 'home' => $h, 'status' => $s, 'priority' => $p, 'summary' => $sum,
        'steps' => array_map(fn ($x) => ['t' => $x, 'done' => false], $steps), 'rule' => $rule, 'notes' => $notes, 'created' => date('c'), 'updated' => date('c')];
    return [
        $mk('The Ad Lab', 'Alchemy Studios', 'next', 1, 'A studio for header images, specials, promotions and social media campaigns, with a logic-based ad generator and Optics, which scores each piece before it goes out. Covers all the sites.',
            ['Plan the flow from brief to finished ad', 'Brand kits for each company (logos, colours, fonts)', 'Platform sizes and safe zones', 'The ad generator', 'Optics scoring', 'Coupon / special links and tracking']),
        $mk('The Body Shop', 'Alchemy Studios', 'planned', 2, 'A futuristic medical / DNA-genetics lab for avatars. Members are scanned, their avatar is spliced together, and it stands up, walks through the portal and arrives on Hyperion Island in The Realm.',
            ['Check-in: photos or short video, consent form, likeness preview', 'Face build with Mesh to MetaHuman (shared face engine with the Lazarus Studio)', 'Splice the strands: body, skin, hair, outfit by MetaHuman Python script on the build machine', 'Portal walk cinematic, arrival on Hyperion', 'Prototype "avatar as data" (shared rig + face DNA, textures, settings)', 'Cooked per-avatar packs for showcase members and Heaven Project scans', 'First test: the Architect\'s own avatar, start to finish'],
            'Living members only, with consent. The Lazarus Studio stays in Digital Epitaph for the departed only; the two share the face engine, never the rules.', 'MetaHumans are licensed for use outside Unreal too, so avatars can serve the other platforms.'),
        $mk('The Workshop', 'Alchemy Studios', 'planned', 3, 'Clones of the Sound Studio and the face engine, plus the local control panel for Hostinger, Claude and the sites.', ['Sound Studio clone', 'Face engine room', 'Control panel']),
        $mk('Creation Lab and Projects Roster', 'Alchemy Studios', 'live', 4, 'This lab: the Architect\'s private side of alchemystudiosorlando.com, starting with the Projects Roster.', ['Architect sign-in', 'Projects Roster', 'Server route for /lab/'], '', ''),
    ];
}
$roster = load('roster', []);
if (!$roster) { $roster = seed(); save('roster', $roster); }
$find = function (string $id) use (&$roster) { foreach ($roster as $i => $p) if ($p['id'] === $id) return $i; return null; };
$sortR = function () use (&$roster) { $o = array_flip(array_keys(STATUSES)); usort($roster, fn ($a, $b) => [$o[$a['status']] ?? 9, $a['priority']] <=> [$o[$b['status']] ?? 9, $b['priority']]); };

if ($method === 'POST' && preg_match('#^/roster(?:/([a-f0-9]{8}))?/(save|step|delete)$#', $path, $m)) {
    $id = $m[1] ?? ''; $act = $m[2]; $i = $id ? $find($id) : null;
    if ($act === 'step' && $i !== null) {
        $k = (int) ($_POST['k'] ?? -1); if (isset($roster[$i]['steps'][$k])) { $roster[$i]['steps'][$k]['done'] = !$roster[$i]['steps'][$k]['done']; $roster[$i]['updated'] = date('c'); save('roster', $roster); }
        if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
        go('/lab/roster/' . $id);
    }
    if ($act === 'delete' && $i !== null) { $t = $roster[$i]['title']; array_splice($roster, $i, 1); save('roster', $roster); flash("Removed “{$t}” from the roster. Yesterday's copy is in lab-data/backups."); go('/lab/roster'); }
    if ($act === 'save') {
        $s = fn ($k, $n = 300) => trim(mb_substr(str_replace("\0", '', (string) ($_POST[$k] ?? '')), 0, $n));
        $title = $s('title', 120); if ($title === '') { flash('Give the project a name.'); go($id ? "/lab/roster/$id/edit" : '/lab/roster/new'); }
        $old = $i !== null ? $roster[$i]['steps'] : []; $done = []; foreach ($old as $st) $done[$st['t']] = $st['done'];
        $steps = []; foreach (preg_split('/\R/', $s('steps', 6000)) as $line) { $line = trim(preg_replace('/^[-*•\s]+/u', '', $line)); if ($line !== '') $steps[] = ['t' => mb_substr($line, 0, 240), 'done' => $done[$line] ?? false]; }
        $p = ['title' => $title, 'home' => in_array($s('home'), HOMES, true) ? $s('home') : 'Other', 'status' => array_key_exists($s('status'), STATUSES) ? $s('status') : 'planned',
            'priority' => max(1, min(99, (int) $s('priority'))), 'summary' => $s('summary', 3000), 'steps' => $steps, 'rule' => $s('rule', 1500), 'notes' => $s('notes', 6000), 'updated' => date('c')];
        if ($i !== null) $roster[$i] = $p + $roster[$i]; else { $p += ['id' => bin2hex(random_bytes(4)), 'created' => date('c')]; $roster[] = $p; $id = $p['id']; }
        save('roster', $roster); flash('Saved.'); go('/lab/roster/' . $id);
    }
}

function ring(int $pct, string $col): string {
    $c = 2 * M_PI * 22; $off = $c * (1 - $pct / 100);
    return '<svg class="ring" viewBox="0 0 54 54" aria-hidden="true"><circle cx="27" cy="27" r="22" class="trk"/><circle cx="27" cy="27" r="22" class="arc" style="stroke:' . $col . ';stroke-dasharray:' . round($c, 2) . ';stroke-dashoffset:' . round($off, 2) . '"/><text x="27" y="31">' . $pct . '%</text></svg>';
}
function pct(array $p): int { $n = count($p['steps']); return $n ? (int) round(100 * count(array_filter($p['steps'], fn ($s) => $s['done'])) / $n) : ($p['status'] === 'live' ? 100 : 0); }
function badge(string $st): string { [$l, $c] = STATUSES[$st] ?? STATUSES['planned']; return '<span class="badge" style="--c:' . $c . '">' . e($l) . '</span>'; }

// ---------- pages
adPages($path, $method);
if ($path === '/') {
    $sortR(); $counts = array_count_values(array_column($roster, 'status'));
    $tiles = ''; foreach (STATUSES as $k => [$l, $c]) $tiles .= '<a class="tile" href="/lab/roster?s=' . $k . '" style="--c:' . $c . '"><b>' . ($counts[$k] ?? 0) . '</b><span>' . e($l) . '</span></a>';
    $rooms = ''; foreach (ROOMS as [$k, $t, $d, $open]) $rooms .= $open ? '<a class="room open" href="/lab/' . $k . '"><h3>' . e($t) . '</h3><p>' . e($d) . '</p><span>Enter →</span></a>' : '<div class="room"><h3>' . e($t) . '</h3><p>' . e($d) . '</p><span>On the roster</span></div>';
    page('Home', '<section class="hero"><h1>The Creation Lab</h1><p>Welcome back, Architect.</p></section><div class="tiles">' . $tiles . '</div><h2 class="rule">Rooms</h2><div class="rooms">' . $rooms . '</div>');
}

if ($path === '/roster') {
    $sortR(); $f = $_GET['s'] ?? ''; $h = $_GET['h'] ?? '';
    $chips = '<a href="/lab/roster"' . (!$f ? ' aria-current="true"' : '') . '>All</a>'; foreach (STATUSES as $k => [$l, $c]) $chips .= '<a href="/lab/roster?s=' . $k . '" style="--c:' . $c . '"' . ($f === $k ? ' aria-current="true"' : '') . '>' . e($l) . '</a>';
    $cols = '';
    foreach (STATUSES as $k => [$l, $c]) {
        if ($f && $f !== $k) continue;
        $cards = ''; foreach ($roster as $p) { if ($p['status'] !== $k || ($h && $p['home'] !== $h)) continue; $n = count($p['steps']); $d = count(array_filter($p['steps'], fn ($s) => $s['done']));
            $cards .= '<a class="card" href="/lab/roster/' . $p['id'] . '" style="--c:' . $c . '">' . ring(pct($p), $c) . '<div><h3>' . e($p['title']) . '</h3><small>' . e($p['home']) . ($n ? " · $d of $n steps" : '') . '</small><p>' . e(mb_strimwidth($p['summary'], 0, 150, '…')) . '</p></div></a>'; }
        if ($cards || $f) $cols .= '<section class="lane" style="--c:' . $c . '"><h2>' . e($l) . '</h2>' . ($cards ?: '<p class="empty">Nothing here yet.</p>') . '</section>';
    }
    page('Projects Roster', '<section class="hero"><h1>The Architect\'s Projects Roster</h1><p>Every build, its stage and its steps.</p><a class="btn" href="/lab/roster/new">+ New project</a></section><nav class="chips">' . $chips . '</nav>' . $cols, '<a href="/lab/">Lab</a>');
}

if (preg_match('#^/roster/(new|[a-f0-9]{8})(/edit)?$#', $path, $m)) {
    $new = $m[1] === 'new'; $i = $new ? null : $find($m[1]);
    if (!$new && $i === null) { http_response_code(404); page('Not found', '<p>That project isn\'t on the roster.</p>'); }
    $p = $new ? ['id' => '', 'title' => '', 'home' => 'Alchemy Studios', 'status' => 'planned', 'priority' => 5, 'summary' => '', 'steps' => [], 'rule' => '', 'notes' => ''] : $roster[$i];
    if ($new || !empty($m[2])) {
        $opt = fn ($list, $cur) => implode('', array_map(fn ($k, $v) => '<option value="' . e((string) $k) . '"' . ((string) $k === $cur ? ' selected' : '') . '>' . e(is_array($v) ? $v[0] : $v) . '</option>', array_keys($list), $list));
        $homes = array_combine(HOMES, HOMES);
        page($new ? 'New project' : 'Edit', '<section class="hero"><h1>' . ($new ? 'New project' : 'Edit · ' . e($p['title'])) . '</h1></section><form method="post" action="/lab/roster' . ($new ? '' : '/' . $p['id']) . '/save" class="stack wide">' . csrf()
            . '<label>Name<input name="title" required maxlength="120" value="' . e($p['title']) . '"></label><div class="row"><label>Home<select name="home">' . $opt($homes, $p['home']) . '</select></label><label>Stage<select name="status">' . $opt(STATUSES, $p['status']) . '</select></label><label>Priority<input type="number" name="priority" min="1" max="99" value="' . (int) $p['priority'] . '"></label></div>'
            . '<label>What it is<textarea name="summary" rows="4">' . e($p['summary']) . '</textarea></label><label>Steps <small>one per line</small><textarea name="steps" rows="8">' . e(implode("\n", array_column($p['steps'], 't'))) . '</textarea></label>'
            . '<label>The rule <small>anything that must never be broken</small><textarea name="rule" rows="2">' . e($p['rule']) . '</textarea></label><label>Notes<textarea name="notes" rows="4">' . e($p['notes']) . '</textarea></label><div class="row"><button>Save</button><a class="ghost" href="/lab/roster' . ($new ? '' : '/' . $p['id']) . '">Cancel</a></div></form>', '<a href="/lab/roster">Roster</a>');
    }
    [$l, $c] = STATUSES[$p['status']] ?? STATUSES['planned'];
    $steps = ''; foreach ($p['steps'] as $k => $s) $steps .= '<li><form method="post" action="/lab/roster/' . $p['id'] . '/step">' . csrf() . '<input type="hidden" name="k" value="' . $k . '"><button class="tick' . ($s['done'] ? ' on' : '') . '" aria-pressed="' . ($s['done'] ? 'true' : 'false') . '" aria-label="Mark step done"></button></form><span>' . e($s['t']) . '</span></li>';
    page($p['title'], '<article class="proj" style="--c:' . $c . '"><header>' . ring(pct($p), $c) . '<div><h1>' . e($p['title']) . '</h1><p>' . badge($p['status']) . ' <span class="home">' . e($p['home']) . '</span> <span class="home">Priority ' . (int) $p['priority'] . '</span></p></div><a class="btn" href="/lab/roster/' . $p['id'] . '/edit">Edit</a></header>'
        . ($p['summary'] ? '<p class="sum">' . nl2br(e($p['summary'])) . '</p>' : '')
        . ($p['steps'] ? '<h2 class="rule">Steps</h2><ol class="steps">' . $steps . '</ol>' : '')
        . ($p['rule'] ? '<aside class="law"><b>The rule</b><p>' . nl2br(e($p['rule'])) . '</p></aside>' : '')
        . ($p['notes'] ? '<h2 class="rule">Notes</h2><p class="sum">' . nl2br(e($p['notes'])) . '</p>' : '')
        . '<p class="meta">Updated ' . e(date('M j, Y g:i a', strtotime($p['updated']))) . '</p><form method="post" action="/lab/roster/' . $p['id'] . '/delete" class="danger" data-confirm="Remove “' . e($p['title']) . '” from the roster?">' . csrf() . '<button class="ghost">Remove from roster</button></form></article>', '<a href="/lab/roster">Roster</a>');
}

http_response_code(404); page('Not here', '<section class="hero"><h1>Not in this lab</h1><p><a href="/lab/">Back to the Creation Lab</a></p></section>');
