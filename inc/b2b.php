<?php
/* ============================================================
   Dry65 — B2B PAKETI (firma -> zajednički mesečni fond feniranja)
   ------------------------------------------------------------
   Firma kupi mesečni fond feniranja (npr. 30/60/90, ali broj je
   slobodan — biće i drugih vrsta ponuda kasnije). Zaposleni
   dobijaju Apple/Google Wallet karticu (NEMA fizičke) koja ih
   identifikuje; svako skidanje troši ZAJEDNIČKI fond firme.
   Potrošnja se BLOKIRA na 0 (nema overage-a, ne ide u minus).
   Admin firme gleda potrošnju na /b2b (odvojen login od gostova).

   Tabele:
     {p}dry65_b2b_companies   firme (fond, ciklus, cena, status)
     {p}dry65_b2b_employees   zaposleni (identifikacija, QR/wallet)
     {p}dry65_b2b_txns        istorija trošenja/obnova/poništenja

   Reuse iz paketi.php: DRY65_PK_CAP/DRY65_PK_ADMIN_CAP, staff PIN
   (dry65_pk_staff_*), QR (dry65_pk_qr_html), bare template
   (dry65_pk_bare_head/foot), denormalizovani log (dry65_pk_stamp_log_table).
   Reuse iz wallet.php/wallet-apple.php: generički JWT/potpis helperi
   (nalog-specifične funkcije se NE diraju, b2b ima svoje objekat/pass
   graditelje).
   ============================================================ */

if (!defined('ABSPATH')) exit;

if (!defined('DRY65_B2B_DB')) define('DRY65_B2B_DB', 2);

/* ---- Tabele ---- */
function dry65_b2b_table()     { global $wpdb; return $wpdb->prefix . 'dry65_b2b_companies'; }
function dry65_b2b_emp_table() { global $wpdb; return $wpdb->prefix . 'dry65_b2b_employees'; }
function dry65_b2b_txn_table() { global $wpdb; return $wpdb->prefix . 'dry65_b2b_txns'; }

function dry65_b2b_install() {
    if ((int) get_option('dry65_b2b_db', 0) === DRY65_B2B_DB) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $co  = dry65_b2b_table();
    $emp = dry65_b2b_emp_table();
    $txn = dry65_b2b_txn_table();
    $sql = "CREATE TABLE $co (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(190) NOT NULL DEFAULT '',
        contact_email VARCHAR(190) NOT NULL DEFAULT '',
        price_rsd INT NOT NULL DEFAULT 0,
        initial INT NOT NULL DEFAULT 0,
        balance INT NOT NULL DEFAULT 0,
        cycle_start DATE NOT NULL,
        cycle_end DATE NOT NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'active',
        logo_path VARCHAR(255) NOT NULL DEFAULT '',
        note TEXT NULL,
        created_at DATETIME NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY name (name),
        KEY status (status)
    ) $charset;
    CREATE TABLE $emp (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL DEFAULT '',
        code VARCHAR(20) NOT NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY code (code),
        KEY company_id (company_id)
    ) $charset;
    CREATE TABLE $txn (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id BIGINT UNSIGNED NOT NULL,
        employee_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        employee_name VARCHAR(190) NOT NULL DEFAULT '',
        delta INT NOT NULL DEFAULT 0,
        balance_after INT NOT NULL DEFAULT 0,
        note VARCHAR(190) NOT NULL DEFAULT '',
        staff_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        staff_name VARCHAR(120) NOT NULL DEFAULT '',
        reversed TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY company_id (company_id),
        KEY employee_id (employee_id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('dry65_b2b_db', DRY65_B2B_DB);
}
add_action('init', 'dry65_b2b_install');

/* ---- Osnovni helperi ---- */
function dry65_b2b_now()   { return function_exists('dry65_pk_now') ? dry65_pk_now() : current_time('mysql'); }
function dry65_b2b_today() { return (new DateTime('now', new DateTimeZone('Europe/Belgrade')))->format('Y-m-d'); }
function dry65_b2b_cycle_end($start) {
    return (new DateTime($start, new DateTimeZone('Europe/Belgrade')))->modify('+1 month')->format('Y-m-d');
}

/* Kod zaposlenog za QR/wallet (prefiks 'b' da se vizuelno razlikuje od ličnih kodova). */
function dry65_b2b_gen_code() {
    global $wpdb;
    $t = dry65_b2b_emp_table();
    $chars = 'abcdefghijkmnpqrstuvwxyz23456789';
    do {
        $code = 'b';
        for ($i = 0; $i < 8; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE code = %s", $code));
    } while ($exists);
    return $code;
}

function dry65_b2b_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . dry65_b2b_table() . " WHERE id=%d", (int) $id));
}
function dry65_b2b_all($status = '') {
    global $wpdb;
    $t = dry65_b2b_table();
    if ($status) return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE status=%s ORDER BY name", $status));
    return $wpdb->get_results("SELECT * FROM $t ORDER BY name");
}
function dry65_b2b_employee_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . dry65_b2b_emp_table() . " WHERE id=%d", (int) $id));
}
function dry65_b2b_employee_by_code($code) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . dry65_b2b_emp_table() . " WHERE code=%s", (string) $code));
}
function dry65_b2b_employees($company_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM " . dry65_b2b_emp_table() . " WHERE company_id=%d ORDER BY name", (int) $company_id));
}
function dry65_b2b_txns($company_id, $limit = 200) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM " . dry65_b2b_txn_table() . " WHERE company_id=%d ORDER BY id DESC LIMIT %d", (int) $company_id, (int) $limit));
}
function dry65_b2b_card_url($code, $company_name = '') {
    $slug = $company_name !== '' ? sanitize_title($company_name) : 'firma';
    return home_url('/b2b/' . $slug . '/' . $code . '/');
}

/* ============================================================
   LOGO FIRME — admin otpremi JEDNU sliku (bilo kog razmera), sistem
   je sam uklapa na belo platno za Apple traku (2.60:1, 3 veličine)
   i Google baner (~3:1). Bez centralne/fiksne slike u kodu — svaka
   firma ima svoj logo, biće ih više vremenom.
   ============================================================ */
function dry65_b2b_logo_file($co) {
    if (empty($co->logo_path)) return '';
    $uploads = wp_upload_dir();
    $p = $uploads['basedir'] . '/' . $co->logo_path;
    return is_readable($p) ? $p : '';
}
function dry65_b2b_logo_url($co) {
    if (empty($co->logo_path)) return '';
    $uploads = wp_upload_dir();
    return $uploads['baseurl'] . '/' . $co->logo_path;
}

/* Uklopi logo (bilo kog razmera) na belo platno tačno $w x $h, centrirano, bez sečenja. */
/* dry65 wordmark (burgundy/terracotta na providnom), uvek u temi — leva strana trake. */
function dry65_b2b_mark_file() {
    $p = get_template_directory() . '/assets/wallet/b2b/dry65-mark.png';
    return is_readable($p) ? $p : '';
}

/* Učitaj JPEG/PNG/WEBP izvor (bilo koji). */
function dry65_b2b_load_image($path) {
    $info = @getimagesize($path);
    if (!$info) return false;
    switch ($info[2]) {
        case IMAGETYPE_JPEG: return @imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:  return @imagecreatefrompng($path);
        case IMAGETYPE_WEBP: return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
        default: return false;
    }
}

/* Odseci providnu/belu marginu oko loga (otpremljene slike često nose svoju marginu) —
   bez ovoga logo nikad ne dodiruje pravu ivicu trake bez obzira na našu kutiju. */
function dry65_b2b_trim_whitespace($im) {
    $w = imagesx($im); $h = imagesy($im);
    $minx = $w; $miny = $h; $maxx = -1; $maxy = -1;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($im, $x, $y);
            $a = ($c >> 24) & 0xFF;
            if ($a >= 120) continue; // skoro providno
            $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
            if ($r > 248 && $g > 248 && $b > 248) continue; // skoro belo
            if ($x < $minx) $minx = $x;
            if ($x > $maxx) $maxx = $x;
            if ($y < $miny) $miny = $y;
            if ($y > $maxy) $maxy = $y;
        }
    }
    if ($maxx < $minx || $maxy < $miny) return $im; // sve belo/providno — ne diraj
    $cw = $maxx - $minx + 1; $ch = $maxy - $miny + 1;
    $trimmed = imagecreatetruecolor($cw, $ch);
    imagealphablending($trimmed, false);
    imagesavealpha($trimmed, true);
    $transparent = imagecolorallocatealpha($trimmed, 0, 0, 0, 127);
    imagefill($trimmed, 0, 0, $transparent);
    imagealphablending($trimmed, true);
    imagecopy($trimmed, $im, 0, 0, $minx, $miny, $cw, $ch);
    imagedestroy($im);
    return $trimmed;
}

/* Uklopi sliku $path u pravougaonik ($box_x,$box_y,$box_w,$box_h) na $canvas, centrirano, bez sečenja. */
function dry65_b2b_fit_region($canvas, $path, $box_x, $box_y, $box_w, $box_h, $align = 'center') {
    $src = dry65_b2b_load_image($path);
    if (!$src) return;
    imagealphablending($src, true);
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = min($box_w / $sw, $box_h / $sh);
    $dw = max(1, (int) round($sw * $scale));
    $dh = max(1, (int) round($sh * $scale));
    if ($align === 'right') { $dx = $box_x + $box_w - $dw; }
    elseif ($align === 'left') { $dx = $box_x; }
    else { $dx = $box_x + (int) round(($box_w - $dw) / 2); }
    $dy = $box_y + (int) round(($box_h - $dh) / 2);
    imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);
}

