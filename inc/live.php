<?php
/* ============================================================
   Dry65 — LIVE status salona (/live)
   ------------------------------------------------------------
   - Admin: "Dry65 Uživo" meni, 2x4 dugmad (0/5/10/15/20/25/30+/Zatvoreno)
   - Frontend: /live stranica sa auto-refresh (AJAX svakih 45s)
   - Storage: WP options (bez baze/CPT-a, super lagano)

   Data model (WP options):
     dry65_live_wait        int   0,5,10,15,20,25,30  (30 = "30+")
     dry65_live_closed      '0'|'1'
     dry65_live_message     string (opciona custom poruka)
     dry65_live_updated_at  unix timestamp
     dry65_live_updated_by  user_id
   ============================================================ */

if (!defined('ABSPATH')) exit;

/* Ko sme da menja status. edit_posts = Editor/Author role (devojke u salonu).
   Kad napravimo poseban "Salon" role, ovde se menja samo ova konstanta. */
if (!defined('DRY65_LIVE_CAP')) define('DRY65_LIVE_CAP', 'edit_posts');

/* Prag: posetiocima prikaži brojač samo ako ima BAR ovoliko gledalaca
   (da 0/1 ne izgleda tužno). Admin uvek vidi tačan broj. */
if (!defined('DRY65_LIVE_VIEWERS_MIN')) define('DRY65_LIVE_VIEWERS_MIN', 3);

/* ---- Presence (uživo brojač posetilaca na /live) ----
   Lagano: transient sa {token => zadnji_put_vidjen}. Aktivan = viđen
   u poslednjih 90s. Svaki /live auto-refresh „javi" token serveru. */
function dry65_live_presence_window() { return 90; }

function dry65_live_presence_prune($list) {
    $now = time();
    $win = dry65_live_presence_window();
    if (!is_array($list)) return [];
    foreach ($list as $k => $ts) {
        if ($now - (int) $ts > $win) unset($list[$k]);
    }
    return $list;
}

/* Registruj token (heartbeat) i vrati trenutni broj aktivnih. */
function dry65_live_presence_touch($token) {
    $list = dry65_live_presence_prune(get_transient('dry65_live_presence'));
    if ($token) $list[$token] = time();
    set_transient('dry65_live_presence', $list, 120);
    return count($list);
}

/* Samo prebroj (bez registracije) — za admin snapshot. */
function dry65_live_presence_count() {
    return count(dry65_live_presence_prune(get_transient('dry65_live_presence')));
}

/* Dozvoljene vrednosti dugmadi (u minutima). 0 = Slobodno. */
function dry65_live_allowed_waits() {
    return [0, 5, 10, 15, 20, 25, 30, 45, 60];
}

/* ---- Trenutni raw status iz opcija ---- */
function dry65_live_get_raw() {
    return [
        'wait'       => (int) get_option('dry65_live_wait', 0),
        'closed'     => get_option('dry65_live_closed', '0') === '1',
        'message'    => (string) get_option('dry65_live_message', ''),
        'updated_at' => (int) get_option('dry65_live_updated_at', 0),
        'updated_by' => (int) get_option('dry65_live_updated_by', 0),
    ];
}

/* ---- Radno vreme: da li je salon otvoren SADA? ----
   Pon-Pet 08-20, Sub 10-18, Ned zatvoreno. (Beograd vreme) */
function dry65_live_is_open_now() {
    // DEV bypass: na lokalu (.local) uvek "otvoreno" da bi mogli da testiramo
    // sve statuse bez obzira na sat. Produkcija (dry65.com) poštuje radno vreme.
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : parse_url(home_url(), PHP_URL_HOST);
    if ($host && substr($host, -6) === '.local') return true;

    $tz  = new DateTimeZone('Europe/Belgrade');
    $now = new DateTime('now', $tz);
    $dow = (int) $now->format('N'); // 1=Pon ... 7=Ned
    $min = (int) $now->format('H') * 60 + (int) $now->format('i');

    if ($dow >= 1 && $dow <= 5) return $min >= 8 * 60  && $min < 20 * 60; // 08-20
    if ($dow === 6)             return $min >= 10 * 60 && $min < 18 * 60; // 10-18
    return false; // Nedelja
}

