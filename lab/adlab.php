<?php
// The Ad Lab: every site's ads, promos, specials, coupons and campaigns, sized for each platform, scored by Optics,
// tracked by their own links, kept running while they work and sent to the Dead Archive when they don't.
// Included by index.php. Public parts (tracked links, finished artwork) run before sign-in; everything else after.
declare(strict_types=1);

const PLATFORMS = [
    // key => [name, link code, image sizes [label => [w, h]], copy limit, hashtags]
    'facebook'  => ['Facebook', 'f', ['Feed' => [1080, 1350], 'Link' => [1200, 630]], 2000, 3],
    'x'         => ['X', 'x', ['Post' => [1600, 900]], 280, 2],
    'pinterest' => ['Pinterest', 'p', ['Pin' => [1000, 1500]], 500, 5],
    'instagram' => ['Instagram', 'i', ['Feed' => [1080, 1350], 'Story' => [1080, 1920]], 2200, 8],
    'linkedin'  => ['LinkedIn', 'l', ['Post' => [1200, 627]], 3000, 3],
    'tiktok'    => ['TikTok', 't', ['Cover' => [1080, 1920]], 2200, 5],
];
const KINDS = ['ad' => 'Ad', 'promo' => 'Promotion', 'special' => 'Special', 'coupon' => 'Coupon', 'campaign' => 'Campaign', 'event' => 'Event'];
const CSTATUS = ['running' => ['Running', '#4ee0a0'], 'draft' => ['Draft', '#d8b45a'], 'paused' => ['Paused', '#7b8a92'], 'dead' => ['Dead Archive', '#e38a7a']];
const FB_GRAPH = 'https://graph.facebook.com/v26.0/';

function adSites(): array
{
    $s = load('sites', []);
    if ($s) return $s;
    $mk = fn ($id, $name, $url, $bg, $accent, $text, $voice, $tags, $logo = '') => compact('id', 'name', 'url', 'bg', 'accent', 'text', 'voice', 'tags', 'logo');
    $s = [
        $mk('de', 'Digital Epitaph', 'https://digitalepitaph.com/', '#0d0b08', '#d4a441', '#f3e9d2', 'Family Lives Forever', 'DigitalEpitaph FamilyHistory Genealogy Ancestry', 'https://digitalepitaph.com/tree/img/mark-gold.svg'),
        $mk('alchemy', 'Alchemy Studios', 'https://alchemystudiosorlando.com/', '#050507', '#c9a45c', '#e8e2d4', 'Imagination → Reality', 'AlchemyStudios Design Orlando', '/assets/brand/alchemy-emblem-2027.webp'),
        $mk('realm', 'The Realm', 'https://therealm.online/', '#06121c', '#7fd3ff', '#e6f6ff', 'Step into The Realm', 'TheRealm VR UnrealEngine', ''),
        $mk('vcards', 'Apex VCards', 'https://apexvcards.com/', '#07070a', '#e2b866', '#f2efe8', 'Your card, your page, one tap', 'ApexVCards BusinessCards NFC', ''),
        $mk('graffix', 'Graffix Tools', 'https://graffixtools.com/', '#111111', '#f2b705', '#ffffff', 'Built by an installer, for installers', 'GraffixTools VehicleWrap SignInstall', ''),
        $mk('grace', 'Grace Reintegration Services', 'https://gracelovesvets.com/', '#0b1a2e', '#c8a24a', '#f4f1ea', 'Helping veterans find home', 'Veterans GraceLovesVets', ''),
        $mk('blackdiamond', 'Black Diamond Liner', 'https://blackdiamondliner.com/', '#000000', '#9fb4c7', '#ffffff', 'Built to take more', 'BlackDiamondLiner Bedliner', ''),
        $mk('laserlab', 'Hughes Laser Lab', 'https://apexvcards.com/', '#0a0a0a', '#ff7a1a', '#f5f5f5', 'Made by hand, cut by light', 'HughesLaserLab LaserEngraving', ''),
    ];
    save('sites', $s);
    return $s;
}
function adSite(string $id): ?array { foreach (adSites() as $s) if ($s['id'] === $id) return $s; return null; }
function adCampaigns(): array { return load('campaigns', []); }
function adFind(array $all, string $id): ?int { foreach ($all as $i => $c) if ($c['id'] === $id) return $i; return null; }
function artDir(string $sub): string { $d = dataDir() . '/' . $sub; if (!is_dir($d)) @mkdir($d, 0700, true); return $d; }

/** The tracked link for one campaign on one platform: counted, then sent on with the campaign's tags. */
function adLink(array $c, string $p): string
{
    $code = PLATFORMS[$p][1] ?? 'd';
    return 'https://alchemystudiosorlando.com/lab/go/' . $c['id'] . $code;
}

/** The words for each platform, written from the campaign by rule: headline, the offer, the coupon, the call to act, the link. */
function adCopy(array $c, array $site, string $p): array
{
    $tags = array_slice(array_filter(preg_split('/[\s,#]+/', (string) ($c['tags'] ?: $site['tags']))), 0, PLATFORMS[$p][4]);
    $hash = $tags ? implode(' ', array_map(fn ($t) => '#' . $t, $tags)) : '';
    $coupon = $c['coupon'] !== '' ? 'Use code ' . $c['coupon'] . ($c['ends'] ? ' by ' . date('M j', strtotime($c['ends'])) : '') . '.' : '';
    $cta = $c['cta'] ?: 'Learn more';
    $c['offer'] = $c['offer'] !== '' && !preg_match('/[.!?…]$/u', $c['offer']) ? $c['offer'] . '.' : $c['offer'];   // the offer ends as a sentence
    $c['body'] = $c['body'] !== '' && !preg_match('/[.!?…]$/u', $c['body']) ? $c['body'] . '.' : $c['body'];
    $link = adLink($c, $p);
    $title = $c['headline'];
    switch ($p) {
        case 'x':
            $body = trim($title . ($c['offer'] ? ' ' . $c['offer'] : '') . ($coupon ? ' ' . $coupon : ''));
            $room = 280 - 24 - (strlen($hash) ? strlen($hash) + 1 : 0) - 1;   // a link counts as 23 characters on X
            if (mb_strlen($body) > $room) $body = rtrim(mb_substr($body, 0, $room - 1)) . '…';
            $text = $body . ' ' . $link . ($hash ? ' ' . $hash : '');
            break;
        case 'instagram':
            $text = $title . "\n\n" . trim($c['body'] . "\n" . $c['offer']) . ($coupon ? "\n" . $coupon : '') . "\n\n" . $cta . ': link in bio.' . ($hash ? "\n\n" . $hash : '');
            break;
        case 'pinterest':
            $text = mb_substr(trim($c['body'] . ' ' . $c['offer'] . ' ' . $coupon), 0, 480) . ($hash ? ' ' . $hash : '');
            break;
        case 'tiktok':
            $text = $title . ($c['offer'] ? ' ' . $c['offer'] : '') . ' ' . $hash;
            break;
        case 'linkedin':
            $text = $title . "\n\n" . trim($c['body']) . ($c['offer'] ? "\n\n" . $c['offer'] : '') . ($coupon ? ' ' . $coupon : '') . "\n\n" . $cta . ': ' . $link . ($hash ? "\n\n" . $hash : '');
            break;
        default:   // facebook
            $text = $title . "\n\n" . trim($c['body']) . ($c['offer'] ? "\n\n" . $c['offer'] : '') . ($coupon ? ' ' . $coupon : '') . "\n\n" . $cta . ' → ' . $link . ($hash ? "\n\n" . $hash : '');
    }
    return ['title' => $p === 'pinterest' ? mb_substr($title, 0, 100) : '', 'text' => trim(preg_replace("/\n{3,}/", "\n\n", $text)), 'link' => $link, 'limit' => PLATFORMS[$p][3]];
}