/* Traka na belom: dry65 levo + logo firme desno (ili samo dry65 dok firma nema otpremljen logo). */
function dry65_b2b_banner_png($co, $w, $h) {
    $mark    = dry65_b2b_mark_file();
    $company = dry65_b2b_logo_file($co);
    if ($mark === '' && $company === '') return '';

    $canvas = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);

    $side_margin = (int) round($w * 0.07);

    if ($mark !== '' && $company !== '') {
        // Ista margina levo/desno (7%) kao padding teksta ispod trake, da se logoi poravnaju sa njim.
        $right_margin = $side_margin;
        $gap = (int) round($w * 0.06);
        $mark_w = (int) round($w * 0.26);
        $comp_w_avail = ($w - $right_margin) - ($side_margin + $mark_w + $gap);
        $comp_w = (int) round($comp_w_avail * 0.62); // logo firme je skoro uvek širi od visok — širina je ograničavajuća, pa se smanjuje preko nje
        $comp_x = ($w - $right_margin) - $comp_w; // desna ivica ostaje fiksna (poravnata), kutija se skuplja sa leve strane

        $mark_box_h = (int) round($h * 0.30);
        $comp_box_h = (int) round($h * 0.42);

        dry65_b2b_fit_region($canvas, $mark, $side_margin, (int) round(($h - $mark_box_h) / 2), $mark_w, $mark_box_h, 'left');
        dry65_b2b_fit_region($canvas, $company, $comp_x, (int) round(($h - $comp_box_h) / 2), $comp_w, $comp_box_h, 'right');
    } else {
        $solo = $mark !== '' ? $mark : $company;
        $margin_y = (int) round($h * 0.24);
        $box_w = $w - 2 * $side_margin;
        dry65_b2b_fit_region($canvas, $solo, $side_margin, $margin_y, $box_w, $h - 2 * $margin_y);
    }

    ob_start(); imagepng($canvas); $bytes = ob_get_clean();
    imagedestroy($canvas);
    return $bytes;
}

/* Google mora da povuče heroImage sa stabilnog URL-a (ne inline) — čuvamo generisan fajl. */
function dry65_b2b_hero_dir() {
    $uploads = wp_upload_dir();
    $dir = $uploads['basedir'] . '/dry65-b2b';
    wp_mkdir_p($dir);
    return $dir;
}
function dry65_b2b_hero_path($co) { return dry65_b2b_hero_dir() . '/hero-' . (int) $co->id . '.png'; }
function dry65_b2b_hero_url($co) {
    if (!is_readable(dry65_b2b_hero_path($co))) return '';
    $uploads = wp_upload_dir();
    return $uploads['baseurl'] . '/dry65-b2b/hero-' . (int) $co->id . '.png';
}
function dry65_b2b_regenerate_hero($co) {
    $bytes = dry65_b2b_banner_png($co, 1032, 336);
    if ($bytes === '') { @unlink(dry65_b2b_hero_path($co)); return false; }
    file_put_contents(dry65_b2b_hero_path($co), $bytes);
    return true;
}
/* Hero URL (traka dry65+firma), generiše ga u hodu ako fajl (iz bilo kog razloga) ne postoji. */
function dry65_b2b_ensure_hero($co) {
    $url = dry65_b2b_hero_url($co);
    if ($url) return $url;
    dry65_b2b_regenerate_hero($co);
    return dry65_b2b_hero_url($co);
}

/* ---- CRUD firme ---- */

/* Fond je slobodan broj (30/60/90 su samo predlog u formi — biće i drugih vrsta ponuda). */
function dry65_b2b_create($name, $contact_email, $initial, $price_rsd, $note = '') {
    global $wpdb;
    $name = sanitize_text_field($name);
    if ($name === '') return 0;
    $initial = max(1, (int) $initial);
    $start = dry65_b2b_today();
    $end   = dry65_b2b_cycle_end($start);
    $ok = $wpdb->insert(dry65_b2b_table(), [
        'name'          => $name,
        'contact_email' => sanitize_email($contact_email),
        'price_rsd'     => max(0, (int) $price_rsd),
        'initial'       => $initial,
        'balance'       => $initial,
        'cycle_start'   => $start,
        'cycle_end'     => $end,
        'status'        => 'active',
        'note'          => $note,
        'created_at'    => dry65_b2b_now(),
        'created_by'    => get_current_user_id(),
    ], ['%s','%s','%d','%d','%d','%s','%s','%s','%s','%s','%d']);
    if (!$ok) return 0;
    $id = (int) $wpdb->insert_id;
    $wpdb->insert(dry65_b2b_txn_table(), [
        'company_id' => $id, 'employee_id' => 0, 'employee_name' => '',
        'delta' => $initial, 'balance_after' => $initial,
        'note' => 'Fond otvoren', 'staff_id' => get_current_user_id(), 'staff_name' => '',
        'created_at' => dry65_b2b_now(),
    ], ['%d','%d','%s','%d','%d','%s','%d','%s','%s']);
    return $id;
}

function dry65_b2b_update($id, $name, $contact_email, $initial, $price_rsd, $status, $note) {
    global $wpdb;
    $co = dry65_b2b_get($id);
    if (!$co) return false;
    $initial = max(1, (int) $initial);
    $data = [
        'name'          => sanitize_text_field($name),
        'contact_email' => sanitize_email($contact_email),
        'price_rsd'     => max(0, (int) $price_rsd),
        'status'        => in_array($status, ['active', 'paused'], true) ? $status : 'active',
        'note'          => $note,
    ];
    $fmt = ['%s','%s','%d','%s','%s'];
    if ($initial !== (int) $co->initial) {
        $diff = $initial - (int) $co->initial;
        $data['initial'] = $initial;
        $data['balance'] = max(0, min($initial, (int) $co->balance + $diff));
        $fmt[] = '%d'; $fmt[] = '%d';
    }
    $wpdb->update(dry65_b2b_table(), $data, ['id' => (int) $id], $fmt, ['%d']);
    do_action('dry65_b2b_balance_changed', (int) $id);
    return true;
}

/* ---- Zaposleni ---- */
function dry65_b2b_employee_create($company_id, $name) {
    global $wpdb;
    $co = dry65_b2b_get($company_id);
    $name = sanitize_text_field($name);
    if (!$co || $name === '') return 0;
    $code = dry65_b2b_gen_code();
    $ok = $wpdb->insert(dry65_b2b_emp_table(), [
        'company_id' => (int) $company_id,
        'name'       => $name,
        'code'       => $code,
        'status'     => 'active',
        'created_at' => dry65_b2b_now(),
    ], ['%d','%s','%s','%s','%s']);
    return $ok ? (int) $wpdb->insert_id : 0;
}
function dry65_b2b_employee_delete($id) {
    global $wpdb;
    $wpdb->delete(dry65_b2b_emp_table(), ['id' => (int) $id], ['%d']);
}

/* ---- Trošenje (BLOKIRA se na 0 — nema overage-a) ---- */
function dry65_b2b_spend($employee, $staff_name = '', $source = '') {
    if (!is_object($employee)) return ['ok' => false, 'msg' => 'Nepoznata kartica.'];
    $co = dry65_b2b_get($employee->company_id);
    if (!$co) return ['ok' => false, 'msg' => 'Nepoznata firma.'];
    if ($co->status !== 'active') return ['ok' => false, 'msg' => 'Firma nije aktivna.'];
    if ((int) $co->balance <= 0) return ['ok' => false, 'msg' => 'Nema preostalih feniranja za ' . $co->name . ' ovog meseca.'];
    global $wpdb;
    $new = (int) $co->balance - 1;
    $wpdb->update(dry65_b2b_table(), ['balance' => $new], ['id' => (int) $co->id], ['%d'], ['%d']);
    $wpdb->insert(dry65_b2b_txn_table(), [
        'company_id'    => (int) $co->id,
        'employee_id'   => (int) $employee->id,
        'employee_name' => $employee->name,
        'delta'         => -1,
        'balance_after' => $new,
        'note'          => 'Feniranje',
        'staff_id'      => get_current_user_id(),
        'staff_name'    => $staff_name,
        'created_at'    => dry65_b2b_now(),
    ], ['%d','%d','%s','%d','%d','%s','%d','%s','%s']);
    // Isti denormalizovani log kao lični paketi — spaja se u budući presek dolazaka.
    if (function_exists('dry65_pk_stamp_log_table')) {
        $wpdb->insert(dry65_pk_stamp_log_table(), [
            'logged_at' => dry65_b2b_now(), 'account_id' => 0, 'customer_id' => 0,
            'code' => $employee->code, 'type' => 'b2b', 'event' => 'feniranje',
            'delta' => -1, 'balance_after' => $new, 'tier' => '', 'plan' => $co->name,
            'source' => $source, 'staff_id' => get_current_user_id(), 'staff_name' => $staff_name,
        ], ['%s','%d','%d','%s','%s','%s','%d','%d','%s','%s','%s','%d','%s']);
    }
    do_action('dry65_b2b_balance_changed', (int) $co->id);
    return ['ok' => true, 'balance' => $new];
}

