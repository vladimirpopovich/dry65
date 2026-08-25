<?php
/* ============================================================
   Dry65 — PAKETI I VAUČERI
   ------------------------------------------------------------
   Jedan model: „nalog gosta sa stanjem koje se troši".
     - Paket:  stanje u broju (npr. 8 feniranja) -> svaki dolazak -1
     - Vaučer: stanje u dinarima (npr. 12.000)   -> potrošnja skida iznos
   Osoblje upravlja preko dashboarda; gost samo GLEDA preko
   tajnog linka /kartica/{kod} (read-only). Plaćanje je u salonu.

   Tabele:
     {p}dry65_accounts       nalozi
     {p}dry65_account_txns   istorija transakcija
   ============================================================ */

if (!defined('ABSPATH')) exit;

if (!defined('DRY65_PK_CAP')) define('DRY65_PK_CAP', 'edit_posts'); // ko sme da vodi (isto kao /live)
if (!defined('DRY65_PK_DB'))  define('DRY65_PK_DB', 7);             // verzija šeme
if (!defined('DRY65_PK_ADMIN_CAP')) define('DRY65_PK_ADMIN_CAP', 'manage_options'); // poništavanje = samo admin

/* ---- Tabele ---- */
function dry65_pk_table()     { global $wpdb; return $wpdb->prefix . 'dry65_accounts'; }
function dry65_pk_txn_table() { global $wpdb; return $wpdb->prefix . 'dry65_account_txns'; }
function dry65_pk_cust_table(){ global $wpdb; return $wpdb->prefix . 'dry65_customers'; }

function dry65_pk_install() {
    if ((int) get_option('dry65_pk_db', 0) === DRY65_PK_DB) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $acc  = dry65_pk_table();
    $txn  = dry65_pk_txn_table();
    $cust = dry65_pk_cust_table();
    $sql = "CREATE TABLE $cust (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(190) NOT NULL DEFAULT '',
        phone VARCHAR(40) NOT NULL DEFAULT '',
        email VARCHAR(190) NOT NULL DEFAULT '',
        source VARCHAR(20) NOT NULL DEFAULT 'salon',
        note TEXT NULL,
        created_at DATETIME NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY phone (phone),
        KEY name (name)
    ) $charset;
    CREATE TABLE $acc (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        code VARCHAR(20) NOT NULL,
        name VARCHAR(190) NOT NULL DEFAULT '',
        phone VARCHAR(40) NOT NULL DEFAULT '',
        email VARCHAR(190) NOT NULL DEFAULT '',
        type VARCHAR(10) NOT NULL DEFAULT 'paket',
        plan VARCHAR(120) NOT NULL DEFAULT '',
        reward VARCHAR(190) NOT NULL DEFAULT '',
        balance INT NOT NULL DEFAULT 0,
        initial INT NOT NULL DEFAULT 0,
        reward_used_at DATETIME NULL,
        expires_at DATE NULL,
        note TEXT NULL,
        created_at DATETIME NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY code (code),
        KEY name (name),
        KEY customer_id (customer_id)
    ) $charset;
    CREATE TABLE $txn (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        account_id BIGINT UNSIGNED NOT NULL,
        delta INT NOT NULL DEFAULT 0,
        balance_after INT NOT NULL DEFAULT 0,
        note VARCHAR(190) NOT NULL DEFAULT '',
        staff_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        staff_name VARCHAR(120) NOT NULL DEFAULT '',
        reversed TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY account_id (account_id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('dry65_pk_db', DRY65_PK_DB);
    dry65_pk_migrate_customers();
}
add_action('init', 'dry65_pk_install');

/* Jednokratno preimenovanje nagrade Signature plana (postojeći nalozi). */
function dry65_pk_rename_rewards() {
    if (get_option('dry65_pk_reward_rename_v1') === '1') return;
    global $wpdb;
    $wpdb->update(dry65_pk_table(),
        ['reward' => 'Signature Hair Mask'],
        ['reward' => 'Medium Hair Treatment Mask'],
        ['%s'], ['%s']);
    update_option('dry65_pk_reward_rename_v1', '1');
}
add_action('init', 'dry65_pk_rename_rewards');

/* Jednokratno: poveži postojeće pakete sa kupcima po telefonu. Bezbedno, ništa se ne briše. */
function dry65_pk_migrate_customers() {
    if (get_option('dry65_pk_cust_migrated') === '1') return;
    global $wpdb;
    $acc  = dry65_pk_table();
    $rows = $wpdb->get_results("SELECT id, name, phone, email FROM $acc WHERE customer_id = 0");
    if ($rows) {
        foreach ($rows as $r) {
            if (trim((string) $r->phone) === '') continue; // bez telefona ne vežemo (retko/nikad)
            $cid = dry65_pk_customer_get_or_create($r->name, $r->phone, $r->email, 'salon');
            if ($cid) $wpdb->update($acc, ['customer_id' => $cid], ['id' => (int) $r->id], ['%d'], ['%d']);
        }
    }
    update_option('dry65_pk_cust_migrated', '1');
}

/* ---- Pomoćne ---- */

/* Beogradsko vreme (server je UTC) — isto kao /live. */
function dry65_pk_now() {
    return (new DateTime('now', new DateTimeZone('Europe/Belgrade')))->format('Y-m-d H:i:s');
}

/* Gotovi paketi (naziv + broj sesija + nagrada). Vaučer je poseban (dinari). */
function dry65_pk_presets() {
    return [
        'essential' => ['name' => 'Essential Plan', 'sessions' => 4,  'reward' => 'Hair Infusion'],
        'signature' => ['name' => 'Signature Plan', 'sessions' => 8,  'reward' => 'Signature Hair Mask'],
        'premium'   => ['name' => 'Premium Plan',   'sessions' => 12, 'reward' => 'Hair Booster Premium Mask'],
    ];
}

/* Podrazumevani rok: 1 mesec od danas (Beograd). */
function dry65_pk_default_expiry() {
    return (new DateTime('now', new DateTimeZone('Europe/Belgrade')))->modify('+1 month')->format('Y-m-d');
}

/* Nepogodljiv kod za /kartica/{kod} (bez 0/o/1/l). */
function dry65_pk_gen_code() {
    global $wpdb;
    $t = dry65_pk_table();
    $chars = 'abcdefghijkmnpqrstuvwxyz23456789';
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE code = %s", $code));
    } while ($exists);
    return $code;
}

function dry65_pk_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . dry65_pk_table() . " WHERE id = %d", (int) $id));
}
function dry65_pk_get_by_code($code) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . dry65_pk_table() . " WHERE code = %s", (string) $code));
}
function dry65_pk_txns($account_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . dry65_pk_txn_table() . " WHERE account_id = %d ORDER BY id DESC", (int) $account_id
    ));
}

/* ---- Kupci (osoba iznad paketa; ključ = telefon) ---- */
function dry65_pk_customer_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . dry65_pk_cust_table() . " WHERE id = %d", (int) $id));
}
function dry65_pk_customer_by_phone($phone) {
    global $wpdb;
    $phone = trim((string) $phone);
    if ($phone === '') return null;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . dry65_pk_cust_table() . " WHERE phone = %s", $phone));
}
/* Nađi kupca po telefonu ili napravi novog. Dopuni prazna polja. Vrati id (0 ako ne može). */
function dry65_pk_customer_get_or_create($name, $phone, $email = '', $source = 'salon') {
    global $wpdb;
    $ct    = dry65_pk_cust_table();
    $phone = trim((string) $phone);
    $name  = sanitize_text_field($name);
    $email = $email ? sanitize_email($email) : '';
    if ($phone === '' && $name === '') return 0;
    if ($phone !== '') {
        $existing = dry65_pk_customer_by_phone($phone);
        if ($existing) {
            $upd = []; $fmt = [];
            if ($existing->name === '' && $name !== '')   { $upd['name']  = $name;  $fmt[] = '%s'; }
            if ($existing->email === '' && $email !== '') { $upd['email'] = $email; $fmt[] = '%s'; }
            if ($upd) $wpdb->update($ct, $upd, ['id' => (int) $existing->id], $fmt, ['%d']);
            return (int) $existing->id;
        }
    }
    $wpdb->insert($ct, [
        'name' => $name, 'phone' => $phone, 'email' => $email,
        'source' => $source, 'created_at' => dry65_pk_now(), 'created_by' => get_current_user_id(),
    ], ['%s','%s','%s','%s','%s','%d']);
    return (int) $wpdb->insert_id;
}
/* Svi paketi/vaučeri jednog kupca (najnoviji prvo). */
function dry65_pk_customer_accounts($customer_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . dry65_pk_table() . " WHERE customer_id = %d ORDER BY id DESC", (int) $customer_id
    ));
}
/* Zbirovi za profil kupca. */
function dry65_pk_customer_stats($customer_id) {
    $accs = dry65_pk_customer_accounts($customer_id);
    $s = ['paketa' => 0, 'vaucera' => 0, 'fen_ukupno' => 0, 'fen_iskorisceno' => 0,
          'tretmani' => 0, 'aktivnih' => 0];
    foreach ($accs as $a) {
        if ($a->type === 'vaucer') { $s['vaucera']++; }
        else {
            $s['paketa']++;
            $s['fen_ukupno']      += (int) $a->initial;
            $s['fen_iskorisceno'] += max(0, (int) $a->initial - (int) $a->balance);
            if (!empty($a->reward_used_at)) $s['tretmani']++;
        }
        if (!dry65_pk_is_expired($a) && (int) $a->balance > 0) $s['aktivnih']++;
    }
    return $s;
}

/* Kreiraj nalog + početnu transakciju. Vrati id ili 0. */
function dry65_pk_create($name, $phone, $type, $initial, $expires_at = '', $note = '', $plan = '', $reward = '', $email = '', $customer_id = 0) {
    global $wpdb;
    $type    = ($type === 'vaucer') ? 'vaucer' : 'paket';
    $initial = max(0, (int) $initial);
    $code    = dry65_pk_gen_code();
    $now     = dry65_pk_now();
    $ok = $wpdb->insert(dry65_pk_table(), [
        'customer_id'=> (int) $customer_id,
        'code'       => $code,
        'name'       => $name,
        'phone'      => $phone,
        'email'      => $email,
        'type'       => $type,
        'plan'       => $plan,
        'reward'     => $reward,
        'balance'    => $initial,
        'initial'    => $initial,
        'expires_at' => $expires_at !== '' ? $expires_at : null,
        'note'       => $note,
        'created_at' => $now,
        'created_by' => get_current_user_id(),
    ], ['%d','%s','%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s','%d']);
    if (!$ok) return 0;
    $id = (int) $wpdb->insert_id;
    $wpdb->insert(dry65_pk_txn_table(), [
        'account_id'    => $id,
        'delta'         => $initial,
        'balance_after' => $initial,
        'note'          => $type === 'vaucer' ? 'Vaučer otvoren' : ($plan !== '' ? $plan . ' otvoren' : 'Paket otvoren'),
        'staff_id'      => get_current_user_id(),
        'created_at'    => $now,
    ], ['%d','%d','%d','%s','%d','%s']);
    return $id;
}

/* Produži/zamrzni rok: postavi novi datum isteka + zabeleži u istoriju. */
function dry65_pk_extend($id, $new_date, $note = '') {
    global $wpdb;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) return false;
    $wpdb->update(dry65_pk_table(), ['expires_at' => $new_date], ['id' => (int) $id], ['%s'], ['%d']);
    $wpdb->insert(dry65_pk_txn_table(), [
        'account_id'    => (int) $id,
        'delta'         => 0,
        'balance_after' => (int) (dry65_pk_get($id)->balance),
        'note'          => 'Rok produžen do ' . $new_date . ($note !== '' ? ' (' . $note . ')' : ''),
        'staff_id'      => get_current_user_id(),
        'created_at'    => dry65_pk_now(),
    ], ['%d','%d','%d','%s','%d','%s']);
    return true;
}