/** Optics: the checks a piece must pass before it goes out, each with its weight. */
function adOptics(array $c, array $site): array
{
    $host = parse_url($site['url'], PHP_URL_HOST);
    $copyOk = true; $long = [];
    foreach ($c['platforms'] as $p) { $cp = adCopy($c, $site, $p); $len = $p === 'x' ? mb_strlen(preg_replace('#https?://\S+#', str_repeat('x', 23), $cp['text'])) : mb_strlen($cp['text']); if ($len > $cp['limit']) { $copyOk = false; $long[] = PLATFORMS[$p][0]; } }
    $minW = 0; foreach ($c['platforms'] as $p) foreach (PLATFORMS[$p][2] as [$w]) $minW = max($minW, $w);
    $checks = [
        ['A headline people read in a glance (under 60 characters)', 15, $c['headline'] !== '' && mb_strlen($c['headline']) <= 60],
        ['It asks them to do something (a call to act)', 10, $c['cta'] !== ''],
        ['The link goes to ' . $site['name'] . '’s own site', 15, $c['link'] !== '' && $host && str_contains((string) parse_url($c['link'], PHP_URL_HOST), preg_replace('/^www\./', '', $host))],
        ['Artwork is in place', 15, !empty($c['art'])],
        ['The artwork is large enough (1024 px or more on its long side)', 10, !empty($c['art']) && max($c['art_w'] ?? 0, $c['art_h'] ?? 0) >= 1024],
        [$c['kind'] === 'coupon' ? 'A coupon has its code' : 'The offer is stated', 10, $c['kind'] === 'coupon' ? $c['coupon'] !== '' : ($c['offer'] !== '' || $c['coupon'] !== '')],
        ['It has an end date, after its start', 10, $c['ends'] !== '' && (!$c['starts'] || $c['ends'] >= $c['starts'])],
        ['The words fit every platform' . ($long ? ' (too long for ' . implode(', ', $long) . ')' : ''), 10, $copyOk && $c['platforms']],
        ['At least one platform chosen', 5, (bool) $c['platforms']],
    ];
    if (!empty($site['client'])) $checks[] = ['Approved by ' . ($site['contact'] ?: 'the client') . ' (this version)', 0, adApproved($c, $site)];
    $score = 0; foreach ($checks as [, $w, $ok]) if ($ok) $score += $w;
    return ['score' => $score, 'checks' => $checks];
}

/** A client's campaign must be approved, on its current version, before it runs. */
function adApproved(array $c, ?array $site): bool
{
    if (empty($site['client'])) return true;
    $a = $c['approval'] ?? null;
    return $a && $a['state'] === 'approved' && ($a['version'] ?? '') === adVersion($c);
}
/** What the client signs off on: everything they see. Any change after approval needs a new approval. */
function adVersion(array $c): string
{
    return substr(hash('sha256', json_encode([$c['site'], $c['headline'], $c['body'], $c['offer'], $c['coupon'], $c['cta'], $c['link'], $c['starts'], $c['ends'], $c['platforms'], $c['tags'], $c['art'] ?? '', $c['art_w'] ?? 0, $c['art_prompt'] ?? ''])), 0, 12);
}

function adClicks(): array { return load('clicks', []); }
function adClickSum(array $clicks, string $cid): array
{
    $by = []; $days = [];
    foreach ($clicks as $k => $n) { [$id, $p, $d] = explode('|', $k) + ['', '', '']; if ($id !== $cid) continue; $by[$p] = ($by[$p] ?? 0) + $n; $days[$d] = ($days[$d] ?? 0) + $n; }
    return [$by, $days];
}