/* Poništi grešku (admin). Vrati stanje, klampuje u [0..initial], ostavlja trag. */
function dry65_b2b_undo($txn_id) {
    global $wpdb;
    $tt = dry65_b2b_txn_table();
    $t = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tt WHERE id=%d", (int) $txn_id));
    if (!$t || (int) $t->reversed === 1) return false;
    $co = dry65_b2b_get($t->company_id);
    if (!$co) return false;
    $delta = (int) $t->delta;
    $new = max(0, min((int) $co->initial, (int) $co->balance - $delta));
    $wpdb->update(dry65_b2b_table(), ['balance' => $new], ['id' => (int) $co->id], ['%d'], ['%d']);
    $wpdb->insert($tt, [
        'company_id' => (int) $co->id, 'employee_id' => (int) $t->employee_id, 'employee_name' => $t->employee_name,
        'delta' => -$delta, 'balance_after' => $new,
        'note' => 'Poništeno: ' . $t->note, 'staff_id' => get_current_user_id(), 'staff_name' => '',
        'reversed' => 1, 'created_at' => dry65_b2b_now(),
    ], ['%d','%d','%s','%d','%d','%s','%d','%s','%d','%s']);
    $wpdb->update($tt, ['reversed' => 1], ['id' => (int) $t->id], ['%d'], ['%d']);
    do_action('dry65_b2b_balance_changed', (int) $co->id);
    return true;
}

/* ---- Mesečna obnova fonda ---- */
function dry65_b2b_cycle_reset($co) {
    global $wpdb;
    $old_balance = (int) $co->balance;
    $new_start = $co->cycle_end;
    $new_end   = dry65_b2b_cycle_end($new_start);
    $wpdb->update(dry65_b2b_table(), [
        'balance'     => (int) $co->initial,
        'cycle_start' => $new_start,
        'cycle_end'   => $new_end,
    ], ['id' => (int) $co->id], ['%d','%s','%s'], ['%d']);
    $wpdb->insert(dry65_b2b_txn_table(), [
        'company_id' => (int) $co->id, 'employee_id' => 0, 'employee_name' => '',
        'delta' => (int) $co->initial - $old_balance, 'balance_after' => (int) $co->initial,
        'note' => 'Obnova meseca', 'staff_id' => 0, 'staff_name' => '',
        'created_at' => dry65_b2b_now(),
    ], ['%d','%d','%s','%d','%d','%s','%d','%s','%s']);
    do_action('dry65_b2b_balance_changed', (int) $co->id);
}

add_action('init', function () {
    if (!wp_next_scheduled('dry65_b2b_daily_cron')) {
        wp_schedule_event(strtotime('tomorrow 03:00:00'), 'daily', 'dry65_b2b_daily_cron');
    }
});
add_action('dry65_b2b_daily_cron', function () {
    $today = dry65_b2b_today();
    foreach (dry65_b2b_all('active') as $co) {
        if ($co->cycle_end <= $today) dry65_b2b_cycle_reset($co);
    }
});

/* ============================================================
   WALLET — GOOGLE (po zaposlenom, zajednički balans firme)
   Reuse generičkih helpera iz wallet.php (JWT/potpis/token) —
   ta funkcija se ne dira, ovo je paralelni objekat-graditelj.
   ============================================================ */
