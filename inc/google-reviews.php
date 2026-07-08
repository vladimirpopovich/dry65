<?php
/* ============================================================
   Dry65 — Google Reviews integration (Places API)
   Cache-uje reviews 12h u WP transient.
   ============================================================ */

/**
 * Sortiraj recenzije prema zadatom redosledu imena.
 * Matchovane idu na vrh u datom redosledu, ostale na kraj.
 */
function dry65_reorder_reviews($reviews, $name_order) {
    $matched = [];
    $remaining = $reviews;

    foreach ($name_order as $needle) {
        foreach ($remaining as $i => $r) {
            if (stripos($r['name'], $needle) !== false) {
                $matched[] = $r;
                unset($remaining[$i]);
                break;
            }
        }
    }
    return array_merge($matched, array_values($remaining));
}

/**
 * Konvertuj ćirilicu u latinicu.
 */
function dry65_cyr_to_lat($text) {
    $map = [
        'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Ђ'=>'Đ','Е'=>'E','Ж'=>'Ž','З'=>'Z','И'=>'I',
        'Ј'=>'J','К'=>'K','Л'=>'L','Љ'=>'Lj','М'=>'M','Н'=>'N','Њ'=>'Nj','О'=>'O','П'=>'P','Р'=>'R',
        'С'=>'S','Т'=>'T','Ћ'=>'Ć','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'C','Ч'=>'Č','Џ'=>'Dž','Ш'=>'Š',
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','ђ'=>'đ','е'=>'e','ж'=>'ž','з'=>'z','и'=>'i',
        'ј'=>'j','к'=>'k','л'=>'l','љ'=>'lj','м'=>'m','н'=>'n','њ'=>'nj','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','ћ'=>'ć','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'č','џ'=>'dž','ш'=>'š',
    ];
    return strtr($text, $map);
}

/**
 * Konvertuj Unix timestamp u "pre X" tekst (latinica).
 */
function dry65_time_ago_latinica($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60)        return 'upravo';
    if ($diff < 3600)      { $n = (int) floor($diff/60);    return 'pre ' . $n . ' ' . ($n == 1 ? 'minut' : ($n < 5 ? 'minuta' : 'minuta')); }
    if ($diff < 86400)     { $n = (int) floor($diff/3600);  return 'pre ' . $n . ' ' . ($n == 1 ? 'sat' : ($n < 5 ? 'sata' : 'sati')); }
    if ($diff < 604800)    { $n = (int) floor($diff/86400); return 'pre ' . $n . ' ' . ($n == 1 ? 'dan' : 'dana'); }
    if ($diff < 2629800)   { $n = (int) floor($diff/604800); return 'pre ' . $n . ' ' . ($n == 1 ? 'nedelju' : ($n < 5 ? 'nedelje' : 'nedelja')); }
    if ($diff < 31557600)  { $n = (int) floor($diff/2629800); return 'pre ' . $n . ' ' . ($n == 1 ? 'mesec' : ($n < 5 ? 'meseca' : 'meseci')); }
    $n = (int) floor($diff/31557600);
    return 'pre ' . $n . ' ' . ($n == 1 ? 'godinu' : ($n < 5 ? 'godine' : 'godina'));
}

/**
 * Interni API poziv za jedan sort mode.
 * Vraća parsed reviews array + meta (rating, total).
 */
function dry65_google_reviews_fetch_single($api_key, $place_id, $sort_mode = 'newest') {
    $url = 'https://maps.googleapis.com/maps/api/place/details/json?'
        . http_build_query([
            'place_id' => $place_id,
            'fields'   => 'reviews,rating,user_ratings_total',
            'language' => 'sr-Latn',
            'reviews_no_translations' => 'true',
            'reviews_sort' => $sort_mode,
            'key'      => $api_key,
        ]);

    $response = wp_remote_get($url, [
        'timeout' => 8,
        'headers' => ['Referer' => home_url('/')],
    ]);
    if (is_wp_error($response)) return ['reviews' => [], 'rating' => 0, 'total' => 0];

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['result']['reviews'])) {
        return [
            'reviews' => [],
            'rating'  => (float)($body['result']['rating'] ?? 0),
            'total'   => (int)($body['result']['user_ratings_total'] ?? 0),
        ];
    }

    $out = [];
    foreach ($body['result']['reviews'] as $r) {
        $when = '';
        $time = 0;
        if (!empty($r['time'])) {
            $time = (int) $r['time'];
            $when = dry65_time_ago_latinica($time);
        } else {
            $when = dry65_cyr_to_lat($r['relative_time_description'] ?? '');
        }
        $out[] = [
            'name'   => dry65_cyr_to_lat($r['author_name'] ?? ''),
            'rating' => (int) ($r['rating'] ?? 5),
            'when'   => $when,
            'text'   => dry65_cyr_to_lat($r['text'] ?? ''),
            'photo'  => $r['profile_photo_url'] ?? '',
            'time'   => $time,
        ];
    }

    return [
        'reviews' => $out,
        'rating'  => (float)($body['result']['rating'] ?? 0),
        'total'   => (int)($body['result']['user_ratings_total'] ?? 0),
    ];
}