/* Primeni promenu (delta<0 = potrošnja). Klampuje na [0..]. Vrati novo stanje. */
function dry65_pk_apply($id, $delta, $note = '', $staff_name = '') {
    global $wpdb;
    $acc = dry65_pk_get($id);
    if (!$acc) return null;
    $bal = (int) $acc->balance;
    if ($delta < 0) $delta = -min($bal, -$delta); // ne ispod 0
    $new = $bal + $delta;
    $now = dry65_pk_now();
    $wpdb->update(dry65_pk_table(), ['balance' => $new], ['id' => $id], ['%d'], ['%d']);
    $wpdb->insert(dry65_pk_txn_table(), [
        'account_id'    => $id,
        'delta'         => $delta,
        'balance_after' => $new,
        'note'          => $note,
        'staff_id'      => get_current_user_id(),
        'staff_name'    => $staff_name,
        'created_at'    => $now,
    ], ['%d','%d','%d','%s','%d','%s','%s']);
    return $new;
}

/* Da li paket ima bonus (tretman) koji još stoji — 1 po paketu, iskoristiv bilo kad. */
function dry65_pk_reward_available($acc) {
    return $acc->type === 'paket' && dry65_pk_effective_reward($acc) !== '' && empty($acc->reward_used_at);
}

/* Potroši jedini bonus: upiši datum + zabeleži u istoriju (ne dira broj feniranja). */
function dry65_pk_use_reward($id, $staff_name = '') {
    global $wpdb;
    $acc = dry65_pk_get($id);
    if (!$acc || $acc->type !== 'paket' || !empty($acc->reward_used_at)) return false;
    $now = dry65_pk_now();
    $wpdb->update(dry65_pk_table(), ['reward_used_at' => $now], ['id' => (int) $id], ['%s'], ['%d']);
    $wpdb->insert(dry65_pk_txn_table(), [
        'account_id'    => (int) $id,
        'delta'         => 0,
        'balance_after' => (int) $acc->balance,
        'note'          => 'Tretman iskorišćen: ' . (dry65_pk_effective_reward($acc) ?: 'bonus'),
        'staff_id'      => get_current_user_id(),
        'staff_name'    => $staff_name,
        'created_at'    => $now,
    ], ['%d','%d','%d','%s','%d','%s','%s']);
    return true;
}

/* Dugme-forma za osoblje (kartica + dashboard): jedna akcija = jedan submit uz potvrdu.
   $return prazno = ostaje u dashboardu; postavljeno = vraća na /kartica/{kod}. */
function dry65_pk_action_form($acc, $act, $label, $return = '', $bg = '') {
    $bg = $bg !== '' ? $bg : 'var(--clay,#b07a5a)';
    ob_start(); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;" onsubmit="return confirm('<?php echo esc_js($label . ' — ' . $acc->name); ?>?');">
      <input type="hidden" name="action" value="dry65_pk_spend">
      <input type="hidden" name="id" value="<?php echo (int) $acc->id; ?>">
      <input type="hidden" name="act" value="<?php echo esc_attr($act); ?>">
      <?php if ($return !== '') echo '<input type="hidden" name="return" value="' . esc_url($return) . '">'; ?>
      <?php wp_nonce_field('dry65_pk_spend'); ?>
      <button type="submit" style="cursor:pointer;border:0;border-radius:999px;padding:12px 24px;font-size:15px;font-weight:600;color:#fff;min-width:230px;background:<?php echo esc_attr($bg); ?>;"><?php echo esc_html($label); ?></button>
    </form>
    <?php return ob_get_clean();
}

/* ---- Radnice: PIN identifikacija na skeneru (ne login, uređaj je već ulogovan) ----
   Čuva se u opciji dry65_pk_staff: [ ['id'=>.., 'name'=>.., 'pin_hash'=>..], .. ]. */
function dry65_pk_staff_all() {
    $s = get_option('dry65_pk_staff', []);
    return is_array($s) ? $s : [];
}
/* Vrati ime radnice čiji PIN odgovara, ili '' ako nema. */
function dry65_pk_staff_verify($pin) {
    $pin = preg_replace('/\D/', '', (string) $pin);
    if ($pin === '') return '';
    foreach (dry65_pk_staff_all() as $w) {
        if (!empty($w['pin_hash']) && password_verify($pin, $w['pin_hash'])) return (string) $w['name'];
    }
    return '';
}
function dry65_pk_staff_add($name, $pin) {
    $name = sanitize_text_field($name);
    $pin  = preg_replace('/\D/', '', (string) $pin);
    if ($name === '' || !preg_match('/^\d{4}$/', $pin)) return false;
    if (dry65_pk_staff_verify($pin) !== '') return false; // PIN već zauzet
    $all = dry65_pk_staff_all();
    $all[] = ['id' => uniqid('r'), 'name' => $name, 'pin_hash' => password_hash($pin, PASSWORD_DEFAULT)];
    update_option('dry65_pk_staff', $all);
    return true;
}
function dry65_pk_staff_delete($id) {
    $all = array_values(array_filter(dry65_pk_staff_all(), function ($w) use ($id) {
        return ($w['id'] ?? '') !== $id;
    }));
    update_option('dry65_pk_staff', $all);
}

/* Poništi jednu transakciju (greška osoblja). Vrati stanje, ostavi trag. Samo admin. */
function dry65_pk_undo($txn_id) {
    global $wpdb;
    $tt = dry65_pk_txn_table();
    $t  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tt WHERE id = %d", (int) $txn_id));
    if (!$t || (int) $t->reversed === 1) return false;
    $acc = dry65_pk_get($t->account_id);
    if (!$acc) return false;
    $now   = dry65_pk_now();
    $delta = (int) $t->delta;
    $is_treatment = (strpos((string) $t->note, 'Tretman iskorišćen') === 0);

    // Vrati stanje: poništi efekat delte (klampuj na 0).
    $new = max(0, (int) $acc->balance - $delta);
    $wpdb->update(dry65_pk_table(), ['balance' => $new], ['id' => (int) $acc->id], ['%d'], ['%d']);
    // Ako je bio tretman, vrati ga kao dostupan.
    if ($is_treatment && !empty($acc->reward_used_at)) {
        $wpdb->update(dry65_pk_table(), ['reward_used_at' => null], ['id' => (int) $acc->id], ['%s'], ['%d']);
    }
    // Kompenzaciona stavka (sama se ne poništava dalje).
    $label = $is_treatment ? (string) $t->note : dry65_pk_txn_desc($acc->type, $t);
    $wpdb->insert($tt, [
        'account_id'    => (int) $acc->id,
        'delta'         => -$delta,
        'balance_after' => $new,
        'note'          => 'Poništeno: ' . $label,
        'staff_id'      => get_current_user_id(),
        'staff_name'    => '',
        'reversed'      => 1,
        'created_at'    => $now,
    ], ['%d','%d','%d','%s','%d','%s','%d','%s']);
    // Označi original kao poništen.
    $wpdb->update($tt, ['reversed' => 1], ['id' => (int) $t->id], ['%d'], ['%d']);
    return true;
}

/* Naslov iznad broja: paket = „Iskorišćeno", vaučer = „Preostalo". */
function dry65_pk_status_label($acc) {
    return $acc->type === 'vaucer' ? 'Preostalo' : 'Iskorišćeno';
}

/* Formatiranje stanja za prikaz. Paket = „X od Y feniranja" (iskorišćeno). */
function dry65_pk_balance_text($acc) {
    if ($acc->type === 'vaucer') {
        return number_format((int) $acc->balance, 0, ',', '.') . ' din';
    }
    $used = max(0, (int) $acc->initial - (int) $acc->balance);
    return $used . ' od ' . (int) $acc->initial . ' feniranja';
}

/* Da li je istekao (ako ima datum). */
function dry65_pk_is_expired($acc) {
    if (empty($acc->expires_at)) return false;
    $today = (new DateTime('now', new DateTimeZone('Europe/Belgrade')))->format('Y-m-d');
    return $acc->expires_at < $today;
}

/* Ljudski opis transakcije. */
function dry65_pk_txn_desc($acc_type, $txn) {
    $d = (int) $txn->delta;
    if ($acc_type === 'vaucer') {
        if ($d < 0) return 'Potrošeno ' . number_format(-$d, 0, ',', '.') . ' din';
        return 'Uplata ' . number_format($d, 0, ',', '.') . ' din';
    }
    if ($d < 0) return 'Iskorišćeno ' . (-$d) . ' feniranje' . ((-$d) === 1 ? '' : 'a');
    return 'Paket ' . $d . ' feniranja';
}

/* URL kartice gosta. */
function dry65_pk_card_url($code) {
    return home_url('/kartica/' . $code . '/');
}

/* ---- QR (generisan lokalno u browseru, bez eksternog servisa — kod je tajni) ---- */
function dry65_pk_qr_lib_url() {
    return get_template_directory_uri() . '/assets/js/qrcode.min.js';
}

/* Odštampaj <script src> QR biblioteke jednom po stranici. */
function dry65_pk_qr_lib_tag() {
    static $printed = false;
    if ($printed) return '';
    $printed = true;
    return '<script src="' . esc_url(dry65_pk_qr_lib_url()) . '"></script>' . "\n";
}