/* ---- Srpski "pre X" (mirror JS funkcije na frontendu) ----
   Koristi se za inicijalni render badge-a pre nego JS preuzme. */
function dry65_live_ago_text($sec) {
    $sec = (int) $sec;
    if ($sec < 0)  return '';
    if ($sec < 60) return 'ažurirano upravo sada';
    $sr = function ($n, $one, $few, $many) {
        $d1 = $n % 10; $d100 = $n % 100;
        if ($d1 === 1 && $d100 !== 11) return $one;
        if ($d1 >= 2 && $d1 <= 4 && ($d100 < 12 || $d100 > 14)) return $few;
        return $many;
    };
    $m = intdiv($sec, 60);
    if ($m < 60)      return 'ažurirano pre ' . $m . ' ' . $sr($m, 'minut', 'minuta', 'minuta');
    $h = intdiv($m, 60);
    if ($h < 24)      return 'ažurirano pre ' . $h . ' ' . $sr($h, 'sat', 'sata', 'sati');
    $d = intdiv($h, 24);
    return 'ažurirano pre ' . $d . ' ' . $sr($d, 'dan', 'dana', 'dana');
}

/* ---- ODBROJAVANJE: koliko je OSTALO od postavljenog vremena ----
   Devojka klikne npr. 30 -> tajmer kreće od 30 i sam ide u minus.
   Kad dođe nova mušterija, klikne veći broj (npr. 45) i tajmer se restartuje.
   Vraća preostale sekunde (0 ako je isteklo ili zatvoreno). */
function dry65_live_remaining_sec($raw = null) {
    if ($raw === null) $raw = dry65_live_get_raw();
    if ($raw['closed'] || !dry65_live_is_open_now()) return 0;
    $set_sec = max(0, (int) $raw['wait']) * 60;
    $elapsed = max(0, current_time('timestamp') - (int) $raw['updated_at']);
    return max(0, $set_sec - $elapsed);
}

/* ---- Mapiranje: preostali minuti -> tier + copy za usera ----
     0        -> free    "Slobodni smo"
     1–10     -> lime    "Uskoro slobodni"
     11–20    -> yellow  "Malo čekanja"
     21–34    -> orange  "Pojačan promet"
     35+      -> red     "Imamo gužvu"                                    */
function dry65_live_tier_copy($remaining_min, $phone) {
    if ($remaining_min <= 0) {
        return ['tier' => 'free', 'emoji' => '🟢', 'headline' => 'Slobodni smo',
                'wait_label' => 'Bez čekanja', 'sub' => 'Samo dođite, čekamo vas.',
                'note' => 'Status se ažurira uživo. Ako planirate dolazak, preporučujemo da krenete uskoro.'];
    }
    if ($remaining_min <= 10) {
        return ['tier' => 'lime', 'emoji' => '🟢', 'headline' => 'Uskoro slobodni',
                'wait_label' => '~' . $remaining_min . ' min', 'sub' => 'Krenite, za nekoliko minuta će se osloboditi mesto.',
                'note' => 'Status se ažurira uživo i može se promeniti kako klijenti dolaze i odlaze.'];
    }
    if ($remaining_min <= 20) {
        return ['tier' => 'yellow', 'emoji' => '🟡', 'headline' => 'Malo čekanja',
                'wait_label' => '~' . $remaining_min . ' min', 'sub' => 'Ako ste u blizini, pravo je vreme da svratite.',
                'note' => 'Status se ažurira uživo. Moguće je da se procena promeni kako se oslobađaju mesta.'];
    }
    if ($remaining_min <= 34) {
        return ['tier' => 'orange', 'emoji' => '🟠', 'headline' => 'Manja gužva',
                'wait_label' => '~' . $remaining_min . ' min', 'sub' => 'Popijte kafu ili prosecco dok čekate. Vreme će proći brže nego što mislite.',
                'note' => 'Status se ažurira uživo. Moguće je da se procena promeni kako se oslobađaju mesta.'];
    }
    return ['tier' => 'red', 'emoji' => '🔴', 'headline' => 'Imamo gužvu',
            'wait_label' => '~' . $remaining_min . ' min', 'sub' => 'Ako vam se ne žuri, preporučujemo da svratite malo kasnije.',
            'note' => 'Status se ažurira uživo. Moguće je da se procena promeni kako se oslobađaju mesta.'];
}

