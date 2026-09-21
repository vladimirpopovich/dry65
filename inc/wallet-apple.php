<?php
/* ============================================================
   Dry65 — APPLE WALLET (.pkpass) za pečat-kartice
   ------------------------------------------------------------
   Isti model kao Google (inc/wallet.php): jedna TRAJNA kartica po
   kupcu (serial = c{customer_id}) koja se dopunjava; pečati se CRTAJU
   na strip slici (GD); QR = /kartica/{kod}. Uživo apdejt preko APNs.

   Guard dry65_wallet_apple_ready(): dok sertifikati nisu na serveru,
   dugme/ruta su neaktivni, sajt radi normalno.

   KREDENCIJALI (wp-config.php, van public_html):
     define('DRY65_AW_CERT', '/putanja/pass.pem');   // Pass Type sertifikat (PEM)
     define('DRY65_AW_KEY',  '/putanja/pass.key');   // privatni ključ (PEM, bez lozinke)
     define('DRY65_AW_WWDR', '/putanja/AppleWWDRCAG4.pem'); // Apple WWDR G4
     // opciono (imaju default):
     define('DRY65_AW_TEAM_ID', '688DMJK683');
     define('DRY65_AW_PASS_TYPE_ID', 'pass.com.dry65.club');
   ============================================================ */

if (!defined('ABSPATH')) exit;

if (!defined('DRY65_AW_TEAM_ID'))      define('DRY65_AW_TEAM_ID', '688DMJK683');
if (!defined('DRY65_AW_PASS_TYPE_ID')) define('DRY65_AW_PASS_TYPE_ID', 'pass.com.dry65.club');

/* Spremno za rad? (sertifikati čitljivi + potrebne ekstenzije) */
function dry65_wallet_apple_ready() {
    foreach (['DRY65_AW_CERT', 'DRY65_AW_KEY', 'DRY65_AW_WWDR'] as $c) {
        if (!defined($c) || !constant($c) || !is_readable(constant($c))) return false;
    }
    if (!function_exists('openssl_pkcs7_sign')) return false;
    if (!class_exists('ZipArchive')) return false;
    if (!function_exists('imagecreatetruecolor')) return false;
    return true;
}

/* Trajni serial: po kupcu (c{id}), fallback na kod. */
function dry65_wallet_apple_serial($acc) {
    $cid = (int) ($acc->customer_id ?? 0);
    return $cid > 0 ? ('c' . $cid) : (string) $acc->code;
}

/* Auth token (stabilan po serialu) — za web servis registracije/apdejt. */
function dry65_wallet_apple_authtoken_for_serial($serial) {
    return substr(hash('sha256', 'dry65aw|' . $serial . '|' . wp_salt('auth')), 0, 32);
}
function dry65_wallet_apple_authtoken($acc) {
    return dry65_wallet_apple_authtoken_for_serial(dry65_wallet_apple_serial($acc));
}

/* #RRGGBB -> "rgb(r,g,b)" (Apple format). */
function dry65_wallet_apple_rgb($hex) {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) !== 6) $hex = '783332';
    return 'rgb(' . hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2)) . ')';
}