/* HTML za QR koji kodira $target (URL kartice). SVG, crta se u browseru. */
function dry65_pk_qr_html($target, $cell = 5) {
    $dom_id = 'dry65-qr-' . wp_generate_password(6, false, false);
    ob_start();
    echo dry65_pk_qr_lib_tag();
    ?>
    <div id="<?php echo esc_attr($dom_id); ?>" data-qr="<?php echo esc_attr($target); ?>" data-cell="<?php echo (int) $cell; ?>" style="display:inline-block;line-height:0;"></div>
    <script>
    (function(){
      var el=document.getElementById(<?php echo wp_json_encode($dom_id); ?>);
      if(!el) return;
      if(typeof qrcode==='undefined'){ el.style.lineHeight=''; el.textContent=el.getAttribute('data-qr'); return; }
      var qr=qrcode(0,'M'); qr.addData(el.getAttribute('data-qr')); qr.make();
      el.innerHTML=qr.createSvgTag({cellSize:parseInt(el.getAttribute('data-cell'),10)||5,margin:0,scalable:true});
      var svg=el.querySelector('svg'); if(svg){ svg.style.width='100%'; svg.style.height='auto'; svg.removeAttribute('width'); svg.removeAttribute('height'); }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* Pošalji link kartice gostu na email (wp_mail). Vrati true/false. */
function dry65_pk_send_email($acc) {
    if (empty($acc->email) || !is_email($acc->email)) return false;
    $url = dry65_pk_card_url($acc->code);
    $lines = [
        'Poštovani/a ' . $acc->name . ',',
        '',
        'Hvala što ste izabrali Dry65.',
        ($acc->type === 'vaucer' ? 'Vaučer' : ($acc->plan ?: 'Paket')) . ', ' . mb_strtolower(dry65_pk_status_label($acc)) . ': ' . dry65_pk_balance_text($acc) . '.',
    ];
    if ($acc->type === 'paket' && !empty($acc->reward)) $lines[] = 'Po završetku dobijate: ' . $acc->reward . '.';
    if (!empty($acc->expires_at)) $lines[] = 'Važi do: ' . $acc->expires_at . '.';
    $lines[] = '';
    $lines[] = 'Vaše stanje uvek možete pogledati na linku:';
    $lines[] = $url;
    $lines[] = '';
    $lines[] = 'Dry65, West 65, Novi Beograd.';

    // Pošalji kao office@dry65.com (samo za ovaj mejl, ne diramo ostale)
    $from = (function_exists('dry65_biz') && !empty(dry65_biz()['email'])) ? dry65_biz()['email'] : 'office@dry65.com';
    $set_from = function () use ($from) { return $from; };
    $set_name = function () { return 'Dry65'; };
    add_filter('wp_mail_from', $set_from);
    add_filter('wp_mail_from_name', $set_name);
    $ok = wp_mail($acc->email, 'Vaša Dry65 kartica', implode("\n", $lines), ['Reply-To: Dry65 <' . $from . '>']);
    remove_filter('wp_mail_from', $set_from);
    remove_filter('wp_mail_from_name', $set_name);
    return $ok;
}

/* ============================================================
   ADMIN
   ============================================================ */
add_action('admin_menu', function () {
    add_menu_page(
        'Paketi i vaučeri',
        'Paketi i vaučeri',
        DRY65_PK_CAP,
        'dry65-paketi',
        'dry65_pk_admin_page',
        'dashicons-tickets-alt',
        4
    );
    add_submenu_page('dry65-paketi', 'Kupci', 'Kupci', DRY65_PK_CAP, 'dry65-kupci', 'dry65_pk_customers_page');
    add_submenu_page('dry65-paketi', 'Radnice (PIN za skener)', 'Radnice', DRY65_PK_CAP, 'dry65-radnice', 'dry65_pk_staff_page');
});

/* Stranica „Radnice": dodaj ime + 4-cifreni PIN, obriši. PIN se čuva heširan. */
function dry65_pk_staff_page() {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    $staff = dry65_pk_staff_all();
    ?>
    <div class="wrap">
      <h1>Radnice</h1>
      <p style="max-width:640px;color:#555;">Svaka radnica ima svoj 4-cifreni PIN. Na strani <code>/skener</code> unese PIN da se zna KO skida pečate; kad završi, klikne „Završi" pa sledeća unese svoj. PIN nije lozinka za sajt (telefon je već ulogovan) nego oznaka ko radi.</p>
      <?php if (isset($_GET['added'])): ?><div class="notice notice-success is-dismissible"><p>Radnica dodata.</p></div><?php endif; ?>
      <?php if (isset($_GET['err'])): ?><div class="notice notice-error is-dismissible"><p>Nije dodato — ime je obavezno, PIN mora biti tačno 4 cifre i ne sme se poklapati sa postojećim.</p></div><?php endif; ?>
      <?php if (isset($_GET['deleted'])): ?><div class="notice notice-success is-dismissible"><p>Radnica obrisana.</p></div><?php endif; ?>

      <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:flex-start;margin-top:12px;">
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:320px;">
          <h2 style="margin-top:0;">Dodaj radnicu</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="dry65_pk_staff_add">
            <?php wp_nonce_field('dry65_pk_staff_add'); ?>
            <p><label>Ime<br><input type="text" name="name" class="regular-text" required style="width:100%;"></label></p>
            <p><label>PIN (4 cifre)<br><input type="text" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required style="width:100%;letter-spacing:0.3em;font-size:18px;"></label></p>
            <p><button type="submit" class="button button-primary">Dodaj</button></p>
          </form>
        </div>

        <div style="flex:1;min-width:320px;">
          <table class="widefat striped" style="max-width:520px;">
            <thead><tr><th>Ime</th><th>PIN</th><th></th></tr></thead>
            <tbody>
              <?php if (!$staff): ?>
                <tr><td colspan="3" style="color:#777;">Još nema radnica. Dodaj prvu levo.</td></tr>
              <?php else: foreach ($staff as $w): ?>
                <tr>
                  <td><?php echo esc_html($w['name']); ?></td>
                  <td style="color:#999;">•••• <span style="font-size:12px;">(skriven)</span></td>
                  <td style="text-align:right;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Obrisati <?php echo esc_js($w['name']); ?>?');" style="display:inline;">
                      <input type="hidden" name="action" value="dry65_pk_staff_delete">
                      <input type="hidden" name="id" value="<?php echo esc_attr($w['id']); ?>">
                      <?php wp_nonce_field('dry65_pk_staff_delete'); ?>
                      <button type="submit" class="button button-small">Obriši</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
          <p style="color:#999;font-size:12px;max-width:520px;">PIN se ne prikazuje iz sigurnosti. Ako ga radnica zaboravi, obriši je i dodaj ponovo sa novim PIN-om.</p>
        </div>
      </div>
    </div>
    <?php
}

add_action('admin_post_dry65_pk_staff_add', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_pk_staff_add');
    $ok = dry65_pk_staff_add($_POST['name'] ?? '', $_POST['pin'] ?? '');
    wp_redirect(admin_url('admin.php?page=dry65-radnice&' . ($ok ? 'added=1' : 'err=1')));
    exit;
});
add_action('admin_post_dry65_pk_staff_delete', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_pk_staff_delete');
    dry65_pk_staff_delete(sanitize_text_field(wp_unslash($_POST['id'] ?? '')));
    wp_redirect(admin_url('admin.php?page=dry65-radnice&deleted=1'));
    exit;
});

function dry65_pk_admin_page() {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    global $wpdb;
    $acc_id = isset($_GET['account']) ? (int) $_GET['account'] : 0;
    if ($acc_id) { dry65_pk_admin_detail($acc_id); return; }
    $cust_id = isset($_GET['customer']) ? (int) $_GET['customer'] : 0;
    if ($cust_id) { dry65_pk_customer_detail($cust_id); return; }

    $s = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $where = '';
    if ($s !== '') {
        $like = '%' . $wpdb->esc_like($s) . '%';
        $where = $wpdb->prepare(" WHERE name LIKE %s OR phone LIKE %s OR code LIKE %s", $like, $like, $like);
    }
    $rows = $wpdb->get_results("SELECT * FROM " . dry65_pk_table() . "$where ORDER BY id DESC LIMIT 200");
    ?>
    <div class="wrap">
      <h1>Paketi i vaučeri</h1>
      <?php if (isset($_GET['created'])): ?><div class="notice notice-success is-dismissible"><p>Nalog napravljen.</p></div><?php endif; ?>
      <?php if (isset($_GET['err'])): ?><div class="notice notice-error is-dismissible"><p>Nalog nije napravljen — ime i telefon su obavezni.</p></div><?php endif; ?>

      <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:flex-start;margin-top:12px;">
        <!-- Novi nalog -->
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:340px;">
          <h2 style="margin-top:0;">Novi nalog</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="dry65_pk_create">
            <?php wp_nonce_field('dry65_pk_create'); ?>
            <p><label>Ime i prezime<br><input type="text" name="name" class="regular-text" required style="width:100%;"></label></p>
            <p><label>Telefon <span style="color:#d63638;">*</span><br><input type="tel" name="phone" class="regular-text" required style="width:100%;"></label></p>
            <p><label>Email (opciono, za slanje linka kartice)<br><input type="email" name="email" class="regular-text" style="width:100%;"></label></p>
            <p><label>Tip<br>
              <select name="type" id="dry65-pk-type" style="width:100%;">
                <option value="paket">Paket (feniranja)</option>
                <option value="vaucer">Vaučer (dinari)</option>
              </select>
            </label></p>
            <div id="dry65-pk-paket">
              <p><label>Plan<br>
                <select name="preset" style="width:100%;">
                  <?php foreach (dry65_pk_presets() as $k => $p): ?>
                  <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($p['name'] . ' — ' . $p['sessions'] . ' (' . $p['reward'] . ')'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label></p>
            </div>
            <div id="dry65-pk-vaucer" style="display:none;">
              <p><label>Iznos (din)<br><input type="number" name="amount" min="0" step="1" value="12000" style="width:100%;"></label></p>
            </div>
            <p><label>Ističe (1 mesec po pravilu)<br><input type="date" name="expires_at" value="<?php echo esc_attr(dry65_pk_default_expiry()); ?>" style="width:100%;"></label></p>
            <p><label>Napomena (opciono)<br><input type="text" name="note" style="width:100%;"></label></p>
            <p><button type="submit" class="button button-primary">Napravi nalog</button></p>
          </form>
          <script>
            (function(){
              var t=document.getElementById('dry65-pk-type'),
                  pk=document.getElementById('dry65-pk-paket'),
                  vc=document.getElementById('dry65-pk-vaucer');
              function upd(){ var v=t.value==='vaucer'; pk.style.display=v?'none':''; vc.style.display=v?'':'none'; }
              if(t){ t.addEventListener('change',upd); upd(); }
            })();
          </script>
        </div>

        <!-- Lista / pretraga -->
        <div style="flex:1;min-width:360px;">
          <form method="get" style="margin-bottom:12px;">
            <input type="hidden" name="page" value="dry65-paketi">
            <input type="search" name="s" value="<?php echo esc_attr($s); ?>" placeholder="Pretraga: ime, telefon ili kod" style="width:280px;">
            <button class="button">Traži</button>
          </form>
          <table class="widefat striped">
            <thead><tr><th>Ime</th><th>Tip</th><th>Stanje</th><th>Telefon</th><th></th></tr></thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="5"><em><?php echo $s !== '' ? 'Nema rezultata.' : 'Još nema naloga.'; ?></em></td></tr>
              <?php else: foreach ($rows as $r):
                $done = $r->type === 'paket' && (int) $r->balance === 0;
                $exp  = dry65_pk_is_expired($r);
              ?>
              <tr>
                <td><strong><?php echo esc_html($r->name); ?></strong></td>
                <td><?php echo $r->type === 'vaucer' ? 'Vaučer' : 'Paket'; ?></td>
                <td>
                  <?php echo esc_html(dry65_pk_balance_text($r)); ?>
                  <?php if ($done): ?><span style="color:#b26a00;font-weight:600;"> 🎁 nega</span><?php endif; ?>
                  <?php if ($exp): ?><span style="color:#a00;"> (isteklo)</span><?php endif; ?>
                </td>
                <td><?php echo esc_html($r->phone); ?></td>
                <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=dry65-paketi&account=' . $r->id)); ?>">Otvori</a></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
}

function dry65_pk_admin_detail($id) {
    $acc = dry65_pk_get($id);
    if (!$acc) { echo '<div class="wrap"><h1>Nalog ne postoji.</h1></div>'; return; }
    $txns = dry65_pk_txns($id);
    $url  = dry65_pk_card_url($acc->code);
    $done = $acc->type === 'paket' && (int) $acc->balance === 0;
    $exp  = dry65_pk_is_expired($acc);
    ?>
    <div class="wrap">
      <p><a href="<?php echo esc_url(admin_url('admin.php?page=dry65-paketi')); ?>">&larr; Svi nalozi</a></p>
      <h1><?php echo esc_html($acc->name); ?> <span style="font-size:14px;color:#666;font-weight:400;">(<?php echo esc_html($acc->type === 'vaucer' ? 'Vaučer' : ($acc->plan ?: 'Paket')); ?>)</span></h1>
      <?php if (!empty($acc->customer_id) && ($cust = dry65_pk_customer_get($acc->customer_id))): ?>
      <p style="margin:-6px 0 10px;">Kupac: <a href="<?php echo esc_url(admin_url('admin.php?page=dry65-paketi&customer=' . (int) $acc->customer_id)); ?>"><strong><?php echo esc_html($cust->name ?: $cust->phone); ?></strong></a> &middot; ceo profil i istorija</p>
      <?php endif; ?>
      <?php if (isset($_GET['done'])): ?><div class="notice notice-success is-dismissible"><p>Sačuvano.</p></div><?php endif; ?>
      <?php if (isset($_GET['mail'])): ?><div class="notice notice-<?php echo $_GET['mail'] === '1' ? 'success' : 'error'; ?> is-dismissible"><p><?php echo $_GET['mail'] === '1' ? 'Link poslat na email.' : 'Slanje nije uspelo — proveri email adresu / mail podešavanja servera.'; ?></p></div><?php endif; ?>

      <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:flex-start;">
        <!-- Stanje + akcija -->
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px 22px;max-width:360px;">
          <div style="font-size:13px;color:#666;"><?php echo esc_html(dry65_pk_status_label($acc)); ?></div>
          <div style="font-size:30px;font-weight:700;margin:4px 0 2px;"><?php echo esc_html(dry65_pk_balance_text($acc)); ?></div>
          <?php if ($acc->type === 'paket' && $acc->reward): ?><p style="color:#555;margin:4px 0;font-size:13px;">Tretman (<strong><?php echo esc_html($acc->reward); ?></strong>): <?php echo empty($acc->reward_used_at) ? '<span style="color:#1f7a4d;font-weight:600;">dostupan</span>' : 'iskorišćen ' . esc_html(mysql2date('d.m.Y.', $acc->reward_used_at)); ?></p><?php endif; ?>
          <?php if ($done): ?><p style="color:#777;margin:6px 0;font-size:13px;">Sva feniranja iz paketa su iskorišćena.</p><?php endif; ?>
          <?php if ($exp): ?><p style="color:#a00;font-weight:600;margin:6px 0;">Kartica je istekla (<?php echo esc_html($acc->expires_at); ?>).</p><?php elseif (!empty($acc->expires_at)): ?><p style="color:#666;margin:6px 0;font-size:13px;">Važi do <?php echo esc_html($acc->expires_at); ?></p><?php endif; ?>

          <hr>
          <?php if ($acc->type === 'vaucer'): ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="dry65_pk_spend">
            <input type="hidden" name="id" value="<?php echo (int) $acc->id; ?>">
            <?php wp_nonce_field('dry65_pk_spend'); ?>
            <p><label>Skini iznos (din)<br><input type="number" name="amount" min="1" step="1" style="width:100%;" required></label></p>
            <p><label>Napomena (opciono)<br><input type="text" name="note" placeholder="npr. Feniranje" style="width:100%;"></label></p>
            <button type="submit" class="button button-primary" <?php disabled((int) $acc->balance, 0); ?>>Skini iznos</button>
          </form>
          <?php else:
            $canFen   = (int) $acc->balance > 0 && !$exp;
            $canBonus = dry65_pk_reward_available($acc) && !$exp;
          ?>
            <p style="color:#555;margin-top:0;">Zabeleži dolazak:</p>
            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-start;">
            <?php
            if ($canFen)              echo dry65_pk_action_form($acc, 'feniranje', 'Feniranje  (−1)');
            if ($canFen && $canBonus) echo dry65_pk_action_form($acc, 'feniranje_tretman', 'Feniranje + tretman');
            if ($canBonus)            echo dry65_pk_action_form($acc, 'tretman', 'Samo tretman' . ($acc->reward ? ' — ' . $acc->reward : ''), '', '#6b5b95');
            if (!$canFen && !$canBonus) echo '<p style="color:#777;margin:0;">' . ($exp ? 'Kartica je istekla.' : 'Paket završen (0 feniranja, tretman iskorišćen).') . '</p>';
            ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Kartica gosta (link) -->
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px 22px;max-width:360px;">
          <h2 style="margin-top:0;font-size:16px;">Kartica gosta (link za gledanje)</h2>
          <p style="color:#666;font-size:12.5px;margin-top:0;">Pošalji gostu ovaj link ili stavi QR na karticu. Samo za gledanje — gost ne može da menja.</p>
          <code style="display:block;background:#f0f0f1;padding:10px 12px;border-radius:6px;user-select:all;font-size:13px;word-break:break-all;"><?php echo esc_html($url); ?></code>
          <div style="margin-top:12px;width:150px;"><?php echo dry65_pk_qr_html($url, 4); ?></div>
          <p style="color:#666;font-size:12px;margin-top:6px;">QR za štampu na kartici / wallet. Osoblje ga skenira (ulogovano) → otvara karticu → dugme „− 1 feniranje".</p>
          <p style="margin-top:8px;"><a class="button" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Otvori karticu ↗</a></p>

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:14px;border-top:1px solid #eee;padding-top:12px;">
            <input type="hidden" name="action" value="dry65_pk_sendlink">
            <input type="hidden" name="id" value="<?php echo (int) $acc->id; ?>">
            <?php wp_nonce_field('dry65_pk_sendlink'); ?>
            <label style="font-size:12px;color:#555;">Email gosta (za slanje linka)</label>
            <input type="email" name="email" value="<?php echo esc_attr($acc->email); ?>" placeholder="ime@email.com" style="width:100%;margin:4px 0 8px;">
            <button type="submit" class="button">✉︎ Pošalji link na email</button>
            <span style="color:#999;font-size:12px;margin-left:6px;">SMS dolazi kasnije (uz provajdera)</span>
          </form>
          <p style="color:#999;font-size:12px;margin-top:10px;">Kod: <strong><?php echo esc_html($acc->code); ?></strong><?php if ($acc->phone) echo ' &middot; Tel: ' . esc_html($acc->phone); ?><?php if ($acc->expires_at) echo ' &middot; Ističe: ' . esc_html($acc->expires_at); ?></p>
        </div>
      </div>

      <!-- Produži / zamrzni rok -->
      <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px 22px;max-width:360px;margin-top:26px;">
        <h2 style="margin-top:0;font-size:16px;">Produži / zamrzni rok</h2>
        <p style="color:#666;font-size:12.5px;margin-top:0;">Ako je gost sprečen (bolest, put), pomeri datum isteka.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="dry65_pk_extend">
          <input type="hidden" name="id" value="<?php echo (int) $acc->id; ?>">
          <?php wp_nonce_field('dry65_pk_extend'); ?>
          <p><label>Novi rok<br><input type="date" id="dry65-pk-newexp" name="new_date" value="<?php echo esc_attr($acc->expires_at ?: dry65_pk_default_expiry()); ?>" required style="width:100%;"></label></p>
          <p style="display:flex;gap:6px;margin:0 0 10px;">
            <button type="button" class="button button-small dry65-pk-add" data-add="15">+15 dana</button>
            <button type="button" class="button button-small dry65-pk-add" data-add="30">+30 dana</button>
          </p>
          <p><label>Razlog (opciono)<br><input type="text" name="note" placeholder="npr. bolest, put" style="width:100%;"></label></p>
          <button type="submit" class="button button-primary">Sačuvaj rok</button>
        </form>
        <script>
          (function(){
            var inp=document.getElementById('dry65-pk-newexp');
            document.querySelectorAll('.dry65-pk-add').forEach(function(b){
              b.addEventListener('click',function(){
                var base=inp.value?new Date(inp.value):new Date();
                base.setDate(base.getDate()+parseInt(b.dataset.add,10));
                inp.value=base.toISOString().slice(0,10);
              });
            });
          })();
        </script>
      </div>

      <!-- Istorija -->
      <h2 style="margin-top:28px;">Istorija</h2>
      <?php $is_admin = current_user_can(DRY65_PK_ADMIN_CAP); ?>
      <table class="widefat striped" style="max-width:800px;">
        <thead><tr><th>Datum</th><th>Promena</th><th>Radnica</th><th>Stanje posle</th><?php if ($is_admin): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
          <?php foreach ($txns as $t):
            $actor = !empty($t->staff_name) ? $t->staff_name : '';
            if ($actor === '' && !empty($t->staff_id)) { $u = get_userdata((int) $t->staff_id); if ($u) $actor = $u->display_name; }
            $note_str     = (string) $t->note;
            $note_is_rev  = (strpos($note_str, 'Poništeno') === 0);
            $note_is_trt  = (strpos($note_str, 'Tretman iskorišćen') === 0);
            $desc         = ($note_is_rev || $note_is_trt) ? $note_str : dry65_pk_txn_desc($acc->type, $t);
            $was_reversed = ((int) $t->reversed === 1 && !$note_is_rev);
            $can_undo     = ((int) $t->reversed === 0 && ((int) $t->delta < 0 || $note_is_trt));
          ?>
          <tr<?php if ($was_reversed) echo ' style="opacity:0.6;"'; ?>>
            <td><?php echo esc_html(mysql2date('d.m.Y. H:i', $t->created_at)); ?></td>
            <td>
              <?php echo esc_html($desc); ?>
              <?php if (!$note_is_rev && !$note_is_trt && $note_str && $note_str !== 'Paket otvoren' && $note_str !== 'Vaučer otvoren') echo ' <span style="color:#888;">(' . esc_html($note_str) . ')</span>'; ?>
              <?php if ($was_reversed) echo ' <span style="color:#a00;font-size:12px;">(poništeno)</span>'; ?>
            </td>
            <td><?php echo $actor !== '' ? esc_html($actor) : '<span style="color:#bbb;">—</span>'; ?></td>
            <td><?php echo $acc->type === 'vaucer' ? esc_html(number_format((int) $t->balance_after, 0, ',', '.') . ' din') : (int) $t->balance_after; ?></td>
            <?php if ($is_admin): ?>
            <td style="text-align:right;">
              <?php if ($can_undo): ?>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Poništiti ovu stavku i vratiti stanje?');" style="display:inline;">
                <input type="hidden" name="action" value="dry65_pk_undo">
                <input type="hidden" name="txn" value="<?php echo (int) $t->id; ?>">
                <input type="hidden" name="id" value="<?php echo (int) $acc->id; ?>">
                <?php wp_nonce_field('dry65_pk_undo'); ?>
                <button type="submit" class="button button-small">Poništi</button>
              </form>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

/* Lista kupaca (pretraga po imenu/telefonu/emailu). */
function dry65_pk_customers_page() {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    global $wpdb;
    $ct = dry65_pk_cust_table();
    $s  = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $where = '';
    if ($s !== '') {
        $like = '%' . $wpdb->esc_like($s) . '%';
        $where = $wpdb->prepare(" WHERE name LIKE %s OR phone LIKE %s OR email LIKE %s", $like, $like, $like);
    }
    $rows = $wpdb->get_results("SELECT * FROM $ct$where ORDER BY id DESC LIMIT 300");
    ?>
    <div class="wrap">
      <h1>Kupci</h1>
      <p style="color:#555;max-width:640px;">Osoba iznad paketa. Jedan kupac može kroz vreme imati više paketa; ovde vidiš njegovu istoriju i zbirove. Kupac se prepoznaje po telefonu.</p>
      <form method="get" style="margin:12px 0;">
        <input type="hidden" name="page" value="dry65-kupci">
        <input type="search" name="s" value="<?php echo esc_attr($s); ?>" placeholder="Ime, telefon ili email" style="width:280px;">
        <button class="button">Traži</button>
      </form>
      <table class="widefat striped" style="max-width:860px;">
        <thead><tr><th>Ime</th><th>Telefon</th><th>Email</th><th>Paketa</th><th>Aktivno</th><th>Član od</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="6" style="color:#777;">Nema kupaca<?php echo $s !== '' ? ' za tu pretragu' : ''; ?>.</td></tr>
          <?php else: foreach ($rows as $c): $st = dry65_pk_customer_stats($c->id); ?>
          <tr>
            <td><a href="<?php echo esc_url(admin_url('admin.php?page=dry65-paketi&customer=' . (int) $c->id)); ?>"><strong><?php echo esc_html($c->name ?: '(bez imena)'); ?></strong></a></td>
            <td><?php echo esc_html($c->phone); ?></td>
            <td><?php echo esc_html($c->email); ?></td>
            <td><?php echo (int) $st['paketa'] + (int) $st['vaucera']; ?></td>
            <td><?php echo (int) $st['aktivnih']; ?></td>
            <td><?php echo esc_html(mysql2date('d.m.Y.', $c->created_at)); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}

/* Profil kupca: zbirovi + svi paketi + „novi paket za kupca". */
function dry65_pk_customer_detail($id) {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    $c = dry65_pk_customer_get($id);
    if (!$c) { echo '<div class="wrap"><h1>Kupac ne postoji.</h1></div>'; return; }
    $accs = dry65_pk_customer_accounts($id);
    $st   = dry65_pk_customer_stats($id);
    ?>
    <div class="wrap">
      <p><a href="<?php echo esc_url(admin_url('admin.php?page=dry65-kupci')); ?>">&larr; Svi kupci</a></p>
      <h1><?php echo esc_html($c->name ?: '(bez imena)'); ?></h1>
      <p style="color:#555;margin-top:0;">
        <?php echo esc_html($c->phone); ?><?php if ($c->email) echo ' &middot; ' . esc_html($c->email); ?>
        &middot; Član od <?php echo esc_html(mysql2date('d.m.Y.', $c->created_at)); ?>
        <?php if ($c->source === 'web') echo ' &middot; <span style="color:#2271b1;">registrovao se online</span>'; ?>
      </p>

      <div style="display:flex;gap:14px;flex-wrap:wrap;margin:16px 0 24px;">
        <?php
        $cards = [
          ['Paketa', (int) $st['paketa']],
          ['Feniranja', (int) $st['fen_iskorisceno'] . ' / ' . (int) $st['fen_ukupno']],
          ['Tretmana', (int) $st['tretmani']],
          ['Aktivno sada', (int) $st['aktivnih']],
        ];
        if ((int) $st['vaucera']) $cards[] = ['Vaučera', (int) $st['vaucera']];
        foreach ($cards as $cd): ?>
          <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px 18px;min-width:120px;">
            <div style="font-size:12px;color:#666;"><?php echo esc_html($cd[0]); ?></div>
            <div style="font-size:24px;font-weight:700;"><?php echo esc_html($cd[1]); ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <h2>Paketi i vaučeri</h2>
      <table class="widefat striped" style="max-width:860px;">
        <thead><tr><th>Plan</th><th>Stanje</th><th>Tretman</th><th>Rok</th><th>Napravljen</th><th></th></tr></thead>
        <tbody>
          <?php if (!$accs): ?><tr><td colspan="6" style="color:#777;">Još nema paketa. Dodaj ispod.</td></tr>
          <?php else: foreach ($accs as $a):
            $exp = dry65_pk_is_expired($a); $done = $a->type === 'paket' && (int) $a->balance === 0;
          ?>
          <tr>
            <td><?php echo esc_html($a->type === 'vaucer' ? 'Vaučer' : ($a->plan ?: 'Paket')); ?></td>
            <td><?php echo esc_html(dry65_pk_balance_text($a)); ?><?php if ($done) echo ' ✓'; ?></td>
            <td><?php echo $a->type === 'paket' ? ($a->reward_used_at ? 'iskorišćen' : ($a->reward ? 'dostupan' : '—')) : '—'; ?></td>
            <td><?php echo $a->expires_at ? esc_html(mysql2date('d.m.Y.', $a->expires_at)) . ($exp ? ' (istekao)' : '') : '—'; ?></td>
            <td><?php echo esc_html(mysql2date('d.m.Y.', $a->created_at)); ?></td>
            <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=dry65-paketi&account=' . (int) $a->id)); ?>">Otvori</a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <h2 style="margin-top:28px;">Novi paket za kupca</h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:340px;">
        <input type="hidden" name="action" value="dry65_pk_create">
        <input type="hidden" name="customer_id" value="<?php echo (int) $c->id; ?>">
        <input type="hidden" name="name" value="<?php echo esc_attr($c->name); ?>">
        <input type="hidden" name="phone" value="<?php echo esc_attr($c->phone); ?>">
        <input type="hidden" name="email" value="<?php echo esc_attr($c->email); ?>">
        <?php wp_nonce_field('dry65_pk_create'); ?>
        <p><label>Tip<br>
          <select name="type" id="dry65-cust-type" style="width:100%;">
            <option value="paket">Paket (feniranja)</option>
            <option value="vaucer">Vaučer (dinari)</option>
          </select></label></p>
        <div id="dry65-cust-paket">
          <p><label>Plan<br><select name="preset" style="width:100%;">
            <?php foreach (dry65_pk_presets() as $k => $p): ?>
            <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($p['name'] . ' — ' . $p['sessions'] . ' (' . $p['reward'] . ')'); ?></option>
            <?php endforeach; ?>
          </select></label></p>
        </div>
        <div id="dry65-cust-vaucer" style="display:none;">
          <p><label>Iznos (din)<br><input type="number" name="amount" min="0" step="1" value="12000" style="width:100%;"></label></p>
        </div>
        <p><label>Ističe<br><input type="date" name="expires_at" value="<?php echo esc_attr(dry65_pk_default_expiry()); ?>" style="width:100%;"></label></p>
        <p><button type="submit" class="button button-primary">Napravi paket</button></p>
      </form>
      <script>
        (function(){var t=document.getElementById('dry65-cust-type'),pk=document.getElementById('dry65-cust-paket'),vc=document.getElementById('dry65-cust-vaucer');
        function u(){var v=t.value==='vaucer';pk.style.display=v?'none':'';vc.style.display=v?'':'none';}
        if(t){t.addEventListener('change',u);u();}})();
      </script>
    </div>
    <?php
}

/* ---- Admin akcije ---- */
add_action('admin_post_dry65_pk_create', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_pk_create');
    $name  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    // Telefon obavezan; email opcion (Vlada: izbaci email kao obavezan).
    if ($name === '' || $phone === '') {
        wp_redirect(admin_url('admin.php?page=dry65-paketi&err=1')); exit;
    }
    $type = sanitize_key($_POST['type'] ?? 'paket');

    if ($type === 'vaucer') {
        $initial = (int) ($_POST['amount'] ?? 0);
        $plan = $reward = '';
    } else {
        $type    = 'paket';
        $presets = dry65_pk_presets();
        $key     = sanitize_key($_POST['preset'] ?? '');
        $p       = $presets[$key] ?? reset($presets);
        $initial = (int) $p['sessions'];
        $plan    = $p['name'];
        $reward  = $p['reward'];
    }

    $expiry = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['expires_at'] ?? '') ? $_POST['expires_at'] : dry65_pk_default_expiry();

    // Kupac: iz profila (posted customer_id) ili nađi/napravi po telefonu.
    $customer_id = (int) ($_POST['customer_id'] ?? 0);
    if (!$customer_id || !dry65_pk_customer_get($customer_id)) {
        $customer_id = dry65_pk_customer_get_or_create($name, $phone, $email, 'salon');
    }

    $id = dry65_pk_create(
        $name,
        $phone,
        $type,
        $initial,
        $expiry,
        sanitize_text_field(wp_unslash($_POST['note'] ?? '')),
        $plan,
        $reward,
        $email,
        $customer_id
    );
    wp_redirect(admin_url('admin.php?page=dry65-paketi&account=' . $id . '&created=1'));
    exit;
});

add_action('admin_post_dry65_pk_sendlink', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_pk_sendlink');
    global $wpdb;
    $id  = (int) ($_POST['id'] ?? 0);
    $acc = dry65_pk_get($id);
    $sent = false;
    if ($acc) {
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if ($email !== '' && $email !== $acc->email) {
            $wpdb->update(dry65_pk_table(), ['email' => $email], ['id' => $id], ['%s'], ['%d']);
            $acc->email = $email;
        }
        $sent = dry65_pk_send_email($acc);
    }
    wp_redirect(admin_url('admin.php?page=dry65-paketi&account=' . $id . '&mail=' . ($sent ? '1' : '0')));
    exit;
});

