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
if (!defined('DRY65_PK_DB'))  define('DRY65_PK_DB', 4);             // verzija šeme

/* ---- Tabele ---- */
function dry65_pk_table()     { global $wpdb; return $wpdb->prefix . 'dry65_accounts'; }
function dry65_pk_txn_table() { global $wpdb; return $wpdb->prefix . 'dry65_account_txns'; }

function dry65_pk_install() {
    if ((int) get_option('dry65_pk_db', 0) === DRY65_PK_DB) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $acc = dry65_pk_table();
    $txn = dry65_pk_txn_table();
    $sql = "CREATE TABLE $acc (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
        KEY name (name)
    ) $charset;
    CREATE TABLE $txn (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        account_id BIGINT UNSIGNED NOT NULL,
        delta INT NOT NULL DEFAULT 0,
        balance_after INT NOT NULL DEFAULT 0,
        note VARCHAR(190) NOT NULL DEFAULT '',
        staff_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY account_id (account_id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('dry65_pk_db', DRY65_PK_DB);
}
add_action('init', 'dry65_pk_install');

/* ---- Pomoćne ---- */

/* Beogradsko vreme (server je UTC) — isto kao /live. */
function dry65_pk_now() {
    return (new DateTime('now', new DateTimeZone('Europe/Belgrade')))->format('Y-m-d H:i:s');
}

/* Gotovi paketi (naziv + broj sesija + nagrada). Vaučer je poseban (dinari). */
function dry65_pk_presets() {
    return [
        'essential' => ['name' => 'Essential Plan', 'sessions' => 4,  'reward' => 'Hair Infusion'],
        'signature' => ['name' => 'Signature Plan', 'sessions' => 8,  'reward' => 'Medium Hair Treatment Mask'],
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

/* Kreiraj nalog + početnu transakciju. Vrati id ili 0. */
function dry65_pk_create($name, $phone, $type, $initial, $expires_at = '', $note = '', $plan = '', $reward = '', $email = '') {
    global $wpdb;
    $type    = ($type === 'vaucer') ? 'vaucer' : 'paket';
    $initial = max(0, (int) $initial);
    $code    = dry65_pk_gen_code();
    $now     = dry65_pk_now();
    $ok = $wpdb->insert(dry65_pk_table(), [
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
    ], ['%s','%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s','%d']);
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
function dry65_pk_apply($id, $delta, $note = '') {
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
        'created_at'    => $now,
    ], ['%d','%d','%d','%s','%d','%s']);
    return $new;
}

/* Da li paket ima bonus (tretman) koji još stoji — 1 po paketu, iskoristiv bilo kad. */
function dry65_pk_reward_available($acc) {
    return $acc->type === 'paket' && !empty($acc->reward) && empty($acc->reward_used_at);
}

/* Potroši jedini bonus: upiši datum + zabeleži u istoriju (ne dira broj feniranja). */
function dry65_pk_use_reward($id) {
    global $wpdb;
    $acc = dry65_pk_get($id);
    if (!$acc || $acc->type !== 'paket' || !empty($acc->reward_used_at)) return false;
    $now = dry65_pk_now();
    $wpdb->update(dry65_pk_table(), ['reward_used_at' => $now], ['id' => (int) $id], ['%s'], ['%d']);
    $wpdb->insert(dry65_pk_txn_table(), [
        'account_id'    => (int) $id,
        'delta'         => 0,
        'balance_after' => (int) $acc->balance,
        'note'          => 'Tretman iskorišćen: ' . ($acc->reward ?: 'bonus'),
        'staff_id'      => get_current_user_id(),
        'created_at'    => $now,
    ], ['%d','%d','%d','%s','%d','%s']);
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
});

function dry65_pk_admin_page() {
    if (!current_user_can(DRY65_PK_CAP)) wp_die('Nemate dozvolu.');
    global $wpdb;
    $acc_id = isset($_GET['account']) ? (int) $_GET['account'] : 0;
    if ($acc_id) { dry65_pk_admin_detail($acc_id); return; }

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
      <?php if (isset($_GET['err'])): ?><div class="notice notice-error is-dismissible"><p>Nalog nije napravljen — ime, telefon i ispravan email su obavezni.</p></div><?php endif; ?>

      <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:flex-start;margin-top:12px;">
        <!-- Novi nalog -->
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:340px;">
          <h2 style="margin-top:0;">Novi nalog</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="dry65_pk_create">
            <?php wp_nonce_field('dry65_pk_create'); ?>
            <p><label>Ime i prezime<br><input type="text" name="name" class="regular-text" required style="width:100%;"></label></p>
            <p><label>Telefon <span style="color:#d63638;">*</span><br><input type="tel" name="phone" class="regular-text" required style="width:100%;"></label></p>
            <p><label>Email <span style="color:#d63638;">*</span><br><input type="email" name="email" class="regular-text" required style="width:100%;"></label></p>
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
      <table class="widefat striped" style="max-width:640px;">
        <thead><tr><th>Datum</th><th>Promena</th><th>Stanje posle</th></tr></thead>
        <tbody>
          <?php foreach ($txns as $t): ?>
          <tr>
            <td><?php echo esc_html(mysql2date('d.m.Y. H:i', $t->created_at)); ?></td>
            <td><?php echo esc_html(dry65_pk_txn_desc($acc->type, $t)); ?><?php if ($t->note && $t->note !== 'Paket otvoren' && $t->note !== 'Vaučer otvoren') echo ' <span style="color:#888;">(' . esc_html($t->note) . ')</span>'; ?></td>
            <td><?php echo $acc->type === 'vaucer' ? esc_html(number_format((int) $t->balance_after, 0, ',', '.') . ' din') : (int) $t->balance_after; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
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
    // Telefon + email su obavezni (nalog = evidencija paketa gosta).
    if ($name === '' || $phone === '' || !is_email($email)) {
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

    $id = dry65_pk_create(
        $name,
        $phone,
        $type,
        $initial,
        $expiry,
        sanitize_text_field(wp_unslash($_POST['note'] ?? '')),
        $plan,
        $reward,
        $email
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
    if (get_option('dry65_pk_rewrite_v') !== '2') {
        flush_rewrite_rules(false);
        update_option('dry65_pk_rewrite_v', '2');
    }
});
add_filter('query_vars', function ($vars) { $vars[] = 'dry65_kartica'; $vars[] = 'dry65_skener'; return $vars; });

add_action('template_redirect', function () {
    $code = get_query_var('dry65_kartica');
    if (!$code) return;
    $acc = dry65_pk_get_by_code($code);
    status_header($acc ? 200 : 404);
    add_filter('wp_robots', 'wp_robots_no_robots'); // privatna kartica — ne indeksiraj
    get_header();
    ?>
    <main class="page-enter">
      <section class="section" style="min-height:50vh;">
        <div class="wrap" style="max-width:560px;">
          <?php if (!$acc): ?>
            <h1 class="display caps" style="font-size:clamp(28px,4vw,44px);">Kartica nije pronađena</h1>
            <p class="lead" style="margin-top:16px;">Proveri link ili se obrati salonu.</p>
          <?php else:
            $done = $acc->type === 'paket' && (int) $acc->balance === 0;
            $exp  = dry65_pk_is_expired($acc);
            $txns = dry65_pk_txns($acc->id);
          ?>
            <p class="mono" style="color:var(--clay);margin:0;"><?php echo esc_html($acc->type === 'vaucer' ? 'Vaučer' : ($acc->plan ?: 'Paket feniranja')); ?></p>
            <h1 class="display caps" style="font-size:clamp(26px,3.6vw,40px);margin-top:6px;"><?php echo esc_html($acc->name); ?></h1>

            <div style="background:var(--paper,#fff);border:1px solid var(--sage-line,#e5e5e0);border-radius:var(--radius-lg,18px);padding:clamp(22px,4vw,34px);margin-top:22px;text-align:center;">
              <div class="mono" style="color:var(--muted);font-size:13px;letter-spacing:0.08em;text-transform:uppercase;"><?php echo esc_html(dry65_pk_status_label($acc)); ?></div>
              <div class="display" style="font-size:clamp(34px,7vw,56px);margin-top:6px;line-height:1;"><?php echo esc_html(dry65_pk_balance_text($acc)); ?></div>
              <?php if ($acc->type === 'paket' && $acc->reward): ?><p class="muted" style="margin:12px 0 0;font-size:14px;">Tretman (<strong><?php echo esc_html($acc->reward); ?></strong>): <?php echo empty($acc->reward_used_at) ? '<span style="color:var(--clay,#b07a5a);font-weight:600;">dostupan</span>' : 'iskorišćen ' . esc_html(date_i18n('d.m.Y.', strtotime($acc->reward_used_at))); ?></p><?php endif; ?>
              <?php if ($done): ?><p class="muted" style="margin:8px 0 0;font-size:14px;">Sva feniranja iz paketa su iskorišćena.</p><?php endif; ?>
              <?php if ($exp): ?><p style="color:#a00;margin:14px 0 0;">Kartica je istekla (<?php echo esc_html(date_i18n('d.m.Y.', strtotime($acc->expires_at))); ?>).</p>
              <?php elseif (!empty($acc->expires_at)): ?><p class="muted" style="margin:14px 0 0;font-size:14px;">Važi do <?php echo esc_html(date_i18n('d.m.Y.', strtotime($acc->expires_at))); ?></p><?php endif; ?>
            </div>

            <?php
            $can_staff = current_user_can(DRY65_PK_CAP);
            $card_url  = dry65_pk_card_url($acc->code);
            ?>
            <?php if (isset($_GET['sk'])): ?>
            <p style="margin:16px 0 0;text-align:center;color:var(--clay,#b07a5a);font-weight:600;">Skinuto 1 feniranje. Novo stanje je gore.</p>
            <?php endif; ?>

            <div style="text-align:center;margin-top:26px;">
              <div style="width:190px;margin:0 auto;padding:14px;background:#fff;border:1px solid var(--sage-line,#e5e5e0);border-radius:var(--radius-lg,18px);">
                <?php echo dry65_pk_qr_html($card_url, 5); ?>
              </div>
              <p class="muted" style="margin:10px 0 0;font-size:13px;">Pokaži ovaj kod osoblju u salonu.</p>
            </div>

            <?php if ($can_staff): ?>
            <div style="margin-top:20px;padding:16px 18px;border:1px dashed var(--clay,#b07a5a);border-radius:var(--radius-lg,18px);text-align:center;">
              <p class="mono" style="margin:0 0 12px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:var(--clay,#b07a5a);">Osoblje</p>
              <?php if ($acc->type === 'vaucer'): ?>
                <p class="muted" style="margin:0 0 10px;font-size:13px;">Vaučer se skida u dashboardu (unosi se iznos usluge).</p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dry65-paketi&account=' . (int) $acc->id)); ?>" style="text-decoration:underline;">Otvori u dashboardu ↗</a>
              <?php else:
                $canFen   = (int) $acc->balance > 0 && !$exp;
                $canBonus = dry65_pk_reward_available($acc) && !$exp;
              ?>
                <div style="display:flex;flex-direction:column;gap:10px;align-items:center;">
                <?php
                if ($canFen)              echo dry65_pk_action_form($acc, 'feniranje', 'Feniranje  (−1)', $card_url);
                if ($canFen && $canBonus) echo dry65_pk_action_form($acc, 'feniranje_tretman', 'Feniranje + tretman', $card_url);
                if ($canBonus)            echo dry65_pk_action_form($acc, 'tretman', 'Samo tretman' . ($acc->reward ? ' — ' . $acc->reward : ''), $card_url, '#6b5b95');
                if (!$canFen && !$canBonus) echo '<p class="muted" style="margin:0;font-size:13px;">' . ($exp ? 'Kartica je istekla.' : 'Paket je završen (0 feniranja, tretman iskorišćen).') . '</p>';
                ?>
                </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($txns): ?>
            <h2 class="display" style="font-size:20px;margin:30px 0 12px;">Istorija</h2>
            <div style="border:1px solid var(--sage-line,#e5e5e0);border-radius:var(--radius-lg,18px);overflow:hidden;">
              <?php foreach ($txns as $i => $t): ?>
              <div style="display:flex;justify-content:space-between;gap:12px;padding:12px 16px;<?php echo $i ? 'border-top:1px solid var(--sage-line,#eee);' : ''; ?>">
                <span><?php echo esc_html(dry65_pk_txn_desc($acc->type, $t)); ?></span>
                <span class="muted" style="font-size:14px;white-space:nowrap;"><?php echo esc_html(mysql2date('d.m.Y.', $t->created_at)); ?></span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <p class="muted" style="margin-top:24px;font-size:14px;">Dry65, West 65, Novi Beograd. Bez zakazivanja — samo svrati.</p>
          <?php endif; ?>
        </div>
      </section>
    </main>
    <?php
    get_footer();
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
        'reward'  => $acc->reward,
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
        $act = sanitize_key($_POST['act'] ?? 'feniranje');
        if ($act === 'tretman') {
            if (!dry65_pk_reward_available($acc)) {
                wp_send_json_error(['msg' => 'Tretman je već iskorišćen.', 'state' => dry65_pk_public_state($acc)], 400);
            }
            dry65_pk_use_reward($acc->id);
        } elseif ($act === 'feniranje_tretman') {
            if ((int) $acc->balance <= 0 && !dry65_pk_reward_available($acc)) {
                wp_send_json_error(['msg' => 'Nema šta da se skine.', 'state' => dry65_pk_public_state($acc)], 400);
            }
            if ((int) $acc->balance > 0)          dry65_pk_apply($acc->id, -1, 'Feniranje (skener)');
            if (dry65_pk_reward_available($acc))   dry65_pk_use_reward($acc->id);
        } else { // feniranje
            if ((int) $acc->balance <= 0) {
                wp_send_json_error(['msg' => 'Paket je već završen (0 feniranja).', 'state' => dry65_pk_public_state($acc)], 400);
            }
            dry65_pk_apply($acc->id, -1, 'Feniranje (skener)');
        }
        $acc = dry65_pk_get($acc->id);
    }
    wp_send_json_success(dry65_pk_public_state($acc));
});
// Neulogovani (npr. istekla sesija) — čist JSON umesto WP „0".
add_action('wp_ajax_nopriv_dry65_pk_scan', function () {
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
    get_header();
    ?>
    <main class="page-enter">
      <section class="section" style="min-height:60vh;">
        <div class="wrap" style="max-width:460px;">
          <p class="mono" style="color:var(--clay);margin:0;">Osoblje</p>
          <h1 class="display caps" style="font-size:clamp(26px,3.6vw,40px);margin:6px 0 4px;">Skener paketa</h1>
          <p class="muted" style="margin:0 0 18px;font-size:14px;">Uperi kameru u gostov QR. Kad pročita, potvrdi „Skini 1 feniranje".</p>

          <div id="pk-scan-box" style="position:relative;background:#000;border-radius:var(--radius-lg,18px);overflow:hidden;aspect-ratio:1/1;max-width:360px;margin:0 auto;">
            <video id="pk-scan-video" playsinline muted style="width:100%;height:100%;object-fit:cover;display:block;"></video>
            <div id="pk-scan-reticle" style="position:absolute;inset:16%;border:3px solid rgba(255,255,255,0.85);border-radius:16px;pointer-events:none;"></div>
          </div>
          <canvas id="pk-scan-canvas" style="display:none;"></canvas>

          <p id="pk-scan-status" class="muted" style="text-align:center;margin:14px 0;font-size:14px;">Pokrećem kameru…</p>
          <p style="text-align:center;margin:0 0 18px;">
            <button id="pk-scan-start" class="button" style="display:none;cursor:pointer;">Uključi kameru</button>
          </p>

          <!-- Rezultat / potvrda -->
          <div id="pk-scan-result" style="display:none;background:var(--paper,#fff);border:1px solid var(--sage-line,#e5e5e0);border-radius:var(--radius-lg,18px);padding:22px;text-align:center;">
            <div id="pk-res-plan" class="mono" style="color:var(--clay);font-size:13px;"></div>
            <h2 id="pk-res-name" class="display" style="font-size:24px;margin:4px 0 10px;"></h2>
            <div class="mono" style="color:var(--muted);font-size:12px;letter-spacing:0.08em;text-transform:uppercase;" id="pk-res-label"></div>
            <div class="display" id="pk-res-balance" style="font-size:clamp(28px,6vw,44px);line-height:1;margin:4px 0 12px;"></div>
            <p id="pk-res-note" style="margin:0 0 14px;font-size:14px;color:var(--muted);"></p>
            <div id="pk-res-actions">
              <div id="pk-res-buttons" style="display:flex;flex-direction:column;gap:10px;align-items:center;"></div>
              <p style="margin:14px 0 0;"><button id="pk-cancel" class="button" style="cursor:pointer;">Otkaži</button></p>
            </div>
            <div id="pk-res-next" style="display:none;">
              <button id="pk-next" style="cursor:pointer;border:0;border-radius:999px;padding:13px 30px;font-size:16px;font-weight:600;background:#1f7a4d;color:#fff;">Sledeći gost →</button>
            </div>
          </div>

          <!-- Ručni unos (rezerva / test bez kamere) -->
          <details style="margin-top:22px;">
            <summary style="cursor:pointer;color:var(--muted);font-size:14px;">Ručni unos koda (ako kamera zakoči)</summary>
            <div style="display:flex;gap:8px;margin-top:10px;">
              <input id="pk-manual-code" type="text" placeholder="npr. testqr123" style="flex:1;padding:10px 12px;border:1px solid var(--sage-line,#ccc);border-radius:10px;">
              <button id="pk-manual-go" class="button" style="cursor:pointer;">Traži</button>
            </div>
          </details>
        </div>
      </section>
    </main>

    <script src="<?php echo esc_url(get_template_directory_uri() . '/assets/js/jsqr.min.js'); ?>"></script>
    <script>
    (function(){
      var AJAX=<?php echo wp_json_encode($ajax); ?>, NONCE=<?php echo wp_json_encode($nonce); ?>;
      var video=document.getElementById('pk-scan-video'),
          canvas=document.getElementById('pk-scan-canvas'), ctx=canvas.getContext('2d', {willReadFrequently:true}),
          statusEl=document.getElementById('pk-scan-status'),
          startBtn=document.getElementById('pk-scan-start'),
          box=document.getElementById('pk-scan-box'),
          result=document.getElementById('pk-scan-result'),
          resPlan=document.getElementById('pk-res-plan'), resName=document.getElementById('pk-res-name'),
          resLabel=document.getElementById('pk-res-label'), resBal=document.getElementById('pk-res-balance'),
          resNote=document.getElementById('pk-res-note'),
          resActions=document.getElementById('pk-res-actions'), resNext=document.getElementById('pk-res-next'),
          resButtons=document.getElementById('pk-res-buttons'), cancelBtn=document.getElementById('pk-cancel'),
          nextBtn=document.getElementById('pk-next'),
          manualCode=document.getElementById('pk-manual-code'), manualGo=document.getElementById('pk-manual-go');

      var stream=null, scanning=false, current=null, raf=null;

      function post(mode, code, act){
        var fd=new FormData();
        fd.append('action','dry65_pk_scan'); fd.append('nonce',NONCE);
        fd.append('mode',mode); fd.append('code',code);
        if(act) fd.append('act',act);
        return fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'})
          .then(function(r){return r.json().then(function(j){return {ok:r.ok, j:j};});});
      }

      function mkBtn(label, act, bg){
        var b=document.createElement('button');
        b.type='button'; b.textContent=label;
        b.style.cssText='cursor:pointer;border:0;border-radius:999px;padding:13px 26px;font-size:16px;font-weight:600;color:#fff;min-width:230px;background:'+bg+';';
        b.addEventListener('click', function(){ doSpend(act, b); });
        return b;
      }

      function showStatus(t){ statusEl.textContent=t; }

      function showResult(state){
        current=state;
        box.style.display='none';
        result.style.display='';
        resActions.style.display=''; resNext.style.display='none';
        resPlan.textContent=state.plan;
        resName.textContent=state.name;
        resLabel.textContent=state.label;
        resBal.textContent=state.text;
        // Status bonusa (tretman) — 1 po paketu.
        var note='';
        if(state.type==='paket' && state.reward){
          note = state.reward_used ? ('Tretman ('+state.reward+') već iskorišćen') : ('Tretman: '+state.reward+' — dostupan');
        }
        if(state.expired) note='⚠ Kartica je istekla.';
        resNote.textContent=note;
        // Dugmad prema stanju.
        resButtons.innerHTML='';
        if(state.expired){ return; }
        if(state.type==='vaucer'){
          var v=document.createElement('p'); v.className='muted'; v.style.margin='0';
          v.textContent='Vaučer se skida u dashboardu (unosi se iznos).'; resButtons.appendChild(v); return;
        }
        var canFen=state.balance>0, canBonus=state.reward_available;
        if(canFen)            resButtons.appendChild(mkBtn('Feniranje  (−1)','feniranje','var(--clay,#b07a5a)'));
        if(canFen && canBonus)resButtons.appendChild(mkBtn('Feniranje + tretman','feniranje_tretman','var(--clay,#b07a5a)'));
        if(canBonus)          resButtons.appendChild(mkBtn('Samo tretman'+(state.reward?' — '+state.reward:''),'tretman','#6b5b95'));
        if(!canFen && !canBonus){
          var d=document.createElement('p'); d.className='muted'; d.style.margin='0';
          d.textContent='Nema šta da se skine (paket završen).'; resButtons.appendChild(d);
        }
      }

      function afterSpend(state){
        current=state;
        resPlan.textContent=state.plan;
        resName.textContent=state.name;
        resLabel.textContent=state.label;
        resBal.textContent=state.text;
        var parts=[state.text];
        if(state.type==='paket' && state.reward) parts.push('Tretman: '+(state.reward_used?'iskorišćen':'dostupan'));
        resNote.textContent='✓ Sačuvano · '+parts.join(' · ');
        resActions.style.display='none';
        resNext.style.display='';
      }

      function lookup(code){
        showStatus('Tražim…');
        post('lookup', code).then(function(res){
          if(res.j && res.j.success){ showResult(res.j.data); }
          else { flash((res.j && res.j.data && res.j.data.msg) || 'Greška.'); resume(); }
        }).catch(function(){ flash('Greška u vezi.'); resume(); });
      }

      var flashTimer=null;
      function flash(msg){ showStatus(msg); if(flashTimer)clearTimeout(flashTimer); flashTimer=setTimeout(function(){ if(scanning)showStatus('Skeniram…'); },2500); }

      function doSpend(act, btn){
        if(!current) return;
        var old=btn.textContent; btn.disabled=true; btn.textContent='…';
        post('spend', current.code, act).then(function(res){
          btn.disabled=false; btn.textContent=old;
          if(res.j && res.j.success){ afterSpend(res.j.data); }
          else { alert((res.j && res.j.data && res.j.data.msg) || 'Nije uspelo.'); if(res.j&&res.j.data&&res.j.data.state) showResult(res.j.data.state); }
        }).catch(function(){ btn.disabled=false; btn.textContent=old; alert('Greška u vezi.'); });
      }

      function resume(){
        current=null;
        result.style.display='none';
        box.style.display='';
        if(stream){ scanning=true; showStatus('Skeniram…'); tick(); }
        else { showStatus('Kamera nije aktivna — koristi ručni unos.'); }
      }
      cancelBtn.addEventListener('click', resume);
      nextBtn.addEventListener('click', resume);

      manualGo.addEventListener('click', function(){
        var c=(manualCode.value||'').trim(); if(!c) return;
        scanning=false; lookup(c);
      });
      manualCode.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); manualGo.click(); } });

      function tick(){
        if(!scanning) return;
        if(video.readyState===video.HAVE_ENOUGH_DATA){
          canvas.width=video.videoWidth; canvas.height=video.videoHeight;
          ctx.drawImage(video,0,0,canvas.width,canvas.height);
          var img=ctx.getImageData(0,0,canvas.width,canvas.height);
          var code=jsQR(img.data, img.width, img.height, {inversionAttempts:'dontInvert'});
          if(code && code.data){ scanning=false; lookup(code.data); return; }
        }
        raf=requestAnimationFrame(tick);
      }

      function startCamera(){
        startBtn.style.display='none';
        showStatus('Pokrećem kameru…');
        if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
          showStatus('Ovaj pregledač ne podržava kameru — koristi ručni unos.'); return;
        }
        navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}})
          .then(function(s){ stream=s; video.srcObject=s; video.setAttribute('playsinline',true);
            return video.play(); })
          .then(function(){ scanning=true; showStatus('Skeniram…'); tick(); })
          .catch(function(err){
            var m='Nema pristupa kameri.';
            if(location.protocol!=='https:' && location.hostname!=='localhost') m='Kamera radi samo preko https:// — koristi ručni unos ovde na lokalu.';
            else if(err && err.name==='NotAllowedError') m='Dozvola za kameru odbijena. Uključi je pa klikni „Uključi kameru".';
            showStatus(m); startBtn.style.display='';
          });
      }
      startBtn.addEventListener('click', startCamera);
      startCamera();
    })();
    </script>
    <?php
    get_footer();
    exit;
});
