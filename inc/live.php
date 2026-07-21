<?php
/* ============================================================
   Dry65 — LIVE status salona (/live)
   ------------------------------------------------------------
   - Admin: "Dry65 Uživo" meni, lista dugmadi (0/10/25/30/60/Zatvoreni)
   - Frontend: /live stranica sa auto-refresh (AJAX svakih 45s)
   - Storage: WP options (bez baze/CPT-a, super lagano)

   Data model (WP options):
     dry65_live_wait        int   vidi dry65_live_allowed_waits()
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

/* Dozvoljene vrednosti dugmadi (u minutima). 0 = Slobodno.
   Prati dugmad u adminu — REST `set` prihvata isto ovo + "closed". */
function dry65_live_allowed_waits() {
    return [0, 5, 10, 15, 20, 25, 30, 35, 45, 60];
}

/* „0,10,25,30,60 ili "closed"" — za poruke/dokumentaciju, da lista ne ide stale. */
function dry65_live_allowed_waits_text() {
    return implode(',', dry65_live_allowed_waits()) . ',closed';
}

/* Boja teksta za datu pozadinu: svetla boja -> crna slova, tamna -> bela.
   (percepciona svetlina; prag ~140) */
function dry65_live_text_on($hex) {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) !== 6) return '#111111';
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
    return $lum > 140 ? '#111111' : '#ffffff';
}

/* Osoblje koje može da radi (redosled prikaza). */
function dry65_live_staff_all() {
    return ['Jelena', 'Ema', 'Jovana', 'Nikola'];
}