/**
 * Dohvati Google reviews sa kombinovanim sort-om (newest + most_relevant).
 * Ovo daje 5-10 unikatnih recenzija umesto samo 5.
 * Cache 12h u transient. Update-uje arhivu.
 */
function dry65_google_reviews($force_refresh = false) {
    $cache_key = 'dry65_google_reviews_v2';

    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        $cached_total = get_transient('dry65_google_total');
        if ($cached !== false && $cached_total !== false && !empty($cached)) return $cached;
    }

    $api_key  = defined('DRY65_GOOGLE_API_KEY')  ? DRY65_GOOGLE_API_KEY  : get_option('dry65_google_api_key');
    $place_id = defined('DRY65_GOOGLE_PLACE_ID') ? DRY65_GOOGLE_PLACE_ID : get_option('dry65_google_place_id');

    if (!$api_key || !$place_id) return [];

    // Poziv 1: NEWEST (najsvežije 5)
    $newest = dry65_google_reviews_fetch_single($api_key, $place_id, 'newest');
    // Poziv 2: MOST_RELEVANT (najboljih 5 po Google-u — različita starost)
    $relevant = dry65_google_reviews_fetch_single($api_key, $place_id, 'most_relevant');

    // Kombinovanje bez duplikata (po hash-u name+text)
    $combined = [];
    $seen_hashes = [];
    foreach (array_merge($newest['reviews'], $relevant['reviews']) as $r) {
        $hash = dry65_review_hash($r);
        if (in_array($hash, $seen_hashes, true)) continue;
        $seen_hashes[] = $hash;
        $combined[] = $r;
    }

    // Debug fallback
    if (empty($combined)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Dry65 Google Reviews: no reviews returned from either sort mode.');
        }
        return [];
    }

    $out = $combined;

    // Meta - prefer newest, fallback relevant
    $rating = $newest['rating'] ?: $relevant['rating'];
    $total  = $newest['total']  ?: $relevant['total'];

    // Update arhive (svi Google recenzije koje smo ikad videli).
    dry65_update_google_reviews_archive($out);

    // Sacuvaj rating + total kao posebne transients
    if ($rating > 0) {
        set_transient('dry65_google_rating', $rating, 12 * HOUR_IN_SECONDS);
    }
    if ($total > 0) {
        set_transient('dry65_google_total', $total, 12 * HOUR_IN_SECONDS);
    }

    // Cache 12h
    set_transient($cache_key, $out, 12 * HOUR_IN_SECONDS);
    return $out;
}

/**
 * Vraca aggregate rating + ukupan broj iz Google-a.
 */
function dry65_google_meta() {
    // Triger fetch ako nema kesa
    dry65_google_reviews();
    return [
        'rating' => (float) get_transient('dry65_google_rating'),
        'total'  => (int)   get_transient('dry65_google_total'),
    ];
}

/**
 * Hash review-a za identifikaciju (name + prvih 50 chars text-a).
 * Koristi se za check listu skrivenih recenzija.
 */
function dry65_review_hash($review) {
    $name = $review['name'] ?? '';
    $text = $review['text'] ?? '';
    return md5($name . '|' . mb_substr($text, 0, 50));
}

/**
 * Update arhive Google recenzija. Cuva sve koje smo ikad videli
 * (Google API vraca max 5 po pozivu, ali nakon vise nedelja imamo sve).
 * Format: array indexed by hash.
 */
function dry65_update_google_reviews_archive($fetched) {
    $archive = get_option('dry65_google_reviews_archive', []);
    if (!is_array($archive)) $archive = [];

    $now = time();
    foreach ($fetched as $r) {
        $hash = dry65_review_hash($r);
        if (isset($archive[$hash])) {
            // Update: refresh "when" tekst i last_seen
            $archive[$hash]['when']      = $r['when'];
            $archive[$hash]['last_seen'] = $now;
        } else {
            // Novi review
            $archive[$hash] = array_merge($r, [
                'hash'       => $hash,
                'first_seen' => $now,
                'last_seen'  => $now,
            ]);
        }
    }
    update_option('dry65_google_reviews_archive', $archive);
}