function dry65_b2b_wallet_google_object_id($employee) {
    return DRY65_GW_ISSUER_ID . '.' . DRY65_GW_CLASS . '-be' . (int) $employee->id;
}
function dry65_b2b_wallet_google_object($employee, $co) {
    $card_url = dry65_b2b_card_url($employee->code, $co->name);
    $hero_url = dry65_b2b_ensure_hero($co);
    $obj = [
        'id'                 => dry65_b2b_wallet_google_object_id($employee),
        'classId'            => dry65_wallet_google_class_id(),
        'state'              => 'ACTIVE',
        'accountName'        => $employee->name,
        'accountId'          => $employee->code,
        'hexBackgroundColor' => $hero_url ? '#FFFFFF' : '#33302c',
        'barcode'            => ['type' => 'QR_CODE', 'value' => $card_url, 'alternateText' => $employee->code],
        'loyaltyPoints'      => ['label' => 'Preostalo (' . $co->name . ')', 'balance' => ['string' => (int) $co->balance . ' / ' . (int) $co->initial]],
        'textModulesData'    => [[
            'id' => 'obnova', 'header' => 'Obnova', 'body' => date_i18n('d.m.Y.', strtotime($co->cycle_end)),
        ]],
    ];
    if ($hero_url) $obj['heroImage'] = ['sourceUri' => ['uri' => $hero_url]];
    return $obj;
}
function dry65_b2b_wallet_google_jwt($employee, $co) {
    if (!function_exists('dry65_wallet_google_ready') || !dry65_wallet_google_ready()) return '';
    $sa = dry65_wallet_google_sa();
    if (!$sa) return '';
    $claims = [
        'iss' => $sa['email'], 'aud' => 'google', 'typ' => 'savetowallet', 'iat' => time(),
        'origins' => [home_url()],
        'payload' => [
            'loyaltyClasses' => [dry65_wallet_google_class()],
            'loyaltyObjects' => [dry65_b2b_wallet_google_object($employee, $co)],
        ],
    ];
    $segments = [
        dry65_wallet_b64url(wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
        dry65_wallet_b64url(wp_json_encode($claims)),
    ];
    $sig = ''; $pkey = openssl_pkey_get_private($sa['key']);
    if (!$pkey || !openssl_sign(implode('.', $segments), $sig, $pkey, OPENSSL_ALGO_SHA256)) return '';
    $segments[] = dry65_wallet_b64url($sig);
    return implode('.', $segments);
}
function dry65_b2b_wallet_google_button($employee, $co) {
    $jwt = dry65_b2b_wallet_google_jwt($employee, $co);
    if (!$jwt) return '';
    $url = 'https://pay.google.com/gp/v/save/' . $jwt;
    return '<a href="' . esc_url($url) . '" style="display:inline-flex;align-items:center;gap:8px;background:#000;color:#fff;'
         . 'text-decoration:none;border-radius:999px;padding:11px 20px;font-size:14px;font-weight:600;line-height:1;">'
         . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="#fff" stroke-width="1.6"/><path d="M3 10h18" stroke="#fff" stroke-width="1.6"/></svg>'
         . 'Dodaj u Google Wallet</a>';
}
function dry65_b2b_wallet_google_patch($employee, $co) {
    if (!function_exists('dry65_wallet_google_ready') || !dry65_wallet_google_ready()) return false;
    $token = dry65_wallet_google_access_token();
    if (!$token) return false;
    $object_id = dry65_b2b_wallet_google_object_id($employee);
    $url = 'https://walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/' . rawurlencode($object_id);
    $hero_url = dry65_b2b_ensure_hero($co);
    $body = [
        'accountName'        => $employee->name,
        'hexBackgroundColor' => $hero_url ? '#FFFFFF' : '#33302c',
        'loyaltyPoints'      => ['label' => 'Preostalo (' . $co->name . ')', 'balance' => ['string' => (int) $co->balance . ' / ' . (int) $co->initial]],
        'textModulesData'    => [[ 'id' => 'obnova', 'header' => 'Obnova', 'body' => date_i18n('d.m.Y.', strtotime($co->cycle_end)) ]],
    ];
    if ($hero_url) $body['heroImage'] = ['sourceUri' => ['uri' => $hero_url]];
    $resp = wp_remote_request($url, [
        'method' => 'PATCH', 'timeout' => 10,
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'body' => wp_json_encode($body),
    ]);
    if (is_wp_error($resp)) return false;
    return (int) wp_remote_retrieve_response_code($resp) === 200;
}

/* ============================================================
   WALLET — APPLE (.pkpass). Fond je zajednički i preveliki za
   punch-krugove, pa umesto pečata ide logo firme kao traka (strip)
   na belom; dok firma nema otpremljen logo koristi se prost
   tamni "generic" stil sa poljima.
   ============================================================ */
function dry65_b2b_wallet_apple_serial($employee) { return 'be' . (int) $employee->id; }

function dry65_b2b_wallet_apple_passjson($employee, $co) {
    $serial   = dry65_b2b_wallet_apple_serial($employee);
    $card_url = dry65_b2b_card_url($employee->code, $co->name);

    $back = [
        ['key' => 'obnova', 'label' => 'Obnova fonda', 'value' => date_i18n('d.m.Y.', strtotime($co->cycle_end))],
        ['key' => 'about', 'label' => 'Dry65', 'value' => 'West 65, Novi Beograd. Pokažite karticu osoblju u salonu.'],
        ['key' => 'link', 'label' => 'Kartica', 'value' => $card_url],
    ];

    // Traka na vrhu (dry65 + logo firme) je uvek belo-pozadinska.
    return [
        'formatVersion'       => 1,
        'passTypeIdentifier'  => DRY65_AW_PASS_TYPE_ID,
        'teamIdentifier'      => DRY65_AW_TEAM_ID,
        'organizationName'    => 'Dry65',
        'description'         => 'Dry65 B2B kartica',
        'serialNumber'        => $serial,
        'backgroundColor'     => 'rgb(255,255,255)',
        'foregroundColor'     => 'rgb(17,28,29)',
        'labelColor'          => 'rgb(120,51,50)',
        'webServiceURL'       => home_url('/wp-json/dry65aw'),
        'authenticationToken' => dry65_wallet_apple_authtoken_for_serial($serial),
        'barcodes'            => [[
            'format' => 'PKBarcodeFormatQR', 'message' => $card_url,
            'messageEncoding' => 'iso-8859-1', 'altText' => (string) $employee->code,
        ]],
        'storeCard' => [
            'secondaryFields' => [
                ['key' => 'zaposleni', 'label' => 'ZAPOSLENI', 'value' => (string) $employee->name],
                ['key' => 'preostalo', 'label' => 'PREOSTALO', 'value' => (int) $co->balance . ' / ' . (int) $co->initial, 'textAlignment' => 'PKTextAlignmentRight'],
            ],
            'backFields' => $back,
        ],
    ];
}

function dry65_b2b_wallet_apple_build($employee, $co) {
    if (!function_exists('dry65_wallet_apple_ready') || !dry65_wallet_apple_ready()) return '';
    if (!is_object($employee) || !is_object($co)) return '';
    $files = [];
    $files['pass.json']    = wp_json_encode(dry65_b2b_wallet_apple_passjson($employee, $co));
    $files['icon.png']     = dry65_wallet_apple_asset('icon.png');
    $files['icon@2x.png']  = dry65_wallet_apple_asset('icon@2x.png');
    $files['icon@3x.png']  = dry65_wallet_apple_asset('icon@3x.png');
    // logo.png (gornji levi ugao) se NE dodaje — dry65 mark je već u traci (strip).
    $files['strip.png']    = dry65_b2b_banner_png($co, 375, 144);
    $files['strip@2x.png'] = dry65_b2b_banner_png($co, 750, 288);
    $files['strip@3x.png'] = dry65_b2b_banner_png($co, 1125, 432);
    foreach ($files as $k => $v) { if ($v === '' && $k !== 'pass.json') unset($files[$k]); }

    $manifest = [];
    foreach ($files as $name => $data) $manifest[$name] = sha1($data);
    $manifest_json = wp_json_encode($manifest);

    $dir = trailingslashit(get_temp_dir()) . 'dry65b2b_' . wp_generate_password(10, false, false);
    if (!wp_mkdir_p($dir)) return '';
    $manifest_path = $dir . '/manifest.json';
    file_put_contents($manifest_path, $manifest_json);

    $sig_smime = $dir . '/signature.smime';
    $ok = openssl_pkcs7_sign(
        $manifest_path, $sig_smime,
        'file://' . DRY65_AW_CERT, ['file://' . DRY65_AW_KEY, ''],
        [], PKCS7_BINARY | PKCS7_DETACHED, DRY65_AW_WWDR
    );
    if (!$ok) { dry65_wallet_apple_rmdir($dir); return ''; }
    $smime = file_get_contents($sig_smime);
    $der = '';
    if (preg_match("/\r?\n\r?\n([A-Za-z0-9+\/=\r\n]+)\r?\n\r?\n------/s", $smime, $m)) {
        $der = base64_decode(preg_replace('/\s+/', '', $m[1]));
    }
    if ($der === '') { dry65_wallet_apple_rmdir($dir); return ''; }

    $pkpass = $dir . '/dry65-b2b.pkpass';
    $zip = new ZipArchive();
    if ($zip->open($pkpass, ZipArchive::CREATE) !== true) { dry65_wallet_apple_rmdir($dir); return ''; }
    foreach ($files as $name => $data) $zip->addFromString($name, $data);
    $zip->addFromString('manifest.json', $manifest_json);
    $zip->addFromString('signature', $der);
    $zip->close();
    return $pkpass;
}

function dry65_b2b_wallet_apple_serve($employee, $co) {
    $path = dry65_b2b_wallet_apple_build($employee, $co);
    if (!$path || !is_file($path)) { status_header(500); exit; }
    nocache_headers();
    header('Content-Type: application/vnd.apple.pkpass');
    header('Content-Disposition: attachment; filename="dry65-b2b-' . $employee->code . '.pkpass"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    dry65_wallet_apple_rmdir(dirname($path));
    exit;
}
function dry65_b2b_wallet_apple_button($employee) {
    if (!function_exists('dry65_wallet_apple_ready') || !dry65_wallet_apple_ready()) return '';
    $url = home_url('/wallet/apple/b2b/' . $employee->code . '/');
    return '<a href="' . esc_url($url) . '" style="display:inline-flex;align-items:center;gap:8px;background:#000;color:#fff;'
         . 'text-decoration:none;border-radius:999px;padding:11px 20px;font-size:14px;font-weight:600;line-height:1;">'
         . '<svg width="16" height="16" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M17 2H7a3 3 0 0 0-3 3v14a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V5a3 3 0 0 0-3-3zM9 6h6v2H9z"/></svg>'
         . 'Dodaj u Apple Wallet</a>';
}

/* Ruta za inicijalno skidanje .pkpass-a: /wallet/apple/b2b/{kod} */
add_filter('query_vars', function ($v) { $v[] = 'dry65_aw_b2b'; return $v; });
add_action('init', function () {
    add_rewrite_rule('^wallet/apple/b2b/([^/]+)/?$', 'index.php?dry65_aw_b2b=$matches[1]', 'top');
}, 9);
add_action('template_redirect', function () {
    $code = get_query_var('dry65_aw_b2b');
    if (!$code) return;
    if (!function_exists('dry65_wallet_apple_ready') || !dry65_wallet_apple_ready()) { status_header(404); exit; }
    $employee = dry65_b2b_employee_by_code($code);
    if (!$employee) { status_header(404); exit; }
    $co = dry65_b2b_get($employee->company_id);
    if (!$co) { status_header(404); exit; }
    dry65_b2b_wallet_apple_serve($employee, $co);
});

/* Apple "povuci najnoviju karticu" (web servis, isti Pass Type za sve) — kači se na
   filter koji wallet-apple.php poziva za serijske koje NE prepozna kao lični nalog. */
add_filter('dry65_aw_pkpass_path_for_serial', function ($path, $serial) {
    if ($path) return $path;
    if (!preg_match('/^be(\d+)$/i', (string) $serial, $m)) return $path;
    $employee = dry65_b2b_employee_get((int) $m[1]);
    if (!$employee) return $path;
    $co = dry65_b2b_get($employee->company_id);
    if (!$co) return $path;
    return dry65_b2b_wallet_apple_build($employee, $co);
}, 10, 2);

/* Kad se zajednički fond promeni, osveži SVE kartice te firme (Google PATCH + Apple APNs). */
add_action('dry65_b2b_balance_changed', function ($company_id) {
    foreach (dry65_b2b_employees((int) $company_id) as $employee) {
        dry65_b2b_wallet_sync_queue($employee->id);
    }
}, 10, 1);

function dry65_b2b_wallet_sync_queue($employee_id = null) {
    static $ids = [];
    if ($employee_id !== null) $ids[(int) $employee_id] = true;
    return array_keys($ids);
}

add_action('shutdown', function () {
    $ids = dry65_b2b_wallet_sync_queue();
    if (!$ids) return;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); // radnica ne čeka Google/Apple
    foreach ($ids as $id) {
        $employee = dry65_b2b_employee_get($id);
        if (!$employee) continue;
        $co = dry65_b2b_get($employee->company_id);
        if (!$co) continue;
        if (function_exists('dry65_wallet_google_ready') && dry65_wallet_google_ready()) {
            dry65_b2b_wallet_google_patch($employee, $co);
        }
        if (function_exists('dry65_wallet_apple_ready') && dry65_wallet_apple_ready()) {
            dry65_wallet_apple_apns_queue(dry65_b2b_wallet_apple_serial($employee));
        }
    }
}, 50); // pre wallet-apple.php shutdown (98) da APNs red stigne na vreme

/* ============================================================
   FRONTEND — /b2b-kartica/{kod}  (zaposleni: wallet dugmići i stanje;
   osoblje ulogovano: PIN-kapija + „Skini 1 feniranje", BLOKIRA na 0)
   ============================================================ */
add_filter('query_vars', function ($v) { $v[] = 'dry65_b2b_kartica'; $v[] = 'dry65_b2b_dash'; return $v; });
add_action('init', function () {
    // Slug firme (prvi segment) je samo kozmetički — kod (drugi segment) je stvarni kljuc.
    add_rewrite_rule('^b2b/([^/]+)/([^/]+)/?$', 'index.php?dry65_b2b_kartica=$matches[2]', 'top');
    add_rewrite_rule('^b2b/?$', 'index.php?dry65_b2b_dash=1', 'top');
    if (get_option('dry65_b2b_rewrite_v') !== '2') {
        flush_rewrite_rules(false);
        update_option('dry65_b2b_rewrite_v', '2');
    }
});

add_action('admin_post_dry65_b2b_spend', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_spend');
    $code     = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));
    $employee = dry65_b2b_employee_by_code($code);
    $return   = isset($_POST['return']) ? wp_unslash($_POST['return']) : '';
    $worker   = dry65_pk_staff_verify($_POST['pin'] ?? '');
    if ($worker === '' && dry65_pk_staff_all()) {
        wp_safe_redirect(add_query_arg('pinreq', '1', $return)); exit;
    }
    if ($employee) {
        $res = dry65_b2b_spend($employee, $worker, 'kartica');
        if ($return !== '') {
            wp_safe_redirect(add_query_arg($res['ok'] ? 'sk' : 'full', '1', $return)); exit;
        }
    }
    if ($return !== '') { wp_safe_redirect($return); exit; }
    wp_redirect(admin_url('admin.php?page=dry65-b2b'));
    exit;
});

