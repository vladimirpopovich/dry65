<?php
/* ============================================================
   Dry65 — i18n (dvojezicni sloj)
   ------------------------------------------------------------
   SR je izvorni jezik. EN se servira preko /en/ prefiksa u putanji
   BEZ dupliranja strana u bazi: /en/usluge/ posluzi istu WP stranu
   kao /usluge/, samo sa jezikom = en.

   Kako radi:
   1) Na ucitavanju teme (pre nego sto WP parsira zahtev) proveravamo
      da li putanja pocinje sa /en. Ako da -> jezik = en, a REQUEST_URI
      prepisemo bez /en da WP normalno resolvuje SR stranu.
   2) Svi front-end linkovi (permalink, home_url) dobiju /en prefiks
      dok je jezik en -> navigacija ostaje u engleskom.
   3) t()  -> prevod kratkih stringova (kljuc = srpski original)
      tk() -> prevod velikih HTML/paragraf blokova (kljuc = kratak kod)
      Prevodi se cuvaju u /languages/en.php (asocijativni niz).
   ============================================================ */

if (!defined('ABSPATH')) exit;

/* ---- 1) Detekcija jezika + skidanje /en prefiksa iz rute ---- */
function dry65_boot_i18n() {
    // Admin, AJAX, REST, cron -> uvek izvorni jezik, ne diramo rutu
    if (is_admin()
        || (defined('DOING_AJAX') && DOING_AJAX)
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (defined('DOING_CRON') && DOING_CRON)) {
        $GLOBALS['dry65_lang'] = 'sr';
        return;
    }

    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    if ($path === null) $path = '/';

    if (preg_match('#^/en(/|$)#', $path)) {
        $GLOBALS['dry65_lang'] = 'en';
        // ukloni vodeci /en, zadrzi ostatak putanje + query string
        $new = preg_replace('#^/en#', '', $uri);
        if ($new === '' || $new[0] === '?') $new = '/' . ltrim($new, '/');
        if ($new === '' || $new[0] !== '/') $new = '/' . ltrim($new, '/');
        $_SERVER['REQUEST_URI'] = $new;
        // zapamti "cistu" (SR) putanju za switcher / hreflang
        $GLOBALS['dry65_path'] = parse_url($new, PHP_URL_PATH) ?: '/';
    } else {
        $GLOBALS['dry65_lang'] = 'sr';
        $GLOBALS['dry65_path'] = $path ?: '/';
    }
}
dry65_boot_i18n();

/* ---- Trenutni jezik ---- */
function dry65_lang() {
    return $GLOBALS['dry65_lang'] ?? 'sr';
}
function dry65_is_en() {
    return dry65_lang() === 'en';
}

/* ---- 2) Front-end linkovi dobijaju /en prefiks dok je jezik en ---- */
function dry65_prefix_en_url($url) {
    if (!dry65_is_en()) return $url;
    if (!is_string($url) || $url === '') return $url;
    // ne diraj REST rutu
    if (strpos($url, '/wp-json') !== false) return $url;

    // baza sajta bez trailing slasha (raw, bez filtera -> nema rekurzije)
    $base = untrailingslashit(get_option('home'));
    if (strpos($url, $base) !== 0) return $url; // eksterni URL

    $rest = substr($url, strlen($base)); // '' ili '/...'
    if ($rest === '/en' || strpos($rest, '/en/') === 0) return $url; // vec ima

    // ne diraj staticke fajlove (favicon.ico, sitemap.xml, robots.txt, *.png...)
    $rpath = parse_url($rest, PHP_URL_PATH) ?: '';
    $last  = basename($rpath);
    if ($last !== '' && strpos($last, '.') !== false) return $url;
    if ($rest === '') $rest = '/';
    return $base . '/en' . $rest;
}

add_filter('home_url', function ($url, $path, $scheme) {
    if (!dry65_is_en()) return $url;
    if ($scheme === 'rest') return $url;
    return dry65_prefix_en_url($url);
}, 10, 3);