// ---------- public: tracked links and finished artwork (no sign-in)
if (preg_match('#^/go/([a-f0-9]{8})([a-z])$#', $path, $m)) {
    $all = adCampaigns(); $i = adFind($all, $m[1]);
    if ($i === null) { http_response_code(404); exit('Not found'); }
    $c = $all[$i]; $p = 'direct'; foreach (PLATFORMS as $k => $pl) if ($pl[1] === $m[2]) $p = $k;
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($c['status'] !== 'draft' && !preg_match('/bot|crawl|spider|preview|facebookexternalhit|slurp|embed/i', $ua)) {
        $f = dataDir() . '/clicks.json'; $h = fopen($f, 'c+'); flock($h, LOCK_EX);
        $data = json_decode((string) stream_get_contents($h), true) ?: [];
        $k = $c['id'] . '|' . $p . '|' . date('Y-m-d'); $data[$k] = ($data[$k] ?? 0) + 1;
        ftruncate($h, 0); rewind($h); fwrite($h, json_encode($data)); flock($h, LOCK_UN); fclose($h);
    }
    $to = $c['link'] ?: (adSite($c['site'])['url'] ?? 'https://alchemystudiosorlando.com/');
    $q = http_build_query(['utm_source' => $p, 'utm_medium' => 'social', 'utm_campaign' => preg_replace('/[^a-z0-9]+/', '-', strtolower($c['headline']))]);
    header('Cache-Control: no-store'); header('Location: ' . $to . (str_contains($to, '?') ? '&' : '?') . $q, true, 302); exit;
}
if (preg_match('#^/art/([a-f0-9]{8})-([a-z]+)-(\d+)\.png$#', $path, $m)) {
    $f = artDir('renders') . "/{$m[1]}-{$m[2]}-{$m[3]}.png";
    if (!is_file($f)) { http_response_code(404); exit('Not found'); }
    header('Content-Type: image/png'); header('Cache-Control: public, max-age=86400'); header('Content-Security-Policy: default-src \'none\''); readfile($f); exit;
}
// ---------- public: the client's review page (the link itself is the key)
if (preg_match('#^/review/([a-f0-9]{32})(/art|/logo)?$#', $path, $m)) {
    $all = adCampaigns(); $i = null;
    foreach ($all as $k => $cc) if (hash_equals((string) ($cc['approval']['token'] ?? ''), $m[1])) $i = $k;
    if ($i === null || ($all[$i]['status'] ?? '') === 'dead') { http_response_code(404); exit('This review link is no longer active.'); }
    $c = $all[$i]; $site = adSite($c['site']);
    if (($m[2] ?? '') === '/art') { $f = artDir('art') . '/' . ($c['art'] ?? ''); if (empty($c['art']) || !is_file($f)) { http_response_code(404); exit; } header('Content-Type: ' . (mime_content_type($f) ?: 'image/jpeg')); header('Cache-Control: private, max-age=600'); readfile($f); exit; }
    if (($m[2] ?? '') === '/logo') { adLogo($site); exit; }
    $current = ($c['approval']['version'] ?? '') === adVersion($c);
    if ($method === 'POST') {
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(400); exit('The page expired. Open the link again.'); }
        $dec = $_POST['decision'] ?? ''; $name = trim(mb_substr((string) ($_POST['name'] ?? ''), 0, 80)); $note = trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 2000));
        $err = !in_array($dec, ['approved', 'changes'], true) ? 'Choose approve or request changes.' : ($name === '' ? 'Type your name to sign.' : ($dec === 'changes' && $note === '' ? 'Tell us what to change.' : ($current ? '' : 'This version has changed since the link was sent. Open the link again.')));
        if (!$err) {
            $rec = ['state' => $dec, 'by' => $name, 'note' => $note, 'at' => date('c'), 'ip' => substr(hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16)];
            $all[$i]['approval'] = array_merge($all[$i]['approval'], ['state' => $dec, 'by' => $name, 'note' => $note, 'at' => $rec['at']]);
            $all[$i]['approval']['history'][] = $rec + ['version' => adVersion($c)];
            save('campaigns', $all);
            @mail('raven@alchemystudiosorlando.com', ($dec === 'approved' ? 'Approved: ' : 'Changes requested: ') . $c['headline'] . ' (' . ($site['name'] ?? '') . ')',
                ($dec === 'approved' ? "$name approved the campaign \"{$c['headline']}\"." : "$name asked for changes to \"{$c['headline']}\":\n\n$note") . "\n\nhttps://alchemystudiosorlando.com/lab/adlab/c/{$c['id']}",
                "From: Alchemy Studios <raven@alchemystudiosorlando.com>\r\nContent-Type: text/plain; charset=UTF-8");
            header('Location: /lab/review/' . $m[1], true, 303); exit;
        }
        $_SESSION['review_err'] = $err; header('Location: /lab/review/' . $m[1], true, 303); exit;
    }
    adReviewPage($c, $site, $m[1], $current);
}
if (!function_exists('adPages')) {
function adPages(string $path, string $method): void
{
    if (!str_starts_with($path, '/adlab')) return;
    $sites = adSites(); $all = adCampaigns();
    $crumb = '<a href="/lab/adlab">Ad Lab</a>';

    // ----- changes
    if ($method === 'POST') {
        if ($path === '/adlab/save') {
            $s = fn ($k, $n = 300) => trim(mb_substr(str_replace("\0", '', (string) ($_POST[$k] ?? '')), 0, $n));
            $id = $s('id', 8); $i = $id !== '' ? adFind($all, $id) : null;
            $c = $i !== null ? $all[$i] : ['id' => bin2hex(random_bytes(4)), 'status' => 'draft', 'created' => date('c'), 'posts' => [], 'metrics' => [], 'art' => null];
            $site = $s('site', 20); if (!adSite($site)) { flash('Choose a site.'); go('/lab/adlab'); }
            $c = array_merge($c, ['site' => $site, 'event' => $s('event', 120), 'kind' => array_key_exists($s('kind'), KINDS) ? $s('kind') : 'ad', 'headline' => $s('headline', 120),
                'body' => $s('body', 1500), 'offer' => $s('offer', 200), 'coupon' => strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $s('coupon', 30))), 'cta' => $s('cta', 40),
                'link' => filter_var($s('link', 400), FILTER_VALIDATE_URL) ?: '', 'tags' => $s('tags', 200),
                'starts' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $s('starts')) ? $s('starts') : '', 'ends' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $s('ends')) ? $s('ends') : '',
                'platforms' => array_values(array_intersect(array_keys(PLATFORMS), (array) ($_POST['platforms'] ?? []))), 'updated' => date('c')]);
            if ($c['headline'] === '') { flash('Give it a headline.'); go($i !== null ? "/lab/adlab/c/{$c['id']}/edit" : '/lab/adlab/new'); }
            $up = $_FILES['art'] ?? null;
            if ($up && ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $info = @getimagesize($up['tmp_name']);
                if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) { flash('Artwork must be a JPEG, PNG or WebP.'); go("/lab/adlab/c/{$c['id']}/edit"); }
                $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
                foreach (glob(artDir('art') . "/{$c['id']}.*") ?: [] as $old) @unlink($old);
                move_uploaded_file($up['tmp_name'], artDir('art') . "/{$c['id']}.$ext");
                $c['art'] = "{$c['id']}.$ext"; $c['art_w'] = $info[0]; $c['art_h'] = $info[1];
            }
            $paused = false;
            if ($c['status'] === 'running' && !adApproved($c, adSite($c['site']))) { $c['status'] = 'paused'; $paused = true; }   // a client's campaign changed: it waits for their approval again
            if ($i !== null) $all[$i] = $c; else $all[] = $c;
            save('campaigns', $all); flash($paused ? 'Saved and paused: the client needs to approve this new version before it runs again.' : 'Saved.'); go("/lab/adlab/c/{$c['id']}");
        }
        if (preg_match('#^/adlab/c/([a-f0-9]{8})/(run|pause|kill|revive|clone|metrics|render|fb-post|fb-stats|generate|approval)$#', $path, $m)) {
            $i = adFind($all, $m[1]); if ($i === null) { http_response_code(404); exit; }
            $c = &$all[$i]; $act = $m[2];
            if ($act === 'generate') {
                $desc = trim(mb_substr((string) ($_POST['desc'] ?? ''), 0, 1500)); $shape = in_array($_POST['shape'] ?? '', ['square', 'wide', 'tall'], true) ? $_POST['shape'] : 'square';
                if ($desc === '') { flash('Describe the picture first.'); go("/lab/adlab/c/{$c['id']}"); }
                $site = adSite($c['site']);
                try {
                    [$bytes, $model] = artistMake(artistPrompt($desc, $site, $c), $shape);
                    $info = @getimagesizefromstring($bytes);
                    if (!$info) throw new RuntimeException('The picture came back unreadable');
                    $ext = $info[2] === IMAGETYPE_JPEG ? 'jpg' : ($info[2] === IMAGETYPE_WEBP ? 'webp' : 'png');
                    foreach (glob(artDir('art') . "/{$c['id']}.*") ?: [] as $old) @rename($old, artDir('art-history') . '/' . basename($old, '.' . pathinfo($old, PATHINFO_EXTENSION)) . '-' . date('YmdHis') . '.' . pathinfo($old, PATHINFO_EXTENSION));
                    file_put_contents(artDir('art') . "/{$c['id']}.$ext", $bytes);
                    $c['art'] = "{$c['id']}.$ext"; $c['art_w'] = $info[0]; $c['art_h'] = $info[1]; $c['art_prompt'] = $desc; $c['art_model'] = $model; $c['generated'] = ($c['generated'] ?? 0) + 1;
                    flash('New artwork made with ' . $model . '. The sizes below are drawn from it.');
                } catch (Throwable $e) { flash('The artist couldn’t make it: ' . $e->getMessage()); }
            }
            if (in_array($act, ['run', 'fb-post'], true) && !adApproved($c, adSite($c['site']))) { flash('This is a client’s campaign: send it for approval first. It can run once they approve this version.'); unset($c); go("/lab/adlab/c/{$m[1]}"); }
            if ($act === 'approval') {
                $c['approval'] = ['state' => 'pending', 'token' => $c['approval']['token'] ?? bin2hex(random_bytes(16)), 'version' => adVersion($c), 'sent' => date('c'), 'history' => $c['approval']['history'] ?? []];
                flash('The approval link is ready. Copy it below and send it to the client.');
            }
            if ($act === 'run') { $c['status'] = 'running'; $c['started'] ??= date('c'); flash('Running.'); }
            if ($act === 'pause') { $c['status'] = 'paused'; flash('Paused.'); }
            if ($act === 'kill') { $c['status'] = 'dead'; $c['killed'] = date('c'); $c['kill_reason'] = trim(mb_substr((string) ($_POST['why'] ?? ''), 0, 300)); flash('Sent to the Dead Archive.'); }
            if ($act === 'revive') { $c['status'] = 'paused'; unset($c['killed']); flash('Brought back from the Dead Archive, paused.'); }
            if ($act === 'clone') {
                $n = $c; $n['id'] = bin2hex(random_bytes(4)); $n['status'] = 'draft'; $n['created'] = date('c'); $n['posts'] = []; $n['metrics'] = []; unset($n['killed'], $n['kill_reason'], $n['started']);
                if (!empty($c['art'])) { $ext = pathinfo($c['art'], PATHINFO_EXTENSION); @copy(artDir('art') . '/' . $c['art'], artDir('art') . "/{$n['id']}.$ext"); $n['art'] = "{$n['id']}.$ext"; }
                $n['headline'] = $c['headline']; unset($c); $all[] = $n; save('campaigns', $all); flash('Copied as a new draft.'); go("/lab/adlab/c/{$n['id']}/edit");
            }
            if ($act === 'metrics') {
                $p = (string) ($_POST['p'] ?? ''); if (isset(PLATFORMS[$p])) {
                    foreach (['views', 'likes', 'comments', 'shares'] as $k) $c['metrics'][$p][$k] = max(0, (int) ($_POST[$k] ?? 0));
                    $c['metrics'][$p]['updated'] = date('c'); $c['metrics'][$p]['by'] = 'hand';
                }
                flash('Numbers saved.');
            }
            if ($act === 'render') {   // a finished size from the composer in the browser, kept for posting
                $p = (string) ($_POST['p'] ?? ''); $w = (int) ($_POST['w'] ?? 0); $f = $_FILES['png'] ?? null;
                header('Content-Type: application/json');
                if (!isset(PLATFORMS[$p]) || !$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || (@getimagesize($f['tmp_name'])[2] ?? 0) !== IMAGETYPE_PNG) { echo '{"ok":false}'; exit; }
                move_uploaded_file($f['tmp_name'], artDir('renders') . "/{$c['id']}-$p-$w.png");
                $c['renders'][$p][$w] = date('c'); unset($c); save('campaigns', $all); echo '{"ok":true}'; exit;
            }
            if ($act === 'fb-post' || $act === 'fb-stats') {
                $fb = load('facebook', []);
                if (empty($fb['page_id']) || empty($fb['token'])) { flash('Connect the Facebook Page first (Ad Lab › Facebook).'); go("/lab/adlab/c/{$c['id']}"); }
                if ($act === 'fb-post') {
                    $size = PLATFORMS['facebook'][2]['Feed'][0];
                    if (empty($c['renders']['facebook'][$size])) { flash('Make the Facebook artwork first: open Artwork and press “Keep for posting”.'); go("/lab/adlab/c/{$c['id']}"); }
                    $site = adSite($c['site']); $cp = adCopy($c, $site, 'facebook');
                    $r = fbCall('POST', $fb['page_id'] . '/photos', ['url' => "https://alchemystudiosorlando.com/lab/art/{$c['id']}-facebook-$size.png?v=" . strtotime($c['renders']['facebook'][$size]), 'caption' => $cp['text'], 'access_token' => $fb['token']]);
                    if (!empty($r['post_id']) || !empty($r['id'])) { $c['posts']['facebook'] = ['id' => $r['post_id'] ?? ($fb['page_id'] . '_' . $r['id']), 'at' => date('c')]; if ($c['status'] === 'draft') { $c['status'] = 'running'; $c['started'] = date('c'); } flash('Posted to ' . ($fb['page_name'] ?? 'the Facebook Page') . '.'); }
                    else flash('Facebook said: ' . ($r['error']['message'] ?? 'no answer'));
                } else {
                    $pid = $c['posts']['facebook']['id'] ?? ''; if (!$pid) { flash('Not posted to Facebook yet.'); go("/lab/adlab/c/{$c['id']}"); }
                    $r = fbCall('GET', $pid, ['fields' => 'reactions.summary(total_count).limit(0),comments.summary(true).limit(0),shares', 'access_token' => $fb['token']]);
                    if (isset($r['error'])) flash('Facebook said: ' . $r['error']['message']);
                    else {
                        $v = fbCall('GET', $pid . '/insights', ['metric' => 'post_total_media_view_unique', 'access_token' => $fb['token']]);
                        $c['metrics']['facebook'] = ['likes' => (int) ($r['reactions']['summary']['total_count'] ?? 0), 'comments' => (int) ($r['comments']['summary']['total_count'] ?? 0),
                            'shares' => (int) ($r['shares']['count'] ?? 0), 'views' => (int) ($v['data'][0]['values'][0]['value'] ?? ($c['metrics']['facebook']['views'] ?? 0)), 'updated' => date('c'), 'by' => 'facebook'];
                        flash('Facebook numbers brought in.' . (isset($v['error']) ? ' (Views need 100+ Page likes on Facebook’s side.)' : ''));
                    }
                }
            }
            unset($c); save('campaigns', $all); go("/lab/adlab/c/{$m[1]}");
        }
        if ($path === '/adlab/site') {
            $id = (string) ($_POST['id'] ?? ''); $s = fn ($k, $n = 200) => trim(mb_substr((string) ($_POST[$k] ?? ''), 0, $n));
            $hex = fn ($k, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', $s($k)) ? $s($k) : $d;
            $found = false;
            $who = ['client' => !empty($_POST['client']), 'contact' => $s('contact', 80), 'email' => filter_var($s('email'), FILTER_VALIDATE_EMAIL) ?: '', 'phone' => preg_replace('/\D/', '', $s('phone', 30))];
            foreach ($sites as &$st) if ($st['id'] === $id) { $found = true; $st = array_merge($st, $who, ['name' => $s('name', 80) ?: $st['name'], 'url' => filter_var($s('url'), FILTER_VALIDATE_URL) ?: $st['url'],
                'bg' => $hex('bg', $st['bg']), 'accent' => $hex('accent', $st['accent']), 'text' => $hex('text', $st['text']), 'voice' => $s('voice', 120), 'tags' => $s('tags'), 'logo' => $s('logo', 300)]); }
            unset($st);
            if (!$found && $s('name') !== '') $sites[] = ['id' => substr(preg_replace('/[^a-z0-9]/', '', strtolower($s('name'))), 0, 16) ?: bin2hex(random_bytes(3)), 'name' => $s('name', 80), 'url' => filter_var($s('url'), FILTER_VALIDATE_URL) ?: '',
                'bg' => $hex('bg', '#000000'), 'accent' => $hex('accent', '#c9a45c'), 'text' => $hex('text', '#ffffff'), 'voice' => $s('voice', 120), 'tags' => $s('tags'), 'logo' => $s('logo', 300)] + $who;
            save('sites', $sites); flash('Site saved.'); go('/lab/adlab/sites');
        }
        if ($path === '/adlab/artist') {
            $old = load('artist', []); $s = fn ($k) => trim((string) ($_POST[$k] ?? ''));
            if (!empty($_POST['forget'])) { save('artist', []); flash('The artist is disconnected.'); go('/lab/adlab/artist'); }
            $ep = rtrim($s('endpoint'), '/'); if (!preg_match('#^https://[a-z0-9.-]+\.(azure\.com|microsoft\.com|openai\.com)(/.*)?$#i', $ep)) { flash('That endpoint doesn’t look like an Azure Foundry address (https://…services.ai.azure.com).'); go('/lab/adlab/artist'); }
            save('artist', ['endpoint' => preg_replace('#/(openai|mai)/v1.*$|/api/projects/.*$#', '', $ep), 'key' => $s('key') !== '' ? $s('key') : ($old['key'] ?? ''), 'main' => $s('main') ?: 'MAI-Image-2', 'backup' => $s('backup'), 'saved' => date('c')]);
            flash('Saved. Make a picture on any campaign to try it.'); go('/lab/adlab/artist');
        }
        if ($path === '/adlab/facebook') {
            $page = preg_replace('/\D/', '', (string) ($_POST['page_id'] ?? '')); $tok = trim((string) ($_POST['token'] ?? ''));
            if (!empty($_POST['forget'])) { save('facebook', []); flash('Facebook disconnected.'); go('/lab/adlab/facebook'); }
            $old = load('facebook', []); if ($tok === '' && !empty($old['token'])) $tok = $old['token'];
            $r = fbCall('GET', $page, ['fields' => 'name,followers_count', 'access_token' => $tok]);
            if (isset($r['error']) || empty($r['name'])) { flash('Facebook didn’t accept that Page and token: ' . ($r['error']['message'] ?? 'no answer')); go('/lab/adlab/facebook'); }
            save('facebook', ['page_id' => $page, 'token' => $tok, 'page_name' => $r['name'], 'followers' => $r['followers_count'] ?? null, 'connected' => date('c')]);
            flash('Connected to ' . $r['name'] . '.'); go('/lab/adlab/facebook');
        }
    }

    // ----- pages
    $clicks = adClicks();
    $sitesById = array_column($sites, null, 'id');
    $badge = fn ($st) => '<span class="badge" style="--c:' . (CSTATUS[$st][1] ?? '#999') . '">' . e(CSTATUS[$st][0] ?? $st) . '</span>';
    $nav = '<nav class="chips"><a href="/lab/adlab"' . ($path === '/adlab' ? ' aria-current="true"' : '') . '>Campaigns</a><a href="/lab/adlab/dead"' . ($path === '/adlab/dead' ? ' aria-current="true"' : '') . '>Dead Archive</a><a href="/lab/adlab/sites"' . ($path === '/adlab/sites' ? ' aria-current="true"' : '') . '>Sites</a><a href="/lab/adlab/facebook"' . ($path === '/adlab/facebook' ? ' aria-current="true"' : '') . '>Facebook</a><a href="/lab/adlab/artist"' . ($path === '/adlab/artist' ? ' aria-current="true"' : '') . '>Artist</a></nav>';
    $card = function (array $c) use ($clicks, $sitesById, $badge): string {
        [$by] = adClickSum($clicks, $c['id']); $site = $sitesById[$c['site']] ?? ['name' => $c['site'], 'accent' => '#c9a45c'];
        $o = adOptics($c, $sitesById[$c['site']] ?? ['url' => '', 'name' => '', 'tags' => '']);
        $likes = array_sum(array_map(fn ($m) => (int) ($m['likes'] ?? 0), $c['metrics'] ?? []));
        return '<a class="card" href="/lab/adlab/c/' . $c['id'] . '" style="--c:' . e($site['accent']) . '">' . ring($o['score'], $o['score'] >= 80 ? '#4ee0a0' : ($o['score'] >= 60 ? '#d8b45a' : '#e38a7a'))
            . '<div><h3>' . e($c['headline']) . '</h3><small>' . e($site['name']) . ' · ' . e(KINDS[$c['kind']] ?? '') . ($c['event'] ? ' · ' . e($c['event']) : '') . '</small>'
            . '<p>' . $badge($c['status']) . ' <span class="home">' . array_sum($by) . ' clicks</span> <span class="home">' . $likes . ' likes</span>' . ($c['ends'] ? ' <span class="home">ends ' . e(date('M j', strtotime($c['ends']))) . '</span>' : '') . '</p></div></a>';
    };

    if ($path === '/adlab' || $path === '/adlab/dead') {
        $dead = $path === '/adlab/dead'; $f = $_GET['site'] ?? '';
        $chips = '<nav class="chips"><a href="?"' . (!$f ? ' aria-current="true"' : '') . '>All sites</a>'; foreach ($sites as $s) $chips .= '<a href="?site=' . e($s['id']) . '" style="--c:' . e($s['accent']) . '"' . ($f === $s['id'] ? ' aria-current="true"' : '') . '>' . e($s['name']) . '</a>'; $chips .= '</nav>';
        $lanes = '';
        foreach ($dead ? ['dead'] : ['running', 'draft', 'paused'] as $st) {
            $cs = array_filter($all, fn ($c) => $c['status'] === $st && (!$f || $c['site'] === $f));
            usort($cs, fn ($a, $b) => strcmp($b['updated'] ?? '', $a['updated'] ?? ''));
            $lanes .= '<section class="lane" style="--c:' . CSTATUS[$st][1] . '"><h2>' . e(CSTATUS[$st][0]) . ' <small class="home">' . count($cs) . '</small></h2>' . ($cs ? implode('', array_map($card, $cs)) : '<p class="empty">' . ($dead ? 'Nothing has been killed yet.' : 'None.') . '</p>') . '</section>';
        }
        $totClicks = 0; foreach ($clicks as $k => $n) $totClicks += $n;
        $running = count(array_filter($all, fn ($c) => $c['status'] === 'running'));
        $tiles = '<div class="tiles"><div class="tile" style="--c:#4ee0a0"><b>' . $running . '</b><span>Running</span></div><div class="tile" style="--c:#d8b45a"><b>' . count(array_filter($all, fn ($c) => $c['status'] === 'draft')) . '</b><span>Drafts</span></div><div class="tile" style="--c:#7fd3ff"><b>' . $totClicks . '</b><span>Tracked clicks</span></div><div class="tile" style="--c:#e38a7a"><b>' . count(array_filter($all, fn ($c) => $c['status'] === 'dead')) . '</b><span>Dead Archive</span></div></div>';
        page($dead ? 'Dead Archive' : 'Ad Lab', '<section class="hero"><h1>' . ($dead ? 'The Dead Archive' : 'The Ad Lab') . '</h1><p>' . ($dead ? 'Campaigns that were killed, kept with their numbers so the good ideas can come back.' : 'Ads, promotions, specials, coupons and campaigns for every site, sized for every platform.') . '</p>' . ($dead ? '' : '<a class="btn" href="/lab/adlab/new">+ New campaign</a>') . '</section>' . $nav . ($dead ? '' : $tiles) . $chips . $lanes, '<a href="/lab/">Lab</a>');
    }

    if ($path === '/adlab/sites') {
        $rows = ''; foreach (array_merge($sites, [['id' => '', 'name' => '', 'url' => '', 'bg' => '#000000', 'accent' => '#c9a45c', 'text' => '#ffffff', 'voice' => '', 'tags' => '', 'logo' => '', 'client' => true]]) as $s)
            $rows .= '<details class="site"' . ($s['id'] ? '' : ' open') . '><summary><i style="background:' . e($s['accent']) . '"></i>' . ($s['id'] ? e($s['name']) : '+ Add a site') . (!empty($s['client']) && $s['id'] ? ' <span class="badge" style="--c:#7fd3ff">client</span>' : '') . ' <small class="home">' . e((string) parse_url($s['url'], PHP_URL_HOST)) . '</small></summary>'
                . '<form method="post" action="/lab/adlab/site" class="stack wide">' . csrf() . '<input type="hidden" name="id" value="' . e($s['id']) . '"><div class="row"><label>Name<input name="name" value="' . e($s['name']) . '" required></label><label>Website<input name="url" type="url" value="' . e($s['url']) . '"></label></div>'
                . '<div class="row"><label>Background<input type="color" name="bg" value="' . e($s['bg']) . '"></label><label>Accent<input type="color" name="accent" value="' . e($s['accent']) . '"></label><label>Text<input type="color" name="text" value="' . e($s['text']) . '"></label></div>'
                . '<label>Tagline<input name="voice" value="' . e($s['voice']) . '"></label><label>Hashtags <small>words, no # needed</small><input name="tags" value="' . e($s['tags']) . '"></label><label>Logo address <small>a link to the logo (PNG, SVG or WebP)</small><input name="logo" value="' . e($s['logo']) . '"></label>'
                . '<label class="tick-l"><input type="checkbox" name="client" value="1"' . (!empty($s['client']) ? ' checked' : '') . '> A client’s site: campaigns go to the client for approval before they run</label>'
                . '<div class="row"><label>Client contact<input name="contact" value="' . e($s['contact'] ?? '') . '"></label><label>Client email<input name="email" type="email" value="' . e($s['email'] ?? '') . '"></label><label>WhatsApp number <small>with country code, e.g. 1 606 555 0123</small><input name="phone" inputmode="tel" value="' . e($s['phone'] ?? '') . '"></label></div><div class="row"><button>Save site</button></div></form></details>';
        page('Sites', '<section class="hero"><h1>Sites</h1><p>Each site’s brand kit: its colours, tagline, hashtags and logo. Every campaign takes its look from here.</p></section>' . $nav . $rows, $crumb);
    }

    if ($path === '/adlab/artist') {
        $ar = load('artist', []);
        page('Artist', '<section class="hero"><h1>The Artist</h1><p>Pictures made from a description, through your own Microsoft Foundry account (Azure). MAI-Image-2 is Microsoft’s own model, the one behind Copilot’s pictures; gpt-image-2 can stand by in case it’s busy.</p></section>' . $nav
            . ($ar ? '<aside class="law"><b>Connected</b><p>' . e((string) parse_url($ar['endpoint'], PHP_URL_HOST)) . ' · ' . e($ar['main']) . ($ar['backup'] ? ' (backup ' . e($ar['backup']) . ')' : '') . '</p></aside>' : '')
            . '<form method="post" action="/lab/adlab/artist" class="stack wide">' . csrf()
            . '<label>Endpoint <small>Foundry › your resource › Endpoint, e.g. https://name.services.ai.azure.com</small><input name="endpoint" required value="' . e($ar['endpoint'] ?? '') . '"></label>'
            . '<label>Key <small>paste it yourself; kept on the server, never shown again</small><input name="key" type="password" autocomplete="off" placeholder="' . ($ar ? 'kept · leave blank to keep it' : '') . '"></label>'
            . '<div class="row"><label>Main model (deployment name)<input name="main" value="' . e($ar['main'] ?? 'MAI-Image-2') . '"></label><label>Backup (deployment name) <small>optional</small><input name="backup" value="' . e($ar['backup'] ?? 'gpt-image-2') . '"></label></div>'
            . '<div class="row"><button>Save</button>' . ($ar ? '<button class="ghost" name="forget" value="1">Disconnect</button>' : '') . '</div></form>', $crumb);
    }
    if ($path === '/adlab/facebook') {
        $fb = load('facebook', []);
        page('Facebook', '<section class="hero"><h1>Facebook</h1><p>Connect a Facebook Page so the Ad Lab can post to it and bring back its likes, comments, shares and views.</p></section>' . $nav
            . ($fb ? '<aside class="law"><b>Connected</b><p>' . e($fb['page_name']) . ' (Page ' . e($fb['page_id']) . ')' . ($fb['followers'] !== null ? ' · ' . (int) $fb['followers'] . ' followers' : '') . ' · since ' . e(date('M j, Y', strtotime($fb['connected']))) . '</p></aside>' : '')
            . '<form method="post" action="/lab/adlab/facebook" class="stack wide">' . csrf() . '<label>Page ID <small>Facebook › your Page › About › Page transparency, or Meta Business Suite › Settings</small><input name="page_id" required value="' . e($fb['page_id'] ?? '') . '" inputmode="numeric"></label>'
            . '<label>Page access token <small>paste it yourself; it is kept on the server, never shown again</small><input name="token" type="password" autocomplete="off" placeholder="' . ($fb ? 'kept · leave blank to keep it' : '') . '"></label><div class="row"><button>' . ($fb ? 'Check and save' : 'Connect') . '</button>' . ($fb ? '<button class="ghost" name="forget" value="1">Disconnect</button>' : '') . '</div></form>'
            . '<h2 class="rule">Getting the token</h2><ol class="steps plain"><li><span>At developers.facebook.com, create an app (type <b>Business</b>) and add <b>Facebook Login for Business</b>.</span></li><li><span>In the Graph API Explorer, choose that app, then <b>Get Page Access Token</b> for the Digital Epitaph Page, with <code>pages_manage_posts</code>, <code>pages_read_engagement</code> and <code>read_insights</code>.</span></li><li><span>Exchange it for a long-lived token (Access Token Debugger › Extend), then paste it above.</span></li></ol>', $crumb);
    }

    if ($path === '/adlab/new' || preg_match('#^/adlab/c/([a-f0-9]{8})/edit$#', $path, $m)) {
        $i = isset($m[1]) ? adFind($all, $m[1]) : null;
        $c = $i !== null ? $all[$i] : ['id' => '', 'site' => $_GET['site'] ?? 'de', 'event' => '', 'kind' => 'promo', 'headline' => '', 'body' => '', 'offer' => '', 'coupon' => '', 'cta' => 'Learn more', 'link' => '', 'tags' => '', 'starts' => date('Y-m-d'), 'ends' => '', 'platforms' => ['facebook'], 'art' => null];
        $so = ''; foreach ($sites as $s) $so .= '<option value="' . e($s['id']) . '"' . ($s['id'] === $c['site'] ? ' selected' : '') . '>' . e($s['name']) . '</option>';
        $ko = ''; foreach (KINDS as $k => $l) $ko .= '<option value="' . $k . '"' . ($k === $c['kind'] ? ' selected' : '') . '>' . $l . '</option>';
        $pl = ''; foreach (PLATFORMS as $k => $p) $pl .= '<label class="tick-l"><input type="checkbox" name="platforms[]" value="' . $k . '"' . (in_array($k, $c['platforms'], true) ? ' checked' : '') . '> ' . e($p[0]) . '</label>';
        page($c['id'] ? 'Edit campaign' : 'New campaign', '<section class="hero"><h1>' . ($c['id'] ? 'Edit · ' . e($c['headline']) : 'New campaign') . '</h1></section>'
            . '<form method="post" action="/lab/adlab/save" enctype="multipart/form-data" class="stack wide">' . csrf() . '<input type="hidden" name="id" value="' . e($c['id']) . '">'
            . '<div class="row"><label>Site<select name="site">' . $so . '</select></label><label>Kind<select name="kind">' . $ko . '</select></label><label>Event <small>launch, holiday, sale…</small><input name="event" value="' . e($c['event']) . '"></label></div>'
            . '<label>Headline <small>under 60 characters reads in a glance</small><input name="headline" maxlength="120" required value="' . e($c['headline']) . '"></label>'
            . '<label>The message<textarea name="body" rows="3">' . e($c['body']) . '</textarea></label>'
            . '<div class="row"><label>The offer <small>e.g. 30% off for 6 months</small><input name="offer" value="' . e($c['offer']) . '"></label><label>Coupon code<input name="coupon" value="' . e($c['coupon']) . '"></label><label>Call to act<input name="cta" value="' . e($c['cta']) . '"></label></div>'
            . '<label>Where it sends people <small>a page on the site</small><input name="link" type="url" value="' . e($c['link']) . '"></label>'
            . '<div class="row"><label>Starts<input type="date" name="starts" value="' . e($c['starts']) . '"></label><label>Ends<input type="date" name="ends" value="' . e($c['ends']) . '"></label><label>Hashtags <small>blank: the site’s own</small><input name="tags" value="' . e($c['tags']) . '"></label></div>'
            . '<fieldset class="plats"><legend>Platforms</legend>' . $pl . '</fieldset>'
            . '<label>Artwork <small>JPEG, PNG or WebP, large; it is cropped to each size, never stretched</small><input type="file" name="art" accept="image/jpeg,image/png,image/webp"></label>' . (!empty($c['art']) ? '<p class="meta">Current artwork kept unless you choose a new one.</p>' : '')
            . '<div class="row"><button>Save</button><a class="ghost" href="/lab/adlab' . ($c['id'] ? '/c/' . $c['id'] : '') . '">Cancel</a></div></form>', $crumb);
    }

    if (preg_match('#^/adlab/c/([a-f0-9]{8})$#', $path, $m)) {
        $i = adFind($all, $m[1]); if ($i === null) { http_response_code(404); page('Not found', '<p>That campaign isn’t here.</p>', $crumb); }
        $c = $all[$i]; $site = $sitesById[$c['site']] ?? adSites()[0]; $o = adOptics($c, $site); [$by, $days] = adClickSum($clicks, $c['id']);
        $col = $o['score'] >= 80 ? '#4ee0a0' : ($o['score'] >= 60 ? '#d8b45a' : '#e38a7a');
        $act = fn ($a, $l, $cls = '') => '<form method="post" action="/lab/adlab/c/' . $c['id'] . '/' . $a . '">' . csrf() . '<button class="' . $cls . '">' . $l . '</button></form>';
        $acts = '<div class="row acts">' . ($c['status'] !== 'running' && $c['status'] !== 'dead' ? $act('run', 'Run it') : '') . ($c['status'] === 'running' ? $act('pause', 'Pause', 'ghost') : '')
            . ($c['status'] !== 'dead' ? '<a class="btn ghost" href="/lab/adlab/c/' . $c['id'] . '/edit">Edit</a>' : $act('revive', 'Bring it back')) . $act('clone', 'Copy as new', 'ghost')
            . ($c['status'] !== 'dead' ? '<form method="post" action="/lab/adlab/c/' . $c['id'] . '/kill" data-confirm="Kill this campaign and send it to the Dead Archive?">' . csrf() . '<input type="hidden" name="why" value=""><button class="ghost danger-b">Kill</button></form>' : '') . '</div>';
        $checks = ''; foreach ($o['checks'] as [$l, $w, $ok]) $checks .= '<li class="' . ($ok ? 'ok' : 'no') . '"><span>' . ($ok ? '✓' : '✗') . '</span>' . e($l) . ' <small class="home">' . $w . '</small></li>';
        // clicks, the last 14 days
        $bars = ''; $max = max(1, ...array_values($days ?: [0])); $x = 0;
        for ($d = 13; $d >= 0; $d--) { $k = date('Y-m-d', strtotime("-$d days")); $n = $days[$k] ?? 0; $h = $n / $max * 70; $bars .= '<rect x="' . ($x + 2) . '" y="' . round(80 - $h, 1) . '" width="18" height="' . round(max($h, 1), 1) . '" rx="2" fill="' . ($n ? '#7fd3ff' : '#2a2a30') . '"><title>' . date('M j', strtotime($k)) . ': ' . $n . '</title></rect>'; $x += 22; }
        $platRows = '';
        foreach ($c['platforms'] as $p) {
            $mt = $c['metrics'][$p] ?? []; $cp = adCopy($c, $site, $p);
            $platRows .= '<tr><th>' . e(PLATFORMS[$p][0]) . (isset($c['posts'][$p]) ? ' <small class="home">posted ' . e(date('M j', strtotime($c['posts'][$p]['at']))) . '</small>' : '') . '</th><td class="n">' . ($by[$p] ?? 0) . '</td>'
                . '<td class="n">' . (int) ($mt['views'] ?? 0) . '</td><td class="n">' . (int) ($mt['likes'] ?? 0) . '</td><td class="n">' . (int) ($mt['comments'] ?? 0) . '</td><td class="n">' . (int) ($mt['shares'] ?? 0) . '</td>'
                . '<td><details><summary>' . ($p === 'facebook' && load('facebook', []) ? 'update' : 'enter') . '</summary><form method="post" action="/lab/adlab/c/' . $c['id'] . '/metrics" class="mini">' . csrf() . '<input type="hidden" name="p" value="' . $p . '">'
                . implode('', array_map(fn ($k) => '<label>' . $k . '<input type="number" min="0" name="' . $k . '" value="' . (int) ($mt[$k] ?? 0) . '"></label>', ['views', 'likes', 'comments', 'shares'])) . '<button>Save</button></form></details></td></tr>';
        }
        $copies = '';
        foreach ($c['platforms'] as $p) {
            $cp = adCopy($c, $site, $p); $len = mb_strlen($cp['text']);
            $copies .= '<div class="copy"><h3>' . e(PLATFORMS[$p][0]) . ' <small class="home">' . $len . ' / ' . $cp['limit'] . '</small></h3>' . ($cp['title'] ? '<p class="meta">Title: ' . e($cp['title']) . '</p>' : '')
                . '<pre>' . e($cp['text']) . '</pre><div class="row"><button type="button" class="ghost" data-copy="' . e($cp['text']) . '">Copy text</button><button type="button" class="ghost" data-copy="' . e($cp['link']) . '">Copy tracked link</button></div></div>';
        }
        $fb = load('facebook', []);
        $fbBox = in_array('facebook', $c['platforms'], true) ? '<div class="row">' . ($fb ? $act('fb-post', isset($c['posts']['facebook']) ? 'Post to Facebook again' : 'Post to ' . e($fb['page_name'])) . (isset($c['posts']['facebook']) ? $act('fb-stats', 'Bring in Facebook numbers', 'ghost') : '') : '<a class="btn ghost" href="/lab/adlab/facebook">Connect Facebook to post from here</a>') . '</div>' : '';
        $kit = adKit($c, $site, '/lab/adlab/art/' . ($c['art'] ?? ''), '/lab/adlab/logo/' . $site['id'], false);
        page($c['headline'], '<article class="proj" style="--c:' . e($site['accent']) . '"><header>' . ring($o['score'], $col) . '<div><h1>' . e($c['headline']) . '</h1><p>' . $badge($c['status']) . ' <span class="home">' . e($site['name']) . '</span> <span class="home">' . e(KINDS[$c['kind']] ?? '') . '</span>' . ($c['event'] ? ' <span class="home">' . e($c['event']) . '</span>' : '') . ($c['starts'] || $c['ends'] ? ' <span class="home">' . e($c['starts'] ? date('M j', strtotime($c['starts'])) : '…') . ' – ' . e($c['ends'] ? date('M j, Y', strtotime($c['ends'])) : 'open') . '</span>' : '') . '</p></div></header>'
            . $acts . ($c['status'] === 'dead' ? '<aside class="law"><b>In the Dead Archive</b><p>Killed ' . e(date('M j, Y', strtotime($c['killed'] ?? 'now'))) . '.' . (!empty($c['kill_reason']) ? ' ' . e($c['kill_reason']) : '') . '</p></aside>' : '')
            . (!empty($site['client']) ? adApprovalBox($c, $site) : '')
            . '<h2 class="rule">Optics · ' . $o['score'] . ' of 100</h2><ul class="optics">' . $checks . '</ul>'
            . '<h2 class="rule">How it’s doing</h2><div class="stats"><svg viewBox="0 0 310 84" class="spark" role="img" aria-label="Tracked clicks, last 14 days">' . $bars . '</svg><div><b>' . array_sum($by) . '</b> tracked clicks<br><small class="home">last 14 days shown</small></div></div>'
            . '<table class="ptable"><thead><tr><th>Platform</th><th>Clicks</th><th>Views</th><th>Likes</th><th>Comments</th><th>Shares</th><th></th></tr></thead><tbody>' . $platRows . '</tbody></table>' . $fbBox
            . '<h2 class="rule">Artwork</h2>'
            . (load('artist', []) ? '<form method="post" action="/lab/adlab/c/' . $c['id'] . '/generate" class="stack wide gen" data-busy="Making the picture… this takes about half a minute.">' . csrf()
                . '<label>Make the artwork from a description <small>keywords are fine; the site’s colours and look are added for you, and the picture is kept free of words so the headline sits clean</small><textarea name="desc" rows="2" placeholder="e.g. grandmother and granddaughter looking at an old family album by a Christmas tree, warm candlelight">' . e($c['art_prompt'] ?? '') . '</textarea></label>'
                . '<div class="row"><label>Shape<select name="shape"><option value="square">Square (crops to every size)</option><option value="tall">Tall (stories, pins)</option><option value="wide">Wide (X, links, LinkedIn)</option></select></label><button>' . (!empty($c['art']) ? 'Make a new picture' : 'Make the picture') . '</button></div></form>'
                : '<p class="meta"><a href="/lab/adlab/artist">Connect the Artist</a> to make artwork from a description.</p>')
            . (!empty($c['art_model']) ? '<p class="meta">Made with ' . e($c['art_model']) . '. Earlier pictures are kept in lab-data/art-history.</p>' : '')
            . '<p class="meta">' . (!empty($c['art']) ? 'Each size is cropped from the artwork, never stretched. Download what you need, or keep it on the server for posting.' : 'Add artwork (Edit) to make the sized images.') . '</p><div id="composer" data-kit="' . e(json_encode($kit)) . '"></div>'
            . '<h2 class="rule">The words, per platform</h2><div class="copies">' . $copies . '</div></article>', $crumb);
    }

    if (preg_match('#^/adlab/logo/([a-z0-9]+)$#', $path, $m)) { adLogo(adSite($m[1])); exit; }
    if (preg_match('#^/adlab/art/([a-f0-9]{8}\.(?:jpg|png|webp))$#', $path, $m)) {
        $f = artDir('art') . '/' . $m[1]; if (!is_file($f)) { http_response_code(404); exit; }
        header('Content-Type: ' . (mime_content_type($f) ?: 'image/jpeg')); header('Cache-Control: private, max-age=3600'); readfile($f); exit;
    }
}
}