add_action('admin_post_dry65_pk_undo', function () {
    if (!current_user_can(DRY65_PK_ADMIN_CAP)) wp_die('Samo administrator može da poništi.');
    check_admin_referer('dry65_pk_undo');
    dry65_pk_undo((int) ($_POST['txn'] ?? 0));
    wp_redirect(admin_url('admin.php?page=dry65-paketi&account=' . (int) ($_POST['id'] ?? 0) . '&done=1'));
    exit;
});

add_action('admin_post_dry65_pk_extend', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_pk_extend');
    $id = (int) ($_POST['id'] ?? 0);
    dry65_pk_extend($id, (string) ($_POST['new_date'] ?? ''), sanitize_text_field(wp_unslash($_POST['note'] ?? '')));
    wp_redirect(admin_url('admin.php?page=dry65-paketi&account=' . $id . '&done=1'));
    exit;
});

add_action('admin_post_dry65_pk_spend', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_pk_spend');
    $id  = (int) ($_POST['id'] ?? 0);
    $acc = dry65_pk_get($id);
    if ($acc) {
        if ($acc->type === 'vaucer') {
            $amount = max(1, (int) ($_POST['amount'] ?? 0));
            $note   = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
            dry65_pk_apply($id, -$amount, $note !== '' ? $note : 'Potrošnja');
        } else {
            // Paket: act = feniranje | feniranje_tretman | tretman
            $act = sanitize_key($_POST['act'] ?? 'feniranje');
            if ($act === 'tretman') {
                dry65_pk_use_reward($id);                                  // samo bonus, ne dira feniranja
            } elseif ($act === 'feniranje_tretman') {
                if ((int) $acc->balance > 0) dry65_pk_apply($id, -1, 'Feniranje');
                dry65_pk_use_reward($id);
            } else {
                dry65_pk_apply($id, -1, 'Feniranje');                      // jedan dolazak
            }
        }
    }
    // Skidanje sa /kartica/{kod} (QR flow) -> vrati na karticu; inače u dashboard.
    $return = isset($_POST['return']) ? wp_unslash($_POST['return']) : '';
    if ($return !== '') {
        $return = add_query_arg('sk', '1', $return);
        wp_safe_redirect($return); exit;
    }
    wp_redirect(admin_url('admin.php?page=dry65-paketi&account=' . $id . '&done=1'));
    exit;
});