add_action('template_redirect', function () {
    $code = get_query_var('dry65_b2b_kartica');
    if (!$code) return;
    $employee  = dry65_b2b_employee_by_code($code);
    $co        = $employee ? dry65_b2b_get($employee->company_id) : null;
    status_header(($employee && $co) ? 200 : 404);
    nocache_headers();
    do_action('litespeed_control_set_nocache', 'dry65 b2b kartica');
    add_filter('wp_robots', 'wp_robots_no_robots');
    add_filter('show_admin_bar', '__return_false');
    dry65_pk_bare_head();
    $card_url  = ($employee && $co) ? dry65_b2b_card_url($employee->code, $co->name) : '';
    $can_staff = current_user_can(DRY65_PK_CAP);
    $has_staff = count(dry65_pk_staff_all()) > 0;
    $pin_nonce = $can_staff ? wp_create_nonce('dry65_pk_scan') : '';
    $blocked   = $co && (int) $co->balance <= 0;
    $hero_url  = $co ? dry65_b2b_ensure_hero($co) : '';
    ?>
    <main class="page-enter" style="min-height:100vh;padding:26px 16px calc(40px + env(safe-area-inset-bottom));">
      <div style="text-align:center;margin-bottom:20px;">
        <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/logo.svg'); ?>" alt="Dry65" style="display:block;height:30px;width:auto;margin:0 auto;">
      </div>
      <section>
        <div class="wrap" style="max-width:560px;margin:0 auto;">
          <?php if (!$employee || !$co): ?>
            <h1 class="display caps" style="font-size:clamp(28px,4vw,44px);">Kartica nije pronađena</h1>
            <p class="lead" style="margin-top:16px;">Obrati se salonu.</p>
          <?php else: ?>
            <?php if ($can_staff): ?>
              <?php if (isset($_GET['pinreq'])): ?>
              <p style="max-width:400px;margin:0 auto 10px;text-align:center;color:#a00;font-weight:600;">Unesi PIN radnice pre skidanja.</p>
              <?php endif; ?>
              <div id="pk-card-worker" style="display:none;max-width:400px;margin:0 auto 16px;text-align:center;">
                <div style="font-size:14px;color:var(--ink);margin-bottom:10px;">Radnica: <strong id="pk-card-worker-name"></strong></div>
                <button type="button" id="pk-card-change" style="cursor:pointer;border:0;border-radius:999px;padding:12px 34px;font-size:16px;font-weight:600;background:var(--clay,#b07a5a);color:#fff;">Odjavi se</button>
              </div>
              <div id="pk-card-gate" style="display:none;max-width:340px;margin:0 auto 16px;background:#fff;border:1px solid var(--sage-line,#e5e5e0);border-radius:16px;padding:18px;text-align:center;">
                <div class="mono" style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:var(--clay);">Osoblje — ko radi?</div>
                <p class="muted" style="margin:4px 0 12px;font-size:13px;">Unesi svoj PIN da bi skidao.</p>
                <input id="pk-card-pin-input" type="password" inputmode="numeric" pattern="\d*" maxlength="4" autocomplete="off" style="width:150px;text-align:center;font-size:24px;letter-spacing:0.35em;padding:8px;border:1px solid var(--sage-line,#ccc);border-radius:10px;">
                <p id="pk-card-pin-status" class="muted" style="min-height:16px;margin:8px 0;font-size:13px;"></p>
                <button type="button" id="pk-card-pin-go" style="cursor:pointer;border:0;border-radius:999px;padding:10px 26px;font-size:15px;font-weight:600;background:var(--clay,#b07a5a);color:#fff;">Prijavi se</button>
              </div>
            <?php endif; ?>
            <div style="max-width:400px;margin:6px auto 0;background:#ffffff;color:#111C1D;border:1px solid #eee;border-radius:26px;overflow:hidden;text-align:center;box-shadow:0 18px 50px rgba(0,0,0,0.16);">
              <?php if ($hero_url): ?>
                <img src="<?php echo esc_url($hero_url); ?>" alt="Dry65 &times; <?php echo esc_attr($co->name); ?>" style="display:block;width:100%;height:auto;">
              <?php endif; ?>
              <div style="padding:10px 20px 8px;border-bottom:1px solid #f0f0f0;display:flex;align-items:baseline;justify-content:space-between;gap:10px;font-family:'Hanken Grotesk',system-ui,-apple-system,sans-serif;font-weight:600;font-size:14px;line-height:1;">
                <span><?php echo esc_html($employee->name); ?></span>
                <span><?php echo (int) $co->balance; ?> / <?php echo (int) $co->initial; ?></span>
              </div>
              <div style="padding:clamp(20px,5vw,28px) clamp(20px,5vw,30px) clamp(26px,6vw,38px);">
              <div style="width:clamp(150px,44vw,178px);aspect-ratio:1;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:20px;padding:14px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;">
                <?php echo dry65_pk_qr_html($card_url, 5); ?>
              </div>

              <?php if ($can_staff): ?>
                <?php if ($blocked): ?>
                  <p style="margin:16px 0 0;color:#a00;font-weight:600;">Nema preostalih feniranja za <?php echo esc_html($co->name); ?> ovog meseca.</p>
                <?php else: ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:18px;">
                  <input type="hidden" name="action" value="dry65_b2b_spend">
                  <input type="hidden" name="code" value="<?php echo esc_attr($employee->code); ?>">
                  <input type="hidden" name="return" value="<?php echo esc_url($card_url); ?>">
                  <input type="hidden" name="pin" class="pk-card-pin" value="">
                  <?php wp_nonce_field('dry65_b2b_spend'); ?>
                  <button type="submit" style="cursor:pointer;border:0;border-radius:999px;padding:14px 32px;font-size:16px;font-weight:600;color:#fff;background:#111C1D;">Skini 1 feniranje</button>
                </form>
                <?php endif; ?>
              <?php endif; ?>

              <?php if (isset($_GET['sk'])): ?><p style="margin:16px 0 0;font-weight:600;">Skinuto. Novo stanje je gore.</p><?php endif; ?>
              <?php if (isset($_GET['full'])): ?><p style="margin:16px 0 0;color:#a00;font-weight:600;">Fond je potrošen ovog meseca.</p><?php endif; ?>

              <p style="margin:16px 0 0;font-size:12.5px;color:#6b625c;">Obnova fonda: <?php echo esc_html(date_i18n('d.m.Y.', strtotime($co->cycle_end))); ?></p>
              </div>
            </div>

            <?php if (!$can_staff):
              $gw = function_exists('dry65_b2b_wallet_google_button') ? dry65_b2b_wallet_google_button($employee, $co) : '';
              $aw = function_exists('dry65_b2b_wallet_apple_button')  ? dry65_b2b_wallet_apple_button($employee)      : '';
            ?>
            <?php if ($gw || $aw): ?>
            <div style="display:flex;flex-direction:column;align-items:center;gap:10px;margin:16px 0 4px;">
              <?php if ($gw) echo $gw; ?>
              <?php if ($aw) echo $aw; ?>
            </div>
            <?php endif; ?>
            <p class="muted" style="text-align:center;margin:14px 0 0;font-size:13px;">Pokaži ovu karticu osoblju u salonu.</p>
            <?php endif; ?>

            <p class="muted" style="text-align:center;margin-top:22px;font-size:14px;">Dry65, West 65, Novi Beograd.</p>
          <?php endif; ?>
        </div>
      </section>
    </main>
    <?php if ($employee && $co && $can_staff): ?>
    <script>
    (function(){
      var HAS_STAFF=<?php echo $has_staff ? 'true' : 'false'; ?>;
      var AJAX=<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, NONCE=<?php echo wp_json_encode($pin_nonce); ?>;
      var gate=document.getElementById('pk-card-gate'), bar=document.getElementById('pk-card-worker'),
          barName=document.getElementById('pk-card-worker-name'),
          pinInput=document.getElementById('pk-card-pin-input'), pinGo=document.getElementById('pk-card-pin-go'),
          pinStatus=document.getElementById('pk-card-pin-status'), changeBtn=document.getElementById('pk-card-change');
      var pins=document.querySelectorAll('input.pk-card-pin');
      var WORKER_PIN=localStorage.getItem('dry65_pk_worker_pin')||'', WORKER_NAME=localStorage.getItem('dry65_pk_worker_name')||'';
      function fill(){ for(var i=0;i<pins.length;i++) pins[i].value=WORKER_PIN; }
      function showWorker(){ fill(); if(bar){ bar.style.display=''; barName.textContent=WORKER_NAME; } if(gate) gate.style.display='none'; }
      function showGate(){ if(gate) gate.style.display=''; if(bar) bar.style.display='none'; setTimeout(function(){ try{ pinInput.focus(); }catch(e){} },60); }
      function submit(){
        var p=(pinInput.value||'').replace(/\D/g,''); if(p.length<4){ pinStatus.textContent='Unesi 4 cifre.'; return; }
        pinGo.disabled=true; pinStatus.textContent='Proveravam…';
        var done=false;
        function proceed(nm){ if(done) return; done=true; pinGo.disabled=false; WORKER_PIN=p; WORKER_NAME=nm||'Radnica'; localStorage.setItem('dry65_pk_worker_pin',p); localStorage.setItem('dry65_pk_worker_name',WORKER_NAME); pinStatus.textContent=''; showWorker(); }
        var to=setTimeout(function(){ proceed(''); }, 5000);
        var fd=new FormData(); fd.append('action','dry65_pk_pin'); fd.append('nonce',NONCE); fd.append('pin',p);
        fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
          clearTimeout(to);
          if(j&&j.success){ proceed(j.data.name); }
          else if(j&&j.data&&j.data.msg && /pogre|PIN/i.test(j.data.msg)){ done=true; pinGo.disabled=false; pinStatus.textContent=j.data.msg; pinInput.value=''; }
          else { proceed(''); }
        }).catch(function(){ clearTimeout(to); proceed(''); });
      }
      if(pinGo){ pinGo.addEventListener('click',submit); pinInput.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); submit(); } }); }
      if(changeBtn){ changeBtn.addEventListener('click',function(){ WORKER_PIN=''; WORKER_NAME=''; localStorage.removeItem('dry65_pk_worker_pin'); localStorage.removeItem('dry65_pk_worker_name'); fill(); showGate(); }); }
      if(HAS_STAFF && !WORKER_PIN){ showGate(); } else { showWorker(); }
    })();
    </script>
    <?php endif; ?>
    <?php
    dry65_pk_bare_foot();
    exit;
});