function dry65_live_resolve() {
    $raw   = dry65_live_get_raw();
    $biz   = function_exists('dry65_biz') ? dry65_biz() : ['phone_display' => '060 6900655'];
    $phone = $biz['phone_display'] ?? '060 6900655';

    // Van radnog vremena ILI ručno zatvoreno -> closed
    $closed = $raw['closed'] || !dry65_live_is_open_now();

    $remaining_sec = $closed ? 0 : dry65_live_remaining_sec($raw);
    $remaining_min = (int) ceil($remaining_sec / 60);

    if ($closed) {
        $data = ['tier' => 'closed', 'emoji' => '⚪', 'headline' => 'Trenutno ne radimo',
                 'wait_label' => 'Radno vreme',
                 'sub' => 'Radujemo se vašoj poseti tokom radnog vremena.',
                 'note' => ''];
    } else {
        $data = dry65_live_tier_copy($remaining_min, $phone);
    }

    // Custom poruka (ako postoji) prepisuje default sub — ali ne za closed
    if ($raw['message'] !== '' && $data['tier'] !== 'closed') {
        $data['sub'] = $raw['message'];
    }

    $data['updated_human'] = $raw['updated_at']
        ? human_time_diff($raw['updated_at'], current_time('timestamp'))
        : '';
    $data['updated_ago_sec'] = $raw['updated_at']
        ? max(0, current_time('timestamp') - (int) $raw['updated_at'])
        : -1;
    // Podaci zastareli? (poslednja izmena > 2h, a salon otvoren)
    $data['stale'] = (!$closed && $raw['updated_at'] && (current_time('timestamp') - $raw['updated_at']) > 2 * HOUR_IN_SECONDS);

    $data['closed']        = $closed;
    $data['remaining_sec'] = $remaining_sec;
    $data['remaining_min'] = $remaining_min;
    $data['wait']          = $raw['wait'];
    return $data;
}

/* ============================================================
   ADMIN — meni "Dry65 Uživo" + čuvanje statusa
   ============================================================ */

add_action('admin_menu', function() {
    add_menu_page(
        'Dry65 Uživo',            // page title
        'Dry65 Uživo',            // menu label
        DRY65_LIVE_CAP,           // capability
        'dry65-live',             // slug
        'dry65_live_admin_page',  // callback
        'dashicons-clock',        // ikonica (sat)
        3                         // pozicija (visoko, odmah ispod Dashboard-a)
    );
});

