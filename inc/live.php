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

/* Registruj token (heartbeat) i vrati trenutni broj aktivnih.
   Kad je token NOV (nije bio u prozoru), uvećaj satni brojač poseta
   (dry65_live_visit) — tako broj upisa prati posetioce, ne heartbeat-e. */
function dry65_live_presence_touch($token) {
    $list   = dry65_live_presence_prune(get_transient('dry65_live_presence'));
    $is_new = $token && !isset($list[$token]);
    if ($token) $list[$token] = time();
    set_transient('dry65_live_presence', $list, 120);
    if ($is_new) dry65_live_visit_bump();
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

/* ---- Trenutni raw status iz opcija ---- */
function dry65_live_get_raw() {
    return [
        'wait'       => (int) get_option('dry65_live_wait', 0),
        'closed'     => get_option('dry65_live_closed', '0') === '1',
        'full'       => get_option('dry65_live_full', '0') === '1',
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
    return 'Radno vreme: ponedeljak - petak od 8h do 20h, subotom od 10h do 18h, nedeljom ne radimo.';
}

/* Dinamičan tekst za „Za danas popunjeni": kad se sledeći put otvaramo.
   Pon-Pet 8h, Sub 10h, Ned zatvoreno. „sutra" ili „u ponedeljak…". */
function dry65_live_next_open_text() {
    $tz    = new DateTimeZone('Europe/Belgrade');
    $now   = new DateTime('now', $tz);
    $days  = [1 => 'ponedeljak', 2 => 'utorak', 3 => 'sredu', 4 => 'četvrtak', 5 => 'petak', 6 => 'subotu'];
    for ($i = 1; $i <= 7; $i++) {
        $d   = (clone $now)->modify("+$i day");
        $dow = (int) $d->format('N');
        if ($dow >= 1 && $dow <= 5) $open = '8h';
        elseif ($dow === 6)         $open = '10h';
        else                        continue; // nedelja — preskoči
        $when = ($i === 1) ? 'sutra' : 'u ' . $days[$dow];
        return 'Hvala vam što nas birate, vidimo se ' . $when . ' od ' . $open . '.';
    }
    return 'Hvala vam što nas birate, vidimo se uskoro.';
}

/* Naslov + opis za „Za danas popunjeni" (editabilno preko admina; opis default = dinamičan). */
function dry65_live_full_copy() {
    $texts = dry65_live_texts();
    $h = (isset($texts['full']['h']) && $texts['full']['h'] !== '') ? $texts['full']['h'] : 'Za danas smo popunjeni';
    $s = (isset($texts['full']['s']) && $texts['full']['s'] !== '') ? $texts['full']['s'] : dry65_live_next_open_text();
    return [$h, $s];
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
if (!defined('DRY65_LIVE_LOG_DB')) define('DRY65_LIVE_LOG_DB', 2); // verzija šeme

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
        is_full TINYINT NOT NULL DEFAULT 0,
        staff SMALLINT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY logged_at (logged_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('dry65_live_log_db', DRY65_LIVE_LOG_DB);
}
add_action('init', 'dry65_live_log_install');

/* Upiši trenutni status kao novi red. Zove se iz admin save i REST set putanja.
   Vreme je uvek Beograd (isto kao prikaz na /live), nezavisno od WP timezone
   podešavanja — inače na serveru sa UTC-om log ispadne pomeren za 2h. */
function dry65_live_log_append() {
    global $wpdb;
    $now_bg = (new DateTime('now', new DateTimeZone('Europe/Belgrade')))->format('Y-m-d H:i:s');
    $wpdb->insert(dry65_live_log_table(), [
        'logged_at' => $now_bg,
        'wait'      => (int) get_option('dry65_live_wait', 0),
        'closed'    => get_option('dry65_live_closed', '0') === '1' ? 1 : 0,
        'is_full'   => get_option('dry65_live_full', '0') === '1' ? 1 : 0,
        'staff'     => dry65_live_schedule_staff_at(),
    ], ['%s', '%d', '%d', '%d', '%d']);
}

/* ============================================================
   Posete /live po satu (za korelaciju sa gužvom / „popular times")
   Jedan red po satu (Beograd), samo brojač jedinstvenih-ish posetilaca.
   ~24 reda/dan, ispod 1 MB godišnje. Hvata podatke od trenutka uvođenja.
   ============================================================ */
if (!defined('DRY65_LIVE_VISIT_DB')) define('DRY65_LIVE_VISIT_DB', 1);

function dry65_live_visit_table() {
    global $wpdb;
    return $wpdb->prefix . 'dry65_live_visit';
}

function dry65_live_visit_install() {
    if ((int) get_option('dry65_live_visit_db', 0) === DRY65_LIVE_VISIT_DB) return;
    global $wpdb;
    $table   = dry65_live_visit_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        bucket DATETIME NOT NULL,
        visitors INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (bucket)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('dry65_live_visit_db', DRY65_LIVE_VISIT_DB);
}
add_action('init', 'dry65_live_visit_install');

/* Uvećaj brojač poseta za tekući sat (Beograd). Zove se kad presence vidi NOV token. */
function dry65_live_visit_bump() {
    global $wpdb;
    $bucket = (new DateTime('now', new DateTimeZone('Europe/Belgrade')))->format('Y-m-d H:00:00');
    $table  = dry65_live_visit_table();
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table} (bucket, visitors) VALUES (%s, 1)
         ON DUPLICATE KEY UPDATE visitors = visitors + 1",
        $bucket
    ));
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

/* ---- Podrazumevani tekstovi [vrednost => [h(naslov), s(opis)]] ----
   Ovo je fallback; admin ih može prepisati u „Dry65 Uživo" panelu. */
function dry65_live_default_texts() {
    return [
        0  => ['h' => 'Samo dođite',             's' => 'Čekamo vas.'],
        5  => ['h' => 'Krenite ka nama',         's' => 'Taman dovoljno vremena da stignete bez žurbe.'],
        10 => ['h' => 'Pravo vreme da krenete',  's' => 'Bićemo spremni baš kada stignete.'],
        15 => ['h' => 'Ako ste u blizini…',      's' => 'Savršen trenutak da isplanirate polazak.'],
        20 => ['h' => 'Vredi svratiti',          's' => 'Uz kafu ili prosecco vreme će brže proći.'],
        25 => ['h' => 'Vredi svratiti',          's' => 'Uz kafu ili prosecco vreme će brže proći.'],
        30 => ['h' => 'Vredi svratiti',          's' => 'Uz kafu ili prosecco vreme će brže proći.'],
        35 => ['h' => 'Salon je danas tražen',   's' => 'Dajemo sve od sebe da smanjimo vreme čekanja.'],
        45 => ['h' => 'Velika zainteresovanost', 's' => 'Dajemo sve od sebe da smanjimo vreme čekanja. Hvala na razumevanju.'],
        60 => ['h' => 'Najprometniji deo dana',  's' => 'Pratite stanje i izaberite mirniji deo dana kako biste izbegli čekanje.'],
        // Za danas popunjeni (manuelni status). Opis prazan = dinamičan default („vidimo se sutra od Xh").
        'full' => ['h' => 'Za danas smo popunjeni', 's' => ''],
    ];
}

/* Sačuvani tekstovi (opcija) spojeni sa podrazumevanim (prazno polje -> default). */
function dry65_live_texts() {
    $saved = get_option('dry65_live_texts', []);
    if (!is_array($saved)) $saved = [];
    $out = [];
    foreach (dry65_live_default_texts() as $v => $def) {
        $h = (isset($saved[$v]['h']) && $saved[$v]['h'] !== '') ? $saved[$v]['h'] : $def['h'];
        $s = (isset($saved[$v]['s']) && $saved[$v]['s'] !== '') ? $saved[$v]['s'] : $def['s'];
        $out[$v] = ['h' => $h, 's' => $s];
    }
    return $out;
}

/* ---- [naslov, podtekst] po TAČNOM preostalom vremenu ----
   Prag = najmanja dozvoljena vrednost >= preostalo. Editabilno preko admina.
   Mirror JS funkcije `copyText` u page-live.php. */
function dry65_live_copy($remaining_min) {
    $texts = dry65_live_texts();
    foreach (dry65_live_allowed_waits() as $v) {
        if ($remaining_min <= $v && isset($texts[$v])) return [$texts[$v]['h'], $texts[$v]['s']];
    }
    $last = end($texts);
    return [$last['h'], $last['s']];
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

    // Van radnog vremena ILI ručno zatvoreno -> closed. „Popunjeni" samo dok je otvoreno.
    $closed = $raw['closed'] || !dry65_live_is_open_now();
    $full   = !$closed && $raw['full'];

    $remaining_sec = ($closed || $full) ? 0 : dry65_live_remaining_sec($raw);
    $remaining_min = (int) ceil($remaining_sec / 60);

    if ($closed) {
        $data = ['tier' => 'closed', 'emoji' => '⚪', 'headline' => 'Zatvoreni smo',
                 'wait_label' => 'Zatvoreno', 'sub' => dry65_live_hours_text(),
                 'note' => '', 'eyebrow' => 'Trenutni status', 'is_free' => false, 'ring_num' => '', 'footnote' => ''];
    } elseif ($full) {
        list($fh, $fs) = dry65_live_full_copy();
        $data = ['tier' => 'full', 'emoji' => '🩶', 'headline' => $fh,
                 'wait_label' => 'Popunjeni', 'sub' => $fs,
                 'note' => '', 'eyebrow' => 'Trenutni status', 'is_free' => false, 'ring_num' => '', 'footnote' => ''];
    } else {
        $data = dry65_live_tier_copy($remaining_min, $phone); // za boju (tier) + emoji
        list($hl, $sub_new) = dry65_live_copy($remaining_min);
        $data['headline']   = $hl;
        $data['sub']        = $sub_new;
        $data['eyebrow']    = ($remaining_min <= 0) ? 'Slobodan termin' : 'Sledeći slobodan termin je za manje od';
        $data['is_free']    = ($remaining_min <= 0);
        $data['ring_num']   = ($remaining_min <= 0) ? '' : (string) dry65_live_ring_num($remaining_min);
        $data['footnote']   = 'Prikazano vreme je procena zasnovana na trenutnoj popunjenosti salona i ažurira se uživo kako se mesta oslobađaju i popunjavaju.';
        $data['wait_label'] = dry65_live_wait_label($remaining_min); // admin panel koristi
    }

    // Custom poruka (ako postoji) prepisuje default sub — ali ne za closed/full
    if ($raw['message'] !== '' && $data['tier'] !== 'closed' && $data['tier'] !== 'full') {
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
    $data['full']          = $full;
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
    add_submenu_page(
        'dry65-live',             // roditelj
        'Live istorija',          // page title
        'Live istorija',          // menu label
        DRY65_LIVE_CAP,           // capability
        'dry65-live-istorija',    // slug
        'dry65_live_history_page' // callback
    );
    add_submenu_page(
        'dry65-live',              // roditelj
        'Raspored smene',          // page title
        'Raspored smene',          // menu label
        DRY65_LIVE_CAP,            // capability
        'dry65-live-raspored',     // slug
        'dry65_live_schedule_page' // callback
    );
});

/* ---- „Live istorija": izveštaj iz log tabele ----
   Popular times = vremenski ponderisano (status važi dok se ne promeni),
   radno vreme 08-20, beogradsko vreme. */
function dry65_live_history_page() {
    if (!current_user_can(DRY65_LIVE_CAP)) wp_die('Nemate dozvolu.');
    global $wpdb;
    $table = dry65_live_log_table();

    $days  = isset($_GET['days']) ? max(1, min(90, (int) $_GET['days'])) : 14;
    $tz    = new DateTimeZone('Europe/Belgrade');
    $since = (new DateTime('now', $tz))->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00');

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT logged_at, wait, closed, is_full, staff FROM {$table} WHERE logged_at >= %s ORDER BY logged_at ASC",
        $since
    ), ARRAY_A);

    // ---- Posete /live po satu (dana u istom periodu) ----
    $vtable = dry65_live_visit_table();
    $vrows  = $wpdb->get_results($wpdb->prepare(
        "SELECT bucket, visitors FROM {$vtable} WHERE bucket >= %s ORDER BY bucket ASC",
        $since
    ), ARRAY_A);
    $visits_by_hour = array_fill(8, 12, 0);
    $visits_total   = 0;
    foreach ($vrows as $vr) {
        $vh = (int) substr($vr['bucket'], 11, 2);
        $vn = (int) $vr['visitors'];
        $visits_total += $vn;
        if ($vh >= 8 && $vh < 20) $visits_by_hour[$vh] += $vn;
    }

    // Grupisanje po danu
    $by_day = [];
    foreach ($rows as $r) $by_day[substr($r['logged_at'], 0, 10)][] = $r;

    // Satno vremenski ponderisano
    $hour_min = $hour_wsum = $hour_staffsum = array_fill(8, 12, 0.0);
    $free_min = $heavy_min = $tot_min = 0.0;
    $dist = ['Slobodno (0)' => 0.0, 'Kratko (5-10)' => 0.0, 'Srednje (15-30)' => 0.0, 'Dugo (35+)' => 0.0, 'Popunjeno' => 0.0, 'Zatvoreno' => 0.0];
    foreach ($by_day as $ev) {
        $n = count($ev);
        for ($i = 0; $i < $n - 1; $i++) {
            $t0  = new DateTime($ev[$i]['logged_at'], $tz);
            $t1  = new DateTime($ev[$i + 1]['logged_at'], $tz);
            $w   = (int) $ev[$i]['wait'];
            $stf = (int) $ev[$i]['staff'];
            $cl  = (int) $ev[$i]['closed'];
            $fl  = (int) $ev[$i]['is_full'];
            $cur = clone $t0;
            while ($cur < $t1) {
                $h    = (int) $cur->format('G');
                $next = (clone $cur)->setTime($h, 0, 0)->modify('+1 hour');
                if ($next > $t1) $next = clone $t1;
                $mins = ($next->getTimestamp() - $cur->getTimestamp()) / 60;
                if ($h >= 8 && $h < 20) {
                    $hour_min[$h]      += $mins;
                    $hour_wsum[$h]     += $w * $mins;
                    $hour_staffsum[$h] += $stf * $mins;
                    $tot_min += $mins;
                    if ($cl) { $dist['Zatvoreno'] += $mins; }
                    elseif ($fl) { $dist['Popunjeno'] += $mins; }
                    elseif ($w == 0) { $dist['Slobodno (0)'] += $mins; $free_min += $mins; }
                    elseif ($w <= 10) { $dist['Kratko (5-10)'] += $mins; }
                    elseif ($w <= 30) { $dist['Srednje (15-30)'] += $mins; }
                    else { $dist['Dugo (35+)'] += $mins; $heavy_min += $mins; }
                }
                $cur = $next;
            }
        }
    }

    // Najveći prosek za skaliranje trake
    $max_avg = 0.01;
    for ($h = 8; $h < 20; $h++) if ($hour_min[$h] > 0) $max_avg = max($max_avg, $hour_wsum[$h] / $hour_min[$h]);

    $DN = ['Sunday' => 'ned', 'Monday' => 'pon', 'Tuesday' => 'uto', 'Wednesday' => 'sre', 'Thursday' => 'čet', 'Friday' => 'pet', 'Saturday' => 'sub'];
    echo '<div class="wrap"><h1>Live istorija</h1>';
    echo '<p style="color:#666;">Popular times = vremenski ponderisano (status važi dok se ne promeni), radno vreme 08–20h, beogradsko vreme.</p>';

    // Izbor perioda
    echo '<p>';
    foreach ([7, 14, 30, 90] as $d) {
        $url = admin_url('admin.php?page=dry65-live-istorija&days=' . $d);
        $st  = $d === $days ? 'font-weight:700;text-decoration:none;' : '';
        echo '<a href="' . esc_url($url) . '" class="button ' . ($d === $days ? 'button-primary' : '') . '" style="margin-right:6px;' . $st . '">' . $d . ' dana</a>';
    }
    echo '</p>';

    if (!$rows) { echo '<p><em>Nema podataka za izabrani period.</em></p></div>'; return; }

    // Najveći broj poseta u satu (za skaliranje trake poseta)
    $max_visits = 0;
    for ($h = 8; $h < 20; $h++) $max_visits = max($max_visits, $visits_by_hour[$h]);

    // ---- Popular times ----
    echo '<h2>Popular times — kad je gužva</h2>';
    echo '<table class="widefat striped" style="max-width:880px;"><thead><tr><th>Sat</th><th>Prosek čekanja</th><th style="width:32%;">Gužva</th><th>Prosek ekipe</th><th>Posete /live</th><th style="width:22%;">Posete</th></tr></thead><tbody>';
    for ($h = 8; $h < 20; $h++) {
        if ($hour_min[$h] <= 0 && $visits_by_hour[$h] <= 0) continue;
        $avg    = $hour_min[$h] > 0 ? $hour_wsum[$h] / $hour_min[$h] : 0;
        $staff  = $hour_min[$h] > 0 ? $hour_staffsum[$h] / $hour_min[$h] : 0;
        $pct    = (int) round(100 * $avg / $max_avg);
        $vis    = $visits_by_hour[$h];
        $vpct   = $max_visits > 0 ? (int) round(100 * $vis / $max_visits) : 0;
        echo '<tr><td><strong>' . sprintf('%02d', $h) . 'h</strong></td>';
        echo '<td>' . number_format($avg, 1) . ' min</td>';
        echo '<td><div style="background:#F6D63B;height:16px;border-radius:3px;width:' . max(2, $pct) . '%;"></div></td>';
        echo '<td>' . ($staff > 0 ? number_format($staff, 1) : '—') . '</td>';
        echo '<td>' . ($vis > 0 ? $vis : '—') . '</td>';
        echo '<td>' . ($vis > 0 ? '<div style="background:#3BA7F6;height:16px;border-radius:3px;width:' . max(2, $vpct) . '%;"></div>' : '') . '</td></tr>';
    }
    echo '</tbody></table>';
    if ($visits_total <= 0) {
        echo '<p style="color:#999;font-size:12px;">Posete /live se loguju od uvođenja ove funkcije — za sad još nema podataka (ili nije prošao nijedan sat sa posetiocem).</p>';
    }

    // ---- Po periodu dana: gužva vs posete ----
    $periods = [
        'Jutro (08–12)'   => [8, 12],
        'Podne (12–16)'   => [12, 16],
        'Popodne (16–20)' => [16, 20],
    ];
    echo '<h2 style="margin-top:28px;">Po periodu dana — gužva vs posete</h2>';
    echo '<table class="widefat striped" style="max-width:560px;"><thead><tr><th>Period</th><th>Prosek čekanja</th><th>Posete /live</th></tr></thead><tbody>';
    foreach ($periods as $label => $range) {
        $wsum = $mins = 0.0; $vis = 0;
        for ($h = $range[0]; $h < $range[1]; $h++) {
            $wsum += $hour_wsum[$h]; $mins += $hour_min[$h];
            $vis  += $visits_by_hour[$h];
        }
        $avg = $mins > 0 ? $wsum / $mins : 0;
        echo '<tr><td><strong>' . esc_html($label) . '</strong></td>';
        echo '<td>' . number_format($avg, 1) . ' min</td>';
        echo '<td>' . ($vis > 0 ? $vis : '—') . '</td></tr>';
    }
    echo '</tbody></table>';

    // ---- Raspodela ----
    echo '<h2 style="margin-top:28px;">Raspodela statusa (udeo radnog vremena)</h2>';
    echo '<table class="widefat striped" style="max-width:420px;"><tbody>';
    foreach ($dist as $k => $v) {
        if ($v <= 0) continue;
        echo '<tr><td>' . esc_html($k) . '</td><td>' . round(100 * $v / max($tot_min, 1)) . '%</td></tr>';
    }
    echo '</tbody></table>';

    // ---- Po danu ----
    echo '<h2 style="margin-top:28px;">Po danu</h2>';
    echo '<table class="widefat striped" style="max-width:820px;"><thead><tr><th>Datum</th><th>Dan</th><th>Updejta</th><th>Radno</th><th>Prosek (kad &gt;0)</th><th>Max</th><th>Ø ekipa</th></tr></thead><tbody>';
    foreach (array_reverse(array_keys($by_day)) as $d) {
        $ev = $by_day[$d];
        $waits = array_map(fn($r) => (int) $r['wait'], $ev);
        $busy  = array_filter($waits, fn($w) => $w > 0);
        $stf   = array_map(fn($r) => (int) $r['staff'], $ev);
        $stf_nonzero = array_filter($stf, fn($s) => $s > 0);
        $avg   = $busy ? round(array_sum($busy) / count($busy), 1) : 0;
        $mx    = $waits ? max($waits) : 0;
        $stfavg = $stf_nonzero ? number_format(array_sum($stf_nonzero) / count($stf_nonzero), 1) : '—';
        $first = substr($ev[0]['logged_at'], 11, 5);
        $last  = substr($ev[count($ev) - 1]['logged_at'], 11, 5);
        $dn    = $DN[(new DateTime($d, $tz))->format('l')];
        echo '<tr><td>' . esc_html($d) . '</td><td>' . esc_html($dn) . '</td><td>' . count($ev) . '</td><td>' . esc_html("$first–$last") . '</td><td>' . $avg . ' min</td><td>' . $mx . ' min</td><td>' . $stfavg . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<p style="color:#999;font-size:12px;margin-top:18px;">Napomena: „Prosek ekipe" počinje da se puni od kada se broj frizera loguje. Stariji zapisi od pre te izmene mogu imati 0.</p>';
    echo '</div>';
}