/* ---- pass.json ---- */
function dry65_wallet_apple_passjson($acc) {
    $serial   = dry65_wallet_apple_serial($acc);
    $is_paket = ($acc->type === 'paket');
    $initial  = (int) $acc->initial;
    $used     = max(0, $initial - (int) $acc->balance);
    $reward   = function_exists('dry65_pk_effective_reward') ? dry65_pk_effective_reward($acc) : (string) $acc->reward;
    $bg       = function_exists('dry65_wallet_bg') ? dry65_wallet_bg($acc) : '#783332';
    $card_url = dry65_pk_card_url($acc->code);

    $fields_secondary = [
        ['key' => 'member', 'label' => 'ČLAN', 'value' => (string) $acc->name],
    ];
    if ($is_paket) {
        $fields_secondary[] = [
            'key'           => 'tier',
            'label'         => 'PAKET',
            'value'         => ($initial >= 8) ? 'Signature' : 'Essential',
            'textAlignment' => 'PKTextAlignmentRight',
        ];
    }
    // TRETMAN i VAŽI DO idu na ZADNJU stranu (lice ostaje čisto: logo + pečati + QR).
    $back = [];
    if ($is_paket && $reward) {
        $back[] = ['key' => 'reward', 'label' => 'Tretman', 'value' => empty($acc->reward_used_at) ? $reward : 'Iskorišćen'];
    }
    if (!empty($acc->expires_at)) {
        $back[] = ['key' => 'exp', 'label' => 'Važi do', 'value' => date_i18n('d.m.Y.', strtotime($acc->expires_at))];
    }
    $back[] = ['key' => 'about', 'label' => 'Dry65', 'value' => 'West 65, Novi Beograd. Pokažite karticu osoblju u salonu.'];
    $back[] = ['key' => 'link',  'label' => 'Kartica', 'value' => $card_url];
    $header = $is_paket
        ? [['key' => 'stamps', 'label' => 'PEČATI', 'value' => $used . ' / ' . $initial]]
        : [['key' => 'bal', 'label' => 'STANJE', 'value' => number_format((int) $acc->balance, 0, ',', '.') . ' din']];

    $pass = [
        'formatVersion'       => 1,
        'passTypeIdentifier'  => DRY65_AW_PASS_TYPE_ID,
        'teamIdentifier'      => DRY65_AW_TEAM_ID,
        'organizationName'    => 'Dry65',
        'description'         => 'Dry65 Club kartica',
        'serialNumber'        => $serial,
        'backgroundColor'     => dry65_wallet_apple_rgb($bg),
        'foregroundColor'     => 'rgb(239,225,210)',
        'labelColor'          => 'rgb(216,179,172)',
        'webServiceURL'       => home_url('/wp-json/dry65aw'),
        'authenticationToken' => dry65_wallet_apple_authtoken_for_serial($serial),
        'barcodes'            => [[
            'format'          => 'PKBarcodeFormatQR',
            'message'         => $card_url,
            'messageEncoding' => 'iso-8859-1',
            'altText'         => (string) $acc->code,
        ]],
        'storeCard' => [
            'headerFields'    => $header,
            'secondaryFields' => $fields_secondary,
            'backFields'      => $back,
        ],
    ];
    return $pass;
}

/* ---- Slike ---- */

/* Statičan asset iz teme (icon/logo) -> PNG bytes. */
function dry65_wallet_apple_asset($name) {
    $p = get_template_directory() . '/assets/wallet/apple/' . $name;
    return is_readable($p) ? file_get_contents($p) : '';
}

/*
 * Strip: bazna slika (prazan strip po tieru: essential=4 / signature=8) + utisnut
 * "dry" žig u zarađene krugove. Dizajn je iz Figme (assets/wallet/apple/), ovde se
 * samo kompozituju pečati. $scale: 1 (@1x 375x123), 2 (@2x), 3 (@3x).
 */
function dry65_wallet_apple_strip_png($acc, $scale = 2) {
    $dir = get_template_directory() . '/assets/wallet/apple/';
    $sfx = $scale >= 3 ? '@3x' : ($scale == 2 ? '@2x' : '');
    $initial = max(1, (int) $acc->initial);
    $used    = max(0, $initial - (int) $acc->balance);
    $tier    = $initial >= 8 ? 'signature' : 'essential';
    $n       = $tier === 'signature' ? 8 : 4;

    $base = @imagecreatefrompng($dir . 'strip-' . $tier . $sfx . '.png');
    if (!$base) return '';
    $W = imagesx($base); $H = imagesy($base);

    // centri pečat-krugova kao odnosi (izmereno iz baznih slika)
    $xs = [0.13627, 0.30160, 0.46693, 0.63227];
    $ys = $tier === 'signature' ? [0.26626, 0.74878] : [0.49675];
    $centers = [];
    foreach ($ys as $ry) foreach ($xs as $rx) $centers[] = [$rx, $ry];

    // reward-krug (MASKA/INFUZIJA) — nagrada se NE zarađuje; žig ide preko kad je iskorišćena
    $reward_center = $tier === 'signature' ? [0.86160, 0.50730] : [0.86093, 0.49512];
    $reward_used   = !empty($acc->reward_used_at);

    $mark = @imagecreatefrompng($dir . 'stamp-dry' . $sfx . '.png');
    if ($mark) {
        $mw = imagesx($mark); $mh = imagesy($mark);
        imagealphablending($base, true);
        $put = function ($rx, $ry) use ($base, $mark, $mw, $mh, $W, $H) {
            $cx = (int) round($rx * $W); $cy = (int) round($ry * $H);
            imagecopy($base, $mark, $cx - (int) round($mw / 2), $cy - (int) round($mh / 2), 0, 0, $mw, $mh);
        };
        $fill = min($used, $n);
        for ($i = 0; $i < $fill; $i++) $put($centers[$i][0], $centers[$i][1]);
        if ($reward_used) $put($reward_center[0], $reward_center[1]);
        imagedestroy($mark);
    }

    imagesavealpha($base, true);
    ob_start(); imagepng($base); $bytes = ob_get_clean();
    imagedestroy($base);
    return $bytes;
}

