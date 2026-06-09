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
 * Dohvati Google reviews. Cache 12h.
 * Vraća isti format kao dry65_reviews() — [['name','rating','when','text','photo']].
 */
function dry65_google_reviews($force_refresh = false) {
    $cache_key = 'dry65_google_reviews_v2';

    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        $cached_total = get_transient('dry65_google_total');
        // Ako nema total cache-a, forsiraj refresh (stari cache je bio bez total polja)
        if ($cached !== false && $cached_total !== false) return $cached;
    }

    $api_key  = defined('DRY65_GOOGLE_API_KEY')  ? DRY65_GOOGLE_API_KEY  : get_option('dry65_google_api_key');
    $place_id = defined('DRY65_GOOGLE_PLACE_ID') ? DRY65_GOOGLE_PLACE_ID : get_option('dry65_google_place_id');

    if (!$api_key || !$place_id) {
        return [];
    }

    $url = 'https://maps.googleapis.com/maps/api/place/details/json?'
        . http_build_query([
            'place_id' => $place_id,
            'fields'   => 'reviews,rating,user_ratings_total',
            'language' => 'sr-Latn',
            'reviews_no_translations' => 'true',
            'reviews_sort' => 'most_relevant',
            'key'      => $api_key,
        ]);

    $response = wp_remote_get($url, [
        'timeout' => 8,
        'headers' => [
            'Referer' => home_url('/'),
        ],
    ]);
    if (is_wp_error($response)) return [];

    $body = json_decode(wp_remote_retrieve_body($response), true);

    // Debug fallback — log šta Google vraća
    if (empty($body['result']['reviews'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Dry65 Google Reviews response: ' . wp_remote_retrieve_body($response));
        }
        return [];
    }

    $out = [];
    foreach ($body['result']['reviews'] as $r) {
        // Konvertuj Unix timestamp u "pre X" tekst na latinici
        $when = '';
        if (!empty($r['time'])) {
            $when = dry65_time_ago_latinica((int) $r['time']);
        } else {
            // Fallback: transliteruj ćirilski opis u latinicu
            $when = dry65_cyr_to_lat($r['relative_time_description'] ?? '');
        }
        $out[] = [
            'name'   => dry65_cyr_to_lat($r['author_name'] ?? ''),
            'rating' => (int) ($r['rating'] ?? 5),
            'when'   => $when,
            'text'   => dry65_cyr_to_lat($r['text'] ?? ''),
            'photo'  => $r['profile_photo_url'] ?? '',
        ];
    }

    // Ručno sortiranje: matchovane recenzije idu na vrh u datom redosledu,
    // ostale na kraj (jer Places API klasik ne podržava reviews_sort).
    $out = dry65_reorder_reviews($out, [
        'Marija Culibrk',
        'Sanda',
        'Marija Radovanovic',
        'Andjela',
        'Sara Sesto',
    ]);

    // Sacuvaj rating + total kao posebne transients
    if (isset($body['result']['rating'])) {
        set_transient('dry65_google_rating', (float) $body['result']['rating'], 12 * HOUR_IN_SECONDS);
    }
    if (isset($body['result']['user_ratings_total'])) {
        set_transient('dry65_google_total', (int) $body['result']['user_ratings_total'], 12 * HOUR_IN_SECONDS);
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
 * Smart helper — koristi samo CPT recenzije (ručno dodate kroz WP Admin → Recenzije).
 * Google API se ne koristi za listu recenzija (rating + ukupan broj se i dalje vuku iz API-ja).
 */
function dry65_reviews_smart() {
    $cpt = dry65_reviews();
    $out = [];
    foreach ($cpt as $r) {
        $out[] = [
            'name'   => $r['name'] ?? '',
            'rating' => (int)($r['rating'] ?? 5),
            'when'   => $r['when'] ?? '',
            'text'   => $r['text'] ?? '',
            'photo'  => $r['photo'] ?? '',
        ];
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

function dry65_google_reviews_settings_page() { ?>
    <div class="wrap">
        <h1>Dry65 — Google Reviews</h1>
        <form method="post" action="options.php">
            <?php settings_fields('dry65_google_reviews'); ?>
            <table class="form-table">
                <tr>
                    <th><label>Google API ključ</label></th>
                    <td><input type="text" name="dry65_google_api_key" value="<?php echo esc_attr(get_option('dry65_google_api_key')); ?>" style="width:480px"></td>
                </tr>
                <tr>
                    <th><label>Place ID</label></th>
                    <td><input type="text" name="dry65_google_place_id" value="<?php echo esc_attr(get_option('dry65_google_place_id')); ?>" style="width:480px"></td>
                </tr>
            </table>
            <?php submit_button('Sačuvaj'); ?>
        </form>

        <h2>Test</h2>
        <?php
        // Direct test sa raw response
        $api_key  = get_option('dry65_google_api_key');
        $place_id = get_option('dry65_google_place_id');
        if ($api_key && $place_id) {
            $debug_url = 'https://maps.googleapis.com/maps/api/place/details/json?'
                . http_build_query(['place_id' => $place_id, 'fields' => 'reviews,rating,user_ratings_total', 'language' => 'sr', 'key' => $api_key]);
            $debug_resp = wp_remote_get($debug_url, ['timeout' => 8, 'headers' => ['Referer' => home_url('/')]]);
            $debug_body = wp_remote_retrieve_body($debug_resp);
            echo '<details style="margin-bottom:20px;padding:10px;background:#fff;border:1px solid #ddd;">';
            echo '<summary>Raw Google response (klikni za detalje)</summary>';
            echo '<pre style="background:#f9f9f9;padding:10px;overflow:auto;max-height:400px;font-size:11px;">' . esc_html($debug_body) . '</pre>';
            echo '</details>';
        }
        $reviews = dry65_google_reviews(true); // force refresh
        if (empty($reviews)) {
            echo '<p style="color:#a00;">Nema rezultata. Proveri ključ i Place ID.</p>';
        } else {
            echo '<p style="color:#080;">Učitano ' . count($reviews) . ' recenzija sa Google-a.</p>';
            echo '<ul>';
            foreach ($reviews as $r) {
                echo '<li><strong>' . esc_html($r['name']) . '</strong> (' . $r['rating'] . '★, ' . esc_html($r['when']) . '): ' . esc_html(mb_substr($r['text'], 0, 120)) . '...</li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
<?php }