/* Tekst „Trenutno u salonu: …" od liste aktivnih imena (sa „i" pred poslednjim). */
function dry65_live_staff_text($names) {
    $names = array_values(array_intersect(dry65_live_staff_all(), (array) $names));
    $n = count($names);
    if ($n === 0) return '';
    if ($n === 1) return 'Trenutno u salonu: ' . $names[0];
    $last = array_pop($names);
    return 'Trenutno u salonu: ' . implode(', ', $names) . ' i ' . $last;
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

/* Radno vreme kao TEKST (prikaz kad je zatvoreno). Logika je iznad, u
   dry65_live_is_open_now() — ako menjaš sate, promeni i brojeve i ovaj tekst. */
function dry65_live_hours_text() {
    return 'Radnim danima od 8h do 20h, subotom od 10h do 18h, nedeljom ne radimo.';
}

/* ---- LED semafor figure (po stanju) ----
   Slike su u Media biblioteci, imenovane po BOJI (green/lime/yellow/orange/red).
   Tražimo ih po slug-u (ne po putanji) da radi i lokalno i na produkciji.
   `closed` nema figuru — tad se prikazuje siva tačkica (fallback). */
function dry65_live_figures_map() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $slugs = ['free' => 'green', 'lime' => 'lime', 'yellow' => 'yellow', 'orange' => 'orange', 'red' => 'red'];
    $out = [];
    foreach ($slugs as $tier => $slug) {
        $att = get_posts([
            'post_type'      => 'attachment',
            'name'           => $slug,
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);
        $out[$tier] = $att ? (string) wp_get_attachment_url($att[0]) : '';
    }
    $cache = $out;
    return $out;
}

function dry65_live_figure_url($tier) {
    $m = dry65_live_figures_map();
    return isset($m[$tier]) ? $m[$tier] : '';
}

/* ---- TIHO LOGOVANJE STATUSA (za buduću „popular times" analizu) ----
   Svaka promena statusa (wait/closed) upisuje red {vreme, wait, closed} u zasebnu
   tabelu. Ništa se ne prikazuje sad — samo se skuplja istorija da za par meseci
   ima šta da se analizira. Vremenska zona = WP podešavanje (salon = Beograd). */
if (!defined('DRY65_LIVE_LOG_DB')) define('DRY65_LIVE_LOG_DB', 1); // verzija šeme

function dry65_live_log_table() {
    global $wpdb;
    return $wpdb->prefix . 'dry65_live_log';
}

/* Kreiraj tabelu jednom (i pri promeni šeme). Jeftina provera po verziji na svakom init-u. */
function dry65_live_log_install() {
    if ((int) get_option('dry65_live_log_db', 0) === DRY65_LIVE_LOG_DB) return;
    global $wpdb;
    $table   = dry65_live_log_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        logged_at DATETIME NOT NULL,
        wait SMALLINT NOT NULL DEFAULT 0,
        closed TINYINT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY logged_at (logged_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('dry65_live_log_db', DRY65_LIVE_LOG_DB);
}
add_action('init', 'dry65_live_log_install');

/* Upiši trenutni status kao novi red. Zove se iz admin save i REST set putanja. */
function dry65_live_log_append() {
    global $wpdb;
    $wpdb->insert(dry65_live_log_table(), [
        'logged_at' => current_time('mysql'),
        'wait'      => (int) get_option('dry65_live_wait', 0),
        'closed'    => get_option('dry65_live_closed', '0') === '1' ? 1 : 0,
    ], ['%s', '%d', '%d']);
}

/* ---- Srpsko trajanje: "3 minuta" / "2 sata" / "1 dan" ----
   Prazan string ispod 60s (tad se kaže „upravo sada", bez „pre"). */
function dry65_live_ago_duration($sec) {
    $sec = (int) $sec;
    if ($sec < 60) return '';
    $sr = function ($n, $one, $few, $many) {
        $d1 = $n % 10; $d100 = $n % 100;
        if ($d1 === 1 && $d100 !== 11) return $one;
        if ($d1 >= 2 && $d1 <= 4 && ($d100 < 12 || $d100 > 14)) return $few;
        return $many;
    };
    $m = intdiv($sec, 60);
    if ($m < 60)      return $m . ' ' . $sr($m, 'minut', 'minuta', 'minuta');
    $h = intdiv($m, 60);
    if ($h < 24)      return $h . ' ' . $sr($h, 'sat', 'sata', 'sati');
    $d = intdiv($h, 24);
    return $d . ' ' . $sr($d, 'dan', 'dana', 'dana');
}

/* „Status je ažuriran pre 3 minuta" — uvod u `note`.
   Mirror JS funkcije `agoSentence` u page-live.php — menjaj na OBA mesta. */
function dry65_live_ago_sentence($sec) {
    if ((int) $sec < 0) return '';
    $d = dry65_live_ago_duration($sec);
    return $d === '' ? 'Status je ažuriran upravo sada' : 'Status je ažuriran pre ' . $d;
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
     `headline` = status (krupno u boksu), `wait_label` = procena vremena (sitno u badge-u iznad).
     Pragovi prate dugmad u adminu — svaka vrednost mora da pogodi svoj tier:
     0    -> free    "Slobodni smo"    (dugme 0)
     ≤10  -> lime    "Uskoro slobodni" (dugmad 5, 10)
     ≤30  -> yellow  "Malo čekanja"    (dugmad 25, 30)
     ≤45  -> orange  "Manja gužva"     (dugmad 35, 45)
     >45  -> red     "Imamo gužvu"     (dugme 60)
     VAŽNO: isti tekst je dupliran u JS (`copyFor` u page-live.php) — menjaj na OBA mesta. */
/* ---- Procena vremena (sitno, u badge-u iznad boksa) ----
   Prati STVARNO preostalo vreme, ne tier — zato se vidno smanjuje dok tajmer ide.
   Zaokruženo naviše na 5 min, pa je „manje od X" uvek istinito i uvek se kaže
   „minuta" (svi koraci se završavaju na 0 ili 5 — nema srpske pluralizacije).
   Mirror JS funkcije `waitLabel` u page-live.php — menjaj na OBA mesta. */
function dry65_live_wait_label($remaining_min) {
    if ($remaining_min <= 0)  return 'Prvi ste na redu';
    return 'Na redu ste za manje od ' . (int) (ceil($remaining_min / 5) * 5) . ' minuta';
}

/* ---- [naslov, podtekst] po TAČNOM preostalom vremenu (mockup kopija) ----
   Mirror JS funkcije `copyText` u page-live.php — menjaj na OBA mesta. */
function dry65_live_copy($remaining_min) {
    if ($remaining_min <= 0)  return ['Samo dođite', 'Čekamo vas.'];
    if ($remaining_min <= 5)  return ['Krenite ka nama', 'Taman dovoljno vremena da stignete bez žurbe.'];
    if ($remaining_min <= 10) return ['Pravo vreme da krenete', 'Bićemo spremni baš kada stignete.'];
    if ($remaining_min <= 15) return ['Ako ste u blizini…', 'Savršen trenutak da isplanirate polazak.'];
    if ($remaining_min <= 30) return ['Vredi svratiti', 'Uz kafu ili prosecco vreme će brže proći.'];
    if ($remaining_min <= 35) return ['Salon je danas tražen', 'Dajemo sve od sebe da smanjimo vreme čekanja.'];
    if ($remaining_min <= 45) return ['Velika zainteresovanost', 'Dajemo sve od sebe da smanjimo vreme čekanja. Hvala na razumevanju.'];
    return ['Najprometniji deo dana', 'Pratite stanje i izaberite mirniji deo dana kako biste izbegli čekanje.'];
}

/* ---- Broj u PRSTENU: preostalo vreme, zaokruženo naviše na 5 min ----
   Mirror JS funkcije `ringNum` u page-live.php — menjaj na OBA mesta. */
function dry65_live_ring_num($remaining_min) {
    return (int) (ceil(max(0, $remaining_min) / 5) * 5);
}

function dry65_live_tier_copy($remaining_min, $phone) {
    // `note` je samo NASTAVAK — resolve() ispred zalepi „Status je ažuriran pre X. "
    $busy_note = 'Moguće je da se procena promeni kako se oslobađaju mesta.';
    if ($remaining_min <= 0) {
        return ['tier' => 'free', 'emoji' => '🟢', 'headline' => 'Slobodni smo',
                'sub' => 'Samo dođite, čekamo vas.',
                'note' => 'Ako planirate dolazak, preporučujemo da krenete uskoro.'];
    }
    if ($remaining_min <= 10) {
        return ['tier' => 'lime', 'emoji' => '🟢', 'headline' => 'Uskoro slobodni',
                'sub' => 'Krenite, uskoro će se osloboditi mesto.',
                'note' => 'Može se promeniti kako klijenti dolaze i odlaze.'];
    }
    if ($remaining_min <= 30) {
        return ['tier' => 'yellow', 'emoji' => '🟡', 'headline' => 'Malo čekanja',
                'sub' => 'Ako ste u blizini, pravo je vreme da svratite.',
                'note' => $busy_note];
    }
    if ($remaining_min <= 45) {
        return ['tier' => 'orange', 'emoji' => '🟠', 'headline' => 'Manja gužva',
                'sub' => 'Popijte kafu ili prosecco dok čekate. Vreme će proći brže nego što mislite.',
                'note' => $busy_note];
    }
    return ['tier' => 'red', 'emoji' => '🔴', 'headline' => 'Imamo gužvu',
            'sub' => 'Ako vam se ne žuri, preporučujemo da svratite malo kasnije.',
            'note' => $busy_note];
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
        $data = ['tier' => 'closed', 'emoji' => '⚪', 'headline' => 'Zatvoreni smo',
                 'wait_label' => 'Zatvoreno', 'sub' => dry65_live_hours_text(),
                 'note' => '', 'eyebrow' => 'TRENUTNI STATUS', 'is_free' => false, 'ring_num' => '', 'footnote' => ''];
    } else {
        $data = dry65_live_tier_copy($remaining_min, $phone); // za boju (tier) + emoji
        list($hl, $sub_new) = dry65_live_copy($remaining_min);
        $data['headline']   = $hl;
        $data['sub']        = $sub_new;
        $data['eyebrow']    = ($remaining_min <= 0) ? 'SLOBODAN TERMIN' : 'SLEDEĆI SLOBODAN TERMIN JE ZA MANJE OD';
        $data['is_free']    = ($remaining_min <= 0);
        $data['ring_num']   = ($remaining_min <= 0) ? '' : (string) dry65_live_ring_num($remaining_min);
        $data['footnote']   = 'Prikazano vreme je procena zasnovana na trenutnoj popunjenosti salona i ažurira se uživo kako se mesta oslobađaju i popunjavaju.';
        $data['wait_label'] = dry65_live_wait_label($remaining_min); // admin panel koristi
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

    // „Status je ažuriran pre 3 minuta. " + nastavak. Bez timestampa ide samo nastavak.
    if ($data['note'] !== '') {
        $ago_sentence = dry65_live_ago_sentence($data['updated_ago_sec']);
        if ($ago_sentence !== '') $data['note'] = $ago_sentence . '. ' . $data['note'];
    }

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
    dry65_live_log_append(); // istorija za „popular times"

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
        <?php if (isset($_GET['keyregen'])): ?>
            <div class="notice notice-warning is-dismissible"><p><strong>Novi ključ je generisan.</strong> Ažuriraj Prečice na telefonima novim ključem.</p></div>
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
            <p style="margin:0 0 14px;color:#888;font-size:12.5px;">Klikni koliko se čeka do slobodnog mesta. Tajmer sam ide u minus. Kad uđe nova mušterija, klikni veće čekanje.</p>

            <?php
            // kružići: [vrednost, boja]. Boja teksta (bela/crna) se računa iz pozadine.
            $circles = [
                [5,  '#C9DB5B'], [10, '#C9DB5B'],                                  // lime
                [15, '#F6D63B'], [20, '#F6D63B'], [25, '#F6D63B'], [30, '#F6D63B'], // žuto
                [35, '#F0A73C'], [45, '#F0A73C'],                                  // orange
                [60, '#E8472B'],                                                   // crveno
            ];
            $free_cur = (!$raw['closed'] && (int) $raw['wait'] === 0);
            ?>
            <div class="dry65-live-list" style="max-width:300px;">
                <button type="submit" name="live_wait" value="0"
                    class="dry65-live-btn<?php echo $free_cur ? ' is-current' : ''; ?>"
                    style="--btn-bg:#84B052;--btn-ink:<?php echo esc_attr(dry65_live_text_on('#84B052')); ?>;">
                    Slobodni smo
                </button>
            </div>

            <div class="dry65-live-circles">
                <?php foreach ($circles as [$w, $bg]):
                    $is_current = (!$raw['closed'] && (int) $raw['wait'] === $w);
                ?>
                <button type="submit" name="live_wait" value="<?php echo esc_attr($w); ?>"
                    class="dry65-live-circle<?php echo $is_current ? ' is-current' : ''; ?>"
                    style="--c-bg:<?php echo esc_attr($bg); ?>;--c-ink:<?php echo esc_attr(dry65_live_text_on($bg)); ?>;">
                    <span class="num"><?php echo esc_html($w); ?></span><span class="unit">min</span>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="dry65-live-list" style="max-width:300px;margin-top:14px;">
                <button type="submit" name="live_action" value="closed"
                    class="dry65-live-btn<?php echo $raw['closed'] ? ' is-current' : ''; ?>"
                    style="--btn-bg:#D0CFC7;--btn-ink:<?php echo esc_attr(dry65_live_text_on('#D0CFC7')); ?>;">
                    Zatvoreni
                </button>
            </div>

            <label style="display:block;font-weight:600;margin:22px 0 6px;">Dodatna poruka <span style="font-weight:400;color:#888;">(opciono — prepisuje podrazumevani tekst)</span>:</label>
            <textarea name="live_message" rows="3" style="width:100%;max-width:560px;" placeholder="npr. Ako krećete iz Airport City-ja, verovatno ćete sesti odmah po dolasku."><?php echo esc_textarea($raw['message']); ?></textarea>

            <p style="margin-top:14px;color:#888;font-size:12px;">
                Napomena: van radnog vremena (Pon–Pet 8–20, Sub 10–18) stranica automatski pokazuje „Zatvoreno“, bez obzira na dugme.
            </p>
        </form>

        <?php
        $staff       = (array) get_option('dry65_live_staff', []);
        $chairs_show = get_option('dry65_live_chairs_show', '0') === '1';
        ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:560px;margin-top:26px;">
            <h2 style="margin-top:0;">💇 Ko danas radi</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dry65_live_save_chairs">
                <?php wp_nonce_field('dry65_live_save_chairs'); ?>

                <label style="display:block;font-weight:600;margin-bottom:8px;">Klikni na svakog ko je danas u smeni:</label>
                <div class="dry65-chairs">
                    <?php foreach (dry65_live_staff_all() as $name):
                        $active = in_array($name, $staff, true);
                    ?>
                    <button type="submit" name="staff_toggle" value="<?php echo esc_attr($name); ?>"
                        class="<?php echo $active ? 'is-current' : ''; ?>"><?php echo esc_html($name); ?></button>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:16px;">
                    <button type="submit" name="chairs_toggle" value="1" class="button button-<?php echo $chairs_show ? 'primary' : 'secondary'; ?>">
                        <?php echo $chairs_show ? '● Prikaz na /live: UKLJUČEN' : '○ Prikaz na /live: ISKLJUČEN'; ?>
                    </button>
                    <span style="color:#888;font-size:12px;margin-left:8px;">klikni da <?php echo $chairs_show ? 'sakriješ' : 'prikažeš'; ?></span>
                </div>
                <?php $preview = dry65_live_staff_text($staff); ?>
                <p style="color:#888;font-size:12px;margin-top:12px;">Kad je uključeno, na /live piše: „<?php echo esc_html($preview !== '' ? $preview : 'Danas rade …'); ?>". Kad je isključeno, ne vidi se.</p>
            </form>
        </div>

        <?php
        $api_key = dry65_live_api_key();
        $api_base = home_url('/wp-json/dry65/v1/live');
        ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:640px;margin-top:26px;">
            <h2 style="margin-top:0;">📱 iPhone / Prečice (Shortcuts)</h2>
            <p style="color:#555;margin-top:4px;">Status se može menjati sa home screen-a telefona preko iOS Prečica — bez ulaska u wp-admin. Prečica šalje <strong>POST</strong> na URL ispod.</p>

            <p style="margin-bottom:4px;"><strong>Tajni ključ</strong> (kopiraj u Prečicu):</p>
            <code style="display:inline-block;background:#f0f0f1;padding:8px 12px;border-radius:6px;user-select:all;font-size:13px;word-break:break-all;"><?php echo esc_html($api_key); ?></code>

            <p style="margin:16px 0 4px;"><strong>URL primeri</strong> (metod POST):</p>
            <ul style="font-family:monospace;font-size:12.5px;color:#333;line-height:1.7;list-style:none;padding-left:0;">
                <li>Čekanje 5–10 min &nbsp;→&nbsp; <?php echo esc_html($api_base); ?>?key=<?php echo esc_html($api_key); ?>&amp;set=10</li>
                <li>Slobodni smo &nbsp;→&nbsp; …/live?key=…&amp;set=0</li>
                <li>Zatvoreni &nbsp;→&nbsp; …/live?key=…&amp;set=closed</li>
                <li>Ko radi &nbsp;→&nbsp; …/live?key=…&amp;staff=Jelena,Ema</li>
                <li>Toggle jedne &nbsp;→&nbsp; …/live?key=…&amp;staff_toggle=Nikola</li>
                <li>Prikaži/sakrij ko radi &nbsp;→&nbsp; …/live?key=…&amp;staff_show=1 (ili 0)</li>
            </ul>
            <p style="color:#888;font-size:12px;"><code>set</code>: <?php echo esc_html(dry65_live_allowed_waits_text()); ?>. &nbsp; <code>staff</code>: imena zarezom (<?php echo esc_html(implode(',', dry65_live_staff_all())); ?>). Odgovor vraća novi status (za potvrdu u Prečici).</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Novi ključ poništava sve postojeće Prečice. Nastaviti?');" style="margin-top:12px;">
                <input type="hidden" name="action" value="dry65_live_regen_key">
                <?php wp_nonce_field('dry65_live_regen_key'); ?>
                <button class="button">Generiši novi ključ</button>
                <span style="color:#888;font-size:12px;margin-left:8px;">(ako ključ procuri)</span>
            </form>
        </div>
    </div>

    <style>
        .dry65-live-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        .dry65-live-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .dry65-live-circles {
            display: flex; flex-wrap: wrap; gap: 12px; margin: 4px 0;
        }
        .dry65-live-circle {
            width: 72px; height: 72px; border-radius: 50% !important;
            background: var(--c-bg) !important;
            color: var(--c-ink) !important;
            border: 3px solid transparent !important;
            cursor: pointer;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            line-height: 1; box-shadow: 0 2px 6px rgba(0,0,0,0.14);
            transition: transform .08s ease, box-shadow .12s ease;
        }
        .dry65-live-circle .num { font-size: 23px; font-weight: 800; }
        .dry65-live-circle .unit { font-size: 11px; font-weight: 600; opacity: .8; margin-top: 2px; }
        .dry65-live-circle:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,0.22); }
        .dry65-live-circle:active { transform: translateY(0); }
        .dry65-live-circle.is-current { border-color: #111 !important; box-shadow: 0 0 0 3px #fff, 0 0 0 6px var(--c-bg); }
        .dry65-live-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 12px;
        }
        .dry65-chairs { display: flex; flex-wrap: wrap; gap: 8px; }
        .dry65-chairs button {
            padding: 12px 20px; font-size: 16px; font-weight: 700;
            border-radius: 10px; border: 2px solid #c3c4c7; background: #fff;
            color: #1d2327; cursor: pointer;
        }
        .dry65-chairs button.is-current { border-color: #2271b1; background: #2271b1; color: #fff; }
        .dry65-live-btn {
            background: var(--btn-bg, #38a169) !important;
            color: var(--btn-ink, #fff) !important;
            border: 3px solid transparent !important;
            border-radius: 12px;
            padding: 18px 20px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            min-height: 60px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            transition: transform .08s ease, box-shadow .12s ease;
        }
        .dry65-live-btn:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(0,0,0,0.2); }
        .dry65-live-btn:active { transform: translateY(0); }
        .dry65-live-btn.is-current { border-color: #111 !important; box-shadow: 0 0 0 3px #fff, 0 0 0 6px var(--btn-bg); }
        @media (max-width: 640px) {
            .dry65-live-grid { grid-template-columns: repeat(2, 1fr); } /* 4x2 na telefonu */
            /* Veći tap-target na telefonu (osoblje menja status sa iPhone-a) */
            .dry65-live-btn { min-height: 66px; font-size: 18px; padding: 20px; }
            .dry65-live-list { gap: 12px; }
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
        // Gotov tekst sa servera — koristi ga homepage widget da ne duplira tier logiku.
        // page-live.php i dalje računa svoj copy lokalno (mora, zbog odbrojavanja između poziva).
        'tier'          => (string) $st['tier'],
        'headline'      => (string) $st['headline'],
        'wait_label'    => (string) $st['wait_label'],
        'message'       => (string) get_option('dry65_live_message', ''),
        'phone'         => $biz['phone_display'] ?? '060 6900655',
        'updated_ago_sec' => (int) $st['updated_ago_sec'],
        'stale'         => (bool) $st['stale'],
        'viewers'       => (int) $viewers,
        'viewers_min'   => (int) DRY65_LIVE_VIEWERS_MIN,
        'staff_text'    => dry65_live_staff_text(get_option('dry65_live_staff', [])),
        'chairs_show'   => get_option('dry65_live_chairs_show', '0') === '1',
    ]);
}

/* ============================================================
   HOMEPAGE WIDGET — živi status + interni link ka /live
   ------------------------------------------------------------
   VAŽNO: homepage je keširan (Cache-Control: max-age=7200), pa se status
   NE SME renderovati na serveru — bio bi zamrznut do 2h i lagao bi mušteriju.
   Zato HTML nosi samo neutralan CTA („Proveri uživo…") koji je uvek tačan,
   a pravi status upisuje JS iz AJAX-a. Ako JS zakaže, ostaje ispravan CTA + link.
   Link je u HTML-u (ne u JS-u) da ga Google vidi — /live je bila siroče stranica.
   ============================================================ */
function dry65_live_widget() {
    ?>
    <a class="live-strip" id="dry65-live-strip" href="<?php echo esc_url(home_url('/live/')); ?>">
        <span class="live-strip-dot" aria-hidden="true"></span>
        <span class="live-strip-text" id="dry65-live-strip-text">Proveri uživo koliko se čeka</span>
        <span class="live-strip-arrow" aria-hidden="true">→</span>
    </a>

    <style>
        .live-strip {
            display: inline-flex; align-items: center; gap: 10px;
            font-family: var(--font-sans); font-size: 15px; font-weight: 500;
            color: var(--ink); text-decoration: none;
            background: var(--paper-2);
            border: 1px solid var(--sage-line);
            border-radius: var(--radius-pill);
            padding: 10px 18px;
            transition: border-color .16s ease, transform .16s ease;
            --dot: var(--muted);
        }
        .live-strip:hover { border-color: var(--clay); transform: translateY(-1px); }
        .live-strip[data-tier="free"]   { --dot: #84B052; }
        .live-strip[data-tier="lime"]   { --dot: #C9DB5B; }
        .live-strip[data-tier="yellow"] { --dot: #F6D63B; }
        .live-strip[data-tier="orange"] { --dot: #F0A73C; }
        .live-strip[data-tier="red"]    { --dot: #E8472B; }
        .live-strip[data-tier="closed"] { --dot: #D0CFC7; }
        .live-strip-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: var(--dot); flex: 0 0 auto;
        }
        /* Puls samo kad je status stvarno stigao i salon radi */
        .live-strip.is-live:not([data-tier="closed"]) .live-strip-dot {
            animation: liveStripPulse 2.2s ease-in-out infinite;
        }
        @keyframes liveStripPulse { 0%,100%{transform:scale(1);opacity:1;} 50%{transform:scale(1.25);opacity:.75;} }
        .live-strip-arrow { color: var(--clay); }
        @media (max-width: 520px) {
            .live-strip { font-size: 14px; padding: 9px 14px; }
        }
    </style>

    <script>
    (function () {
        var el = document.getElementById('dry65-live-strip');
        if (!el || !window.fetch) return;
        var txt = document.getElementById('dry65-live-strip-text');
        // Namerno BEZ `&v=` — taj parametar registruje gledaoca, pa bi svaki
        // posetilac homepage-a naduvao brojač „ko gleda /live".
        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?> + '?action=dry65_live_get', { cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.tier) return;
                el.setAttribute('data-tier', d.tier);
                txt.textContent = d.closed ? d.headline : d.wait_label;
                el.classList.add('is-live');
            })
            .catch(function () { /* tiho — ostaje neutralan CTA */ });
    })();
    </script>
    <?php
}

/* ============================================================
   REST API — menjanje statusa sa iPhone-a (iOS Prečice/Shortcuts)
   POST /wp-json/dry65/v1/live?key=SECRET&set=10   (ili set=0 / set=closed)
   Auth: ulogovan korisnik sa cap-om ILI tajni ključ (?key= ili X-Dry65-Key header)
   ============================================================ */

/* Tajni ključ za REST — generiše se jednom, prikazuje u adminu. */
function dry65_live_api_key() {
    $k = get_option('dry65_live_api_key', '');
    if (!$k) {
        $k = wp_generate_password(32, false, false);
        update_option('dry65_live_api_key', $k);
    }
    return $k;
}

function dry65_live_rest_can() {
    if (current_user_can(DRY65_LIVE_CAP)) return true;
    $key = '';
    if (!empty($_SERVER['HTTP_X_DRY65_KEY'])) $key = (string) $_SERVER['HTTP_X_DRY65_KEY'];
    if ($key === '' && isset($_GET['key'])) $key = (string) $_GET['key'];
    $stored = (string) get_option('dry65_live_api_key', '');
    return ($stored !== '' && $key !== '' && hash_equals($stored, $key));
}

add_action('rest_api_init', function () {
    register_rest_route('dry65/v1', '/live', [
        [
            'methods'             => 'POST',
            'callback'            => 'dry65_live_rest_set',
            'permission_callback' => 'dry65_live_rest_can',
        ],
        [
            'methods'             => 'GET',
            'callback'            => 'dry65_live_rest_status',
            'permission_callback' => '__return_true',
        ],
    ]);
});

function dry65_live_rest_set($req) {
    $set     = $req->get_param('set');
    $message = $req->get_param('message');
    $did     = false;
    $status_changed = false; // logujemo samo kad se stварno menja status (ne na staff/message)

    if ($set !== null && $set !== '') {
        if (strtolower((string) $set) === 'closed') {
            update_option('dry65_live_closed', '1');
            $did = true; $status_changed = true;
        } else {
            $wait = (int) $set;
            if (!in_array($wait, dry65_live_allowed_waits(), true)) {
                return new WP_Error('dry65_bad_set', 'Nedozvoljena vrednost. Dozvoljeno: ' . dry65_live_allowed_waits_text() . '.', ['status' => 400]);
            }
            update_option('dry65_live_wait', $wait);
            update_option('dry65_live_closed', '0');
            $did = true; $status_changed = true;
        }
    }
    if ($message !== null) {
        update_option('dry65_live_message', sanitize_textarea_field((string) $message));
        $did = true;
    }

    // Ko radi: staff=Jelena,Ema (postavi tačno te) ILI staff_toggle=Jelena (uključi/isključi jednu)
    $staff_param = $req->get_param('staff');
    if ($staff_param !== null) {
        $names = array_filter(array_map('trim', explode(',', (string) $staff_param)));
        $names = array_values(array_intersect(dry65_live_staff_all(), $names));
        update_option('dry65_live_staff', $names);
        $did = true;
    }
    $toggle = $req->get_param('staff_toggle');
    if ($toggle !== null && $toggle !== '') {
        $name = trim((string) $toggle);
        if (in_array($name, dry65_live_staff_all(), true)) {
            $active = (array) get_option('dry65_live_staff', []);
            if (in_array($name, $active, true)) $active = array_diff($active, [$name]);
            else $active[] = $name;
            update_option('dry65_live_staff', array_values(array_intersect(dry65_live_staff_all(), $active)));
            $did = true;
        }
    }
    // Prikaz „ko radi" na /live: staff_show=1 / 0
    $staff_show = $req->get_param('staff_show');
    if ($staff_show !== null && $staff_show !== '') {
        $on = ($staff_show === '1' || strtolower((string) $staff_show) === 'true');
        update_option('dry65_live_chairs_show', $on ? '1' : '0');
        $did = true;
    }

    if (!$did) {
        return new WP_Error('dry65_nothing', 'Pošalji "set", "message", "staff", "staff_toggle" ili "staff_show".', ['status' => 400]);
    }

    update_option('dry65_live_updated_at', current_time('timestamp'));
    update_option('dry65_live_updated_by', get_current_user_id());
    if ($status_changed) dry65_live_log_append(); // istorija za „popular times"

    $st = dry65_live_resolve();
    return [
        'ok'         => true,
        'status'     => $st['headline'],
        'wait'       => (int) $st['wait'],
        'closed'     => (bool) $st['closed'],
        'tier'       => $st['tier'],
        'staff_text' => dry65_live_staff_text(get_option('dry65_live_staff', [])),
    ];
}

function dry65_live_rest_status() {
    $st = dry65_live_resolve();
    return [
        'status'        => $st['headline'],
        'tier'          => $st['tier'],
        'remaining_min' => (int) $st['remaining_min'],
        'closed'        => (bool) $st['closed'],
    ];
}

/* Sačuvaj stolice (broj + prikaz na /live). */
add_action('admin_post_dry65_live_save_chairs', function () {
    if (!current_user_can(DRY65_LIVE_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_live_save_chairs');

    if (isset($_POST['staff_toggle'])) {
        $name = sanitize_text_field(wp_unslash($_POST['staff_toggle']));
        if (in_array($name, dry65_live_staff_all(), true)) {
            $active = (array) get_option('dry65_live_staff', []);
            if (in_array($name, $active, true)) {
                $active = array_diff($active, [$name]);
            } else {
                $active[] = $name;
            }
            // sačuvaj u kanonskom redosledu
            $active = array_values(array_intersect(dry65_live_staff_all(), $active));
            update_option('dry65_live_staff', $active);
        }
    }
    if (isset($_POST['chairs_toggle'])) {
        $cur = get_option('dry65_live_chairs_show', '0') === '1';
        update_option('dry65_live_chairs_show', $cur ? '0' : '1');
    }
    wp_redirect(add_query_arg(['page' => 'dry65-live', 'saved' => '1'], admin_url('admin.php')));
    exit;
});

/* Regeneriši tajni ključ (dugme u adminu). */
add_action('admin_post_dry65_live_regen_key', function () {
    if (!current_user_can(DRY65_LIVE_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_live_regen_key');
    update_option('dry65_live_api_key', wp_generate_password(32, false, false));
    wp_redirect(add_query_arg(['page' => 'dry65-live', 'keyregen' => '1'], admin_url('admin.php')));
    exit;
});

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