/**
 * Vrati arhivu Google recenzija sa opcionalnim filterima.
 * @param array $filters ['rating' => 5, 'has_text' => true, 'sort' => 'newest'|'oldest']
 */
function dry65_google_reviews_archive($filters = []) {
    $archive = get_option('dry65_google_reviews_archive', []);
    if (!is_array($archive)) return [];

    $items = array_values($archive);

    // Filter po ratingu (npr. samo 5⭐)
    if (!empty($filters['rating'])) {
        $items = array_filter($items, fn($r) => (int)$r['rating'] >= (int)$filters['rating']);
    }

    // Filter: samo sa tekstom
    if (!empty($filters['has_text'])) {
        $items = array_filter($items, fn($r) => trim($r['text'] ?? '') !== '');
    }

    // Sort
    $sort = $filters['sort'] ?? 'newest';
    usort($items, function($a, $b) use ($sort) {
        $ta = (int)($a['time'] ?? $a['first_seen'] ?? 0);
        $tb = (int)($b['time'] ?? $b['first_seen'] ?? 0);
        return $sort === 'oldest' ? ($ta <=> $tb) : ($tb <=> $ta);
    });

    return array_values($items);
}

/**
 * Smart helper — vraća SAMO Google recenzije iz arhive.
 * CPT (ručno unete) recenzije se ne prikazuju na sajtu — Google API je jedini izvor.
 * Napomena: CPT ostaju u DB (WP Admin → Recenzije) za backup/istoriju.
 * Filter: samo 5⭐ + sa tekstom, sortirano po najnovijim.
 */
function dry65_reviews_smart() {
    $hidden = get_option('dry65_hidden_google_reviews', []);
    if (!is_array($hidden)) $hidden = [];

    // Trigger API fetch (updateuje arhivu ako treba)
    dry65_google_reviews();

    // Uzmi ARHIVU (svi koje smo ikad videli) minus skrivene
    $archive = dry65_google_reviews_archive([
        'rating'   => 5,
        'has_text' => true,
        'sort'     => 'newest',
    ]);
    $out = [];
    foreach ($archive as $r) {
        if (!in_array($r['hash'] ?? dry65_review_hash($r), $hidden, true)) {
            $out[] = $r;
        }
    }
    return $out;
}