/* ============================================================
   FRONTEND — /kartica/{kod}  (read-only za gosta)
   ============================================================ */
add_action('init', function () {
    add_rewrite_rule('^kartica/([^/]+)/?$', 'index.php?dry65_kartica=$matches[1]', 'top');
    add_rewrite_rule('^skener/?$', 'index.php?dry65_skener=1', 'top');
    add_rewrite_rule('^registracija/?$', 'index.php?dry65_registracija=1', 'top');
    if (get_option('dry65_pk_rewrite_v') !== '3') {
        flush_rewrite_rules(false);
        update_option('dry65_pk_rewrite_v', '3');
    }
});
add_filter('query_vars', function ($vars) { $vars[] = 'dry65_kartica'; $vars[] = 'dry65_skener'; $vars[] = 'dry65_registracija'; return $vars; });

/* Boje kartice po planu (Essential/Signature/Premium). Premium = PRIVREMENO dok Vlada ne pošalje. */
function dry65_pk_card_theme($acc) {
    $init = (int) $acc->initial;
    $plan = strtolower((string) $acc->plan);
    if     (strpos($plan, 'essential') !== false || $init <= 4)  $tier = 'essential';
    elseif (strpos($plan, 'premium')   !== false || $init >= 12) $tier = 'premium';
    else                                                          $tier = 'signature';
    $themes = [
        'essential' => ['bg' => '#EADAC9', 'ink' => '#2a201a', 'sub' => '#7a6553', 'ring' => '#b89a86'],
        'signature' => ['bg' => '#783332', 'ink' => '#EFE1D2', 'sub' => '#d8b3ac', 'ring' => '#c39089'],
        'premium'   => ['bg' => '#2b2521', 'ink' => '#EADAC7', 'sub' => '#c3ad98', 'ring' => '#8f7562'], // PRIVREMENO (čeka Vladinu boju)
    ];
    $t = $themes[$tier]; $t['tier'] = $tier;
    return $t;
}
/* Asseti kartice (žig + ikonice bonusa). */
function dry65_pk_asset_url($file) { return get_template_directory_uri() . '/assets/packages/' . $file; }
function dry65_pk_stamp_url()       { return dry65_pk_asset_url('dry-stamp.svg'); }
function dry65_pk_bonus_icon_url($acc) {
    $tier = dry65_pk_card_theme($acc)['tier'];
    if ($tier === 'essential') return dry65_pk_asset_url('infusion.svg');
    return dry65_pk_asset_url('mask.svg'); // signature = maska; premium privremeno maska (booster ikonica nije stigla)
}
/* Bonus (nagrada) za prikaz: iz naloga, a ako je prazno — po planu/broju feniranja. */
function dry65_pk_effective_reward($acc) {
    if (!empty($acc->reward)) return $acc->reward;
    foreach (dry65_pk_presets() as $p) {
        if (strcasecmp($p['name'], (string) $acc->plan) === 0 || (int) $p['sessions'] === (int) $acc->initial) {
            return $p['reward'];
        }
    }
    return '';
}
/* Ikonica bonusa — PRIVREMENA (bočica). Vlada šalje prave pa ćemo zameniti. */
function dry65_pk_bonus_icon_svg($color) {
    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="' . esc_attr($color) . '" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2h4M10 2.2v2.8M14 2.2v2.8M9 5h6l1 3.2V20a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V8.2z"/><path d="M8.4 11.2h7.2"/></svg>';
}
/* Kratka labela transakcije za istoriju na kartici. */
function dry65_pk_card_txn_label($acc, $t) {
    if (strpos((string) $t->note, 'Tretman iskorišćen') === 0) return 'Tretman';
    $d = (int) $t->delta;
    if ($acc->type === 'vaucer') return $d < 0 ? 'Potrošeno ' . number_format(-$d, 0, ',', '.') . ' din' : 'Uplata';
    if ($d < 0) return 'Feniranje';
    return 'Otvoren paket';
}