/* ---- Sastavi .pkpass (vrati putanju do fajla ili '' ) ---- */
function dry65_wallet_apple_build($acc) {
    if (!dry65_wallet_apple_ready() || !is_object($acc)) return '';

    $files = [];
    $files['pass.json']    = wp_json_encode(dry65_wallet_apple_passjson($acc));
    $files['icon.png']     = dry65_wallet_apple_asset('icon.png');
    $files['icon@2x.png']  = dry65_wallet_apple_asset('icon@2x.png');
    $files['icon@3x.png']  = dry65_wallet_apple_asset('icon@3x.png');
    $files['logo.png']     = dry65_wallet_apple_asset('logo.png');
    $files['logo@2x.png']  = dry65_wallet_apple_asset('logo@2x.png');
    $files['logo@3x.png']  = dry65_wallet_apple_asset('logo@3x.png');
    $files['strip.png']    = dry65_wallet_apple_strip_png($acc, 1);
    $files['strip@2x.png'] = dry65_wallet_apple_strip_png($acc, 2);
    $files['strip@3x.png'] = dry65_wallet_apple_strip_png($acc, 3);
    foreach ($files as $k => $v) { if ($v === '' && $k !== 'pass.json') unset($files[$k]); }

    // manifest.json = sha1 svakog fajla
    $manifest = [];
    foreach ($files as $name => $data) $manifest[$name] = sha1($data);
    $manifest_json = wp_json_encode($manifest);

    // Radni dir
    $dir = trailingslashit(get_temp_dir()) . 'dry65aw_' . wp_generate_password(10, false, false);
    if (!wp_mkdir_p($dir)) return '';
    $manifest_path = $dir . '/manifest.json';
    file_put_contents($manifest_path, $manifest_json);

    // Potpis (PKCS#7 detached, DER) — proveren metod
    $sig_smime = $dir . '/signature.smime';
    $ok = openssl_pkcs7_sign(
        $manifest_path, $sig_smime,
        'file://' . DRY65_AW_CERT,
        ['file://' . DRY65_AW_KEY, ''],
        [], PKCS7_BINARY | PKCS7_DETACHED, DRY65_AW_WWDR
    );
    if (!$ok) { dry65_wallet_apple_rmdir($dir); return ''; }
    $smime = file_get_contents($sig_smime);
    $der = '';
    if (preg_match("/\r?\n\r?\n([A-Za-z0-9+\/=\r\n]+)\r?\n\r?\n------/s", $smime, $m)) {
        $der = base64_decode(preg_replace('/\s+/', '', $m[1]));
    }
    if ($der === '') { dry65_wallet_apple_rmdir($dir); return ''; }

    // ZIP -> .pkpass
    $pkpass = $dir . '/dry65.pkpass';
    $zip = new ZipArchive();
    if ($zip->open($pkpass, ZipArchive::CREATE) !== true) { dry65_wallet_apple_rmdir($dir); return ''; }
    foreach ($files as $name => $data) $zip->addFromString($name, $data);
    $zip->addFromString('manifest.json', $manifest_json);
    $zip->addFromString('signature', $der);
    $zip->close();

    return $pkpass;
}

function dry65_wallet_apple_rmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*') as $f) { is_file($f) && @unlink($f); }
    @rmdir($dir);
}