/* ============================================================
   /b2b — login firme (odvojen od gostovskog /login) + dashboard
   ============================================================ */
function dry65_b2b_user_company_id($uid) {
    $v = get_user_meta((int) $uid, 'dry65_b2b_company_id', true);
    return $v ? (int) $v : 0;
}

/* Napravi/osveži pristup: WP nalog vezan za firmu preko user meta + branded reset-lozinke mejl
   (reuse iste /reset rute koja već postoji za gostove — generička WP provera, radi za bilo kog korisnika). */
function dry65_b2b_grant_access($co, $email) {
    global $wpdb;
    if (!is_email($email)) return false;
    $uid = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='dry65_b2b_company_id' AND meta_value=%s", (string) $co->id));
    $user = $uid ? get_user_by('id', (int) $uid) : get_user_by('email', $email);
    if ($user && (int) $uid === (int) $user->ID) {
        if ($user->user_email !== $email) wp_update_user(['ID' => $user->ID, 'user_email' => $email]);
    } elseif ($user) {
        if (dry65_b2b_user_company_id($user->ID)) return false; // email već vezan za drugu firmu
        update_user_meta($user->ID, 'dry65_b2b_company_id', $co->id);
    } else {
        $new_id = wp_insert_user([
            'user_login' => $email, 'user_email' => $email,
            'user_pass'  => wp_generate_password(32), 'role' => 'subscriber',
        ]);
        if (is_wp_error($new_id)) return false;
        $user = get_user_by('id', $new_id);
        update_user_meta($user->ID, 'dry65_b2b_company_id', $co->id);
    }
    $key = get_password_reset_key($user);
    if (is_wp_error($key)) return false;
    $link = home_url('/reset/?key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login));
    $from = 'office@dry65.com';
    $set_from = function () use ($from) { return $from; };
    $set_name = function () { return 'Dry65'; };
    add_filter('wp_mail_from', $set_from);
    add_filter('wp_mail_from_name', $set_name);
    wp_mail($email, 'Dry65 B2B — pristup panelu', implode("\n", [
        'Poštovani,', '',
        'Napravljen je pristup Dry65 B2B panelu za firmu "' . $co->name . '".',
        'Klikni na link da postaviš lozinku i uloguješ se:', $link, '',
        'Panel: ' . home_url('/b2b/'), '',
        'Dry65, West 65, Novi Beograd.',
    ]));
    remove_filter('wp_mail_from', $set_from);
    remove_filter('wp_mail_from_name', $set_name);
    return true;
}

add_action('template_redirect', function () {
    if (!get_query_var('dry65_b2b_dash')) return;
    nocache_headers();
    add_filter('wp_robots', 'wp_robots_no_robots');
    add_filter('show_admin_bar', '__return_false');
    $logo = get_template_directory_uri() . '/assets/logo.svg';

    if (isset($_GET['logout']) && is_user_logged_in()) {
        wp_logout();
        wp_safe_redirect(home_url('/b2b/')); exit;
    }

    $err = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['dry65_b2b_nonce']) && wp_verify_nonce($_POST['dry65_b2b_nonce'], 'dry65_b2b_login')) {
        $user = wp_signon([
            'user_login'    => sanitize_text_field(wp_unslash($_POST['log'] ?? '')),
            'user_password' => (string) ($_POST['pwd'] ?? ''),
            'remember'      => true,
        ], is_ssl());
        if (is_wp_error($user)) {
            $err = 'Pogrešan email ili lozinka.';
        } elseif (!dry65_b2b_user_company_id($user->ID)) {
            wp_logout();
            $err = 'Ovaj nalog nema pristup B2B panelu.';
        } else {
            wp_safe_redirect(home_url('/b2b/')); exit;
        }
    }

    $company_id = is_user_logged_in() ? dry65_b2b_user_company_id(get_current_user_id()) : 0;
    $co = $company_id ? dry65_b2b_get($company_id) : null;

    status_header(200);
    dry65_pk_bare_head();
    ?>
    <main class="page-enter" style="min-height:100vh;padding:40px 16px;">
      <?php if (!$co): ?>
        <div style="max-width:400px;margin:0 auto;text-align:center;">
          <img src="<?php echo esc_url($logo); ?>" alt="Dry65" style="height:32px;width:auto;margin:0 auto 26px;display:block;">
          <h1 class="display caps" style="font-size:clamp(26px,4vw,38px);margin:2px 0 22px;">B2B Panel</h1>
          <?php if ($err): ?><p style="color:#a00;margin:0 0 12px;"><?php echo esc_html($err); ?></p><?php endif; ?>
          <?php if (is_user_logged_in()): ?>
            <p class="muted">Ovaj nalog nema pristup B2B panelu.</p>
            <p style="margin-top:10px;"><a href="<?php echo esc_url(home_url('/b2b/?logout=1')); ?>">Odjavi se</a></p>
          <?php else: ?>
          <form method="post" style="display:grid;gap:12px;text-align:left;">
            <label style="font-size:14px;">Email
              <input type="text" name="log" required autocomplete="username" style="width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid var(--sage-line,#ccc);border-radius:12px;font-size:16px;">
            </label>
            <label style="font-size:14px;">Lozinka
              <input type="password" name="pwd" required autocomplete="current-password" style="width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid var(--sage-line,#ccc);border-radius:12px;font-size:16px;">
            </label>
            <?php wp_nonce_field('dry65_b2b_login', 'dry65_b2b_nonce'); ?>
            <button type="submit" style="cursor:pointer;border:0;border-radius:999px;padding:13px 30px;font-size:16px;font-weight:600;background:var(--clay,#b07a5a);color:#fff;">Prijavi se</button>
          </form>
          <p style="margin-top:8px;font-size:13px;"><a href="<?php echo esc_url(home_url('/zaboravljena-lozinka/')); ?>">Zaboravljena lozinka?</a></p>
          <?php endif; ?>
        </div>
      <?php else:
        $txns = dry65_b2b_txns($company_id, 150);
        $used = (int) $co->initial - (int) $co->balance;
        $per_emp = [];
        foreach ($txns as $t) {
            if ((int) $t->employee_id <= 0 || (int) $t->reversed === 1) continue;
            if ($t->created_at < $co->cycle_start) continue;
            $key = (int) $t->employee_id;
            if (!isset($per_emp[$key])) $per_emp[$key] = ['name' => $t->employee_name, 'count' => 0];
            if ((int) $t->delta < 0) $per_emp[$key]['count']++;
        }
        uasort($per_emp, function ($a, $b) { return $b['count'] - $a['count']; });
      ?>
      <div class="wrap" style="max-width:720px;margin:0 auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
          <div>
            <img src="<?php echo esc_url($logo); ?>" alt="Dry65" style="height:26px;width:auto;display:block;margin-bottom:8px;">
            <h1 class="display caps" style="font-size:clamp(22px,3.4vw,30px);margin:0;"><?php echo esc_html($co->name); ?></h1>
          </div>
          <a href="<?php echo esc_url(home_url('/b2b/?logout=1')); ?>" style="font-size:13px;">Odjavi se</a>
        </div>

        <div style="background:#33302c;color:#efe1d2;border-radius:20px;padding:22px 24px;margin-bottom:22px;">
          <div class="mono" style="letter-spacing:0.14em;text-transform:uppercase;font-size:11.5px;color:#c8beb4;">Ovaj mesec (<?php echo esc_html(date_i18n('d.m.', strtotime($co->cycle_start))); ?>–<?php echo esc_html(date_i18n('d.m.Y.', strtotime($co->cycle_end))); ?>)</div>
          <div style="font-family:'Cormorant Garamond',Cormorant,Georgia,serif;font-size:clamp(30px,5vw,40px);margin-top:4px;"><?php echo $used; ?> / <?php echo (int) $co->initial; ?> feniranja</div>
          <div style="font-size:13px;color:#c8beb4;margin-top:4px;">Preostalo: <?php echo (int) $co->balance; ?> · Obnova <?php echo esc_html(date_i18n('d.m.Y.', strtotime($co->cycle_end))); ?></div>
        </div>

        <h2 style="font-size:16px;text-transform:uppercase;letter-spacing:0.08em;">Po zaposlenom (ovaj mesec)</h2>
        <table style="width:100%;border-collapse:collapse;margin:10px 0 24px;">
          <?php if (!$per_emp): ?>
            <tr><td style="padding:8px 0;color:var(--muted);">Još nema očitavanja ovog meseca.</td></tr>
          <?php else: foreach ($per_emp as $row): ?>
            <tr style="border-bottom:1px solid var(--sage-line,#eee);">
              <td style="padding:8px 0;"><?php echo esc_html($row['name']); ?></td>
              <td style="padding:8px 0;text-align:right;font-weight:600;"><?php echo (int) $row['count']; ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </table>

        <h2 style="font-size:16px;text-transform:uppercase;letter-spacing:0.08em;">Istorija</h2>
        <table style="width:100%;border-collapse:collapse;margin:10px 0;font-size:13.5px;">
          <?php if (!$txns): ?>
            <tr><td style="padding:8px 0;color:var(--muted);">Nema istorije.</td></tr>
          <?php else: foreach ($txns as $t): if ((int) $t->reversed === 1) continue; ?>
            <tr style="border-bottom:1px solid var(--sage-line,#eee);">
              <td style="padding:7px 0;white-space:nowrap;color:var(--muted);"><?php echo esc_html(mysql2date('d.m.Y. H:i', $t->created_at)); ?></td>
              <td style="padding:7px 10px;"><?php echo esc_html($t->employee_name ?: '—'); ?></td>
              <td style="padding:7px 0;"><?php echo esc_html($t->note); ?></td>
              <td style="padding:7px 0;text-align:right;font-weight:600;"><?php echo (int) $t->delta; ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </table>

        <p class="muted" style="text-align:center;margin-top:30px;font-size:13px;">Dry65, West 65, Novi Beograd.</p>
      </div>
      <?php endif; ?>
    </main>
    <?php
    dry65_pk_bare_foot();
    exit;
});

