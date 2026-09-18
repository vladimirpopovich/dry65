<?php
/* ============================================================
   Dry65 — DIGITALNI WALLET (Apple Wallet + Google Wallet)
   ------------------------------------------------------------
   Kartica sa pečatima u telefonu gosta. Podaci o pečatima žive
   u paketi.php (nalog: initial = ukupno feniranja, balance = ostalo,
   used = initial - balance = odrađeni pečati). Ovaj fajl samo
   PROIZVODI wallet karticu i vodi računa o instalaciji/apdejtu.

   FAZA 1 (ovo): Google Wallet — „Add to Google Wallet" dugme na
   /kartica/{code}. Kartica se pravi inline preko potpisanog JWT-a
   (RS256, čist OpenSSL, bez biblioteka). Dugme je skriveno dok se
   ne unesu kredencijali (vidi dry65_wallet_google_ready()).

   FAZA 2 (kasnije): Apple Wallet (.pkpass + APNs) + live apdejt
   pečata (Google PATCH). Tabela dole je spremna za Apple registracije.

   KREDENCIJALI (staviti u wp-config.php, NE u temu/git):
     define('DRY65_GW_ISSUER_ID', '3388000000022xxxxxxx'); // Google Pay & Wallet Console -> Issuer ID
     define('DRY65_GW_SA_KEY',    '/apsolutna/putanja/dry65-wallet-sa.json'); // Service Account JSON
     define('DRY65_GW_CLASS',     'dry65_paket_v1'); // sufiks Loyalty klase (opciono, ima default)
   ============================================================ */

if (!defined('ABSPATH')) exit;

if (!defined('DRY65_WALLET_DB')) define('DRY65_WALLET_DB', 1); // verzija šeme (Apple registracije)
if (!defined('DRY65_GW_CLASS'))  define('DRY65_GW_CLASS', 'dry65_paket_v1');

/* ---- Tabela za Apple registracije uređaja (faza 2; pravi se odmah da je spremna) ---- */
function dry65_wallet_dev_table() { global $wpdb; return $wpdb->prefix . 'dry65_wallet_devices'; }

function dry65_wallet_install() {
    if ((int) get_option('dry65_wallet_db', 0) === DRY65_WALLET_DB) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $dev = dry65_wallet_dev_table();
    $sql = "CREATE TABLE $dev (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        device_lib_id VARCHAR(190) NOT NULL DEFAULT '',
        pass_type_id  VARCHAR(190) NOT NULL DEFAULT '',
        serial_number VARCHAR(64)  NOT NULL DEFAULT '',
        push_token    VARCHAR(190) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY reg (device_lib_id, pass_type_id, serial_number),
        KEY serial_number (serial_number)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('dry65_wallet_db', DRY65_WALLET_DB);
}
add_action('init', 'dry65_wallet_install');

/* ============================================================
   GOOGLE WALLET
   ============================================================ */

/* Da li je Google Wallet spreman za rad (svi kredencijali tu + ključ postoji). */
function dry65_wallet_google_ready() {
    if (!defined('DRY65_GW_ISSUER_ID') || !DRY65_GW_ISSUER_ID) return false;
    if (!defined('DRY65_GW_SA_KEY')    || !DRY65_GW_SA_KEY)    return false;
    if (!is_readable(DRY65_GW_SA_KEY)) return false;
    if (!function_exists('openssl_sign')) return false;
    return true;
}

/* Učitaj Service Account JSON (client_email + private_key). Kešira po zahtevu. */
function dry65_wallet_google_sa() {
    static $sa = null;
    if ($sa !== null) return $sa;
    $sa = false;
    if (!defined('DRY65_GW_SA_KEY') || !is_readable(DRY65_GW_SA_KEY)) return $sa;
    $raw = file_get_contents(DRY65_GW_SA_KEY);
    $j   = json_decode($raw, true);
    if (is_array($j) && !empty($j['private_key']) && !empty($j['client_email'])) {
        $sa = ['email' => $j['client_email'], 'key' => $j['private_key']];
    }
    return $sa;
}