/* Servira .pkpass sa pravim headerima, pa počisti. */
function dry65_wallet_apple_serve($acc) {
    $path = dry65_wallet_apple_build($acc);
    if (!$path || !is_file($path)) { status_header(500); exit; }
    nocache_headers();
    header('Content-Type: application/vnd.apple.pkpass');
    header('Content-Disposition: attachment; filename="dry65-' . $acc->code . '.pkpass"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    dry65_wallet_apple_rmdir(dirname($path));
    exit;
}

/* Dugme „Add to Apple Wallet" (vraća '' ako nije konfigurisano). */
function dry65_wallet_apple_button($acc) {
    if (!dry65_wallet_apple_ready()) return '';
    $url = home_url('/wallet/apple/' . $acc->code . '/');
    return '<a href="' . esc_url($url) . '" style="display:inline-flex;align-items:center;gap:8px;background:#000;color:#fff;'
         . 'text-decoration:none;border-radius:999px;padding:11px 20px;font-size:14px;font-weight:600;line-height:1;">'
         . '<svg width="16" height="16" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M17 2H7a3 3 0 0 0-3 3v14a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V5a3 3 0 0 0-3-3zM9 6h6v2H9z"/></svg>'
         . 'Dodaj u Apple Wallet</a>';
}
/* Za email/druga mesta: puni URL .pkpass-a (ili '' ). */
function dry65_wallet_apple_pkpass_url($acc) {
    if (!dry65_wallet_apple_ready() || !is_object($acc)) return '';
    return home_url('/wallet/apple/' . $acc->code . '/');
}

/* ---- Ruta za serviranje: /wallet/apple/{code} ---- */
add_filter('query_vars', function ($v) { $v[] = 'dry65_aw'; return $v; });
add_action('init', function () {
    add_rewrite_rule('^wallet/apple/([^/]+)/?$', 'index.php?dry65_aw=$matches[1]', 'top');
}, 9); // prioritet 9: registruj pre paketovog flush-a (init@10) da uđe u rewrite pravila
add_action('template_redirect', function () {
    $code = get_query_var('dry65_aw');
    if (!$code) return;
    if (!dry65_wallet_apple_ready()) { status_header(404); exit; }
    $acc = dry65_pk_get_by_code($code);
    if (!$acc) { status_header(404); exit; }
    dry65_wallet_apple_serve($acc);
});

/* ============================================================
   WEB SERVIS (Apple pull apdejt) — REST /wp-json/dry65aw/v1/...
   ============================================================ */
add_action('rest_api_init', function () {
    $ns = 'dry65aw/v1';
    // Registracija uređaja
    register_rest_route($ns, '/devices/(?P<device>[^/]+)/registrations/(?P<ptid>[^/]+)/(?P<serial>[^/]+)', [
        'methods'  => 'POST',
        'permission_callback' => '__return_true',
        'callback' => 'dry65_aw_rest_register',
    ]);
    register_rest_route($ns, '/devices/(?P<device>[^/]+)/registrations/(?P<ptid>[^/]+)/(?P<serial>[^/]+)', [
        'methods'  => 'DELETE',
        'permission_callback' => '__return_true',
        'callback' => 'dry65_aw_rest_unregister',
    ]);
    // Lista izmenjenih serijskih za uređaj
    register_rest_route($ns, '/devices/(?P<device>[^/]+)/registrations/(?P<ptid>[^/]+)', [
        'methods'  => 'GET',
        'permission_callback' => '__return_true',
        'callback' => 'dry65_aw_rest_serials',
    ]);
    // Najnoviji pass
    register_rest_route($ns, '/passes/(?P<ptid>[^/]+)/(?P<serial>[^/]+)', [
        'methods'  => 'GET',
        'permission_callback' => '__return_true',
        'callback' => 'dry65_aw_rest_latest',
    ]);
    // Log
    register_rest_route($ns, '/log', [
        'methods'  => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function () { return new WP_REST_Response(null, 200); },
    ]);
});

/* Proveri ApplePass auth token za serial. */
function dry65_aw_check_auth($request, $serial) {
    $hdr = $request->get_header('authorization');
    if (!$hdr || stripos($hdr, 'ApplePass ') !== 0) return false;
    $token = trim(substr($hdr, 10));
    return hash_equals(dry65_wallet_apple_authtoken_for_serial($serial), $token);
}

/* Nalog iz serijala (c{customer_id} -> aktivni nalog kupca; inače po kodu). */
function dry65_aw_acc_from_serial($serial) {
    if (preg_match('/^c(\d+)$/', $serial, $m)) {
        $active = function_exists('dry65_pk_customer_active_account') ? dry65_pk_customer_active_account((int) $m[1]) : null;
        if ($active) return $active;
        // fallback: najnoviji nalog kupca
        $accs = function_exists('dry65_pk_customer_accounts') ? dry65_pk_customer_accounts((int) $m[1]) : [];
        return $accs ? $accs[0] : null;
    }
    return dry65_pk_get_by_code($serial);
}

function dry65_aw_rest_register($request) {
    $serial = $request['serial'];
    if (!dry65_aw_check_auth($request, $serial)) return new WP_REST_Response(null, 401);
    $body  = json_decode($request->get_body(), true);
    $token = is_array($body) && !empty($body['pushToken']) ? sanitize_text_field($body['pushToken']) : '';
    if ($token === '') return new WP_REST_Response(null, 400);
    global $wpdb;
    $t = dry65_wallet_dev_table();
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $t WHERE device_lib_id=%s AND pass_type_id=%s AND serial_number=%s",
        $request['device'], $request['ptid'], $serial
    ));
    if ($exists) {
        $wpdb->update($t, ['push_token' => $token], ['id' => (int) $exists], ['%s'], ['%d']);
        return new WP_REST_Response(null, 200);
    }
    $wpdb->insert($t, [
        'device_lib_id' => $request['device'], 'pass_type_id' => $request['ptid'],
        'serial_number' => $serial, 'push_token' => $token, 'created_at' => current_time('mysql'),
    ], ['%s','%s','%s','%s','%s']);
    return new WP_REST_Response(null, 201);
}