function fbCall(string $method, string $path, array $params): array
{
    $url = FB_GRAPH . ltrim($path, '/');
    if ($method === 'GET') { $url .= '?' . http_build_query($params); $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 25, 'ignore_errors' => true]]); }
    else $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 40, 'ignore_errors' => true, 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query($params)]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? ['error' => ['message' => 'Facebook could not be reached']] : (json_decode($body, true) ?: ['error' => ['message' => 'Unexpected answer']]);
}

/** The words sent to the artist: the member's description, dressed in the site's look, with room for the headline. */
function artistPrompt(string $desc, ?array $site, array $c): string
{
    $look = $site ? "Colour palette led by {$site['bg']} and {$site['accent']}, in keeping with {$site['name']} (\"{$site['voice']}\")." : '';
    return trim($desc) . '. ' . $look . ' A polished advertising photograph or illustration, rich and professional. The main subject sits in the centre and upper half with calm space around it, '
        . 'so the picture can be cropped to wide, square and tall formats, and the lower third stays quiet for a headline. No words, letters, numbers, logos or watermarks anywhere in the picture.';
}

/** Make one picture: the main model first, the backup if the main one fails. Returns [bytes, model name]. */
function artistMake(string $prompt, string $shape): array
{
    $ar = load('artist', []);
    if (empty($ar['endpoint']) || empty($ar['key'])) throw new RuntimeException('the Artist isn’t connected');
    @set_time_limit(170);
    $errors = [];
    foreach (array_filter([$ar['main'] ?? '', $ar['backup'] ?? '']) as $model) {
        $mai = stripos($model, 'mai') !== false;
        [$w, $h] = ['square' => [1024, 1024], 'tall' => [832, 1248], 'wide' => [1248, 832]][$shape];
        $body = $mai ? ['model' => $model, 'prompt' => $prompt, 'width' => $w, 'height' => $h]
                     : ['model' => $model, 'prompt' => $prompt, 'size' => ['square' => '1024x1024', 'tall' => '1024x1536', 'wide' => '1536x1024'][$shape], 'quality' => 'medium', 'output_format' => 'png', 'n' => 1];
        $ch = curl_init($ar['endpoint'] . ($mai ? '/mai/v1/images/generations' : '/openai/v1/images/generations'));
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'api-key: ' . $ar['key']], CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 75, CURLOPT_CONNECTTIMEOUT => 10]);
        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        $j = is_string($raw) ? json_decode($raw, true) : null;
        $b64 = $j['data'][0]['b64_json'] ?? null;
        if ($code < 300 && $b64) return [base64_decode($b64), $model];
        $errors[] = $model . ': ' . ($raw === false ? $err : (string) ($j['error']['message'] ?? $j['message'] ?? ('HTTP ' . $code)));
    }
    throw new RuntimeException(implode(' · ', $errors));
}