/* „Gola" strana bez header/footer sajta (zadržava CSS+fontove preko wp_head). */
function dry65_pk_bare_head() {
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?php wp_head(); ?>
</head>
<body <?php body_class('dry65-bare'); ?>><?php wp_body_open(); ?>
<?php
}
function dry65_pk_bare_foot() {
    wp_footer();
    ?>
</body>
</html>
<?php
}

add_action('template_redirect', function () {
    $code = get_query_var('dry65_kartica');
    if (!$code) return;
    $acc = dry65_pk_get_by_code($code);
    status_header($acc ? 200 : 404);
    add_filter('wp_robots', 'wp_robots_no_robots'); // privatna kartica — ne indeksiraj
    dry65_pk_bare_head();
    ?>
    <main class="page-enter" style="min-height:100vh;padding:26px 16px calc(40px + env(safe-area-inset-bottom));">
      <div style="text-align:center;margin-bottom:20px;">
        <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/logo.svg'); ?>" alt="Dry65" style="height:30px;width:auto;">
      </div>
      <section>
        <div class="wrap" style="max-width:560px;margin:0 auto;">
          <?php if (!$acc): ?>
            <h1 class="display caps" style="font-size:clamp(28px,4vw,44px);">Kartica nije pronađena</h1>
            <p class="lead" style="margin-top:16px;">Proveri link ili se obrati salonu.</p>
          <?php else:
            $done      = $acc->type === 'paket' && (int) $acc->balance === 0;
            $exp       = dry65_pk_is_expired($acc);
            $txns      = dry65_pk_txns($acc->id);
            $can_staff = current_user_can(DRY65_PK_CAP);
            $card_url  = dry65_pk_card_url($acc->code);
            $th        = dry65_pk_card_theme($acc);
            $is_paket  = $acc->type === 'paket';
            $used      = max(0, (int) $acc->initial - (int) $acc->balance);
            $reward    = dry65_pk_effective_reward($acc);
          ?>
            <div style="max-width:400px;margin:6px auto 0;background:<?php echo $th['bg']; ?>;color:<?php echo $th['ink']; ?>;border-radius:26px;padding:clamp(26px,6vw,38px) clamp(20px,5vw,30px);text-align:center;box-shadow:0 18px 50px rgba(0,0,0,0.16);">
              <div class="mono" style="letter-spacing:0.22em;text-transform:uppercase;font-size:12px;color:<?php echo $th['sub']; ?>;"><?php echo esc_html($is_paket ? ($acc->plan ?: 'Paket') : 'Vaučer'); ?></div>
              <div style="font-family:'Cormorant Garamond',Cormorant,Georgia,serif;font-weight:600;font-size:clamp(30px,7.5vw,42px);line-height:1.04;margin-top:4px;"><?php echo esc_html($acc->name); ?></div>

              <div style="width:clamp(150px,44vw,178px);aspect-ratio:1;margin:clamp(20px,5vw,28px) auto;background:#fff;border-radius:20px;padding:14px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;">
                <?php echo dry65_pk_qr_html($card_url, 5); ?>
              </div>

              <?php if ($is_paket): ?>
              <div class="mono" style="letter-spacing:0.1em;text-transform:uppercase;font-size:12px;color:<?php echo $th['sub']; ?>;margin-bottom:16px;"><?php echo (int) $acc->initial; ?> feniranja<?php if ($reward) echo ' + ' . esc_html($reward); ?></div>

              <?php
              $can_fen   = (int) $acc->balance > 0 && !$exp;
              $can_bonus = dry65_pk_reward_available($acc) && !$exp;
              $stamp_url = dry65_pk_stamp_url();
              $dot_empty = 'width:42px;height:42px;border-radius:50%;border:1.6px solid ' . $th['ring'] . ';background:transparent;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;padding:0;';
              $dot_full  = 'width:42px;height:42px;border-radius:50%;background:#fff;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;';
              $bonus_style = 'width:46px;height:46px;border-radius:50%;background:#fff;display:inline-flex;align-items:center;justify-content:center;position:relative;box-sizing:border-box;padding:9px;';
              $bonus_img   = '<img src="' . esc_url(dry65_pk_bonus_icon_url($acc)) . '" alt="" style="max-width:100%;max-height:100%;' . (empty($acc->reward_used_at) ? '' : 'opacity:0.9;') . '">';
              ?>
              <div style="display:flex;flex-wrap:wrap;gap:11px;justify-content:center;align-items:center;">
                <?php if ($can_staff && $can_fen): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:contents;">
                  <input type="hidden" name="action" value="dry65_pk_spend">
                  <input type="hidden" name="id" value="<?php echo (int) $acc->id; ?>">
                  <input type="hidden" name="act" value="feniranje">
                  <input type="hidden" name="return" value="<?php echo esc_url($card_url); ?>">
                  <?php wp_nonce_field('dry65_pk_spend'); ?>
                <?php endif; ?>
                  <?php for ($i = 0; $i < (int) $acc->initial; $i++): $on = $i < $used; ?>
                    <?php if ($on): ?>
                      <span style="<?php echo $dot_full; ?>"><img src="<?php echo esc_url($stamp_url); ?>" alt="" style="width:26px;height:auto;"></span>
                    <?php elseif ($can_staff && $can_fen): ?>
                      <button type="submit" title="Skini feniranje" style="<?php echo $dot_empty; ?>cursor:pointer;"></button>
                    <?php else: ?>
                      <span style="<?php echo $dot_empty; ?>"></span>
                    <?php endif; ?>
                  <?php endfor; ?>
                <?php if ($can_staff && $can_fen): ?>
                </form>
                <?php endif; ?>

                <?php if ($reward): ?>
                  <span style="font-size:22px;line-height:1;color:<?php echo $th['sub']; ?>;">+</span>
                  <?php if ($can_staff && $can_bonus): ?>
                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:contents;">
                    <input type="hidden" name="action" value="dry65_pk_spend">
                    <input type="hidden" name="id" value="<?php echo (int) $acc->id; ?>">
                    <input type="hidden" name="act" value="tretman">
                    <input type="hidden" name="return" value="<?php echo esc_url($card_url); ?>">
                    <?php wp_nonce_field('dry65_pk_spend'); ?>
                    <button type="submit" title="Tretman: <?php echo esc_attr($reward); ?>" style="<?php echo $bonus_style; ?>cursor:pointer;"><?php echo $bonus_img; ?></button>
                  </form>
                  <?php else: ?>
                    <span style="<?php echo $bonus_style; ?>">
                      <?php echo $bonus_img; ?>
                      <?php if (!empty($acc->reward_used_at)): ?><span style="position:absolute;right:-4px;top:-4px;width:19px;height:19px;border-radius:50%;background:<?php echo $th['bg']; ?>;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="<?php echo $th['ink']; ?>" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span><?php endif; ?>
                    </span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <?php if ($can_staff && $is_paket): ?><p style="margin:12px 0 0;font-size:12px;color:<?php echo $th['sub']; ?>;">Tapni krug = feniranje (može i dva). Bočica/tegla = tretman.</p><?php endif; ?>
              <?php else: ?>
              <div class="mono" style="letter-spacing:0.1em;text-transform:uppercase;font-size:12px;color:<?php echo $th['sub']; ?>;">Preostalo</div>
              <div style="font-family:'Cormorant Garamond',Cormorant,Georgia,serif;font-size:clamp(30px,8vw,46px);margin-top:2px;line-height:1;"><?php echo esc_html(number_format((int) $acc->balance, 0, ',', '.')); ?> din</div>
              <?php endif; ?>

              <?php if (isset($_GET['sk'])): ?><p style="margin:16px 0 0;font-weight:600;">Skinuto. Novo stanje je gore.</p><?php endif; ?>

              <?php if ($txns): ?>
              <div style="margin-top:clamp(22px,5vw,30px);text-align:left;">
                <div class="mono" style="letter-spacing:0.16em;text-transform:uppercase;font-size:11.5px;color:<?php echo $th['sub']; ?>;">Istorija</div>
                <div style="border-top:1px dashed <?php echo $th['ring']; ?>;margin-top:8px;">
                  <?php foreach ($txns as $t): if ((int) $t->reversed === 1) continue; ?>
                  <div style="display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px dashed <?php echo $th['ring']; ?>66;">
                    <span style="text-transform:uppercase;letter-spacing:0.05em;font-size:12px;"><?php echo esc_html(dry65_pk_card_txn_label($acc, $t)); ?></span>
                    <span style="color:<?php echo $th['sub']; ?>;white-space:nowrap;font-size:12.5px;"><?php echo esc_html(mysql2date('d.m.Y.', $t->created_at)); ?></span>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

              <?php if ($exp): ?><p style="margin:16px 0 0;color:#f0c4c4;font-size:13px;">Kartica je istekla (<?php echo esc_html(date_i18n('d.m.Y.', strtotime($acc->expires_at))); ?>).</p>
              <?php elseif (!empty($acc->expires_at)): ?><p style="margin:16px 0 0;font-size:12.5px;color:<?php echo $th['sub']; ?>;">Važi do <?php echo esc_html(date_i18n('d.m.Y.', strtotime($acc->expires_at))); ?></p><?php endif; ?>
            </div>

            <?php if (!$can_staff): ?>
            <p class="muted" style="text-align:center;margin:14px 0 0;font-size:13px;">Pokaži ovu karticu osoblju u salonu.</p>
            <?php elseif ($acc->type === 'vaucer'): ?>
            <p style="text-align:center;margin:14px 0 0;font-size:13px;"><a href="<?php echo esc_url(admin_url('admin.php?page=dry65-paketi&account=' . (int) $acc->id)); ?>" style="text-decoration:underline;">Vaučer se skida u dashboardu ↗</a></p>
            <?php endif; ?>

            <p class="muted" style="text-align:center;margin-top:22px;font-size:14px;">Dry65, West 65, Novi Beograd.</p>
          <?php endif; ?>
        </div>
      </section>
    </main>
    <?php
    dry65_pk_bare_foot();
    exit;
});

/* ============================================================
   SKENER (samo osoblje) — /skener + AJAX „skini pečat po kodu"
   ============================================================ */

/* Stanje naloga za JSON (bez telefona/emaila — samo za prikaz osoblju). */
function dry65_pk_public_state($acc) {
    return [
        'code'    => $acc->code,
        'name'    => $acc->name,
        'type'    => $acc->type,
        'plan'    => $acc->type === 'vaucer' ? 'Vaučer' : ($acc->plan ?: 'Paket feniranja'),
        'balance' => (int) $acc->balance,
        'initial' => (int) $acc->initial,
        'used'    => max(0, (int) $acc->initial - (int) $acc->balance),
        'reward'  => dry65_pk_effective_reward($acc),
        'bonus_icon' => dry65_pk_bonus_icon_url($acc),
        'reward_available' => dry65_pk_reward_available($acc),
        'reward_used'      => !empty($acc->reward_used_at),
        'expired' => dry65_pk_is_expired($acc),
        'done'    => $acc->type === 'paket' && (int) $acc->balance === 0,
        'text'    => dry65_pk_balance_text($acc),
        'label'   => dry65_pk_status_label($acc),
    ];
}

