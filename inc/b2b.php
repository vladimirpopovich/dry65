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

if (!defined('DRY65_B2B_DB')) define('DRY65_B2B_DB', 4);

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
        model VARCHAR(20) NOT NULL DEFAULT 'mesecni-paket',
        company_amount INT NOT NULL DEFAULT 0,
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
        email VARCHAR(190) NOT NULL DEFAULT '',
        code VARCHAR(20) NOT NULL,
        initial INT NOT NULL DEFAULT 0,
        balance INT NOT NULL DEFAULT 0,
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
        amount INT NOT NULL DEFAULT 0,
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
/* ---- Tip saradnje (isti ključevi kao u online prijavi sa /za-firme/) ----
   mesecni-paket  = zajednički mesečni fond feniranja po fiksnoj ceni; kartica skida 1 feniranje
   sufinansiranje = firma plaća dogovoreni iznos po feniranju (company_amount), zaposleni doplaćuje
                    razliku na kasi; QR samo beleži dolazak, bez limita; faktura = dolasci × iznos
   vauceri        = svaki zaposleni ima svoj vaučer u dinarima (employees.balance); skida se iznos */
function dry65_b2b_types() {
    return [
        'mesecni-paket'  => '1. Mesečni paket (fiksna cena, zajednički fond)',
        'sufinansiranje' => '2. Deo firma, ostatak zaposleni',
        'vauceri'        => '3. Digitalni vaučeri (iznos po zaposlenom)',
    ];
}
function dry65_b2b_type($co) {
    $t = is_object($co) && !empty($co->model) ? $co->model : 'mesecni-paket';
    return isset(dry65_b2b_types()[$t]) ? $t : 'mesecni-paket';
}
/* Srpska množina: 1 dolazak, 2 do 4 dolaska, 5+ dolazaka (11 do 14 su uvek „mnogo"). */
function dry65_b2b_plural($n, $one, $few, $many) {
    $n = abs((int) $n); $d = $n % 10; $dd = $n % 100;
    if ($d === 1 && $dd !== 11) return $n . ' ' . $one;
    if ($d >= 2 && $d <= 4 && ($dd < 12 || $dd > 14)) return $n . ' ' . $few;
    return $n . ' ' . $many;
}
function dry65_b2b_visits($n) { return dry65_b2b_plural($n, 'dolazak', 'dolaska', 'dolazaka'); }
function dry65_b2b_fens($n)   { return dry65_b2b_plural($n, 'feniranje', 'feniranja', 'feniranja'); }
function dry65_b2b_din($n) { return number_format((int) $n, 0, ',', '.') . ' din'; }

/* Obračun za mesec (YYYY-MM): svi dolasci/potrošnje zaposlenih, bez poništenih. */
function dry65_b2b_month_report($co, $ym) {
    global $wpdb;
    if (!preg_match('/^\d{4}-\d{2}$/', (string) $ym)) $ym = substr(dry65_b2b_today(), 0, 7);
    $from = $ym . '-01 00:00:00';
    $to   = (new DateTime($ym . '-01'))->modify('+1 month')->format('Y-m-d') . ' 00:00:00';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . dry65_b2b_txn_table() . " WHERE company_id=%d AND employee_id>0 AND delta<0 AND reversed=0
         AND created_at >= %s AND created_at < %s ORDER BY created_at ASC",
        (int) $co->id, $from, $to
    ));
    $per = [];
    $sum = 0;
    foreach ($rows as $r) {
        $k = (int) $r->employee_id;
        if (!isset($per[$k])) $per[$k] = ['name' => $r->employee_name, 'count' => 0, 'sum' => 0];
        $per[$k]['count']++;
        $per[$k]['sum'] += (int) $r->amount;
        $sum += (int) $r->amount;
    }
    uasort($per, function ($a, $b) { return $b['count'] - $a['count']; });
    return ['ym' => $ym, 'rows' => $rows, 'per' => $per, 'count' => count($rows), 'sum' => $sum];
}
function dry65_b2b_month_label($ym) {
    $m = ['01'=>'januar','02'=>'februar','03'=>'mart','04'=>'april','05'=>'maj','06'=>'jun','07'=>'jul','08'=>'avgust','09'=>'septembar','10'=>'oktobar','11'=>'novembar','12'=>'decembar'];
    return ($m[substr($ym, 5, 2)] ?? '') . ' ' . substr($ym, 0, 4) . '.';
}
function dry65_b2b_month_shift($ym, $n) {
    return (new DateTime($ym . '-01'))->modify(($n >= 0 ? '+' : '') . (int) $n . ' month')->format('Y-m');
}

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
function dry65_b2b_create($name, $contact_email, $initial, $price_rsd, $note = '', $model = 'mesecni-paket', $company_amount = 0) {
    global $wpdb;
    $name = sanitize_text_field($name);
    if ($name === '') return 0;
    $model = isset(dry65_b2b_types()[$model]) ? $model : 'mesecni-paket';
    $is_fond = $model === 'mesecni-paket';
    $initial = $is_fond ? max(1, (int) $initial) : 0;
    $start = dry65_b2b_today();
    $end   = dry65_b2b_cycle_end($start);
    $ok = $wpdb->insert(dry65_b2b_table(), [
        'name'          => $name,
        'contact_email' => sanitize_email($contact_email),
        'price_rsd'     => max(0, (int) $price_rsd),
        'model'         => $model,
        'company_amount'=> max(0, (int) $company_amount),
        'initial'       => $initial,
        'balance'       => $initial,
        'cycle_start'   => $start,
        'cycle_end'     => $end,
        'status'        => 'active',
        'note'          => $note,
        'created_at'    => dry65_b2b_now(),
        'created_by'    => get_current_user_id(),
    ], ['%s','%s','%d','%s','%d','%d','%d','%s','%s','%s','%s','%s','%d']);
    if (!$ok) return 0;
    $id = (int) $wpdb->insert_id;
    if ($is_fond) $wpdb->insert(dry65_b2b_txn_table(), [
        'company_id' => $id, 'employee_id' => 0, 'employee_name' => '',
        'delta' => $initial, 'balance_after' => $initial,
        'note' => 'Fond otvoren', 'staff_id' => get_current_user_id(), 'staff_name' => '',
        'created_at' => dry65_b2b_now(),
    ], ['%d','%d','%s','%d','%d','%s','%d','%s','%s']);
    return $id;
}