/* ---- Raspored smene (nedeljni): po imenu, radni dani (Pon-Pet) + subota ---- */

/* HH:MM ili '' ako nije validno. */
function dry65_live_sanitize_time($v) {
    $v = trim((string) $v);
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : '';
}

/* Ceo raspored: [ 'Ime' => ['wd_from','wd_to','sat_from','sat_to'], ... ] */
function dry65_live_schedule_get() {
    $saved = get_option('dry65_live_schedule', []);
    if (!is_array($saved)) $saved = [];
    $out = [];
    foreach (dry65_live_staff_all() as $name) {
        $r = isset($saved[$name]) && is_array($saved[$name]) ? $saved[$name] : [];
        $out[$name] = [
            'wd_from'  => dry65_live_sanitize_time($r['wd_from']  ?? ''),
            'wd_to'    => dry65_live_sanitize_time($r['wd_to']    ?? ''),
            'sat_from' => dry65_live_sanitize_time($r['sat_from'] ?? ''),
            'sat_to'   => dry65_live_sanitize_time($r['sat_to']   ?? ''),
        ];
    }
    return $out;
}

/* Izuzeci po datumu: [ 'Y-m-d' => [ 'Ime' => ['from','to'], ... ], ... ].
   Prošli datumi se automatski odbacuju (ne moraš ručno da čistiš). */