/* Snimanje: jedan admin_post handler za sva dugmad. */
add_action('admin_post_dry65_live_save', function() {
    if (!current_user_can(DRY65_LIVE_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_live_save');

    $action = isset($_POST['live_action']) ? sanitize_key($_POST['live_action']) : '';

    if ($action === 'closed') {
        update_option('dry65_live_closed', '1');
    } else {
        $wait = isset($_POST['live_wait']) ? (int) $_POST['live_wait'] : 0;
        if (!in_array($wait, dry65_live_allowed_waits(), true)) $wait = 0;
        update_option('dry65_live_wait', $wait);
        update_option('dry65_live_closed', '0');
    }

    // Custom poruka (opciono) — uvek se snima iz forme
    $msg = isset($_POST['live_message']) ? sanitize_textarea_field($_POST['live_message']) : '';
    update_option('dry65_live_message', $msg);

    update_option('dry65_live_updated_at', current_time('timestamp'));
    update_option('dry65_live_updated_by', get_current_user_id());

    wp_redirect(add_query_arg(['page' => 'dry65-live', 'saved' => '1'], admin_url('admin.php')));
    exit;
});

function dry65_live_admin_page() {
    $raw   = dry65_live_get_raw();
    $st    = dry65_live_resolve();
    $waits = dry65_live_allowed_waits();

    $updated_by_name = '';
    if ($raw['updated_by']) {
        $u = get_userdata($raw['updated_by']);
        if ($u) $updated_by_name = $u->display_name;
    }
    ?>
    <div class="wrap">
        <h1 style="margin-bottom:6px;">Dry65 — Uživo status salona</h1>
        <p style="color:#666;margin-top:0;">Jedan klik = odmah vidljivo na <a href="<?php echo esc_url(home_url('/live/')); ?>" target="_blank"><?php echo esc_html(home_url('/live/')); ?></a></p>

        <?php if (isset($_GET['saved'])): ?>
            <div class="notice notice-success is-dismissible"><p><strong>Sačuvano.</strong> Status je ažuriran.</p></div>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:560px;margin-top:14px;">
            <div style="font-size:13px;color:#666;">Trenutni status:</div>
            <div style="font-size:22px;font-weight:600;margin:4px 0 2px;">
                <?php echo esc_html($st['emoji'] . ' ' . $st['headline']); ?>
                <span style="font-size:15px;color:#666;font-weight:400;">— <?php echo esc_html($st['wait_label']); ?></span>
            </div>
            <?php if ($updated_by_name): ?>
                <div style="font-size:12px;color:#888;">Poslednja izmena: <?php echo esc_html($updated_by_name); ?><?php if ($st['updated_human']) echo ', pre ' . esc_html($st['updated_human']); ?></div>
            <?php endif; ?>
            <div style="font-size:13px;color:#2271b1;margin-top:8px;">
                <span class="dashicons dashicons-visibility" style="font-size:16px;vertical-align:-3px;"></span>
                Trenutno gleda <strong><?php echo (int) dry65_live_presence_count(); ?></strong> <?php echo dry65_live_presence_count() === 1 ? 'osoba' : 'ljudi'; ?> stranicu /live
                <span style="color:#999;">(u ovom trenutku)</span>
            </div>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:22px;max-width:560px;">
            <input type="hidden" name="action" value="dry65_live_save">
            <?php wp_nonce_field('dry65_live_save'); ?>

            <label style="display:block;font-weight:600;margin-bottom:6px;">Postavi vreme čekanja:</label>
            <p style="margin:0 0 12px;color:#888;font-size:12.5px;">Broj = koliko još minuta do slobodnog mesta. Tajmer sam ide u minus. Kad uđe nova mušterija, klikni novi (veći) broj.</p>

            <div class="dry65-live-grid">
                <?php foreach ([5, 10, 15, 20, 25, 30, 45, 60] as $w):
                    if ($w <= 10)      { $bg = '#C9DB5B'; $ink = '#3f4a12'; } // lime
                    elseif ($w <= 20)  { $bg = '#F6D63B'; $ink = '#5a4900'; } // žuto
                    elseif ($w <= 34)  { $bg = '#F0A73C'; $ink = '#5a3400'; } // narandžasto
                    else               { $bg = '#E8472B'; $ink = '#ffffff'; } // crveno
                    $is_current = (!$raw['closed'] && (int) $raw['wait'] === $w);
                ?>
                <button type="submit" name="live_wait" value="<?php echo esc_attr($w); ?>"
                    class="dry65-live-btn<?php echo $is_current ? ' is-current' : ''; ?>"
                    style="--btn-bg:<?php echo esc_attr($bg); ?>;--btn-ink:<?php echo esc_attr($ink); ?>;">
                    <?php echo esc_html($w . ' min'); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="dry65-live-actions">
                <button type="submit" name="live_wait" value="0"
                    class="dry65-live-btn<?php echo (!$raw['closed'] && (int) $raw['wait'] === 0) ? ' is-current' : ''; ?>"
                    style="--btn-bg:#84B052;--btn-ink:#22330f;">
                    ✓ Slobodno
                </button>
                <button type="submit" name="live_action" value="closed"
                    class="dry65-live-btn<?php echo $raw['closed'] ? ' is-current' : ''; ?>"
                    style="--btn-bg:#D0CFC7;--btn-ink:#3a3a34;">
                    Zatvoreno
                </button>
            </div>

            <label style="display:block;font-weight:600;margin:22px 0 6px;">Dodatna poruka <span style="font-weight:400;color:#888;">(opciono — prepisuje podrazumevani tekst)</span>:</label>
            <textarea name="live_message" rows="3" style="width:100%;max-width:560px;" placeholder="npr. Ako krećete iz Airport City-ja, verovatno ćete sesti odmah po dolasku."><?php echo esc_textarea($raw['message']); ?></textarea>

            <p style="margin-top:14px;color:#888;font-size:12px;">
                Napomena: van radnog vremena (Pon–Pet 8–20, Sub 10–18) stranica automatski pokazuje „Zatvoreno“, bez obzira na dugme.
            </p>
        </form>
    </div>

    <style>
        .dry65-live-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        .dry65-live-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 12px;
        }
        .dry65-live-btn {
            background: var(--btn-bg, #38a169) !important;
            color: var(--btn-ink, #fff) !important;
            border: 3px solid transparent !important;
            border-radius: 12px;
            padding: 22px 8px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            min-height: 64px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            transition: transform .08s ease, box-shadow .12s ease;
        }
        .dry65-live-btn:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(0,0,0,0.2); }
        .dry65-live-btn:active { transform: translateY(0); }
        .dry65-live-btn.is-current { border-color: #111 !important; box-shadow: 0 0 0 3px #fff, 0 0 0 6px var(--btn-bg); }
        @media (max-width: 640px) {
            .dry65-live-grid { grid-template-columns: repeat(2, 1fr); } /* 4x2 na telefonu */
            .dry65-live-btn { min-height: 72px; font-size: 18px; }
        }
    </style>
    <?php
}

/* ============================================================
   AJAX — sveži status za auto-refresh (nije keširano od LiteSpeed-a)
   ============================================================ */
add_action('wp_ajax_dry65_live_get', 'dry65_live_ajax');
add_action('wp_ajax_nopriv_dry65_live_get', 'dry65_live_ajax');
function dry65_live_ajax() {
    $st  = dry65_live_resolve();
    $biz = function_exists('dry65_biz') ? dry65_biz() : ['phone_display' => '060 6900655'];

    // Heartbeat: token iz sessionStorage-a (v). Registruj i prebroj gledaoce.
    $token = isset($_GET['v']) ? preg_replace('/[^a-z0-9]/i', '', substr((string) $_GET['v'], 0, 32)) : '';
    $viewers = dry65_live_presence_touch($token);

    wp_send_json([
        'closed'        => (bool) $st['closed'],
        'remaining_sec' => (int) $st['remaining_sec'],
        'message'       => (string) get_option('dry65_live_message', ''),
        'phone'         => $biz['phone_display'] ?? '060 6900655',
        'updated_ago_sec' => (int) $st['updated_ago_sec'],
        'stale'         => (bool) $st['stale'],
        'viewers'       => (int) $viewers,
        'viewers_min'   => (int) DRY65_LIVE_VIEWERS_MIN,
    ]);
}

/* ============================================================
   Osiguraj da /live (i /faq) stranice postoje posle deploy-a
   (bez potrebe da se tema reaktivira). Radi jednom.
   ============================================================ */
add_action('admin_init', function() {
    if (get_option('dry65_live_pages_v') === '1') return;

    $need = [
        ['title' => 'Uživo',         'slug' => 'live', 'template' => 'page-live.php', 'order' => 10],
        ['title' => 'Česta pitanja', 'slug' => 'faq',  'template' => 'page-faq.php',  'order' => 9],
    ];
    foreach ($need as $p) {
        if (get_page_by_path($p['slug'])) continue;
        $id = wp_insert_post([
            'post_title'  => $p['title'],
            'post_name'   => $p['slug'],
            'post_status' => 'publish',
            'post_type'   => 'page',
            'menu_order'  => $p['order'],
            'post_content'=> '',
        ]);
        if ($id && !is_wp_error($id)) {
            update_post_meta($id, '_wp_page_template', $p['template']);
        }
    }
    update_option('dry65_live_pages_v', '1');
    flush_rewrite_rules(false);
});