/* ============================================================
   WP-ADMIN — „B2B Firme"
   ============================================================ */
add_action('admin_menu', function () {
    add_menu_page('B2B Firme', 'B2B Firme', DRY65_PK_CAP, 'dry65-b2b', 'dry65_b2b_admin_page', 'dashicons-building', 5);
});

function dry65_b2b_admin_page() {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    $id = isset($_GET['company']) ? (int) $_GET['company'] : 0;
    if ($id) { dry65_b2b_admin_detail($id); return; }
    $rows = dry65_b2b_all();
    ?>
    <div class="wrap">
      <h1>B2B Firme</h1>
      <?php if (isset($_GET['created'])): ?><div class="notice notice-success is-dismissible"><p>Firma napravljena.</p></div><?php endif; ?>
      <?php if (isset($_GET['err'])): ?><div class="notice notice-error is-dismissible"><p>Naziv firme je obavezan.</p></div><?php endif; ?>

      <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:flex-start;margin-top:12px;">
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:340px;">
          <h2 style="margin-top:0;">Nova firma</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="dry65_b2b_create">
            <?php wp_nonce_field('dry65_b2b_create'); ?>
            <p><label>Naziv firme<br><input type="text" name="name" class="regular-text" required style="width:100%;"></label></p>
            <p><label>Kontakt email<br><input type="email" name="contact_email" class="regular-text" style="width:100%;"></label></p>
            <p><label>Mesečni fond (feniranja)<br>
              <input type="number" name="initial" min="1" step="1" value="30" list="dry65-b2b-plans" style="width:100%;">
              <datalist id="dry65-b2b-plans"><option value="30"><option value="60"><option value="90"></datalist>
            </label></p>
            <p><label>Cena mesečno (din)<br><input type="number" name="price_rsd" min="0" step="1" style="width:100%;"></label></p>
            <p><label>Napomena<br><input type="text" name="note" style="width:100%;"></label></p>
            <p><button type="submit" class="button button-primary">Napravi firmu</button></p>
          </form>
        </div>
        <div style="flex:1;min-width:360px;">
          <table class="widefat striped">
            <thead><tr><th>Firma</th><th>Fond</th><th>Status</th><th>Obnova</th><th></th></tr></thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="5"><em>Još nema firmi.</em></td></tr>
              <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><strong><?php echo esc_html($r->name); ?></strong></td>
                <td><?php echo (int) $r->balance; ?> / <?php echo (int) $r->initial; ?></td>
                <td><?php echo $r->status === 'active' ? 'Aktivna' : 'Pauzirana'; ?></td>
                <td><?php echo esc_html(date_i18n('d.m.Y.', strtotime($r->cycle_end))); ?></td>
                <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=dry65-b2b&company=' . $r->id)); ?>">Otvori</a></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
}

