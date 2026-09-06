<?php
/* ============================================================
   Dry65 — EN sitemap (/sitemap-en.xml)
   ------------------------------------------------------------
   Yoast ne zna za /en/ (virtuelne) strane, pa ih ovde izlistamo
   i dodamo u Yoast sitemap indeks. Prati mapu slug-ova (engleski
   URL-ovi). Iskljucuje: karijera (SR-only), podesavanja, i
   nespremne (noindex) usluge.
   ============================================================ */

if (!defined('ABSPATH')) exit;

/* Lista EN URL-ova sa lastmod-om. */
function dry65_en_sitemap_urls() {
    $out = [];
    $base = untrailingslashit(get_option('home'));

    // Pocetna (EN)
    $out[] = ['loc' => $base . '/en/', 'mod' => gmdate('c')];

    $skip_pages = ['karijera', 'dry65-podesavanja'];
    $pages = get_posts([
        'post_type' => 'page', 'posts_per_page' => -1, 'post_status' => 'publish',
        'orderby' => 'menu_order', 'order' => 'ASC',
    ]);
    foreach ($pages as $p) {
        if (in_array($p->post_name, $skip_pages, true)) continue;
        if (get_post_meta($p->ID, '_yoast_wpseo_meta-robots-noindex', true) === '1') continue;
        $path = parse_url(get_permalink($p), PHP_URL_PATH);
        if (!$path || $path === '/') continue; // pocetna vec dodata
        $out[] = ['loc' => dry65_lang_url('en', $path), 'mod' => get_post_modified_time('c', true, $p)];
    }

    // Usluge (CPT) — bez nespremnih (noindex) kategorija i njihove dece
    $unready = function_exists('dry65_unready_service_slugs') ? dry65_unready_service_slugs() : [];
    $svcs = get_posts([
        'post_type' => 'dry65_service', 'posts_per_page' => -1, 'post_status' => 'publish',
        'orderby' => 'menu_order', 'order' => 'ASC',
    ]);
    foreach ($svcs as $s) {
        if (in_array($s->post_name, $unready, true)) continue;
        $parent_slug = $s->post_parent ? get_post_field('post_name', $s->post_parent) : '';
        if ($parent_slug && in_array($parent_slug, $unready, true)) continue;
        $path = parse_url(get_permalink($s), PHP_URL_PATH);
        if (!$path) continue;
        $out[] = ['loc' => dry65_lang_url('en', $path), 'mod' => get_post_modified_time('c', true, $s)];
    }

    return $out;
}

/* Rewrite: /sitemap-en.xml (Yoast NE presreće ovaj oblik). */
add_action('init', function () {
    add_rewrite_rule('^sitemap-en\.xml$', 'index.php?dry65_en_sitemap=1', 'top');
    if (get_option('dry65_en_sitemap_rw_v') !== '1') {
        flush_rewrite_rules(false);
        update_option('dry65_en_sitemap_rw_v', '1');
    }
});
add_filter('query_vars', function ($v) { $v[] = 'dry65_en_sitemap'; return $v; });

/* Bez canonical redirecta na /sitemap-en.xml/ (WP inace dodaje kosu crtu). */
add_filter('redirect_canonical', function ($redirect) {
    return get_query_var('dry65_en_sitemap') ? false : $redirect;
});

add_action('template_redirect', function () {
    if (!get_query_var('dry65_en_sitemap')) return;
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex, follow', true);
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (dry65_en_sitemap_urls() as $u) {
        echo '  <url><loc>' . esc_url($u['loc']) . '</loc>'
           . '<lastmod>' . esc_html($u['mod']) . '</lastmod></url>' . "\n";
    }
    echo '</urlset>';
    exit;
});

/* Dodaj EN sitemap u Yoast sitemap indeks. */
add_filter('wpseo_sitemap_index', function ($xml) {
    $loc = home_url('/sitemap-en.xml');
    $xml .= '<sitemap><loc>' . esc_url($loc) . '</loc><lastmod>' . gmdate('c') . '</lastmod></sitemap>' . "\n";
    return $xml;
});