// Permalinkovi strana / CPT / arhiva / termina
add_filter('page_link',              'dry65_prefix_en_url', 10, 1);
add_filter('post_link',              'dry65_prefix_en_url', 10, 1);
add_filter('post_type_link',         'dry65_prefix_en_url', 10, 1);
add_filter('post_type_archive_link', 'dry65_prefix_en_url', 10, 1);
add_filter('term_link',              'dry65_prefix_en_url', 10, 1);

/* Bez canonical redirect petlje: dok je jezik en, browser stoji na
   /en/... a WP interno vidi SR putanju, pa redirect_canonical ne treba. */
add_action('template_redirect', function () {
    if (dry65_is_en()) remove_action('template_redirect', 'redirect_canonical');
}, 0);

/* <html lang="..."> */
add_filter('language_attributes', function ($output) {
    return dry65_is_en() ? 'lang="en"' : 'lang="sr"';
}, 20);

/* ---- 3) Prevodi ---- */
function dry65_en_map() {
    static $map = null;
    if ($map === null) {
        $file = get_template_directory() . '/languages/en.php';
        $map  = file_exists($file) ? include $file : [];
        if (!is_array($map)) $map = [];
    }
    return $map;
}

/**
 * t() — kratki stringovi. Kljuc = srpski original.
 * SR: vrati original. EN: vrati prevod ako postoji, inace original (fallback).
 */
function t($sr) {
    if (!dry65_is_en()) return $sr;
    $map = dry65_en_map();
    // prazna vrednost = "jos nije prevedeno" -> fallback na srpski original
    return (isset($map[$sr]) && $map[$sr] !== '') ? $map[$sr] : $sr;
}
/** echo varijanta */
function te($sr) { echo t($sr); }

/**
 * tk() — veliki HTML/paragraf blokovi. Kljuc = kratak kod (npr. 'home.hero.lead').
 * SR: vrati $sr_default. EN: vrati prevod po kljucu, inace $sr_default (fallback).
 */
function tk($key, $sr_default = '') {
    if (!dry65_is_en()) return $sr_default;
    $map = dry65_en_map();
    return (isset($map[$key]) && $map[$key] !== '') ? $map[$key] : $sr_default;
}
function tke($key, $sr_default = '') { echo tk($key, $sr_default); }

/* ---- 4) Pomocni URL-ovi za switcher / hreflang ---- */
function dry65_current_path() {
    return $GLOBALS['dry65_path'] ?? '/';
}
/** Puni URL date strane u trazenom jeziku (za switcher i hreflang) */
function dry65_lang_url($lang, $path = null) {
    $base = untrailingslashit(get_option('home'));
    $path = $path ?: dry65_current_path();
    if ($path === '' || $path[0] !== '/') $path = '/' . ltrim($path, '/');
    return $lang === 'en' ? $base . '/en' . ($path === '/' ? '/' : $path)
                          : $base . $path;
}

/* ---- 5) Switcher u navigaciji ---- */
function dry65_lang_switcher() {
    $sr = dry65_lang_url('sr');
    $en = dry65_lang_url('en');
    $cur = dry65_lang();
    ?>
    <div class="lang-switch" role="group" aria-label="Jezik / Language">
      <a class="lang-opt<?php echo $cur === 'sr' ? ' is-active' : ''; ?>"
         href="<?php echo esc_url($sr); ?>" hreflang="sr"
         <?php echo $cur === 'sr' ? 'aria-current="true"' : ''; ?>>SR</a>
      <span class="lang-sep" aria-hidden="true">/</span>
      <a class="lang-opt<?php echo $cur === 'en' ? ' is-active' : ''; ?>"
         href="<?php echo esc_url($en); ?>" hreflang="en"
         <?php echo $cur === 'en' ? 'aria-current="true"' : ''; ?>>EN</a>
    </div>
    <?php
}