add_action('wp_ajax_dry65_pk_scan', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_send_json_error(['msg' => 'Nemate dozvolu.'], 403);
    check_ajax_referer('dry65_pk_scan', 'nonce');
    $mode = sanitize_key($_POST['mode'] ?? 'lookup');
    $code = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));
    // QR sadrži ceo URL /kartica/{kod} — izvuci kod; ručni unos je već sam kod.
    if (preg_match('#kartica/([^/?#\s]+)#', $code, $m)) $code = $m[1];
    $code = trim($code, "/ \t\n");
    if ($code === '') wp_send_json_error(['msg' => 'Prazan kod.'], 400);
    $acc = dry65_pk_get_by_code($code);
    if (!$acc) wp_send_json_error(['msg' => 'Kartica nije pronađena.'], 404);

    if ($mode === 'spend') {
        if ($acc->type !== 'paket') {
            wp_send_json_error(['msg' => 'Ovo je vaučer — skida se u dashboardu (unosi se iznos).', 'state' => dry65_pk_public_state($acc)], 400);
        }
        if (dry65_pk_is_expired($acc)) {
            wp_send_json_error(['msg' => 'Kartica je istekla.', 'state' => dry65_pk_public_state($acc)], 400);
        }
        // Ko radi (PIN). Ako još nema unetih radnica, ne blokiramo (dok Vlada ne podesi PIN-ove).
        $worker = dry65_pk_staff_verify($_POST['pin'] ?? '');
        if ($worker === '' && dry65_pk_staff_all()) {
            wp_send_json_error(['msg' => 'Unesi PIN radnice.', 'need_pin' => true, 'state' => dry65_pk_public_state($acc)], 401);
        }
        $act = sanitize_key($_POST['act'] ?? 'feniranje');
        if ($act === 'tretman') {
            if (!dry65_pk_reward_available($acc)) {
                wp_send_json_error(['msg' => 'Tretman je već iskorišćen.', 'state' => dry65_pk_public_state($acc)], 400);
            }
            dry65_pk_use_reward($acc->id, $worker);
        } elseif ($act === 'feniranje_tretman') {
            if ((int) $acc->balance <= 0 && !dry65_pk_reward_available($acc)) {
                wp_send_json_error(['msg' => 'Nema šta da se skine.', 'state' => dry65_pk_public_state($acc)], 400);
            }
            if ((int) $acc->balance > 0)          dry65_pk_apply($acc->id, -1, 'Feniranje', $worker);
            if (dry65_pk_reward_available($acc))   dry65_pk_use_reward($acc->id, $worker);
        } else { // feniranje
            if ((int) $acc->balance <= 0) {
                wp_send_json_error(['msg' => 'Paket je već završen (0 feniranja).', 'state' => dry65_pk_public_state($acc)], 400);
            }
            dry65_pk_apply($acc->id, -1, 'Feniranje', $worker);
        }
        $acc = dry65_pk_get($acc->id);
    }
    wp_send_json_success(dry65_pk_public_state($acc));
});
// Neulogovani (npr. istekla sesija) — čist JSON umesto WP „0".
add_action('wp_ajax_nopriv_dry65_pk_scan', function () {
    wp_send_json_error(['msg' => 'Sesija je istekla — prijavi se ponovo na sajt.'], 403);
});

// Prijava radnice PIN-om (vrati ime ako PIN odgovara).
add_action('wp_ajax_dry65_pk_pin', function () {
    if (!current_user_can(DRY65_PK_CAP)) wp_send_json_error(['msg' => 'Nemate dozvolu.'], 403);
    check_ajax_referer('dry65_pk_scan', 'nonce');
    $name = dry65_pk_staff_verify($_POST['pin'] ?? '');
    if ($name === '') wp_send_json_error(['msg' => 'Pogrešan PIN.'], 400);
    wp_send_json_success(['name' => $name]);
});
add_action('wp_ajax_nopriv_dry65_pk_pin', function () {
    wp_send_json_error(['msg' => 'Sesija je istekla — prijavi se ponovo na sajt.'], 403);
});

add_action('template_redirect', function () {
    if (!get_query_var('dry65_skener')) return;
    if (!is_user_logged_in() || !current_user_can(DRY65_PK_CAP)) {
        auth_redirect(); // WP login, pa nazad na /skener
        exit;
    }
    $nonce = wp_create_nonce('dry65_pk_scan');
    $ajax  = admin_url('admin-ajax.php');
    status_header(200);
    add_filter('wp_robots', 'wp_robots_no_robots'); // interni alat osoblja — ne indeksiraj
    dry65_pk_bare_head();
    ?>
    <main class="page-enter" style="min-height:100vh;padding:18px 14px calc(24px + env(safe-area-inset-bottom));">
      <section>
        <div class="wrap" style="max-width:460px;margin:0 auto;">

          <!-- Prijava radnice PIN-om -->
          <div id="pk-pin-gate" style="display:none;background:var(--paper,#fff);border:1px solid var(--sage-line,#e5e5e0);border-radius:var(--radius-lg,18px);padding:24px;text-align:center;max-width:320px;margin:0 auto;">
            <p class="mono" style="margin:0 0 6px;color:var(--clay);font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">Ko radi?</p>
            <p class="muted" style="margin:0 0 14px;font-size:14px;">Unesi svoj PIN.</p>
            <input id="pk-pin-input" type="password" inputmode="numeric" pattern="\d*" maxlength="4" autocomplete="off" style="width:170px;text-align:center;font-size:28px;letter-spacing:0.4em;padding:10px;border:1px solid var(--sage-line,#ccc);border-radius:12px;">
            <p id="pk-pin-status" class="muted" style="min-height:18px;margin:10px 0;font-size:13px;"></p>
            <button id="pk-pin-go" style="cursor:pointer;border:0;border-radius:999px;padding:12px 30px;font-size:16px;font-weight:600;background:var(--clay,#b07a5a);color:#fff;">Prijavi se</button>
          </div>

          <!-- Traka: ko je prijavljen -->
          <div id="pk-worker-bar" style="display:none;justify-content:center;align-items:center;gap:12px;margin:0 auto 14px;font-size:14px;">
            <span>Radnica: <strong id="pk-worker-name"></strong></span>
            <button id="pk-worker-end" class="button" style="cursor:pointer;">Završi</button>
          </div>

          <div id="pk-scan-box" style="max-width:360px;margin:0 auto;">
            <div id="pk-reader" style="width:100%;border-radius:var(--radius-lg,18px);overflow:hidden;background:#000;"></div>
          </div>

          <p id="pk-scan-status" class="muted" style="text-align:center;margin:14px 0;font-size:14px;">Pokrećem kameru…</p>
          <p style="text-align:center;margin:0 0 18px;">
            <button id="pk-scan-start" class="button" style="display:none;cursor:pointer;">Uključi kameru</button>
          </p>

          <!-- Rezultat: punch-kartica (tap krug = pečat) -->
          <style>
            #pk-res-punch{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;align-items:center;}
            .pk-dot{width:48px;height:48px;border-radius:50%;border:1.8px solid #b89984;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:0;transition:transform .08s,border-color .12s;-webkit-tap-highlight-color:transparent;}
            .pk-dot:not(.on):not([disabled]):hover{border-color:var(--clay,#b07a5a);}
            .pk-dot:not(.on):not([disabled]):active{transform:scale(.9);}
            .pk-dot.on{cursor:default;border-color:#6E3130;}
            .pk-dot[disabled]{cursor:default;}
            .pk-dot img{width:30px;height:30px;object-fit:contain;pointer-events:none;}
            .pk-dot svg{pointer-events:none;}
            .pk-plus{font-size:22px;color:var(--muted);}
          </style>
          <div id="pk-scan-result" style="display:none;background:var(--paper,#fff);border:1px solid var(--sage-line,#e5e5e0);border-radius:var(--radius-lg,18px);padding:22px;text-align:center;">
            <div id="pk-res-plan" class="mono" style="color:var(--clay);font-size:12px;letter-spacing:0.08em;text-transform:uppercase;"></div>
            <h2 id="pk-res-name" class="display" style="font-size:24px;margin:4px 0 16px;"></h2>
            <div id="pk-res-punch"></div>
            <p id="pk-res-hint" style="margin:14px 0 0;font-size:13px;color:var(--muted);"></p>
            <div style="margin-top:16px;">
              <button id="pk-cancel" class="button" style="cursor:pointer;">Otkaži</button>
              <button id="pk-next" style="cursor:pointer;border:0;border-radius:999px;padding:11px 26px;font-size:15px;font-weight:600;background:#1f7a4d;color:#fff;margin-left:8px;">Sledeći gost →</button>
            </div>
          </div>

          <!-- Ručni unos (rezerva / test bez kamere) -->
          <details id="pk-manual-wrap" style="margin-top:22px;">
            <summary style="cursor:pointer;color:var(--muted);font-size:14px;">Ručni unos koda (ako kamera zakoči)</summary>
            <div style="display:flex;gap:8px;margin-top:10px;">
              <input id="pk-manual-code" type="text" placeholder="npr. testqr123" style="flex:1;padding:10px 12px;border:1px solid var(--sage-line,#ccc);border-radius:10px;">
              <button id="pk-manual-go" class="button" style="cursor:pointer;">Traži</button>
            </div>
          </details>
        </div>
      </section>
    </main>

    <script src="<?php echo esc_url(get_template_directory_uri() . '/assets/js/html5-qrcode.min.js'); ?>"></script>
    <script>
    (function(){
      var AJAX=<?php echo wp_json_encode($ajax); ?>, NONCE=<?php echo wp_json_encode($nonce); ?>;
      var HAS_STAFF=<?php echo count(dry65_pk_staff_all()) ? 'true' : 'false'; ?>;
      var pinGate=document.getElementById('pk-pin-gate'), pinInput=document.getElementById('pk-pin-input'),
          pinStatus=document.getElementById('pk-pin-status'), pinGo=document.getElementById('pk-pin-go'),
          workerBar=document.getElementById('pk-worker-bar'), workerName=document.getElementById('pk-worker-name'),
          workerEnd=document.getElementById('pk-worker-end'), manualWrap=document.getElementById('pk-manual-wrap');
      var WORKER_PIN=localStorage.getItem('dry65_pk_worker_pin')||'', WORKER_NAME=localStorage.getItem('dry65_pk_worker_name')||'';
      var statusEl=document.getElementById('pk-scan-status'),
          startBtn=document.getElementById('pk-scan-start'),
          box=document.getElementById('pk-scan-box'),
          result=document.getElementById('pk-scan-result'),
          resPlan=document.getElementById('pk-res-plan'), resName=document.getElementById('pk-res-name'),
          resPunch=document.getElementById('pk-res-punch'), resHint=document.getElementById('pk-res-hint'),
          cancelBtn=document.getElementById('pk-cancel'), nextBtn=document.getElementById('pk-next'),
          manualCode=document.getElementById('pk-manual-code'), manualGo=document.getElementById('pk-manual-go');
      var STAMP_URL=<?php echo wp_json_encode(dry65_pk_stamp_url()); ?>;

      var scanning=false, current=null, hintTimer=null, html5qr=null, starting=false;

      function post(mode, code, act){
        var fd=new FormData();
        fd.append('action','dry65_pk_scan'); fd.append('nonce',NONCE);
        fd.append('mode',mode); fd.append('code',code);
        if(act) fd.append('act',act);
        if(WORKER_PIN) fd.append('pin',WORKER_PIN);
        return fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'})
          .then(function(r){return r.json().then(function(j){return {ok:r.ok, j:j};});});
      }

      function escapeHtml(s){ var d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
      function stampImg(){ return '<img src="'+STAMP_URL+'" alt="">'; }

      function showStatus(t){ statusEl.textContent=t; }

      function renderPunch(state){
        current=state;
        box.style.display='none';
        result.style.display='';
        resPlan.textContent=state.plan;
        resName.textContent=state.name;
        if(state.type!=='paket'){
          resPunch.innerHTML='<p class="muted" style="margin:0;">Vaučer '+escapeHtml(state.text)+' — skida se u dashboardu (unosi se iznos).</p>';
          resHint.textContent=''; return;
        }
        var lock=!!state.expired, h='';
        for(var i=0;i<state.initial;i++){
          var on=i<state.used, dis=on||lock;
          h+='<button type="button" class="pk-dot'+(on?' on':'')+'" data-act="feniranje"'+(dis?' disabled':'')+'>'+(on?stampImg():'')+'</button>';
        }
        if(state.reward){
          var bon=!!state.reward_used, dib=bon||lock;
          h+='<span class="pk-plus">+</span>';
          h+='<button type="button" class="pk-dot pk-bonus'+(bon?' on':'')+'" data-act="tretman"'+(dib?' disabled':'')+' title="'+escapeHtml(state.reward)+'"><img src="'+state.bonus_icon+'" alt="" style="max-width:64%;max-height:64%;'+(bon?'opacity:.5;':'')+'"></button>';
        }
        resPunch.innerHTML=h;
        if(lock) resHint.textContent='Kartica je istekla — skidanje onemogućeno.';
        else if(state.balance<=0 && (!state.reward||state.reward_used)) resHint.textContent='Sve iskorišćeno. Klikni „Sledeći gost →".';
        else resHint.textContent='Tapni krug = feniranje (možeš i dva). Bočica/tegla = tretman.';
      }

      function stamp(dot, act){
        if(!current) return;
        dot.setAttribute('disabled','');
        post('spend', current.code, act).then(function(res){
          if(res.j && res.j.success){ renderPunch(res.j.data); }
          else if(res.j && res.j.data && res.j.data.need_pin){ clearWorker(); alert('Prijavi se PIN-om.'); showGate(); }
          else { dot.removeAttribute('disabled'); alert((res.j && res.j.data && res.j.data.msg) || 'Nije uspelo.'); if(res.j&&res.j.data&&res.j.data.state) renderPunch(res.j.data.state); }
        }).catch(function(){ dot.removeAttribute('disabled'); alert('Greška u vezi.'); });
      }
      resPunch.addEventListener('click', function(e){
        var dot=e.target && e.target.closest ? e.target.closest('.pk-dot') : null;
        if(!dot || dot.classList.contains('on') || dot.hasAttribute('disabled')) return;
        stamp(dot, dot.getAttribute('data-act'));
      });

      function lookup(code){
        showStatus('Tražim…');
        post('lookup', code).then(function(res){
          if(res.j && res.j.success){ renderPunch(res.j.data); }
          else { flash((res.j && res.j.data && res.j.data.msg) || 'Greška.'); resume(); }
        }).catch(function(){ flash('Greška u vezi.'); resume(); });
      }

      var flashTimer=null;
      function flash(msg){ showStatus(msg); if(flashTimer)clearTimeout(flashTimer); flashTimer=setTimeout(function(){ if(scanning)showStatus('Skeniram…'); },2500); }

      function resume(){
        current=null;
        result.style.display='none';
        box.style.display='';
        statusEl.style.display='';
        startCamera();
      }
      cancelBtn.addEventListener('click', resume);
      nextBtn.addEventListener('click', resume);

      manualGo.addEventListener('click', function(){
        var c=(manualCode.value||'').trim(); if(!c) return;
        scanning=false; stopCamera(); lookup(c);
      });
      manualCode.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); manualGo.click(); } });

      function found(data){
        if(!scanning) return;
        scanning=false;
        if(hintTimer)clearTimeout(hintTimer);
        stopCamera();
        lookup(data);
      }

      function startCamera(){
        startBtn.style.display='none';
        if(!window.Html5Qrcode){ showStatus('Čitač nije učitan — koristi ručni unos.'); startBtn.style.display=''; return; }
        if(scanning || starting) return;
        starting=true;
        showStatus('Pokrećem kameru…');
        if(!html5qr){ try { html5qr=new Html5Qrcode('pk-reader', {verbose:false}); } catch(e){ starting=false; showStatus('Greška pri pokretanju kamere — koristi ručni unos.'); return; } }
        // Bez fiksnog qrbox-a: skeniraj veliki centralni deo kadra (bez aspectRatio da ne treperi).
        var cfg={ fps:10, qrbox:function(vw,vh){ var m=Math.floor(Math.min(vw,vh)*0.92); return {width:m,height:m}; } };
        var watchdog=setTimeout(function(){
          if(!starting) return;
          starting=false;
          showStatus('Kamera ne odgovara na ovom pregledaču. Otvori /skener u Safari-ju, ili koristi ručni unos.');
          startBtn.style.display='';
          try { if(html5qr && typeof html5qr.stop==='function') html5qr.stop().catch(function(){}); } catch(e){}
        }, 9000);
        html5qr.start({facingMode:'environment'}, cfg,
          function(text){ found(text); },
          function(){ /* nema QR u frejmu — normalno, ignoriši */ }
        ).then(function(){
          clearTimeout(watchdog);
          starting=false; scanning=true; showStatus('Skeniram… uperi u QR');
          if(hintTimer)clearTimeout(hintTimer);
          hintTimer=setTimeout(function(){ if(scanning) showStatus('Ako ne čita: priđi bliže, dobro osvetli QR, ili koristi ručni unos ispod.'); },8000);
        }).catch(function(err){
          clearTimeout(watchdog);
          starting=false;
          var name=(err && (err.name||err.message)) ? (err.name||err.message) : (''+err), m;
          if(location.protocol!=='https:' && location.hostname!=='localhost') m='Kamera radi samo preko https://.';
          else if(/NotAllowed|Permission|Denied/i.test(name)) m='Nema dozvole za kameru. Uključi je za ovaj sajt u podešavanjima pregledača, pa tapni „Uključi kameru".';
          else if(/NotFound|Overconstrained|NotReadable|Track|Starting/i.test(name)) m='Kamera zauzeta/nedostupna ('+name+'). Zatvori druge aplikacije sa kamerom pa probaj.';
          else m='Kamera greška: '+name+'. Koristi ručni unos.';
          showStatus(m); startBtn.style.display='';
        });
      }
      startBtn.addEventListener('click', startCamera);

      function stopCamera(){
        if(hintTimer) clearTimeout(hintTimer);
        if(html5qr){
          try {
            if(typeof html5qr.getState==='function' && html5qr.getState()===2){ // 2 = SCANNING
              html5qr.stop().then(function(){ try{html5qr.clear();}catch(e){} }).catch(function(){});
            }
          } catch(e){}
        }
      }
      function clearWorker(){
        WORKER_PIN=''; WORKER_NAME='';
        localStorage.removeItem('dry65_pk_worker_pin'); localStorage.removeItem('dry65_pk_worker_name');
      }
      function showGate(){
        pinGate.style.display='';
        workerBar.style.display='none';
        box.style.display='none'; statusEl.style.display='none'; startBtn.style.display='none';
        result.style.display='none';
        if(manualWrap) manualWrap.style.display='none';
        stopCamera();
        setTimeout(function(){ pinInput.value=''; try{ pinInput.focus(); }catch(e){} }, 50);
      }
      function enterScanner(name){
        pinGate.style.display='none';
        if(name){ workerBar.style.display='flex'; workerName.textContent=name; } else { workerBar.style.display='none'; }
        statusEl.style.display=''; box.style.display='';
        if(manualWrap) manualWrap.style.display='';
        promptCamera();
      }
      function promptCamera(){
        showStatus('Tapni „Uključi kameru" pa uperi u QR gosta.');
        startBtn.style.display='';
      }
      function pinSubmit(){
        var p=(pinInput.value||'').replace(/\D/g,'');
        if(p.length<4){ pinStatus.textContent='Unesi 4 cifre.'; return; }
        pinGo.disabled=true; pinStatus.textContent='Palim kameru…';
        // Kameru palimo ODMAH u okviru ovog tapa (iPhone traži gesture). PIN proveravamo u pozadini.
        pinGate.style.display='none'; workerBar.style.display='none';
        box.style.display=''; statusEl.style.display='';
        if(manualWrap) manualWrap.style.display='';
        startCamera();
        var fd=new FormData(); fd.append('action','dry65_pk_pin'); fd.append('nonce',NONCE); fd.append('pin',p);
        fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
          pinGo.disabled=false;
          if(j && j.success){
            WORKER_PIN=p; WORKER_NAME=j.data.name;
            localStorage.setItem('dry65_pk_worker_pin',p); localStorage.setItem('dry65_pk_worker_name',WORKER_NAME);
            pinStatus.textContent=''; workerBar.style.display='flex'; workerName.textContent=WORKER_NAME;
          } else {
            stopCamera();
            pinStatus.textContent=(j&&j.data&&j.data.msg)||'Pogrešan PIN.'; pinInput.value='';
            showGate();
          }
        }).catch(function(){ pinGo.disabled=false; stopCamera(); pinStatus.textContent='Greška u vezi.'; showGate(); });
      }
      pinGo.addEventListener('click', pinSubmit);
      pinInput.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); pinSubmit(); } });
      workerEnd.addEventListener('click', function(){ clearWorker(); showGate(); });

      // Init: ima radnica a niko nije prijavljen -> PIN kapija; inače uđi u skener.
      if(HAS_STAFF && !WORKER_PIN){ showGate(); }
      else { enterScanner(WORKER_NAME); }
    })();
    </script>
    <?php
    dry65_pk_bare_foot();
    exit;
});