/** The site's logo, fetched once and kept here, so the composer can draw it (same address as the page). */
function adLogo(?array $site): void
{
    $src = (string) ($site['logo'] ?? '');
    if ($src === '') { http_response_code(404); return; }
    if (str_starts_with($src, '/')) { header('Location: ' . $src); return; }
    $f = artDir('logos') . '/' . $site['id'] . '-' . substr(hash('sha256', $src), 0, 10);
    if (!is_file($f)) { $b = @file_get_contents($src, false, stream_context_create(['http' => ['timeout' => 15]])); if ($b === false || strlen($b) > 5_000_000) { http_response_code(404); return; } file_put_contents($f, $b); }
    $mime = mime_content_type($f) ?: 'image/png'; if (str_contains($mime, 'xml') || str_contains($mime, 'text')) $mime = 'image/svg+xml';
    header('Content-Type: ' . $mime); header('Cache-Control: private, max-age=86400'); readfile($f);
}

function adKit(array $c, array $site, string $artUrl, string $logoUrl, bool $review): array
{
    $sizes = []; foreach ($c['platforms'] as $p) foreach (PLATFORMS[$p][2] as $lab => [$w, $h]) $sizes[] = ['p' => $p, 'name' => PLATFORMS[$p][0] . ' · ' . $lab, 'w' => $w, 'h' => $h];
    return ['art' => !empty($c['art']) ? $artUrl : null, 'logo' => $site['logo'] !== '' ? $logoUrl : null, 'bg' => $site['bg'], 'accent' => $site['accent'], 'text' => $site['text'],
        'headline' => $c['headline'], 'offer' => $c['offer'], 'coupon' => $c['coupon'], 'cta' => $c['cta'], 'site' => $site['name'], 'url' => (string) parse_url($site['url'], PHP_URL_HOST), 'voice' => $site['voice'],
        'sizes' => $sizes, 'save' => $review ? null : '/lab/adlab/c/' . $c['id'] . '/render', 'csrf' => $review ? '' : $_SESSION['csrf'], 'review' => $review];
}