/* base64url (bez paddinga) — za JWT. */
function dry65_wallet_b64url($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/* Puni ID Loyalty klase: ISSUER.CLASS */
function dry65_wallet_google_class_id() {
    return DRY65_GW_ISSUER_ID . '.' . DRY65_GW_CLASS;
}

/* Puni ID Loyalty objekta. TRAJNA kartica: vezan za KUPCA (c{customer_id}), da se
   ista instalirana kartica dopunjava kroz pakete. Ako nalog nema kupca — fallback na kod. */
function dry65_wallet_google_object_id($acc) {
    $cid = (int) ($acc->customer_id ?? 0);
    $suffix = $cid > 0 ? ('c' . $cid) : $acc->code;
    return DRY65_GW_ISSUER_ID . '.' . DRY65_GW_CLASS . '-' . $suffix;
}

/* Definicija Loyalty KLASE (šablon programa; ista za sve kartice). */
function dry65_wallet_google_class() {
    $biz  = function_exists('dry65_biz') ? dry65_biz() : ['name' => 'Dry65'];
    $logo = get_template_directory_uri() . '/assets/logo-square.png'; // kvadratni logo (min 660x660); zameniti pravim
    return [
        'id'           => dry65_wallet_google_class_id(),
        'issuerName'   => $biz['name'] ?: 'Dry65',
        'programName'  => 'Dry65 Club',
        'reviewStatus' => 'UNDER_REVIEW',
        'hexBackgroundColor' => '#783332', // brend (Signature bordo); po kartici se gazi bojom tira
        'programLogo'  => ['sourceUri' => ['uri' => $logo]],
        'countryCode'  => 'RS',
    ];
}

/* Boja pozadine Wallet kartice po broju feniranja (samo wallet, ne dira web karticu).
   Dodaj još parova po potrebi (npr. 12 => '#...'); ostali padaju na temu. */
function dry65_wallet_bg($acc) {
    $map = [
        4 => '#783332',
        8 => '#501918',
    ];
    $init = (int) $acc->initial;
    if (isset($map[$init])) return $map[$init];
    $th = function_exists('dry65_pk_card_theme') ? dry65_pk_card_theme($acc) : ['bg' => '#783332'];
    return $th['bg'];
}

/* Hero baner po broju feniranja (širi vizual preko vrha kartice). Prazno = bez herora. */
function dry65_wallet_hero($acc) {
    $map = [
        4 => 'hero-essential.png',
        8 => 'hero-signature.png',
    ];
    $init = (int) $acc->initial;
    if (empty($map[$init])) return '';
    $path = get_template_directory() . '/assets/packages/' . $map[$init];
    if (!file_exists($path)) return ''; // dok slika ne postoji, kartica ide bez herora
    return get_template_directory_uri() . '/assets/packages/' . $map[$init];
}

/* Definicija Loyalty OBJEKTA za konkretan nalog (pečati + QR). */
function dry65_wallet_google_object($acc) {
    $bg        = dry65_wallet_bg($acc);
    $card_url  = dry65_pk_card_url($acc->code);

    $obj = [
        'id'                 => dry65_wallet_google_object_id($acc),
        'classId'            => dry65_wallet_google_class_id(),
        'state'              => 'ACTIVE',
        'accountName'        => $acc->name,
        'accountId'          => $acc->code,
        'hexBackgroundColor' => $bg,
        'barcode'            => [
            'type'         => 'QR_CODE',
            'value'        => $card_url,
            'alternateText'=> $acc->code,
        ],
    ];

    $hero = dry65_wallet_hero($acc);
    if ($hero) $obj['heroImage'] = ['sourceUri' => ['uri' => $hero]];

    // Broj pečata (deljeno sa PATCH-om da se instalacija i apdejt ne raziđu).
    $obj = array_merge($obj, dry65_wallet_google_points($acc));

    if (!empty($acc->expires_at)) {
        $obj['textModulesData'] = [[
            'id'     => 'vazi_do',
            'header' => 'Važi do',
            'body'   => date_i18n('d.m.Y.', strtotime($acc->expires_at)),
        ]];
    }

    return $obj;
}

/* Potpisan „Save to Google Wallet" JWT (RS256) sa inline klasom + objektom. */
function dry65_wallet_google_jwt($acc) {
    if (!dry65_wallet_google_ready()) return '';
    $sa = dry65_wallet_google_sa();
    if (!$sa) return '';

    $claims = [
        'iss'     => $sa['email'],
        'aud'     => 'google',
        'typ'     => 'savetowallet',
        'iat'     => time(),
        'origins' => [ home_url() ],
        'payload' => [
            'loyaltyClasses'  => [ dry65_wallet_google_class() ],
            'loyaltyObjects'  => [ dry65_wallet_google_object($acc) ],
        ],
    ];

    $header  = ['alg' => 'RS256', 'typ' => 'JWT'];
    $segments = [
        dry65_wallet_b64url(wp_json_encode($header)),
        dry65_wallet_b64url(wp_json_encode($claims)),
    ];
    $signing_input = implode('.', $segments);

    $sig = '';
    $pkey = openssl_pkey_get_private($sa['key']);
    if (!$pkey || !openssl_sign($signing_input, $sig, $pkey, OPENSSL_ALGO_SHA256)) return '';

    $segments[] = dry65_wallet_b64url($sig);
    return implode('.', $segments);
}

/* Puni „Save" URL za Google Wallet. */
function dry65_wallet_google_save_url($acc) {
    $jwt = dry65_wallet_google_jwt($acc);
    return $jwt ? ('https://pay.google.com/gp/v/save/' . $jwt) : '';
}

/* „Add to Google Wallet" dugme (vraća '' ako nije konfigurisano). 
   NAPOMENA: za produkciju zameniti zvaničnim Google badge-om po brand guideline-u. */
function dry65_wallet_google_button($acc) {
    $url = dry65_wallet_google_save_url($acc);
    if (!$url) return '';
    return '<a href="' . esc_url($url) . '" style="display:inline-flex;align-items:center;gap:8px;background:#000;color:#fff;'
         . 'text-decoration:none;border-radius:999px;padding:11px 20px;font-size:14px;font-weight:600;line-height:1;">'
         . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="#fff" stroke-width="1.6"/><path d="M3 10h18" stroke="#fff" stroke-width="1.6"/></svg>'
         . 'Dodaj u Google Wallet</a>';
}

/* ============================================================
   GOOGLE WALLET — LIVE APDEJT (PATCH loyalty objekta)
   ------------------------------------------------------------
   Kad se pečat promeni, već instalirana kartica se osveži. Google
   objekat postoji na serveru tek kad gost SAČUVA karticu (inline JWT
   ga tada kreira); dok ne sačuva, PATCH vrati 404 i mi ga tiho
   preskočimo. Poziv ide na 'shutdown' (posle odgovora), da radnica
   ne čeka Google dok tapće pečat.
   ============================================================ */

/* OAuth2 access token preko Service Account JWT-bearer granta. Kešira se. */
function dry65_wallet_google_access_token() {
    if (!dry65_wallet_google_ready()) return '';
    $cached = get_transient('dry65_gw_token');
    if ($cached) return $cached;

    $sa = dry65_wallet_google_sa();
    if (!$sa) return '';
    $now    = time();
    $claims = [
        'iss'   => $sa['email'],
        'scope' => 'https://www.googleapis.com/auth/wallet_object.issuer',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];
    $segments = [
        dry65_wallet_b64url(wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
        dry65_wallet_b64url(wp_json_encode($claims)),
    ];
    $sig  = '';
    $pkey = openssl_pkey_get_private($sa['key']);
    if (!$pkey || !openssl_sign(implode('.', $segments), $sig, $pkey, OPENSSL_ALGO_SHA256)) return '';
    $segments[] = dry65_wallet_b64url($sig);
    $assertion  = implode('.', $segments);

    $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
        'timeout' => 10,
        'body'    => [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $assertion,
        ],
    ]);
    if (is_wp_error($resp)) return '';
    $j = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($j['access_token'])) return '';
    $ttl = max(60, (int) ($j['expires_in'] ?? 3600) - 60);
    set_transient('dry65_gw_token', $j['access_token'], $ttl);
    return $j['access_token'];
}