function dry65_live_schedule_exceptions_get() {
    $saved = get_option('dry65_live_schedule_exc', []);
    if (!is_array($saved)) $saved = [];
    $tz    = new DateTimeZone('Europe/Belgrade');
    $today = (new DateTime('now', $tz))->format('Y-m-d');
    $out = [];
    foreach ($saved as $date => $people) {
        $date = (string) $date;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
        if ($date < $today) continue;           // prošlo — preskoči
        if (!is_array($people)) continue;
        $row = [];
        foreach (dry65_live_staff_all() as $name) {
            $p = isset($people[$name]) && is_array($people[$name]) ? $people[$name] : [];
            $row[$name] = [
                'from' => dry65_live_sanitize_time($p['from'] ?? ''),
                'to'   => dry65_live_sanitize_time($p['to']   ?? ''),
            ];
        }
        $out[$date] = $row;
    }
    ksort($out);
    return $out;
}

/* Raspored za konkretan dan kao [ 'Ime' => ['from','to'], ... ].
   Ako za taj datum postoji izuzetak, on GAZI osnovni (Pon–Pet/Subota).
   Nedelja bez izuzetka = []. */
function dry65_live_shifts_for_date($dt) {
    $date = $dt->format('Y-m-d');
    $exc  = dry65_live_schedule_exceptions_get();
    if (isset($exc[$date])) return $exc[$date];

    $dow = (int) $dt->format('N'); // 1=Pon..7=Ned
    if ($dow === 7) return [];     // nedelja bez izuzetka = ne radi se
    $pref = ($dow === 6) ? 'sat' : 'wd';
    $out = [];
    foreach (dry65_live_schedule_get() as $name => $r) {
        $out[$name] = ['from' => $r[$pref . '_from'], 'to' => $r[$pref . '_to']];
    }
    return $out;
}