/** On the campaign page: where the client's approval stands, and the link to send them. */
function adApprovalBox(array $c, array $site): string
{
    $a = $c['approval'] ?? null; $cur = $a && ($a['version'] ?? '') === adVersion($c);
    $link = $a ? 'https://alchemystudiosorlando.com/lab/review/' . $a['token'] : '';
    $state = !$a ? ['Not sent yet', '#7b8a92'] : (!$cur ? ['Changed since it was sent: send it again', '#d8b45a'] : (['pending' => ['Waiting for ' . ($site['contact'] ?: 'the client'), '#d8b45a'], 'approved' => ['Approved by ' . ($a['by'] ?? '') . ' · ' . date('M j, g:i a', strtotime($a['at'] ?? 'now')), '#4ee0a0'], 'changes' => ['Changes requested by ' . ($a['by'] ?? ''), '#e38a7a']][$a['state']] ?? ['', '#999']));
    $mailto = $a && $cur && !empty($site['email']) ? 'mailto:' . rawurlencode($site['email']) . '?subject=' . rawurlencode('For your approval: ' . $c['headline']) . '&body=' . rawurlencode('Hi ' . ($site['contact'] ?: '') . ",\n\nHere is the campaign for your approval. You can see every size and the words for each platform, then approve it or ask for changes:\n\n$link\n\nThank you,\nAlchemy Studios") : '';
    $msg = 'Hi ' . ($site['contact'] ?: '') . ', here is the campaign "' . $c['headline'] . '" for your approval. You can see every size and the words for each platform, then approve it or ask for changes: ' . $link;
    $wa = $a && $cur ? 'https://wa.me/' . ($site['phone'] ?? '') . '?text=' . rawurlencode($msg) : '';
    return '<aside class="law appr" style="border-color:' . $state[1] . '"><b>Client approval</b><p><span class="badge" style="--c:' . $state[1] . '">' . e($state[0]) . '</span></p>'
        . ($a && ($a['state'] ?? '') === 'changes' && $cur ? '<p>“' . nl2br(e($a['note'] ?? '')) . '”</p>' : '')
        . ($a && $cur ? '<p class="row"><input readonly value="' . e($link) . '" onclick="this.select()"><button type="button" class="ghost" data-copy="' . e($link) . '">Copy link</button>' . ($mailto ? '<a class="btn ghost" href="' . e($mailto) . '">Email it to ' . e($site['contact'] ?: 'the client') . '</a>' : '') . ($wa ? '<a class="btn ghost" href="' . e($wa) . '" target="_blank" rel="noopener">Send on WhatsApp' . (empty($site['phone']) ? ' (choose the chat)' : ' to ' . e($site['contact'] ?: 'the client')) . '</a>' : '') . '</p>' : '')
        . (!$a || !$cur || ($a['state'] ?? '') === 'changes' ? '<form method="post" action="/lab/adlab/c/' . $c['id'] . '/approval">' . csrf() . '<button>' . ($a ? 'Send this version for approval' : 'Make the approval link') . '</button></form>' : '')
        . '<p class="meta">The campaign can run, or post, once the client approves the version they saw. Any later change asks for a new approval.</p></aside>';
}