/* ============================================================
   JAVNA REGISTRACIJA — /registracija (pravi kupca, bez paketa)
   Email OBAVEZAN (za razliku od dashboarda). Vlada posle dodeli paket.
   ============================================================ */
add_action('template_redirect', function () {
    if (!get_query_var('dry65_registracija')) return;

    $done = false; $errors = [];
    $vals = ['name' => '', 'phone' => '', 'email' => ''];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $ok_nonce = isset($_POST['dry65_reg_nonce']) && wp_verify_nonce($_POST['dry65_reg_nonce'], 'dry65_registracija');
        $hp = trim((string) ($_POST['website'] ?? '')); // honeypot (bot popuni)
        $vals['name']  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $vals['phone'] = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $vals['email'] = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (!$ok_nonce) {
            $errors[] = 'Sesija je istekla, osveži stranu i pokušaj ponovo.';
        } elseif ($hp !== '') {
            $done = true; // bot — tiho „uspeh", ništa ne upisujemo
        } else {
            if ($vals['name'] === '')          $errors[] = 'Unesi ime i prezime.';
            if ($vals['phone'] === '')         $errors[] = 'Unesi broj telefona.';
            if (!is_email($vals['email']))     $errors[] = 'Unesi ispravan email.';
            if (!$errors) {
                dry65_pk_customer_get_or_create($vals['name'], $vals['phone'], $vals['email'], 'web');
                $done = true;
            }
        }
    }

    status_header(200);
    get_header();
    ?>
    <main class="page-enter">
      <section class="section" style="min-height:56vh;">
        <div class="wrap" style="max-width:520px;">
          <?php if ($done): ?>
            <p class="mono" style="color:var(--clay);margin:0;">Dry65</p>
            <h1 class="display caps" style="font-size:clamp(28px,4vw,44px);margin-top:6px;">Hvala na registraciji</h1>
            <p class="lead" style="margin-top:16px;">Uspešno smo te upisali. Kada sledeći put svratiš, vežemo ti paket za nalog i pratiš svoje stanje.</p>
            <p class="muted" style="margin-top:20px;font-size:14px;">Dry65, West 65, Novi Beograd. Bez zakazivanja — samo svrati.</p>
          <?php else: ?>
            <p class="mono" style="color:var(--clay);margin:0;">Dry65</p>
            <h1 class="display caps" style="font-size:clamp(28px,4vw,44px);margin-top:6px;">Registracija</h1>
            <p class="lead" style="margin-top:14px;">Upiši se da bismo ti pratili pakete feniranja i tretmane.</p>

            <?php if ($errors): ?>
            <div style="background:#fff3f3;border:1px solid #e0b4b4;border-radius:12px;padding:12px 16px;margin:18px 0;">
              <?php foreach ($errors as $e): ?><p style="margin:4px 0;color:#a00;"><?php echo esc_html($e); ?></p><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="post" style="margin-top:22px;display:grid;gap:14px;">
              <label>Ime i prezime
                <input type="text" name="name" required value="<?php echo esc_attr($vals['name']); ?>" style="width:100%;padding:12px 14px;border:1px solid var(--sage-line,#ccc);border-radius:12px;font-size:16px;">
              </label>
              <label>Telefon
                <input type="tel" name="phone" required value="<?php echo esc_attr($vals['phone']); ?>" style="width:100%;padding:12px 14px;border:1px solid var(--sage-line,#ccc);border-radius:12px;font-size:16px;">
              </label>
              <label>Email
                <input type="email" name="email" required value="<?php echo esc_attr($vals['email']); ?>" style="width:100%;padding:12px 14px;border:1px solid var(--sage-line,#ccc);border-radius:12px;font-size:16px;">
              </label>
              <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
              </div>
              <?php wp_nonce_field('dry65_registracija', 'dry65_reg_nonce'); ?>
              <button type="submit" style="justify-self:start;cursor:pointer;border:0;border-radius:999px;padding:13px 32px;font-size:16px;font-weight:600;background:var(--clay,#b07a5a);color:#fff;">Registruj se</button>
            </form>
            <p class="muted" style="margin-top:16px;font-size:13px;">Podatke koristimo samo za evidenciju tvojih paketa u salonu.</p>
          <?php endif; ?>
        </div>
      </section>
    </main>
    <?php
    get_footer();
    exit;
});