/* Broj ljudi koji rade u dati DateTime (osnovni raspored + izuzeci). Ned = 0. */
function dry65_live_schedule_staff_at($dt = null) {
    $tz = new DateTimeZone('Europe/Belgrade');
    $dt = $dt instanceof DateTime ? $dt : new DateTime('now', $tz);
    $min  = (int) $dt->format('H') * 60 + (int) $dt->format('i');
    $n = 0;
    foreach (dry65_live_shifts_for_date($dt) as $p) {
        $f = $p['from']; $t = $p['to'];
        if ($f === '' || $t === '') continue;
        $fm = (int) substr($f, 0, 2) * 60 + (int) substr($f, 3, 2);
        $tm = (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        if ($min >= $fm && $min < $tm) $n++;
    }
    return $n;
}

/* Imena koja rade U OVOM TRENUTKU (osnovni raspored + izuzeci). Ned = []. */
function dry65_live_schedule_now_names() {
    $tz  = new DateTimeZone('Europe/Belgrade');
    $now = new DateTime('now', $tz);
    $min = (int) $now->format('H') * 60 + (int) $now->format('i');
    $out = [];
    foreach (dry65_live_shifts_for_date($now) as $name => $p) {
        $f = $p['from']; $t = $p['to'];
        if ($f === '' || $t === '') continue;
        $fm = (int) substr($f, 0, 2) * 60 + (int) substr($f, 3, 2);
        $tm = (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        if ($min >= $fm && $min < $tm) $out[] = $name;
    }
    return $out;
}

/* Srpski naziv dana za Y-m-d (npr. "Sreda"). */
function dry65_live_dow_name($date) {
    $tz = new DateTimeZone('Europe/Belgrade');
    $d  = DateTime::createFromFormat('Y-m-d', $date, $tz);
    if (!$d) return '';
    $names = [1 => 'Ponedeljak', 2 => 'Utorak', 3 => 'Sreda', 4 => 'Četvrtak', 5 => 'Petak', 6 => 'Subota', 7 => 'Nedelja'];
    return $names[(int) $d->format('N')] ?? '';
}

/* „Trenutno rade: X, Y i Z" iz rasporeda (za /live). '' ako trenutno niko / nedelja. */
function dry65_live_today_text() {
    $names = dry65_live_schedule_now_names();
    $n = count($names);
    if ($n === 0) return '';
    if ($n === 1) return 'Trenutno radi: ' . $names[0];
    $last = array_pop($names);
    return 'Trenutno rade: ' . implode(', ', $names) . ' i ' . $last;
}

function dry65_live_schedule_page() {
    if (!current_user_can(DRY65_LIVE_CAP)) wp_die('Nemate dozvolu.');
    $sched = dry65_live_schedule_get();
    $now_n = dry65_live_schedule_staff_at();
    $show  = get_option('dry65_live_chairs_show', '0') === '1';
    $today = dry65_live_today_text();
    ?>
    <div class="wrap">
      <h1>Raspored smene</h1>
      <p style="color:#666;max-width:640px;">Nedeljni raspored. Za svako ime unesi kada počinje i završava — radnim danima (Pon–Pet) i subotom. Vremena se preklapaju (npr. jedna 8–17, druga 10–20). Nedelja = ne radi se.</p>
      <?php if (isset($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>Raspored sačuvan.</p></div><?php endif; ?>
      <p style="font-size:13px;color:#555;">Trenutno po rasporedu radi: <strong><?php echo (int) $now_n; ?></strong> <?php echo $now_n === 1 ? 'osoba' : 'osoba/e'; ?>.</p>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dry65_live_schedule_save">
        <?php wp_nonce_field('dry65_live_schedule_save'); ?>
        <table class="widefat striped" style="max-width:680px;margin-top:12px;">
          <thead>
            <tr>
              <th rowspan="2" style="vertical-align:bottom;">Ime</th>
              <th colspan="2" style="text-align:center;border-left:1px solid #dcdcde;">Radni dani (Pon–Pet)</th>
              <th colspan="2" style="text-align:center;border-left:1px solid #dcdcde;">Subota</th>
            </tr>
            <tr>
              <th style="border-left:1px solid #dcdcde;">Od</th><th>Do</th>
              <th style="border-left:1px solid #dcdcde;">Od</th><th>Do</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (dry65_live_staff_all() as $name): $r = $sched[$name]; ?>
            <tr>
              <td><strong><?php echo esc_html($name); ?></strong></td>
              <td style="border-left:1px solid #dcdcde;"><input type="time" name="schedule[<?php echo esc_attr($name); ?>][wd_from]" value="<?php echo esc_attr($r['wd_from']); ?>"></td>
              <td><input type="time" name="schedule[<?php echo esc_attr($name); ?>][wd_to]" value="<?php echo esc_attr($r['wd_to']); ?>"></td>
              <td style="border-left:1px solid #dcdcde;"><input type="time" name="schedule[<?php echo esc_attr($name); ?>][sat_from]" value="<?php echo esc_attr($r['sat_from']); ?>"></td>
              <td><input type="time" name="schedule[<?php echo esc_attr($name); ?>][sat_to]" value="<?php echo esc_attr($r['sat_to']); ?>"></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p style="color:#888;font-size:12px;max-width:680px;">Ostavi prazno ako neko taj dan ne radi. Imena se uređuju u kodu (<code>dry65_live_staff_all()</code>).</p>

        <?php
          $exc       = dry65_live_schedule_exceptions_get();
          $today_str = (new DateTime('now', new DateTimeZone('Europe/Belgrade')))->format('Y-m-d');
          $staff     = dry65_live_staff_all();
        ?>
        <h2 style="margin-top:26px;">Posebni dani (izuzeci)</h2>
        <p style="color:#666;max-width:680px;font-size:13px;">Za dane koji odstupaju od osnovnog rasporeda: klikni <strong>„+ Dodaj dan"</strong>, izaberi datum i upiši smene za taj dan. Taj datum <strong>gazi</strong> Pon–Pet/Subotu samo za sebe, i sam nestaje kad prođe. Ostavi prazno kod nekoga ko taj dan ne radi.</p>

        <div id="dry65-exc-list">
          <?php $i = 0; foreach ($exc as $date => $people): ?>
          <div class="dry65-exc" style="border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;margin:10px 0;background:#fff;max-width:680px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
              <label style="font-weight:600;">Datum: <input type="date" class="dry65-exc-date" name="exc[<?php echo $i; ?>][date]" value="<?php echo esc_attr($date); ?>" min="<?php echo esc_attr($today_str); ?>"></label>
              <span class="dry65-exc-dow" style="color:#666;font-size:13px;"><?php echo esc_html(dry65_live_dow_name($date)); ?></span>
              <button type="button" class="button-link dry65-exc-remove" style="margin-left:auto;color:#b32d2e;">Ukloni</button>
            </div>
            <table class="widefat striped" style="max-width:520px;">
              <thead><tr><th>Ime</th><th>Od</th><th>Do</th></tr></thead>
              <tbody>
                <?php foreach ($staff as $name): ?>
                <tr>
                  <td><strong><?php echo esc_html($name); ?></strong></td>
                  <td><input type="time" name="exc[<?php echo $i; ?>][people][<?php echo esc_attr($name); ?>][from]" value="<?php echo esc_attr($people[$name]['from'] ?? ''); ?>"></td>
                  <td><input type="time" name="exc[<?php echo $i; ?>][people][<?php echo esc_attr($name); ?>][to]" value="<?php echo esc_attr($people[$name]['to'] ?? ''); ?>"></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php $i++; endforeach; ?>
        </div>

        <p><button type="button" class="button" id="dry65-exc-add">+ Dodaj dan</button></p>

        <template id="dry65-exc-tpl">
          <div class="dry65-exc" style="border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;margin:10px 0;background:#fff;max-width:680px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
              <label style="font-weight:600;">Datum: <input type="date" class="dry65-exc-date" name="exc[__IDX__][date]" value="" min="<?php echo esc_attr($today_str); ?>"></label>
              <span class="dry65-exc-dow" style="color:#666;font-size:13px;"></span>
              <button type="button" class="button-link dry65-exc-remove" style="margin-left:auto;color:#b32d2e;">Ukloni</button>
            </div>
            <table class="widefat striped" style="max-width:520px;">
              <thead><tr><th>Ime</th><th>Od</th><th>Do</th></tr></thead>
              <tbody>
                <?php foreach ($staff as $name): ?>
                <tr>
                  <td><strong><?php echo esc_html($name); ?></strong></td>
                  <td><input type="time" name="exc[__IDX__][people][<?php echo esc_attr($name); ?>][from]" value=""></td>
                  <td><input type="time" name="exc[__IDX__][people][<?php echo esc_attr($name); ?>][to]" value=""></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </template>

        <script>
        (function () {
          var list = document.getElementById('dry65-exc-list');
          var tpl  = document.getElementById('dry65-exc-tpl');
          var add  = document.getElementById('dry65-exc-add');
          var idx  = <?php echo (int) $i; ?>;
          var DOW  = ['Nedelja','Ponedeljak','Utorak','Sreda','Četvrtak','Petak','Subota'];

          function dowName(val) {
            var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(val || '');
            if (!m) return '';
            var d = new Date(+m[1], +m[2] - 1, +m[3]);
            return DOW[d.getDay()] || '';
          }
          function wire(box) {
            var date = box.querySelector('.dry65-exc-date');
            var dow  = box.querySelector('.dry65-exc-dow');
            var rm   = box.querySelector('.dry65-exc-remove');
            if (date && dow) date.addEventListener('change', function () { dow.textContent = dowName(date.value); });
            if (rm) rm.addEventListener('click', function () { box.remove(); });
          }
          list.querySelectorAll('.dry65-exc').forEach(wire);
          add.addEventListener('click', function () {
            var html = tpl.innerHTML.replace(/__IDX__/g, idx++);
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            var box = wrap.firstChild;
            list.appendChild(box);
            wire(box);
          });
        })();
        </script>

        <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;max-width:680px;margin:18px 0;">
          <label style="display:flex;align-items:center;gap:10px;font-weight:600;">
            <input type="checkbox" name="show_on_live" value="1" <?php checked($show); ?>>
            Prikaži „ko danas radi" na /live
          </label>
          <p style="color:#888;font-size:12px;margin:8px 0 0;">
            Kad je uključeno, na /live piše ko je <strong>trenutno</strong> u smeni: „<?php echo esc_html($today !== '' ? $today : 'Trenutno rade …'); ?>" (automatski iz rasporeda, po satu). Kad je isključeno, ne vidi se.
          </p>
        </div>

        <p><button type="submit" class="button button-primary">Sačuvaj raspored</button></p>
      </form>
    </div>
    <?php
}

add_action('admin_post_dry65_live_schedule_save', function() {
    if (!current_user_can(DRY65_LIVE_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_live_schedule_save');
    $in  = isset($_POST['schedule']) && is_array($_POST['schedule']) ? $_POST['schedule'] : [];
    $out = [];
    foreach (dry65_live_staff_all() as $name) {
        $r = isset($in[$name]) && is_array($in[$name]) ? $in[$name] : [];
        $out[$name] = [
            'wd_from'  => dry65_live_sanitize_time($r['wd_from']  ?? ''),
            'wd_to'    => dry65_live_sanitize_time($r['wd_to']    ?? ''),
            'sat_from' => dry65_live_sanitize_time($r['sat_from'] ?? ''),
            'sat_to'   => dry65_live_sanitize_time($r['sat_to']   ?? ''),
        ];
    }
    update_option('dry65_live_schedule', $out);

    // Izuzeci po datumu: [ 'Y-m-d' => [ 'Ime' => ['from','to'] ] ]. Prošli/nevalidni se odbacuju.
    $exc_in    = isset($_POST['exc']) && is_array($_POST['exc']) ? $_POST['exc'] : [];
    $tz        = new DateTimeZone('Europe/Belgrade');
    $today_str = (new DateTime('now', $tz))->format('Y-m-d');
    $exc_out   = [];
    foreach ($exc_in as $block) {
        if (!is_array($block)) continue;
        $date = isset($block['date']) ? trim((string) $block['date']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
        $d = DateTime::createFromFormat('Y-m-d', $date, $tz);
        if (!$d || $d->format('Y-m-d') !== $date) continue; // nepostojeći datum
        if ($date < $today_str) continue;                    // prošlo — preskoči
        $people = isset($block['people']) && is_array($block['people']) ? $block['people'] : [];
        $row = [];
        foreach (dry65_live_staff_all() as $name) {
            $p = isset($people[$name]) && is_array($people[$name]) ? $people[$name] : [];
            $row[$name] = [
                'from' => dry65_live_sanitize_time($p['from'] ?? ''),
                'to'   => dry65_live_sanitize_time($p['to']   ?? ''),
            ];
        }
        $exc_out[$date] = $row; // isti datum unet dvaput: poslednji pobeđuje
    }
    ksort($exc_out);
    update_option('dry65_live_schedule_exc', $exc_out);

    update_option('dry65_live_chairs_show', isset($_POST['show_on_live']) ? '1' : '0');
    wp_redirect(add_query_arg(['page' => 'dry65-live-raspored', 'saved' => '1'], admin_url('admin.php')));
    exit;
});

/* Snimanje: jedan admin_post handler za sva dugmad. */
add_action('admin_post_dry65_live_save', function() {
    if (!current_user_can(DRY65_LIVE_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_live_save');

    $action = isset($_POST['live_action']) ? sanitize_key($_POST['live_action']) : '';

    if ($action === 'closed') {
        update_option('dry65_live_closed', '1');
        update_option('dry65_live_full', '0');
    } elseif ($action === 'full') {
        update_option('dry65_live_full', '1');
        update_option('dry65_live_closed', '0');
    } else {
        $wait = isset($_POST['live_wait']) ? (int) $_POST['live_wait'] : 0;
        if (!in_array($wait, dry65_live_allowed_waits(), true)) $wait = 0;
        update_option('dry65_live_wait', $wait);
        update_option('dry65_live_closed', '0');
        update_option('dry65_live_full', '0'); // klik na vreme = ponovo primamo
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
                <button type="submit" name="live_action" value="full"
                    class="dry65-live-btn<?php echo $raw['full'] ? ' is-current' : ''; ?>"
                    style="--btn-bg:#E8C3C2;--btn-ink:<?php echo esc_attr(dry65_live_text_on('#E8C3C2')); ?>;">
                    ♥ Za danas popunjeni
                </button>
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

        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;max-width:620px;margin-top:26px;">
            <h2 style="margin-top:0;">✏️ Tekstovi po vremenu</h2>
            <p style="color:#555;margin-top:4px;">Naslov i opis koji se prikazuju na <code>/live</code> za svako vreme čekanja. Ostavi prazno = podrazumevani tekst. Menjaš ovde, bez koda.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dry65_live_save_texts">
                <?php wp_nonce_field('dry65_live_save_texts'); ?>
                <?php foreach (dry65_live_texts() as $v => $t):
                    if ($v === 'full') { $label = '♥ Za danas popunjeni'; $sph = 'Opis (prazno = „vidimo se sutra od 8h/10h" automatski)'; }
                    elseif ($v === 0)  { $label = 'Slobodno (0 min)'; $sph = 'Opisni tekst'; }
                    elseif ($v === 60) { $label = '60+ min'; $sph = 'Opisni tekst'; }
                    else               { $label = $v . ' min'; $sph = 'Opisni tekst'; }
                ?>
                <div style="border-top:1px solid #eef0f2;padding:14px 0;">
                    <div style="font-weight:600;font-size:13px;color:#1d2327;margin-bottom:7px;"><?php echo esc_html($label); ?></div>
                    <input type="text" name="texts[<?php echo esc_attr($v); ?>][h]" value="<?php echo esc_attr($t['h']); ?>"
                        placeholder="Naslov" style="width:100%;max-width:560px;margin-bottom:7px;display:block;">
                    <input type="text" name="texts[<?php echo esc_attr($v); ?>][s]" value="<?php echo esc_attr($t['s']); ?>"
                        placeholder="<?php echo esc_attr($sph); ?>" style="width:100%;max-width:560px;display:block;">
                </div>
                <?php endforeach; ?>
                <button class="button button-primary" style="margin-top:16px;">Sačuvaj tekstove</button>
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
                <li>Za danas popunjeni &nbsp;→&nbsp; …/live?key=…&amp;set=full</li>
                <li>Zatvoreni &nbsp;→&nbsp; …/live?key=…&amp;set=closed</li>
                <li>Prikaži/sakrij ko radi &nbsp;→&nbsp; …/live?key=…&amp;staff_show=1 (ili 0)</li>
            </ul>
            <p style="color:#888;font-size:12px;"><code>set</code>: <?php echo esc_html(dry65_live_allowed_waits_text()); ?>, full. &nbsp; „Ko danas radi" se sad vodi iz <strong>Raspored smene</strong> (automatski), a <code>staff_show</code> samo pali/gasi prikaz. Odgovor vraća novi status.</p>

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
    nocache_headers(); // status je uživo — nikad ne keširaj (ni CDN ni browser)
    if (function_exists('do_action')) do_action('litespeed_control_set_nocache', 'dry65 live ajax');
    $st  = dry65_live_resolve();
    $biz = function_exists('dry65_biz') ? dry65_biz() : ['phone_display' => '060 6900655'];

    // Heartbeat: token iz sessionStorage-a (v). Registruj i prebroj gledaoce.
    $token = isset($_GET['v']) ? preg_replace('/[^a-z0-9]/i', '', substr((string) $_GET['v'], 0, 32)) : '';
    $viewers = dry65_live_presence_touch($token);

    list($full_h, $full_s) = dry65_live_full_copy();
    wp_send_json([
        'closed'        => (bool) $st['closed'],
        'full'          => (bool) $st['full'],
        'full_h'        => (string) $full_h,
        'full_s'        => (string) $full_s,
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
        'staff_text'    => dry65_live_today_text(),
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
        $sv = strtolower((string) $set);
        if ($sv === 'closed') {
            update_option('dry65_live_closed', '1');
            update_option('dry65_live_full', '0');
            $did = true; $status_changed = true;
        } elseif ($sv === 'full') {
            update_option('dry65_live_full', '1');
            update_option('dry65_live_closed', '0');
            $did = true; $status_changed = true;
        } else {
            $wait = (int) $set;
            if (!in_array($wait, dry65_live_allowed_waits(), true)) {
                return new WP_Error('dry65_bad_set', 'Nedozvoljena vrednost. Dozvoljeno: ' . dry65_live_allowed_waits_text() . ', full.', ['status' => 400]);
            }
            update_option('dry65_live_wait', $wait);
            update_option('dry65_live_closed', '0');
            update_option('dry65_live_full', '0');
            $did = true; $status_changed = true;
        }
    }
    if ($message !== null) {
        update_option('dry65_live_message', sanitize_textarea_field((string) $message));
        $did = true;
    }

    // Prikaz „ko radi" na /live: staff_show=1 / 0. („Ko radi" se vodi iz Rasporeda smene.)
    $staff_show = $req->get_param('staff_show');
    if ($staff_show !== null && $staff_show !== '') {
        $on = ($staff_show === '1' || strtolower((string) $staff_show) === 'true');
        update_option('dry65_live_chairs_show', $on ? '1' : '0');
        $did = true;
    }

    if (!$did) {
        return new WP_Error('dry65_nothing', 'Pošalji "set", "message" ili "staff_show".', ['status' => 400]);
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
        'staff_text' => dry65_live_today_text(),
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

/* Sačuvaj editabilne tekstove (naslov + opis po vremenu). */
add_action('admin_post_dry65_live_save_texts', function () {
    if (!current_user_can(DRY65_LIVE_CAP)) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_live_save_texts');

    $in  = (isset($_POST['texts']) && is_array($_POST['texts'])) ? wp_unslash($_POST['texts']) : [];
    $out = [];
    foreach (array_keys(dry65_live_default_texts()) as $v) { // uključuje i 'full'
        $h = isset($in[$v]['h']) ? sanitize_text_field($in[$v]['h']) : '';
        $s = isset($in[$v]['s']) ? sanitize_text_field($in[$v]['s']) : '';
        if ($h !== '' || $s !== '') $out[$v] = ['h' => $h, 's' => $s];
    }
    update_option('dry65_live_texts', $out);

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

/* ============================================================
   Jutarnji auto-reset na „Slobodno" (08:00 Beograd, svaki dan)
   ------------------------------------------------------------
   Ako uveče ostane „Za danas popunjeni" (ili bilo šta od juče —
   čekanje/zatvoreno/custom poruka), ujutru u 8h WP-Cron vraća
   status na Slobodno, da nova dnevna gužva kreće od nule i da
   mušterije mogu opet da „pritiskaju". Radno vreme i dalje
   automatski gasi prikaz pre otvaranja (Sub pre 10h, Ned).
   Napomena: WP-Cron okida na prvi saobraćaj posle 8h (ne baš u 8:00:00).
   ============================================================ */
function dry65_live_morning_reset() {
    // Uvek prezakaži za sledećih 08:00 (single-event = tačno vreme i preko DST-a).
    dry65_live_arm_daily_reset(true);

    // Diraj status samo ako ima šta da se počisti — bez nepotrebnih upisa/logova.
    $raw = dry65_live_get_raw();
    if (!$raw['full'] && !$raw['closed'] && $raw['wait'] <= 0 && $raw['message'] === '') return;

    update_option('dry65_live_wait', 0);
    update_option('dry65_live_full', '0');
    update_option('dry65_live_closed', '0');
    update_option('dry65_live_message', '');
    update_option('dry65_live_updated_at', current_time('timestamp'));
    update_option('dry65_live_updated_by', 0); // 0 = automatika (ne osoblje)
    dry65_live_log_append(); // upiši „slobodno u 8h" u istoriju
}
add_action('dry65_live_daily_reset', 'dry65_live_morning_reset');

/* Zakaži jednokratni događaj za sledećih 08:00 po Beogradu.
   $force = true prvo očisti postojeći zakazani termin (koristi se pri
   prezakazivanju iz samog handlera). */
function dry65_live_arm_daily_reset($force = false) {
    if (!$force && wp_next_scheduled('dry65_live_daily_reset')) return;
    if ($force) wp_clear_scheduled_hook('dry65_live_daily_reset');

    $tz   = new DateTimeZone('Europe/Belgrade');
    $now  = new DateTime('now', $tz);
    $next = new DateTime('today 08:00', $tz);
    if ($next <= $now) $next->modify('+1 day');
    wp_schedule_single_event($next->getTimestamp(), 'dry65_live_daily_reset');
}
add_action('init', 'dry65_live_arm_daily_reset');