/* ---- Admin opcije: gde editor unosi API ključ i Place ID ---- */
add_action('admin_menu', function() {
    add_options_page(
        'Dry65 Google Reviews',
        'Dry65 Google Reviews',
        'manage_options',
        'dry65-google-reviews',
        'dry65_google_reviews_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('dry65_google_reviews', 'dry65_google_api_key');
    register_setting('dry65_google_reviews', 'dry65_google_place_id');
});

/* ---- Handle "sakrij/prikazi" checkbox form (odvojeno od API settings) ---- */
add_action('admin_post_dry65_save_hidden_reviews', function() {
    if (!current_user_can('manage_options')) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_hidden_reviews');

    // Svi hash-evi iz forme (znaci koje recenzije POSTOJE u trenutnom API responseu)
    $all_hashes = isset($_POST['all_hashes']) ? (array) $_POST['all_hashes'] : [];
    // Hash-evi koji su cekirani (znaci prikazi na sajtu)
    $visible = isset($_POST['visible']) ? (array) $_POST['visible'] : [];

    // Skrivene = sve minus cekirane
    $hidden = array_diff($all_hashes, $visible);
    update_option('dry65_hidden_google_reviews', array_values($hidden));

    // Force purge LiteSpeed cache ako plugin postoji
    if (function_exists('do_action')) {
        do_action('litespeed_purge_all');
    }

    wp_redirect(add_query_arg(['page' => 'dry65-google-reviews', 'saved' => '1'], admin_url('options-general.php')));
    exit;
});

/* ---- Refresh dugme: force fetch iz Google-a ---- */
add_action('admin_post_dry65_refresh_google_reviews', function() {
    if (!current_user_can('manage_options')) wp_die('Nemate dozvolu.');
    check_admin_referer('dry65_refresh_google');

    delete_transient('dry65_google_reviews_v2');
    delete_transient('dry65_google_rating');
    delete_transient('dry65_google_total');
    dry65_google_reviews(true); // force refresh

    wp_redirect(add_query_arg(['page' => 'dry65-google-reviews', 'refreshed' => '1'], admin_url('options-general.php')));
    exit;
});

// Brisi keš svaki put kad se sačuva Settings stranica
add_action('update_option_dry65_google_api_key', function() {
    delete_transient('dry65_google_reviews_v1');
    delete_transient('dry65_google_reviews_v2');
    delete_transient('dry65_google_rating');
    delete_transient('dry65_google_total');
});
add_action('update_option_dry65_google_place_id', function() {
    delete_transient('dry65_google_reviews_v1');
    delete_transient('dry65_google_reviews_v2');
    delete_transient('dry65_google_rating');
    delete_transient('dry65_google_total');
});

function dry65_google_reviews_settings_page() {
    $api_key  = get_option('dry65_google_api_key');
    $place_id = get_option('dry65_google_place_id');
    $hidden   = get_option('dry65_hidden_google_reviews', []);
    if (!is_array($hidden)) $hidden = [];
    ?>
    <div class="wrap">
        <h1>Dry65 — Google recenzije</h1>

        <?php if (isset($_GET['saved'])): ?>
            <div class="notice notice-success is-dismissible"><p>Sačuvano. Sajt će odmah odražavati promene.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['refreshed'])): ?>
            <div class="notice notice-success is-dismissible"><p>Osveženo sa Google-a.</p></div>
        <?php endif; ?>

        <h2>1. API podešavanja</h2>
        <form method="post" action="options.php">
            <?php settings_fields('dry65_google_reviews'); ?>
            <table class="form-table">
                <tr>
                    <th><label>Google API ključ</label></th>
                    <td><input type="text" name="dry65_google_api_key" value="<?php echo esc_attr($api_key); ?>" style="width:480px"></td>
                </tr>
                <tr>
                    <th><label>Place ID</label></th>
                    <td><input type="text" name="dry65_google_place_id" value="<?php echo esc_attr($place_id); ?>" style="width:480px"></td>
                </tr>
            </table>
            <?php submit_button('Sačuvaj API podešavanja'); ?>
        </form>

        <hr style="margin:32px 0;">

        <h2>2. Google recenzije — biraj koje ide na sajt</h2>
        <p class="description">
            Google API vraća 5 najnovijih po pozivu, ali <strong>arhiviramo svaku koju vidimo</strong>.
            Za mesec-dva imaš sve recenzije. Klikni "🔄 Osveži sada" da povučeš najsvežije.
        </p>

        <p style="margin:16px 0;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                <input type="hidden" name="action" value="dry65_refresh_google_reviews">
                <?php wp_nonce_field('dry65_refresh_google'); ?>
                <button type="submit" class="button button-secondary">🔄 Osveži sada sa Google-a</button>
            </form>
            <span style="color:#666;margin-left:12px;">Cache 12h. Klikni ovde da povučeš najnovije odmah.</span>
        </p>

        <?php
        // Filteri (samo 5⭐ + sa tekstom, sortirano po najnovijim)
        $archive = dry65_google_reviews_archive([
            'rating'   => 5,
            'has_text' => true,
            'sort'     => 'newest',
        ]);

        // Force API poziv da pokupi najnovije (updateuje arhivu)
        dry65_google_reviews();

        // Ponovo uzmi arhivu nakon fetch-a
        $archive = dry65_google_reviews_archive([
            'rating'   => 5,
            'has_text' => true,
            'sort'     => 'newest',
        ]);

        if (empty($archive)) {
            // Debug: prikaži raw Google response da vidimo šta se dešava
            $api_key  = get_option('dry65_google_api_key');
            $place_id = get_option('dry65_google_place_id');
            echo '<div style="color:#a00;background:#fee;padding:12px;border-left:4px solid #c00;">';
            echo '<strong>Arhiva je prazna.</strong> Klikni "🔄 Osveži sada" iznad da pokušaš.<br>';
            echo 'Ako se ne pojavi, proveri raw response ispod:';
            echo '</div>';

            if ($api_key && $place_id) {
                $debug_url = 'https://maps.googleapis.com/maps/api/place/details/json?'
                    . http_build_query(['place_id' => $place_id, 'fields' => 'reviews,rating,user_ratings_total', 'language' => 'sr-Latn', 'key' => $api_key]);
                $debug_resp = wp_remote_get($debug_url, ['timeout' => 8, 'headers' => ['Referer' => home_url('/')]]);
                $debug_body = wp_remote_retrieve_body($debug_resp);
                echo '<details style="margin-top:16px;padding:10px;background:#fff;border:1px solid #ddd;">';
                echo '<summary><strong>Raw Google response</strong> (klikni za detalje)</summary>';
                echo '<pre style="background:#f9f9f9;padding:10px;overflow:auto;max-height:400px;font-size:11px;">' . esc_html($debug_body) . '</pre>';
                echo '</details>';
            }
        } else { ?>
            <p style="background:#e7f5e7;padding:10px;border-left:4px solid #46b450;margin-top:10px;">
                <strong>U arhivi:</strong> <?php echo count($archive); ?> recenzija (5⭐ + sa tekstom).
                Cekiraj koje želiš na sajtu.
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dry65_save_hidden_reviews">
                <?php wp_nonce_field('dry65_hidden_reviews'); ?>

                <p style="margin:12px 0;">
                    <button type="button" class="button" onclick="document.querySelectorAll('.review-check').forEach(c => c.checked = true)">✓ Cekiraj sve</button>
                    <button type="button" class="button" onclick="document.querySelectorAll('.review-check').forEach(c => c.checked = false)">☐ Odcekiraj sve</button>
                </p>

                <table class="wp-list-table widefat fixed striped" style="margin-top:8px;">
                    <thead>
                        <tr>
                            <th style="width:70px;text-align:center;">Prikaži</th>
                            <th style="width:180px;">Ime</th>
                            <th style="width:80px;">Ocena</th>
                            <th style="width:130px;">Vreme</th>
                            <th>Tekst</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archive as $r):
                            $hash = $r['hash'] ?? dry65_review_hash($r);
                            $is_visible = !in_array($hash, $hidden, true);
                        ?>
                            <tr>
                                <td style="text-align:center;">
                                    <input type="hidden" name="all_hashes[]" value="<?php echo esc_attr($hash); ?>">
                                    <input type="checkbox" class="review-check" name="visible[]" value="<?php echo esc_attr($hash); ?>" <?php checked($is_visible); ?> style="transform:scale(1.4);">
                                </td>
                                <td><strong><?php echo esc_html($r['name']); ?></strong></td>
                                <td>
                                    <span style="color:#f0b400;font-size:16px;">
                                        <?php echo str_repeat('★', (int)$r['rating']); ?><?php echo str_repeat('☆', 5 - (int)$r['rating']); ?>
                                    </span>
                                </td>
                                <td><em><?php echo esc_html($r['when']); ?></em></td>
                                <td style="font-size:13px;color:#333;">
                                    <?php echo esc_html(mb_substr($r['text'], 0, 220)); ?><?php echo mb_strlen($r['text']) > 220 ? '...' : ''; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top:20px;">
                    <button type="submit" class="button button-primary button-large">Sačuvaj izbor</button>
                </p>
            </form>

        <?php } ?>

        <hr style="margin:32px 0;">

        <h2>3. Statistika</h2>
        <?php
        $meta = dry65_google_meta();
        $full_archive = get_option('dry65_google_reviews_archive', []);
        $visible_count = 0;
        foreach ($archive as $r) {
            $hash = $r['hash'] ?? dry65_review_hash($r);
            if (!in_array($hash, $hidden, true)) $visible_count++;
        }
        $cpt_count = count(dry65_reviews());
        ?>
        <ul style="line-height:1.8;">
            <li><strong>Ukupno na Google-u:</strong> <?php echo (int)$meta['total']; ?> recenzija (prosek <?php echo number_format($meta['rating'], 1); ?>★)</li>
            <li><strong>U arhivi (svi 5⭐ sa tekstom):</strong> <?php echo count($archive); ?></li>
            <li><strong>Ukupno u arhivi (svi rangovi):</strong> <?php echo count($full_archive); ?></li>
            <li><strong>Prikazano na sajtu:</strong> <?php echo $visible_count; ?></li>
            <li style="color:#888;font-size:13px;"><em>Ručno unete (WP Admin → Recenzije) se više NE prikazuju na sajtu (samo Google API je izvor). CPT ostaje u DB za istoriju: <?php echo $cpt_count; ?></em></li>
        </ul>

        <p class="description" style="margin-top:16px;">
            💡 Google API sad koristi <strong>DVA sort-a u jednom pozivu</strong> — najnovije + najbolje po Google-u.
            Ovo daje <strong>5-10 unikatnih recenzija</strong> po refresh-u umesto samo 5.
            Arhiva raste sa svakim "Osveži sada" — za mesec-dva imaš sve.
        </p>
    </div>
<?php }