function dry65_b2b_update($id, $name, $contact_email, $initial, $price_rsd, $status, $note, $model = null, $company_amount = null) {
    global $wpdb;
    $co = dry65_b2b_get($id);
    if (!$co) return false;
    $old_model = dry65_b2b_type($co);
    $model = ($model !== null && isset(dry65_b2b_types()[$model])) ? $model : $old_model;
    $data = [
        'name'           => sanitize_text_field($name),
        'contact_email'  => sanitize_email($contact_email),
        'price_rsd'      => max(0, (int) $price_rsd),
        'status'         => in_array($status, ['active', 'paused'], true) ? $status : 'active',
        'note'           => $note,
        'model'          => $model,
        'company_amount' => $company_amount !== null ? max(0, (int) $company_amount) : (int) $co->company_amount,
    ];
    $fmt = ['%s','%s','%d','%s','%s','%s','%d'];
    if ($model === 'mesecni-paket') {
        $initial = max(1, (int) $initial);
        if ($old_model !== 'mesecni-paket') {          // prelazak na fond: puni fond od danas
            $data['initial'] = $initial; $data['balance'] = $initial;
            $data['cycle_start'] = dry65_b2b_today(); $data['cycle_end'] = dry65_b2b_cycle_end(dry65_b2b_today());
            $fmt = array_merge($fmt, ['%d','%d','%s','%s']);
        } elseif ($initial !== (int) $co->initial) {
            $diff = $initial - (int) $co->initial;
            $data['initial'] = $initial;
            $data['balance'] = max(0, min($initial, (int) $co->balance + $diff));
            $fmt[] = '%d'; $fmt[] = '%d';
        }
    }
    $wpdb->update(dry65_b2b_table(), $data, ['id' => (int) $id], $fmt, ['%d']);
    do_action('dry65_b2b_balance_changed', (int) $id);
    return true;
}

/* ---- Zaposleni ---- */
function dry65_b2b_employee_create($company_id, $name, $email = '', $voucher = null) {
    global $wpdb;
    $co = dry65_b2b_get($company_id);
    $name = sanitize_text_field($name);
    $email = sanitize_email($email);
    if (!$co || $name === '') return 0;
    // Vaučer model: svaki zaposleni dobija svoj iznos (podrazumevano iznos firme).
    $amt = dry65_b2b_type($co) === 'vauceri' ? max(0, (int) ($voucher !== null && $voucher !== '' ? $voucher : $co->company_amount)) : 0;
    $code = dry65_b2b_gen_code();
    $ok = $wpdb->insert(dry65_b2b_emp_table(), [
        'company_id' => (int) $company_id,
        'name'       => $name,
        'email'      => is_email($email) ? $email : '',
        'code'       => $code,
        'initial'    => $amt,
        'balance'    => $amt,
        'status'     => 'active',
        'created_at' => dry65_b2b_now(),
    ], ['%d','%s','%s','%s','%d','%d','%s','%s']);
    if (!$ok) return 0;
    $emp_id = (int) $wpdb->insert_id;
    if ($amt > 0) {
        $wpdb->insert(dry65_b2b_txn_table(), [
            'company_id' => (int) $company_id, 'employee_id' => $emp_id, 'employee_name' => $name,
            'delta' => $amt, 'balance_after' => $amt, 'amount' => 0,
            'note' => 'Vaučer izdat', 'staff_id' => get_current_user_id(), 'staff_name' => '',
            'created_at' => dry65_b2b_now(),
        ], ['%d','%d','%s','%d','%d','%d','%s','%d','%s','%s']);
    }
    return $emp_id;
}

/* Dopuna vaučera zaposlenom (dodaje iznos na stanje). */
function dry65_b2b_employee_topup($employee, $amount) {
    global $wpdb;
    $amount = (int) $amount;
    if (!$employee || $amount <= 0) return false;
    $new = (int) $employee->balance + $amount;
    $wpdb->update(dry65_b2b_emp_table(), ['balance' => $new, 'initial' => (int) $employee->initial + $amount], ['id' => (int) $employee->id], ['%d','%d'], ['%d']);
    $wpdb->insert(dry65_b2b_txn_table(), [
        'company_id' => (int) $employee->company_id, 'employee_id' => (int) $employee->id, 'employee_name' => $employee->name,
        'delta' => $amount, 'balance_after' => $new, 'amount' => 0,
        'note' => 'Vaučer dopunjen', 'staff_id' => get_current_user_id(), 'staff_name' => '',
        'created_at' => dry65_b2b_now(),
    ], ['%d','%d','%s','%d','%d','%d','%s','%d','%s','%s']);
    dry65_b2b_wallet_sync_queue((int) $employee->id);
    return true;
}

/* Mejl zaposlenom sa linkom ka B2B kartici (na kartici su Apple/Google Wallet dugmad). */
function dry65_b2b_employee_send_email($employee, $co) {
    if (!$employee || !$co || empty($employee->email) || !is_email($employee->email)) return false;
    $url  = dry65_b2b_card_url($employee->code, $co->name);
    $logo = get_template_directory_uri() . '/assets/logo-square.png';
    $btn  = 'display:inline-block;padding:13px 24px;border-radius:999px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;text-decoration:none;background:#b07a5a;color:#ffffff;';
    ob_start(); ?>
<div style="background:#f7f1e7;padding:24px 12px;font-family:Arial,Helvetica,sans-serif;color:#2a201a;">
  <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;padding:28px 26px;">
    <div style="text-align:center;margin-bottom:16px;"><img src="<?php echo esc_url($logo); ?>" width="56" height="56" alt="Dry65" style="border-radius:50%;display:inline-block;"></div>
    <p style="font-size:16px;margin:0 0 6px;">Zdravo <?php echo esc_html($employee->name); ?>,</p>
    <p style="font-size:15px;color:#555555;margin:0 0 18px;line-height:1.5;"><?php echo esc_html($co->name); ?> vam je obezbedio/la feniranje u Dry65 salonu. Vaša kartica je spremna: otvorite je i dodajte u Apple ili Google Wallet.</p>
    <div style="text-align:center;margin:0 0 6px;">
      <a href="<?php echo esc_url($url); ?>" style="<?php echo $btn; ?>">Otvori karticu</a>
    </div>
    <p style="font-size:13px;color:#888888;text-align:center;margin:20px 0 0;line-height:1.5;">Ne treba zakazivanje. Dođite kad vam odgovara i pokažite karticu na kasi.</p>
    <p style="font-size:12px;color:#aaaaaa;text-align:center;margin:16px 0 0;">Dry65, West 65, Novi Beograd</p>
  </div>
</div>
<?php
    $html = ob_get_clean();
    $from = (function_exists('dry65_biz') && !empty(dry65_biz()['email'])) ? dry65_biz()['email'] : 'office@dry65.com';
    $set_from  = function () use ($from) { return $from; };
    $set_name  = function () { return 'Dry65'; };
    $set_ctype = function () { return 'text/html'; };
    add_filter('wp_mail_from', $set_from);
    add_filter('wp_mail_from_name', $set_name);
    add_filter('wp_mail_content_type', $set_ctype);
    $ok = wp_mail($employee->email, 'Vaša Dry65 kartica (' . $co->name . ')', $html, ['Reply-To: Dry65 <' . $from . '>']);
    remove_filter('wp_mail_from', $set_from);
    remove_filter('wp_mail_from_name', $set_name);
    remove_filter('wp_mail_content_type', $set_ctype);
    return $ok;
}
function dry65_b2b_employee_delete($id) {
    global $wpdb;
    $wpdb->delete(dry65_b2b_emp_table(), ['id' => (int) $id], ['%d']);
}