function dry65_b2b_admin_detail($id) {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    $co = dry65_b2b_get($id);
    if (!$co) { echo '<div class="wrap"><p>Firma nije pronađena.</p></div>'; return; }
    $employees = dry65_b2b_employees($id);
    $txns      = dry65_b2b_txns($id, 300);
    $is_admin  = current_user_can(DRY65_PK_ADMIN_CAP);
    global $wpdb;
    $uid = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='dry65_b2b_company_id' AND meta_value=%s", (string) $id));
    $wp_user = $uid ? get_user_by('id', (int) $uid) : null;
    ?>
    <div class="wrap">
      <p><a href="<?php echo esc_url(admin_url('admin.php?page=dry65-b2b')); ?>">← Sve firme</a></p>
      <h1><?php echo esc_html($co->name); ?></h1>
      <?php if (isset($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>Sačuvano.</p></div><?php endif; ?>
      <?php if (isset($_GET['emp_added'])): ?><div class="notice notice-success is-dismissible"><p>Zaposleni dodat.</p></div><?php endif; ?>
      <?php if (isset($_GET['access_sent'])): ?><div class="notice notice-success is-dismissible"><p>Pristup kreiran/osvežen — mejl za lozinku poslat.</p></div><?php endif; ?>
      <?php if (isset($_GET['access_err'])): ?><div class="notice notice-error is-dismissible"><p>Taj email je već vezan za drugu firmu.</p></div><?php endif; ?>
      <?php if (isset($_GET['logo_saved'])): ?><div class="notice notice-success is-dismissible"><p>Logo sačuvan.</p></div><?php endif; ?>
      <?php if (isset($_GET['logo_err'])): ?><div class="notice notice-error is-dismissible"><p>Logo nije sačuvan — probaj drugi fajl.</p></div><?php endif; ?>

      <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:flex-start;">
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:340px;">
          <h2 style="margin-top:0;">Podaci</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="dry65_b2b_update">
            <input type="hidden" name="id" value="<?php echo (int) $co->id; ?>">
            <?php wp_nonce_field('dry65_b2b_update'); ?>
            <p><label>Naziv<br><input type="text" name="name" value="<?php echo esc_attr($co->name); ?>" class="regular-text" required style="width:100%;"></label></p>
            <p><label>Kontakt email<br><input type="email" name="contact_email" value="<?php echo esc_attr($co->contact_email); ?>" style="width:100%;"></label></p>
            <p><label>Mesečni fond<br><input type="number" name="initial" min="1" value="<?php echo (int) $co->initial; ?>" style="width:100%;"></label></p>
            <p><label>Cena mesečno (din)<br><input type="number" name="price_rsd" min="0" value="<?php echo (int) $co->price_rsd; ?>" style="width:100%;"></label></p>
            <p><label>Status<br>
              <select name="status" style="width:100%;">
                <option value="active" <?php selected($co->status, 'active'); ?>>Aktivna</option>
                <option value="paused" <?php selected($co->status, 'paused'); ?>>Pauzirana</option>
              </select>
            </label></p>
            <p><label>Napomena<br><input type="text" name="note" value="<?php echo esc_attr($co->note); ?>" style="width:100%;"></label></p>
            <p><button type="submit" class="button button-primary">Sačuvaj</button></p>
          </form>
          <hr>
          <p><strong>Ciklus:</strong> <?php echo esc_html(date_i18n('d.m.Y.', strtotime($co->cycle_start))); ?> – <?php echo esc_html(date_i18n('d.m.Y.', strtotime($co->cycle_end))); ?></p>
          <p><strong>Preostalo:</strong> <?php echo (int) $co->balance; ?> / <?php echo (int) $co->initial; ?></p>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Resetuj mesec sada (fond se vraća na <?php echo (int) $co->initial; ?>)?');">
            <input type="hidden" name="action" value="dry65_b2b_reset_now">
            <input type="hidden" name="id" value="<?php echo (int) $co->id; ?>">
            <?php wp_nonce_field('dry65_b2b_reset_now'); ?>
            <button type="submit" class="button">Resetuj mesec sada</button>
          </form>

          <hr>
          <h2>Logo za Wallet karticu</h2>
          <?php $logo_url = dry65_b2b_logo_url($co); ?>
          <?php if ($logo_url): ?>
            <p><img src="<?php echo esc_url($logo_url); ?>" alt="" style="max-width:100%;border:1px solid #dcdcde;border-radius:6px;background:#fff;padding:6px;"></p>
          <?php else: ?>
            <p class="description">Nema otpremljen logo — traka za sad prikazuje samo dry65.</p>
          <?php endif; ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="dry65_b2b_logo">
            <input type="hidden" name="id" value="<?php echo (int) $co->id; ?>">
            <?php wp_nonce_field('dry65_b2b_logo'); ?>
            <p><input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required></p>
            <p class="description">Bilo koji razmer — ide DESNO na traci (dry65 je uvek levo), sistem sam uklapa na belo.</p>
            <p><button type="submit" class="button"><?php echo $logo_url ? 'Zameni logo' : 'Otpremi logo'; ?></button></p>
          </form>

          <hr>
          <h2>Pristup dashboardu (/b2b)</h2>
          <?php if ($wp_user): ?>
            <p>Login: <strong><?php echo esc_html($wp_user->user_email); ?></strong></p>
          <?php else: ?>
            <p class="description">Firma još nema pristup.</p>
          <?php endif; ?>
          <?php if ($is_admin): ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="dry65_b2b_access">
            <input type="hidden" name="id" value="<?php echo (int) $co->id; ?>">
            <?php wp_nonce_field('dry65_b2b_access'); ?>
            <p><label>Email za login<br><input type="email" name="email" value="<?php echo esc_attr($wp_user ? $wp_user->user_email : $co->contact_email); ?>" required style="width:100%;"></label></p>
            <p><button type="submit" class="button button-primary"><?php echo $wp_user ? 'Resetuj lozinku' : 'Napravi pristup'; ?></button></p>
          </form>
          <?php else: ?>
          <p class="description">Samo administrator može da pravi pristup dashboardu.</p>
          <?php endif; ?>
        </div>

        <div style="min-width:360px;flex:1;">
          <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;margin-bottom:20px;">
            <h2 style="margin-top:0;">Zaposleni</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:14px;display:flex;gap:8px;">
              <input type="hidden" name="action" value="dry65_b2b_employee_add">
              <input type="hidden" name="company_id" value="<?php echo (int) $co->id; ?>">
              <?php wp_nonce_field('dry65_b2b_employee_add'); ?>
              <input type="text" name="name" placeholder="Ime zaposlenog" required class="regular-text">
              <button type="submit" class="button">Dodaj</button>
            </form>
            <table class="widefat striped">
              <thead><tr><th>Ime</th><th>Link za Wallet karticu</th><th></th></tr></thead>
              <tbody>
                <?php if (!$employees): ?>
                  <tr><td colspan="3"><em>Još nema zaposlenih.</em></td></tr>
                <?php else: foreach ($employees as $e): ?>
                <tr>
                  <td><?php echo esc_html($e->name); ?></td>
                  <td><input type="text" readonly value="<?php echo esc_url(dry65_b2b_card_url($e->code, $co->name)); ?>" style="width:100%;" onclick="this.select();"></td>
                  <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('Obriši zaposlenog <?php echo esc_js($e->name); ?>?');">
                      <input type="hidden" name="action" value="dry65_b2b_employee_delete">
                      <input type="hidden" name="id" value="<?php echo (int) $e->id; ?>">
                      <input type="hidden" name="company_id" value="<?php echo (int) $co->id; ?>">
                      <?php wp_nonce_field('dry65_b2b_employee_delete'); ?>
                      <button type="submit" class="button button-small">Obriši</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
            <p class="description">Link se šalje zaposlenom (mejl/WhatsApp) — na njemu dodaje karticu u Apple/Google Wallet. Nema fizičke kartice.</p>
          </div>

          <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;">
            <h2 style="margin-top:0;">Istorija</h2>
            <table class="widefat striped">
              <thead><tr><th>Kad</th><th>Zaposleni</th><th>Napomena</th><th>Delta</th><th>Radnica</th><th></th></tr></thead>
              <tbody>
                <?php if (!$txns): ?>
                  <tr><td colspan="6"><em>Nema istorije.</em></td></tr>
                <?php else: foreach ($txns as $t): ?>
                <tr<?php echo (int) $t->reversed === 1 ? ' style="opacity:0.5;"' : ''; ?>>
                  <td><?php echo esc_html(mysql2date('d.m.Y. H:i', $t->created_at)); ?></td>
                  <td><?php echo esc_html($t->employee_name ?: '—'); ?></td>
                  <td><?php echo esc_html($t->note); ?></td>
                  <td><?php echo (int) $t->delta; ?></td>
                  <td><?php echo esc_html($t->staff_name); ?></td>
                  <td>
                    <?php if ($is_admin && (int) $t->delta < 0 && (int) $t->reversed === 0): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('Poništi ovu stavku?');">
                      <input type="hidden" name="action" value="dry65_b2b_undo">
                      <input type="hidden" name="txn" value="<?php echo (int) $t->id; ?>">
                      <input type="hidden" name="company_id" value="<?php echo (int) $co->id; ?>">
                      <?php wp_nonce_field('dry65_b2b_undo'); ?>
                      <button type="submit" class="button button-small">Poništi</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php
}

add_action('admin_post_dry65_b2b_create', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_create');
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    if ($name === '') { wp_redirect(admin_url('admin.php?page=dry65-b2b&err=1')); exit; }
    $id = dry65_b2b_create(
        $name,
        sanitize_email(wp_unslash($_POST['contact_email'] ?? '')),
        (int) ($_POST['initial'] ?? 30),
        (int) ($_POST['price_rsd'] ?? 0),
        sanitize_text_field(wp_unslash($_POST['note'] ?? ''))
    );
    wp_redirect(admin_url('admin.php?page=dry65-b2b' . ($id ? '&created=1' : '&err=1')));
    exit;
});

add_action('admin_post_dry65_b2b_update', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_update');
    $id = (int) ($_POST['id'] ?? 0);
    dry65_b2b_update(
        $id,
        sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
        sanitize_email(wp_unslash($_POST['contact_email'] ?? '')),
        (int) ($_POST['initial'] ?? 0),
        (int) ($_POST['price_rsd'] ?? 0),
        sanitize_key($_POST['status'] ?? 'active'),
        sanitize_text_field(wp_unslash($_POST['note'] ?? ''))
    );
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $id . '&saved=1'));
    exit;
});

add_action('admin_post_dry65_b2b_reset_now', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_reset_now');
    $id = (int) ($_POST['id'] ?? 0);
    $co = dry65_b2b_get($id);
    if ($co) dry65_b2b_cycle_reset($co);
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $id . '&saved=1'));
    exit;
});

add_action('admin_post_dry65_b2b_employee_add', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_employee_add');
    $company_id = (int) ($_POST['company_id'] ?? 0);
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    if ($name !== '') dry65_b2b_employee_create($company_id, $name);
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $company_id . '&emp_added=1'));
    exit;
});

add_action('admin_post_dry65_b2b_employee_delete', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_employee_delete');
    $id = (int) ($_POST['id'] ?? 0);
    $company_id = (int) ($_POST['company_id'] ?? 0);
    dry65_b2b_employee_delete($id);
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $company_id));
    exit;
});

add_action('admin_post_dry65_b2b_undo', function () {
    if (!current_user_can(DRY65_PK_ADMIN_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_undo');
    $txn_id = (int) ($_POST['txn'] ?? 0);
    $company_id = (int) ($_POST['company_id'] ?? 0);
    dry65_b2b_undo($txn_id);
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $company_id));
    exit;
});

add_action('admin_post_dry65_b2b_access', function () {
    if (!current_user_can(DRY65_PK_ADMIN_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_access');
    $id = (int) ($_POST['id'] ?? 0);
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $co = dry65_b2b_get($id);
    $ok = ($co && $email) ? dry65_b2b_grant_access($co, $email) : false;
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $id . ($ok ? '&access_sent=1' : '&access_err=1')));
    exit;
});

add_action('admin_post_dry65_b2b_logo', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_logo');
    $id = (int) ($_POST['id'] ?? 0);
    $co = dry65_b2b_get($id);
    $ok = false;
    if ($co && !empty($_FILES['logo']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $overrides = ['test_form' => false, 'mimes' => ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']];
        $moved = wp_handle_upload($_FILES['logo'], $overrides);
        if (!empty($moved['file'])) {
            global $wpdb;
            $uploads = wp_upload_dir();
            $src_path = $moved['file'];
            // Oseci belu/providnu marginu JEDNOM ovde (ne pri svakom generisanju trake) da logo
            // poravnanje bude tačno, bez obzira koliko margine nosi otpremljena slika.
            $final_path = $src_path;
            $im = dry65_b2b_load_image($src_path);
            if ($im) {
                $im = dry65_b2b_trim_whitespace($im);
                $final_path = preg_replace('/\.[^.]+$/', '', $src_path) . '-trim.png';
                imagepng($im, $final_path);
                imagedestroy($im);
                if ($final_path !== $src_path) @unlink($src_path);
            }
            $rel = ltrim(str_replace($uploads['basedir'], '', $final_path), '/');
            $wpdb->update(dry65_b2b_table(), ['logo_path' => $rel], ['id' => (int) $id], ['%s'], ['%d']);
            $co = dry65_b2b_get($id);
            dry65_b2b_regenerate_hero($co);
            do_action('dry65_b2b_balance_changed', (int) $id); // osvezi sve vec instalirane kartice firme novim logom
            $ok = true;
        }
    }
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $id . ($ok ? '&logo_saved=1' : '&logo_err=1')));
    exit;
});