/* Promenljivi deo kartice (pečati) — deli ga i inline objekat i PATCH. */
function dry65_wallet_google_points($acc) {
    $out = [];
    if ($acc->type === 'paket') {
        $initial = (int) $acc->initial;
        $used    = max(0, $initial - (int) $acc->balance);
        $reward  = function_exists('dry65_pk_effective_reward') ? dry65_pk_effective_reward($acc) : (string) $acc->reward;
        $out['loyaltyPoints'] = ['label' => 'Pečati', 'balance' => ['string' => $used . ' / ' . $initial]];
        if ($reward) {
            $out['secondaryLoyaltyPoints'] = ['label' => 'Tretman', 'balance' => ['string' => empty($acc->reward_used_at) ? $reward : 'Iskorišćen']];
        }
    } else {
        $out['loyaltyPoints'] = ['label' => 'Stanje', 'balance' => ['string' => number_format((int) $acc->balance, 0, ',', '.') . ' din']];
    }
    return $out;
}

/* PATCH loyalty objekta na Google-u novim brojem pečata. Vrati true/false.
   Tiho preskače ako objekat još ne postoji (gost nije sačuvao karticu). */
function dry65_wallet_google_patch($acc) {
    if (!dry65_wallet_google_ready() || !is_object($acc)) return false;
    $token = dry65_wallet_google_access_token();
    if (!$token) return false;

    $object_id = dry65_wallet_google_object_id($acc);
    $url  = 'https://walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/' . rawurlencode($object_id);

    // Ceo promenljivi deo (pečati + boja/hero/QR po tiru), da dopuna paketa osveži sve na kartici.
    $body = dry65_wallet_google_points($acc);
    $body['accountName']        = $acc->name;
    $body['hexBackgroundColor'] = dry65_wallet_bg($acc);
    $body['barcode']            = ['type' => 'QR_CODE', 'value' => dry65_pk_card_url($acc->code), 'alternateText' => $acc->code];
    $hero = dry65_wallet_hero($acc);
    if ($hero) $body['heroImage'] = ['sourceUri' => ['uri' => $hero]];

    $resp = wp_remote_request($url, [
        'method'  => 'PATCH',
        'timeout' => 10,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode($body),
    ]);
    if (is_wp_error($resp)) return false;
    return (int) wp_remote_retrieve_response_code($resp) === 200; // 404 = kartica još nije sačuvana -> ignoriši
}

/* Skupi izmenjene naloge u toku zahteva, pa ih obradi na 'shutdown'. */
function dry65_wallet_sync_queue($account_id = null) {
    static $ids = [];
    if ($account_id !== null) $ids[(int) $account_id] = true;
    return array_keys($ids);
}

add_action('dry65_stamp_changed', function ($account_id) {
    if (!dry65_wallet_google_ready()) return;   // ništa dok Google nije konfigurisan
    dry65_wallet_sync_queue($account_id);
}, 10, 1);

/* Posle poslatog odgovora: zatvori konekciju ka klijentu pa apdejtuj wallet. */
add_action('shutdown', function () {
    $ids = dry65_wallet_sync_queue();
    if (!$ids || !dry65_wallet_google_ready()) return;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); // radnica ne čeka Google
    foreach ($ids as $id) {
        $acc = function_exists('dry65_pk_get') ? dry65_pk_get($id) : null;
        if ($acc) dry65_wallet_google_patch($acc);
    }
}, 99);