/* ---- Trošenje po tipu saradnje ----
   mesecni-paket:  -1 iz zajedničkog fonda firme (BLOKIRA na 0, nema overage-a)
   sufinansiranje: beleži dolazak (delta -1), amount = iznos firme; bez limita
   vauceri:        skida $amount din sa vaučera zaposlenog (ne ispod 0)            */
function dry65_b2b_spend($employee, $staff_name = '', $source = '', $amount = 0) {
    if (!is_object($employee)) return ['ok' => false, 'msg' => 'Nepoznata kartica.'];
    $co = dry65_b2b_get($employee->company_id);
    if (!$co) return ['ok' => false, 'msg' => 'Nepoznata firma.'];
    if ($co->status !== 'active') return ['ok' => false, 'msg' => 'Firma nije aktivna.'];
    global $wpdb;
    $type = dry65_b2b_type($co);
    $note = 'Feniranje';
    if ($type === 'mesecni-paket') {
        if ((int) $co->balance <= 0) return ['ok' => false, 'msg' => 'Nema preostalih feniranja za ' . $co->name . ' ovog meseca.'];
        $delta = -1; $new = (int) $co->balance - 1; $amt = 0;
        $wpdb->update(dry65_b2b_table(), ['balance' => $new], ['id' => (int) $co->id], ['%d'], ['%d']);
    } elseif ($type === 'sufinansiranje') {
        $delta = -1; $new = 0; $amt = (int) $co->company_amount;
        $note = 'Feniranje, firma plaća ' . dry65_b2b_din($amt);
    } else { // vauceri
        $employee = dry65_b2b_employee_get($employee->id); // sveže stanje iz baze
        $amount = (int) $amount;
        $bal = (int) $employee->balance;
        if ($bal <= 0) return ['ok' => false, 'msg' => 'Vaučer je potrošen.'];
        if ($amount <= 0) return ['ok' => false, 'msg' => 'Unesi iznos.'];
        $amount = min($amount, $bal);
        $delta = -$amount; $new = $bal - $amount; $amt = $amount;
        $note = 'Potrošnja ' . dry65_b2b_din($amount);
        $wpdb->update(dry65_b2b_emp_table(), ['balance' => $new], ['id' => (int) $employee->id], ['%d'], ['%d']);
    }
    $wpdb->insert(dry65_b2b_txn_table(), [
        'company_id'    => (int) $co->id,
        'employee_id'   => (int) $employee->id,
        'employee_name' => $employee->name,
        'delta'         => $delta,
        'balance_after' => $new,
        'amount'        => $amt,
        'note'          => $note,
        'staff_id'      => get_current_user_id(),
        'staff_name'    => $staff_name,
        'created_at'    => dry65_b2b_now(),
    ], ['%d','%d','%s','%d','%d','%d','%s','%d','%s','%s']);
    // Isti denormalizovani log kao lični paketi — spaja se u budući presek dolazaka.
    if (function_exists('dry65_pk_stamp_log_table')) {
        $wpdb->insert(dry65_pk_stamp_log_table(), [
            'logged_at' => dry65_b2b_now(), 'account_id' => 0, 'customer_id' => 0,
            'code' => $employee->code, 'type' => 'b2b', 'event' => $type === 'vauceri' ? 'potrosnja' : 'feniranje',
            'delta' => $delta, 'balance_after' => $new, 'tier' => $type, 'plan' => $co->name,
            'source' => $source, 'staff_id' => get_current_user_id(), 'staff_name' => $staff_name,
        ], ['%s','%d','%d','%s','%s','%s','%d','%d','%s','%s','%s','%d','%s']);
    }
    if ($type === 'mesecni-paket') do_action('dry65_b2b_balance_changed', (int) $co->id);
    elseif ($type === 'vauceri')   dry65_b2b_wallet_sync_queue((int) $employee->id);
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
    $type  = dry65_b2b_type($co);
    $emp   = (int) $t->employee_id > 0 ? dry65_b2b_employee_get($t->employee_id) : null;
    if ($type === 'vauceri' && $emp) {
        $new = max(0, (int) $emp->balance - $delta);
        $wpdb->update(dry65_b2b_emp_table(), ['balance' => $new], ['id' => (int) $emp->id], ['%d'], ['%d']);
        if ($delta > 0) $wpdb->update(dry65_b2b_emp_table(), ['initial' => max(0, (int) $emp->initial - $delta)], ['id' => (int) $emp->id], ['%d'], ['%d']);
        dry65_b2b_wallet_sync_queue((int) $emp->id);
    } elseif ($type === 'sufinansiranje') {
        $new = 0;
    } else {
        $new = max(0, min((int) $co->initial, (int) $co->balance - $delta));
        $wpdb->update(dry65_b2b_table(), ['balance' => $new], ['id' => (int) $co->id], ['%d'], ['%d']);
    }
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
        if (dry65_b2b_type($co) === 'mesecni-paket' && $co->cycle_end <= $today) dry65_b2b_cycle_reset($co);
    }
});

/* ============================================================
   WALLET — GOOGLE (po zaposlenom, zajednički balans firme)
   Reuse generičkih helpera iz wallet.php (JWT/potpis/token) —
   ta funkcija se ne dira, ovo je paralelni objekat-graditelj.
   ============================================================ */
/* Šta kartica pokazuje, po tipu saradnje (isto za Google, Apple i web karticu). */
function dry65_b2b_card_summary($employee, $co) {
    $type = dry65_b2b_type($co);
    if ($type === 'sufinansiranje') {
        return [
            'label' => 'Firma plaća', 'short' => 'FIRMA PLAĆA', 'value' => dry65_b2b_din($co->company_amount),
            'info_h' => 'Kako radi', 'info_b' => $co->name . ' plaća ' . dry65_b2b_din($co->company_amount) . ' po feniranju, razliku doplaćujete na kasi.',
        ];
    }
    if ($type === 'vauceri') {
        return [
            'label' => 'Vaučer (' . $co->name . ')', 'short' => 'PREOSTALO', 'value' => dry65_b2b_din($employee->balance),
            'info_h' => 'Vaučer', 'info_b' => 'Iznos se skida na kasi za usluge u Dry65.',
        ];
    }
    return [
        'label' => 'Preostalo (' . $co->name . ')', 'short' => 'PREOSTALO', 'value' => (int) $co->balance . ' / ' . (int) $co->initial,
        'info_h' => 'Obnova', 'info_b' => date_i18n('d.m.Y.', strtotime($co->cycle_end)),
    ];
}