function dry65_aw_rest_unregister($request) {
    $serial = $request['serial'];
    if (!dry65_aw_check_auth($request, $serial)) return new WP_REST_Response(null, 401);
    global $wpdb;
    $wpdb->delete(dry65_wallet_dev_table(), [
        'device_lib_id' => $request['device'], 'pass_type_id' => $request['ptid'], 'serial_number' => $serial,
    ], ['%s','%s','%s']);
    return new WP_REST_Response(null, 200);
}

/* Vrati serijske koje uređaj drži (uvek sve njegove; Apple onda povuče svaki). */
function dry65_aw_rest_serials($request) {
    global $wpdb;
    $t = dry65_wallet_dev_table();
    $serials = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT serial_number FROM $t WHERE device_lib_id=%s AND pass_type_id=%s",
        $request['device'], $request['ptid']
    ));
    if (!$serials) return new WP_REST_Response(null, 204);
    return new WP_REST_Response(['lastUpdated' => (string) time(), 'serialNumbers' => $serials], 200);
}

function dry65_aw_rest_latest($request) {
    $serial = $request['serial'];
    if (!dry65_aw_check_auth($request, $serial)) return new WP_REST_Response(null, 401);
    $acc = dry65_aw_acc_from_serial($serial);
    if (!$acc) return new WP_REST_Response(null, 404);
    $path = dry65_wallet_apple_build($acc);
    if (!$path) return new WP_REST_Response(null, 500);
    nocache_headers();
    header('Content-Type: application/vnd.apple.pkpass');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    dry65_wallet_apple_rmdir(dirname($path));
    exit;
}

/* ============================================================
   APNs push kad se pečat promeni (uživo apdejt)
   ============================================================ */
add_action('dry65_stamp_changed', function ($account_id) {
    if (!dry65_wallet_apple_ready()) return;
    $acc = function_exists('dry65_pk_get') ? dry65_pk_get($account_id) : null;
    if (!$acc) return;
    dry65_wallet_apple_apns_queue(dry65_wallet_apple_serial($acc));
}, 10, 1);

function dry65_wallet_apple_apns_queue($serial = null) {
    static $serials = [];
    if ($serial !== null) $serials[$serial] = true;
    return array_keys($serials);
}

add_action('shutdown', function () {
    $serials = dry65_wallet_apple_apns_queue();
    if (!$serials || !dry65_wallet_apple_ready()) return;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    global $wpdb;
    $t = dry65_wallet_dev_table();
    foreach ($serials as $serial) {
        $tokens = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT push_token FROM $t WHERE serial_number=%s AND push_token<>''", $serial));
        foreach ($tokens as $tok) dry65_wallet_apple_apns_send($tok);
    }
}, 98);

/* Pošalji prazan APNs push (Wallet topic = Pass Type ID). Vrati true/false. */
function dry65_wallet_apple_apns_send($push_token) {
    if (!function_exists('curl_init')) return false;
    $url = 'https://api.push.apple.com/3/device/' . rawurlencode($push_token);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '{}',
        CURLOPT_HTTPHEADER     => ['apns-topic: ' . DRY65_AW_PASS_TYPE_ID, 'apns-push-type: background'],
        CURLOPT_SSLCERT        => DRY65_AW_CERT,
        CURLOPT_SSLKEY         => DRY65_AW_KEY,
        CURLOPT_HTTP_VERSION   => defined('CURL_HTTP_VERSION_2_0') ? CURL_HTTP_VERSION_2_0 : 3,
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}