/** The page the client opens: their campaign, every size, the words per platform, approve or ask for changes. */
function adReviewPage(array $c, ?array $site, string $token, bool $current): never
{
    $a = $c['approval']; $err = $_SESSION['review_err'] ?? null; unset($_SESSION['review_err']);
    $kit = adKit($c, $site, '/lab/review/' . $token . '/art', '/lab/review/' . $token . '/logo', true);
    $copies = ''; foreach ($c['platforms'] as $p) { $cp = adCopy($c, $site, $p); $copies .= '<div class="copy"><h3>' . e(PLATFORMS[$p][0]) . '</h3>' . ($cp['title'] ? '<p class="meta">Title: ' . e($cp['title']) . '</p>' : '') . '<pre>' . e(preg_replace('#https://alchemystudiosorlando\.com/lab/go/\S+#', $c['link'] ?: $site['url'], $cp['text'])) . '</pre></div>'; }
    $done = $current && in_array($a['state'], ['approved', 'changes'], true);
    $decided = $done ? '<aside class="law" style="border-color:' . ($a['state'] === 'approved' ? '#4ee0a0' : '#e38a7a') . '"><b>' . ($a['state'] === 'approved' ? 'Approved' : 'Changes requested') . '</b><p>' . e($a['by']) . ' · ' . e(date('F j, Y \a\t g:i a', strtotime($a['at']))) . ($a['note'] ? '<br>“' . nl2br(e($a['note'])) . '”' : '') . '</p><p class="meta">Thank you. ' . ($a['state'] === 'approved' ? 'We’ll run it as shown' . ($c['starts'] ? ' from ' . e(date('F j', strtotime($c['starts']))) : '') . '.' : 'We’ll make the changes and send you the new version.') . '</p></aside>' : '';
    $form = !$current ? '<aside class="law"><b>Updated</b><p>This campaign was changed after the link was sent. We’ll send you the new version shortly.</p></aside>'
        : ($done ? '' : '<form method="post" class="stack wide decide">' . csrf() . ($err ? '<p class="flash">' . e($err) . '</p>' : '')
        . '<fieldset class="plats"><legend>Your decision</legend><label class="tick-l"><input type="radio" name="decision" value="approved" required> Approve: run it as shown</label><label class="tick-l"><input type="radio" name="decision" value="changes"> Request changes</label></fieldset>'
        . '<label>Notes <small>needed if you’re asking for changes</small><textarea name="note" rows="3"></textarea></label><label>Your name <small>typing it signs your decision</small><input name="name" required autocomplete="name" value="' . e($site['contact'] ?? '') . '"></label><div class="row"><button>Send my decision</button></div></form>');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>For your approval · ' . e($c['headline']) . '</title>'
        . '<link rel="icon" href="/assets/brand/favicon-2027.png"><link rel="stylesheet" href="/lab/lab.css?v=3"></head><body>'
        . '<header class="top"><span class="mark"><img src="/assets/brand/alchemy-emblem-2027-sm.webp" alt="" width="44" height="44"><span><b>Alchemy Studios</b><small>Campaign review for ' . e($site['name'] ?? '') . '</small></span></span></header><main>'
        . '<section class="hero"><h1>' . e($c['headline']) . '</h1><p>' . e(KINDS[$c['kind']] ?? '') . ($c['event'] ? ' · ' . e($c['event']) : '') . ($c['starts'] || $c['ends'] ? ' · runs ' . e($c['starts'] ? date('M j', strtotime($c['starts'])) : 'now') . ' – ' . e($c['ends'] ? date('M j, Y', strtotime($c['ends'])) : 'open') : '') . '</p></section>'
        . $decided . '<h2 class="rule">The artwork, every size</h2><div id="composer" data-kit="' . e(json_encode($kit)) . '"></div>'
        . '<h2 class="rule">The words, per platform</h2><div class="copies">' . $copies . '</div>'
        . ($c['offer'] || $c['coupon'] ? '<p class="meta">Offer: ' . e($c['offer']) . ($c['coupon'] ? ' · code ' . e($c['coupon']) : '') . '</p>' : '')
        . ($form ? '<h2 class="rule">Approve or request changes</h2>' . $form : '')
        . '</main><footer>Alchemy Studios · Imagination → Reality</footer><script src="/lab/lab.js?v=5"></script></body></html>';
    exit;
}