function dry65_b2b_wallet_google_object_id($employee) {
    return DRY65_GW_ISSUER_ID . '.' . DRY65_GW_CLASS . '-be' . (int) $employee->id;
}
function dry65_b2b_wallet_google_object($employee, $co) {
    $card_url = dry65_b2b_card_url($employee->code, $co->name);
    $hero_url = dry65_b2b_ensure_hero($co);
    $sum      = dry65_b2b_card_summary($employee, $co);
    $obj = [
        'id'                 => dry65_b2b_wallet_google_object_id($employee),
        'classId'            => dry65_wallet_google_class_id(),
        'state'              => 'ACTIVE',
        'accountName'        => $employee->name,
        'accountId'          => $employee->code,
        'hexBackgroundColor' => $hero_url ? '#FFFFFF' : '#33302c',
        'barcode'            => ['type' => 'QR_CODE', 'value' => $card_url, 'alternateText' => $employee->code],
        'loyaltyPoints'      => ['label' => $sum['label'], 'balance' => ['string' => $sum['value']]],
        'textModulesData'    => [[
            'id' => 'obnova', 'header' => $sum['info_h'], 'body' => $sum['info_b'],
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
    $sum      = dry65_b2b_card_summary($employee, $co);
    $body = [
        'accountName'        => $employee->name,
        'hexBackgroundColor' => $hero_url ? '#FFFFFF' : '#33302c',
        'loyaltyPoints'      => ['label' => $sum['label'], 'balance' => ['string' => $sum['value']]],
        'textModulesData'    => [[ 'id' => 'obnova', 'header' => $sum['info_h'], 'body' => $sum['info_b'] ]],
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

    $sum  = dry65_b2b_card_summary($employee, $co);
    $back = [
        ['key' => 'obnova', 'label' => $sum['info_h'], 'value' => $sum['info_b']],
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
                ['key' => 'preostalo', 'label' => $sum['short'], 'value' => $sum['value'], 'textAlignment' => 'PKTextAlignmentRight'],
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
        $res = dry65_b2b_spend($employee, $worker, 'kartica', (int) ($_POST['amount'] ?? 0));
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
    $type      = $co ? dry65_b2b_type($co) : 'mesecni-paket';
    $sum       = ($employee && $co) ? dry65_b2b_card_summary($employee, $co) : null;
    $blocked   = $co && (($type === 'mesecni-paket' && (int) $co->balance <= 0) || ($type === 'vauceri' && (int) $employee->balance <= 0) || $co->status !== 'active');
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
                <span><?php echo esc_html($sum['value']); ?></span>
              </div>
              <div style="padding:clamp(20px,5vw,28px) clamp(20px,5vw,30px) clamp(26px,6vw,38px);">
              <div style="width:clamp(150px,44vw,178px);aspect-ratio:1;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:20px;padding:14px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;">
                <?php echo dry65_pk_qr_html($card_url, 5); ?>
              </div>

              <?php if ($can_staff): ?>
                <?php if ($blocked): ?>
                  <p style="margin:16px 0 0;color:#a00;font-weight:600;"><?php
                    if ($co->status !== 'active') echo 'Saradnja sa ' . esc_html($co->name) . ' je pauzirana.';
                    elseif ($type === 'vauceri') echo 'Vaučer je potrošen.';
                    else echo 'Nema preostalih feniranja za ' . esc_html($co->name) . ' ovog meseca.';
                  ?></p>
                <?php else: ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:18px auto 0;max-width:280px;display:grid;gap:10px;">
                  <input type="hidden" name="action" value="dry65_b2b_spend">
                  <input type="hidden" name="code" value="<?php echo esc_attr($employee->code); ?>">
                  <input type="hidden" name="return" value="<?php echo esc_url($card_url); ?>">
                  <input type="hidden" name="pin" class="pk-card-pin" value="">
                  <?php wp_nonce_field('dry65_b2b_spend'); ?>
                  <?php if ($type === 'vauceri'): ?>
                    <label style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#6b625c;text-align:left;">Iznos za skidanje (din)
                      <input type="number" name="amount" min="1" max="<?php echo (int) $employee->balance; ?>" step="1" inputmode="numeric" required style="display:block;width:100%;box-sizing:border-box;margin-top:6px;padding:12px 14px;border:1px solid rgba(17,28,29,0.18);border-radius:12px;font-size:20px;text-align:center;">
                    </label>
                    <button type="submit" style="cursor:pointer;border:0;border-radius:999px;padding:14px 32px;font-size:16px;font-weight:600;color:#fff;background:#111C1D;">Skini iznos</button>
                  <?php elseif ($type === 'sufinansiranje'): ?>
                    <button type="submit" style="cursor:pointer;border:0;border-radius:999px;padding:14px 32px;font-size:16px;font-weight:600;color:#fff;background:#111C1D;">Zabeleži feniranje</button>
                    <p style="margin:0;font-size:13px;color:#6b625c;"><?php echo esc_html($co->name); ?> plaća <?php echo esc_html(dry65_b2b_din($co->company_amount)); ?>. Naplati razliku do cene usluge.</p>
                  <?php else: ?>
                    <button type="submit" style="cursor:pointer;border:0;border-radius:999px;padding:14px 32px;font-size:16px;font-weight:600;color:#fff;background:#111C1D;">Skini 1 feniranje</button>
                  <?php endif; ?>
                </form>
                <?php endif; ?>
              <?php endif; ?>

              <?php if (isset($_GET['sk'])): ?><p style="margin:16px 0 0;font-weight:600;"><?php echo $type === 'sufinansiranje' ? 'Feniranje zabeleženo.' : 'Skinuto. Novo stanje je gore.'; ?></p><?php endif; ?>
              <?php if (isset($_GET['full'])): ?><p style="margin:16px 0 0;color:#a00;font-weight:600;"><?php echo $type === 'vauceri' ? 'Nije skinuto, proveri iznos.' : 'Fond je potrošen ovog meseca.'; ?></p><?php endif; ?>

              <p style="margin:16px 0 0;font-size:12.5px;color:#6b625c;"><?php echo $type === 'mesecni-paket' ? 'Obnova fonda: ' . esc_html($sum['info_b']) : esc_html($sum['info_b']); ?></p>
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
    // Admin pregled: /b2b/?firma=ID prikazuje panel te firme tačno kako ga firma vidi (samo čitanje).
    $admin_preview = false;
    if (isset($_GET['firma']) && current_user_can(DRY65_PK_CAP)) {
        $company_id = (int) $_GET['firma'];
        $admin_preview = true;
    }
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
        $type = dry65_b2b_type($co);
        $ym   = isset($_GET['mesec']) ? sanitize_text_field(wp_unslash($_GET['mesec'])) : '';
        $rep  = dry65_b2b_month_report($co, $ym);
        $ym   = $rep['ym'];
        $is_now = $ym === substr(dry65_b2b_today(), 0, 7);
        $base = $admin_preview ? home_url('/b2b/?firma=' . (int) $co->id) : home_url('/b2b/');
        $prev = add_query_arg('mesec', dry65_b2b_month_shift($ym, -1), $base);
        $next = add_query_arg('mesec', dry65_b2b_month_shift($ym, 1), $base);
        $used = (int) $co->initial - (int) $co->balance;
        $cell = 'padding:8px 0;border-bottom:1px solid var(--sage-line,#eee);';
      ?>
      <div class="wrap" style="max-width:720px;margin:0 auto;">
        <?php if ($admin_preview): ?>
          <div style="background:#fff;box-shadow:0 1px 2px rgba(17,28,29,0.08),0 8px 20px -10px rgba(17,28,29,0.30);border-radius:12px;padding:10px 14px;margin-bottom:20px;font-size:13px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <span><strong>Pregled kao admin.</strong> Ovako panel vidi firma.</span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dry65-b2b&company=' . (int) $co->id)); ?>">Nazad u wp-admin</a>
          </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
          <div>
            <img src="<?php echo esc_url($logo); ?>" alt="Dry65" style="height:26px;width:auto;display:block;margin-bottom:8px;">
            <h1 class="display caps" style="font-size:clamp(22px,3.4vw,30px);margin:0;"><?php echo esc_html($co->name); ?></h1>
            <div class="muted" style="font-size:13px;margin-top:4px;"><?php echo esc_html(preg_replace('/^\d\.\s*/', '', dry65_b2b_types()[$type])); ?></div>
          </div>
          <?php if (!$admin_preview): ?><a href="<?php echo esc_url(home_url('/b2b/?logout=1')); ?>" style="font-size:13px;">Odjavi se</a><?php endif; ?>
        </div>

        <?php if ($type === 'mesecni-paket'): ?>
        <div style="background:#33302c;color:#efe1d2;border-radius:20px;padding:22px 24px;margin-bottom:22px;">
          <div class="mono" style="letter-spacing:0.14em;text-transform:uppercase;font-size:11.5px;color:#c8beb4;">Tekući ciklus (<?php echo esc_html(date_i18n('d.m.', strtotime($co->cycle_start))); ?> do <?php echo esc_html(date_i18n('d.m.Y.', strtotime($co->cycle_end))); ?>)</div>
          <div style="font-family:'Cormorant Garamond',Cormorant,Georgia,serif;font-size:clamp(30px,5vw,40px);margin-top:4px;"><?php echo $used; ?> / <?php echo (int) $co->initial; ?> feniranja</div>
          <div style="font-size:13px;color:#c8beb4;margin-top:4px;">Preostalo: <?php echo (int) $co->balance; ?> · Obnova <?php echo esc_html(date_i18n('d.m.Y.', strtotime($co->cycle_end))); ?></div>
        </div>
        <?php endif; ?>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin:6px 0 12px;">
          <a href="<?php echo esc_url($prev); ?>" style="font-size:14px;">Prethodni mesec</a>
          <h2 style="font-size:16px;text-transform:uppercase;letter-spacing:0.08em;margin:0;"><?php echo esc_html(dry65_b2b_month_label($ym)); ?></h2>
          <?php if (!$is_now): ?><a href="<?php echo esc_url($next); ?>" style="font-size:14px;">Sledeći mesec</a><?php else: ?><span style="width:110px;"></span><?php endif; ?>
        </div>

        <div style="background:#fff;box-shadow:0 1px 2px rgba(17,28,29,0.08),0 8px 20px -10px rgba(17,28,29,0.30);border-radius:16px;padding:18px 20px;margin-bottom:22px;">
          <?php if ($type === 'sufinansiranje'): ?>
            <div style="font-size:14px;color:var(--muted);">Za fakturu</div>
            <div style="font-size:22px;font-weight:600;margin-top:4px;"><?php echo esc_html(dry65_b2b_fens($rep['count'])); ?> × <?php echo esc_html(dry65_b2b_din($co->company_amount)); ?> = <?php echo esc_html(dry65_b2b_din($rep['sum'])); ?></div>
          <?php elseif ($type === 'vauceri'):
            $left = 0; foreach (dry65_b2b_employees($co->id) as $e) $left += (int) $e->balance; ?>
            <div style="font-size:14px;color:var(--muted);">Potrošeno sa vaučera</div>
            <div style="font-size:22px;font-weight:600;margin-top:4px;"><?php echo esc_html(dry65_b2b_din($rep['sum'])); ?> <span style="font-size:14px;font-weight:400;color:var(--muted);">(<?php echo esc_html(dry65_b2b_visits($rep['count'])); ?>)</span></div>
            <div style="font-size:13px;color:var(--muted);margin-top:4px;">Trenutno preostalo na svim vaučerima: <?php echo esc_html(dry65_b2b_din($left)); ?></div>
          <?php else: ?>
            <div style="font-size:14px;color:var(--muted);">Feniranja u mesecu</div>
            <div style="font-size:22px;font-weight:600;margin-top:4px;"><?php echo (int) $rep['count']; ?><?php if ((int) $co->price_rsd): ?> <span style="font-size:14px;font-weight:400;color:var(--muted);">· mesečni paket <?php echo esc_html(dry65_b2b_din($co->price_rsd)); ?></span><?php endif; ?></div>
          <?php endif; ?>
        </div>

        <h2 style="font-size:16px;text-transform:uppercase;letter-spacing:0.08em;">Po zaposlenom</h2>
        <table style="width:100%;border-collapse:collapse;margin:10px 0 24px;">
          <?php if (!$rep['per']): ?>
            <tr><td style="padding:8px 0;color:var(--muted);">Nema dolazaka u ovom mesecu.</td></tr>
          <?php else: foreach ($rep['per'] as $row): ?>
            <tr>
              <td style="<?php echo $cell; ?>"><?php echo esc_html($row['name']); ?></td>
              <td style="<?php echo $cell; ?>text-align:right;font-weight:600;"><?php echo esc_html(dry65_b2b_visits($row['count'])); ?><?php if ($type !== 'mesecni-paket'): ?> · <?php echo esc_html(dry65_b2b_din($row['sum'])); ?><?php endif; ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </table>

        <h2 style="font-size:16px;text-transform:uppercase;letter-spacing:0.08em;">Dolasci</h2>
        <table style="width:100%;border-collapse:collapse;margin:10px 0;font-size:13.5px;">
          <?php if (!$rep['rows']): ?>
            <tr><td style="padding:8px 0;color:var(--muted);">Nema dolazaka u ovom mesecu.</td></tr>
          <?php else: foreach ($rep['rows'] as $t): ?>
            <tr>
              <td style="<?php echo $cell; ?>white-space:nowrap;color:var(--muted);"><?php echo esc_html(mysql2date('d.m.Y. H:i', $t->created_at)); ?></td>
              <td style="<?php echo $cell; ?>padding-left:10px;padding-right:10px;"><?php echo esc_html($t->employee_name); ?></td>
              <td style="<?php echo $cell; ?>text-align:right;font-weight:600;white-space:nowrap;"><?php echo $type === 'mesecni-paket' ? 'Feniranje' : esc_html(dry65_b2b_din($t->amount)); ?></td>
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
// Prioritet 9: meni mora da postoji PRE nego što WP doda „Prijave" (CPT, prioritet 10),
// inače Prijave preuzmu link „B2B Firme" i lista firmi nestane iz menija.
add_action('admin_menu', function () {
    add_menu_page('B2B Firme', 'B2B Firme', DRY65_PK_CAP, 'dry65-b2b', 'dry65_b2b_admin_page', 'dashicons-building', 5);
    add_submenu_page('dry65-b2b', 'B2B Firme', 'Firme', DRY65_PK_CAP, 'dry65-b2b', 'dry65_b2b_admin_page');
}, 9);

/* Polja tipa saradnje u admin formi (nova firma i izmena). JS prikazuje samo polja izabranog tipa. */
function dry65_b2b_admin_type_fields($model, $initial, $price_rsd, $company_amount, $uid) {
    ?>
    <p><label>Tip saradnje<br>
      <select name="model" id="<?php echo esc_attr($uid); ?>-model" style="width:100%;">
        <?php foreach (dry65_b2b_types() as $k => $label): ?>
          <option value="<?php echo esc_attr($k); ?>" <?php selected($model, $k); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
    </label></p>
    <div data-b2b-type="mesecni-paket">
      <p><label>Mesečni fond (feniranja)<br>
        <input type="number" name="initial" min="1" step="1" value="<?php echo (int) $initial; ?>" list="dry65-b2b-plans" style="width:100%;">
        <datalist id="dry65-b2b-plans"><option value="40"><option value="60"><option value="90"></datalist>
      </label></p>
      <p><label>Cena mesečno (din)<br><input type="number" name="price_rsd" min="0" step="1" value="<?php echo (int) $price_rsd ?: ''; ?>" style="width:100%;"></label></p>
      <p class="description">Kartica skida 1 feniranje iz zajedničkog fonda. Fond se obnavlja svakog meseca.</p>
    </div>
    <div data-b2b-type="sufinansiranje">
      <p><label>Firma plaća po feniranju (din)<br><input type="number" name="company_amount_suf" min="0" step="1" value="<?php echo (int) $company_amount ?: ''; ?>" style="width:100%;"></label></p>
      <p class="description">QR beleži dolazak, zaposleni doplaćuje razliku na kasi. Na kraju meseca faktura: dolasci × iznos.</p>
    </div>
    <div data-b2b-type="vauceri">
      <p><label>Iznos vaučera po zaposlenom (din)<br><input type="number" name="company_amount_vau" min="0" step="1" value="<?php echo (int) $company_amount ?: ''; ?>" style="width:100%;"></label></p>
      <p class="description">Podrazumevani iznos pri dodavanju zaposlenog (može da se promeni za svakog). Na kasi se skida iznos.</p>
    </div>
    <script>
    (function(){
      var sel=document.getElementById(<?php echo wp_json_encode($uid . '-model'); ?>); if(!sel) return;
      var form=sel.form;
      function upd(){ form.querySelectorAll('[data-b2b-type]').forEach(function(d){ var on=d.getAttribute('data-b2b-type')===sel.value; d.style.display=on?'':'none'; d.querySelectorAll('input').forEach(function(i){ i.disabled=!on; }); }); }
      sel.addEventListener('change',upd); upd();
    })();
    </script>
    <?php
}
function dry65_b2b_post_company_amount() {
    $m = sanitize_key($_POST['model'] ?? '');
    if ($m === 'sufinansiranje') return (int) ($_POST['company_amount_suf'] ?? 0);
    if ($m === 'vauceri')        return (int) ($_POST['company_amount_vau'] ?? 0);
    return 0;
}

function dry65_b2b_admin_page() {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    $id = isset($_GET['company']) ? (int) $_GET['company'] : 0;
    if ($id) { dry65_b2b_admin_detail($id); return; }
    $rows = dry65_b2b_all();
    // Popuni formu iz online prijave (Prijave > „Napravi firmu iz prijave").
    $pf = ['name' => '', 'email' => '', 'model' => 'mesecni-paket', 'note' => ''];
    $pid = isset($_GET['prijava']) ? (int) $_GET['prijava'] : 0;
    if ($pid && get_post_type($pid) === 'dry65_b2b_prijava') {
        $pm = fn($k) => (string) get_post_meta($pid, '_b2bp_' . $k, true);
        $pf = [
            'name'  => $pm('company'),
            'email' => $pm('email'),
            'model' => isset(dry65_b2b_types()[$pm('model')]) ? $pm('model') : 'mesecni-paket',
            'note'  => trim('PIB ' . $pm('pib') . ', ' . $pm('contact') . ', ' . $pm('phone') . ($pm('employees') ? ', ' . $pm('employees') . ' zaposlenih' : '')),
        ];
    }
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
            <?php if ($pid && $pf['name'] !== ''): ?><p class="description" style="margin-top:0;">Popunjeno iz online prijave.</p><?php endif; ?>
            <p><label>Naziv firme<br><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr($pf['name']); ?>" style="width:100%;"></label></p>
            <p><label>Kontakt email<br><input type="email" name="contact_email" class="regular-text" value="<?php echo esc_attr($pf['email']); ?>" style="width:100%;"></label></p>
            <?php dry65_b2b_admin_type_fields($pf['model'], 40, 0, 0, 'b2b-new'); ?>
            <p><label>Napomena<br><input type="text" name="note" value="<?php echo esc_attr($pf['note']); ?>" style="width:100%;"></label></p>
            <p><button type="submit" class="button button-primary">Napravi firmu</button></p>
          </form>
        </div>
        <div style="flex:1;min-width:360px;">
          <table class="widefat striped">
            <thead><tr><th>Firma</th><th>Tip</th><th>Stanje</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="5"><em>Još nema firmi.</em></td></tr>
              <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><strong><?php echo esc_html($r->name); ?></strong></td>
                <?php $rt = dry65_b2b_type($r); ?>
                <td><?php echo esc_html(substr(dry65_b2b_types()[$rt], 0, 1)); ?>. <?php echo esc_html(['mesecni-paket' => 'Paket', 'sufinansiranje' => 'Deo firma', 'vauceri' => 'Vaučeri'][$rt]); ?></td>
                <td><?php
                  if ($rt === 'mesecni-paket') echo (int) $r->balance . ' / ' . (int) $r->initial . ', obnova ' . esc_html(date_i18n('d.m.', strtotime($r->cycle_end)));
                  elseif ($rt === 'sufinansiranje') echo esc_html(dry65_b2b_din($r->company_amount)) . ' po feniranju';
                  else echo esc_html(dry65_b2b_din($r->company_amount)) . ' po zaposlenom';
                ?></td>
                <td><?php echo $r->status === 'active' ? 'Aktivna' : 'Pauzirana'; ?></td>
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
    $type      = dry65_b2b_type($co);
    $ym        = isset($_GET['mesec']) ? sanitize_text_field(wp_unslash($_GET['mesec'])) : '';
    $rep       = dry65_b2b_month_report($co, $ym);
    $ym        = $rep['ym'];
    $self      = admin_url('admin.php?page=dry65-b2b&company=' . (int) $co->id);
    $is_admin  = current_user_can(DRY65_PK_ADMIN_CAP);
    global $wpdb;
    $uid = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='dry65_b2b_company_id' AND meta_value=%s", (string) $id));
    $wp_user = $uid ? get_user_by('id', (int) $uid) : null;
    ?>
    <div class="wrap">
      <p><a href="<?php echo esc_url(admin_url('admin.php?page=dry65-b2b')); ?>">← Sve firme</a></p>
      <h1><?php echo esc_html($co->name); ?></h1>
      <?php if (isset($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>Sačuvano.</p></div><?php endif; ?>
      <?php if (isset($_GET['emp_added'])): ?><div class="notice notice-success is-dismissible"><p>Zaposleni dodat<?php echo isset($_GET['mail']) ? ($_GET['mail'] === '1' ? ', kartica poslata mejlom.' : '. Mejl NIJE poslat, proveri email adresu.') : '.'; ?></p></div><?php endif; ?>
      <?php if (isset($_GET['emp_sent'])): ?><?php if ($_GET['emp_sent'] === '1'): ?><div class="notice notice-success is-dismissible"><p>Kartica poslata mejlom.</p></div><?php else: ?><div class="notice notice-error is-dismissible"><p>Mejl nije poslat, proveri email adresu.</p></div><?php endif; ?><?php endif; ?>
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
            <?php dry65_b2b_admin_type_fields(dry65_b2b_type($co), (int) $co->initial ?: 40, (int) $co->price_rsd, (int) $co->company_amount, 'b2b-edit'); ?>
            <p><label>Status<br>
              <select name="status" style="width:100%;">
                <option value="active" <?php selected($co->status, 'active'); ?>>Aktivna</option>
                <option value="paused" <?php selected($co->status, 'paused'); ?>>Pauzirana</option>
              </select>
            </label></p>
            <p><label>Napomena<br><input type="text" name="note" value="<?php echo esc_attr($co->note); ?>" style="width:100%;"></label></p>
            <p><button type="submit" class="button button-primary">Sačuvaj</button></p>
          </form>
          <?php if (dry65_b2b_type($co) === 'mesecni-paket'): ?>
          <hr>
          <p><strong>Ciklus:</strong> <?php echo esc_html(date_i18n('d.m.Y.', strtotime($co->cycle_start))); ?> – <?php echo esc_html(date_i18n('d.m.Y.', strtotime($co->cycle_end))); ?></p>
          <p><strong>Preostalo:</strong> <?php echo (int) $co->balance; ?> / <?php echo (int) $co->initial; ?></p>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Resetuj mesec sada (fond se vraća na <?php echo (int) $co->initial; ?>)?');">
            <input type="hidden" name="action" value="dry65_b2b_reset_now">
            <input type="hidden" name="id" value="<?php echo (int) $co->id; ?>">
            <?php wp_nonce_field('dry65_b2b_reset_now'); ?>
            <button type="submit" class="button">Resetuj mesec sada</button>
          </form>
          <?php endif; ?>

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
          <p><a class="button" href="<?php echo esc_url(home_url('/b2b/?firma=' . (int) $co->id)); ?>" target="_blank" rel="noopener">Pogledaj panel firme</a></p>
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
              <input type="text" name="name" placeholder="Ime zaposlenog" required style="flex:1;min-width:0;">
              <input type="email" name="email" placeholder="Email (šaljemo karticu)" style="flex:1;min-width:0;">
              <?php if ($type === 'vauceri'): ?>
              <input type="number" name="voucher" min="0" step="1" value="<?php echo (int) $co->company_amount ?: ''; ?>" placeholder="Iznos (din)" title="Iznos vaučera (din)" style="width:110px;">
              <?php endif; ?>
              <button type="submit" class="button">Dodaj</button>
            </form>
            <table class="widefat striped">
              <thead><tr><th>Ime</th><th>Link za Wallet karticu</th><th></th></tr></thead>
              <tbody>
                <?php if (!$employees): ?>
                  <tr><td colspan="3"><em>Još nema zaposlenih.</em></td></tr>
                <?php else: foreach ($employees as $e): ?>
                <tr>
                  <td>
                    <?php echo esc_html($e->name); ?>
                    <?php if ($type === 'vauceri'): ?>
                      <div style="margin-top:4px;color:#555;">Vaučer: <strong><?php echo esc_html(dry65_b2b_din($e->balance)); ?></strong> / <?php echo esc_html(dry65_b2b_din($e->initial)); ?></div>
                      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:4px;margin-top:6px;">
                        <input type="hidden" name="action" value="dry65_b2b_employee_topup">
                        <input type="hidden" name="id" value="<?php echo (int) $e->id; ?>">
                        <input type="hidden" name="company_id" value="<?php echo (int) $co->id; ?>">
                        <?php wp_nonce_field('dry65_b2b_employee_topup'); ?>
                        <input type="number" name="amount" min="1" step="1" placeholder="din" required style="width:80px;">
                        <button type="submit" class="button button-small">Dopuni</button>
                      </form>
                    <?php endif; ?>
                  </td>
                  <td>
                    <input type="text" readonly value="<?php echo esc_url(dry65_b2b_card_url($e->code, $co->name)); ?>" style="width:100%;" onclick="this.select();">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:6px;margin-top:6px;">
                      <input type="hidden" name="action" value="dry65_b2b_employee_send">
                      <input type="hidden" name="id" value="<?php echo (int) $e->id; ?>">
                      <input type="hidden" name="company_id" value="<?php echo (int) $co->id; ?>">
                      <?php wp_nonce_field('dry65_b2b_employee_send'); ?>
                      <input type="email" name="email" value="<?php echo esc_attr($e->email ?? ''); ?>" placeholder="Email zaposlenog" required style="flex:1;min-width:0;">
                      <button type="submit" class="button button-small"><?php echo !empty($e->email) ? 'Pošalji ponovo' : 'Pošalji karticu'; ?></button>
                    </form>
                  </td>
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
            <p class="description">Kad uneseš email, kartica se odmah šalje zaposlenom. Link možeš poslati i ručno (WhatsApp). Na kartici zaposleni dodaje karticu u Apple/Google Wallet.</p>
          </div>

          <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
              <h2 style="margin:0;">Obračun za fakturu: <?php echo esc_html(dry65_b2b_month_label($ym)); ?></h2>
              <span>
                <a class="button button-small" href="<?php echo esc_url(add_query_arg('mesec', dry65_b2b_month_shift($ym, -1), $self)); ?>">Prethodni</a>
                <a class="button button-small" href="<?php echo esc_url(add_query_arg('mesec', dry65_b2b_month_shift($ym, 1), $self)); ?>">Sledeći</a>
                <a class="button button-small button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dry65_b2b_csv&company=' . (int) $co->id . '&mesec=' . $ym), 'dry65_b2b_csv')); ?>">Preuzmi CSV</a>
              </span>
            </div>
            <p style="font-size:15px;margin:12px 0;">
              <?php if ($type === 'sufinansiranje'): ?>
                <strong><?php echo esc_html(dry65_b2b_fens($rep['count'])); ?> × <?php echo esc_html(dry65_b2b_din($co->company_amount)); ?> = <?php echo esc_html(dry65_b2b_din($rep['sum'])); ?></strong>
              <?php elseif ($type === 'vauceri'): ?>
                <strong>Potrošeno sa vaučera: <?php echo esc_html(dry65_b2b_din($rep['sum'])); ?></strong> (<?php echo esc_html(dry65_b2b_visits($rep['count'])); ?>)
              <?php else: ?>
                <strong><?php echo esc_html(dry65_b2b_fens($rep['count'])); ?></strong><?php if ((int) $co->price_rsd): ?>, mesečni paket <?php echo esc_html(dry65_b2b_din($co->price_rsd)); ?><?php endif; ?>
              <?php endif; ?>
            </p>
            <table class="widefat striped">
              <thead><tr><th>Kad</th><th>Zaposleni</th><th>Iznos</th><th>Radnica</th></tr></thead>
              <tbody>
                <?php if (!$rep['rows']): ?>
                  <tr><td colspan="4"><em>Nema dolazaka u ovom mesecu.</em></td></tr>
                <?php else: foreach ($rep['rows'] as $t): ?>
                <tr>
                  <td><?php echo esc_html(mysql2date('d.m.Y. H:i', $t->created_at)); ?></td>
                  <td><?php echo esc_html($t->employee_name); ?></td>
                  <td><?php echo $type === 'mesecni-paket' ? '1 feniranje' : esc_html(dry65_b2b_din($t->amount)); ?></td>
                  <td><?php echo esc_html($t->staff_name); ?></td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
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
        (int) ($_POST['initial'] ?? 40),
        (int) ($_POST['price_rsd'] ?? 0),
        sanitize_text_field(wp_unslash($_POST['note'] ?? '')),
        sanitize_key($_POST['model'] ?? 'mesecni-paket'),
        dry65_b2b_post_company_amount()
    );
    wp_redirect(admin_url('admin.php?page=dry65-b2b' . ($id ? '&company=' . $id . '&saved=1' : '&err=1')));
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
        sanitize_text_field(wp_unslash($_POST['note'] ?? '')),
        sanitize_key($_POST['model'] ?? ''),
        dry65_b2b_post_company_amount()
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
    $name  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $mail  = '';
    if ($name !== '') {
        $emp_id = dry65_b2b_employee_create($company_id, $name, $email, isset($_POST['voucher']) ? (int) $_POST['voucher'] : null);
        if ($emp_id && $email !== '') {
            $mail = dry65_b2b_employee_send_email(dry65_b2b_employee_get($emp_id), dry65_b2b_get($company_id)) ? '1' : '0';
        }
    }
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $company_id . '&emp_added=1' . ($mail !== '' ? '&mail=' . $mail : '')));
    exit;
});

add_action('admin_post_dry65_b2b_employee_topup', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_employee_topup');
    $company_id = (int) ($_POST['company_id'] ?? 0);
    $emp = dry65_b2b_employee_get((int) ($_POST['id'] ?? 0));
    if ($emp && (int) $emp->company_id === $company_id) dry65_b2b_employee_topup($emp, (int) ($_POST['amount'] ?? 0));
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $company_id . '&saved=1'));
    exit;
});

/* CSV obračuna za mesec (za fakturu): datum, vreme, zaposleni, iznos, radnica + zbir. */
add_action('admin_post_dry65_b2b_csv', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_csv');
    $co = dry65_b2b_get((int) ($_GET['company'] ?? 0));
    if (!$co) wp_die('Firma nije pronađena.');
    $rep  = dry65_b2b_month_report($co, sanitize_text_field(wp_unslash($_GET['mesec'] ?? '')));
    $type = dry65_b2b_type($co);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dry65-' . sanitize_title($co->name) . '-' . $rep['ym'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM da Excel pravilno prikaže č, ć, š...
    fputcsv($out, ['Datum', 'Vreme', 'Zaposleni', $type === 'mesecni-paket' ? 'Feniranja' : 'Iznos (din)', 'Radnica'], ';');
    foreach ($rep['rows'] as $t) {
        fputcsv($out, [
            mysql2date('d.m.Y.', $t->created_at), mysql2date('H:i', $t->created_at), $t->employee_name,
            $type === 'mesecni-paket' ? 1 : (int) $t->amount, $t->staff_name,
        ], ';');
    }
    fputcsv($out, [], ';');
    fputcsv($out, ['Ukupno', '', dry65_b2b_visits($rep['count']), $type === 'mesecni-paket' ? $rep['count'] : $rep['sum'], ''], ';');
    fclose($out);
    exit;
});

add_action('admin_post_dry65_b2b_employee_send', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_b2b_employee_send');
    global $wpdb;
    $id = (int) ($_POST['id'] ?? 0);
    $company_id = (int) ($_POST['company_id'] ?? 0);
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $emp = dry65_b2b_employee_get($id);
    $ok = false;
    if ($emp && (int) $emp->company_id === $company_id && is_email($email)) {
        if ($email !== ($emp->email ?? '')) {
            $wpdb->update(dry65_b2b_emp_table(), ['email' => $email], ['id' => $id], ['%s'], ['%d']);
            $emp = dry65_b2b_employee_get($id);
        }
        $ok = dry65_b2b_employee_send_email($emp, dry65_b2b_get($company_id));
    }
    wp_redirect(admin_url('admin.php?page=dry65-b2b&company=' . $company_id . '&emp_sent=' . ($ok ? '1' : '0')));
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
