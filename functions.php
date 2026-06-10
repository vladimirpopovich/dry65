<?php
/* ============================================================
   Dry65 — functions.php
   ============================================================ */

require_once get_template_directory() . '/inc/data.php';
require_once get_template_directory() . '/inc/cpt.php';
require_once get_template_directory() . '/inc/acf-fields.php';
require_once get_template_directory() . '/inc/google-reviews.php';

/* ---- WebP kao podrazumevani output za thumbnaile ----
   Bez ovog WP generise .jpg thumbnaile cak i ako je original .webp.
   Sa ovim svi -300x214, -768x547, -1024x731 itd. su .webp. */
add_filter('image_editor_output_format', function($formats) {
    $formats['image/jpeg'] = 'image/webp';
    $formats['image/jpg']  = 'image/webp';
    return $formats;
});

/* ---- Theme setup ---- */
function dry65_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo');
    register_nav_menus(['primary' => __('Primary Navigation', 'dry65')]);
    load_theme_textdomain('dry65', get_template_directory() . '/languages');
}
add_action('after_setup_theme', 'dry65_setup');

/* ---- Enqueue ---- */
function dry65_scripts() {
    wp_enqueue_style('dry65-style', get_stylesheet_uri(), [], '1.0.2');
    wp_enqueue_script('dry65-js', get_template_directory_uri() . '/assets/js/dry65.js', [], '1.2.0', true);
    wp_localize_script('dry65-js', 'dry65', [
        'themeUrl' => get_template_directory_uri(),
        'lengths'  => dry65_lengths(),
    ]);
}
add_action('wp_enqueue_scripts', 'dry65_scripts');

/* ---- Google Fonts: async + reduced weights ----
   - Smanjeni weights (samo oni koji se koriste) — manje CDN trafika
   - Async load preko preload trick-a — ne blokira render */
function dry65_head_fonts() {
    // Samo weights koji se realno koriste
    $url = 'https://fonts.googleapis.com/css2?'
         . 'family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300'
         . '&family=Oooh+Baby'
         . '&family=Baloo+2:wght@700'
         . '&family=Hanken+Grotesk:wght@400;500;600'
         . '&family=Newsreader:opsz,wght@6..72,300;6..72,400;6..72,500'
         . '&display=swap';

    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    // Async load (preload kao style, posle render-a postaje stylesheet)
    echo '<link rel="preload" as="style" href="' . esc_url($url) . '" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
    echo '<noscript><link rel="stylesheet" href="' . esc_url($url) . '"></noscript>' . "\n";
}
add_action('wp_head', 'dry65_head_fonts', 1);

/* ---- Hero preload uklonjen kao eksperiment za NO_LCP ----
   Preload je trebao da pomogne, ali Lighthouse i dalje ne detektuje LCP.
   Mozda preload izaziva da slika bude "prerano u cache-u" i Lighthouse
   je ne registruje kao LCP. Test sa uklonjenim preload-om. */

/* ---- Favicon ---- */
function dry65_favicon() {
    $tpl = get_template_directory_uri();
    echo '<link rel="icon" type="image/x-icon" href="' . esc_url(home_url('/favicon.ico')) . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url($tpl . '/assets/favicon/favicon-32.png') . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="16x16" href="' . esc_url($tpl . '/assets/favicon/favicon-16.png') . '">' . "\n";
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url($tpl . '/assets/favicon/favicon-180.png') . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url($tpl . '/assets/favicon/favicon-192.png') . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="512x512" href="' . esc_url($tpl . '/assets/favicon/favicon-512.png') . '">' . "\n";
}
add_action('wp_head', 'dry65_favicon', 2);

/* ---- LocalBusiness / HairSalon Schema (JSON-LD) ---- */
function dry65_schema() {
    $biz = dry65_biz();
    $hours_spec = [];
    $day_map = [
        'Ponedeljak' => 'Monday', 'Utorak' => 'Tuesday', 'Sreda' => 'Wednesday',
        'Četvrtak' => 'Thursday', 'Petak' => 'Friday', 'Subota' => 'Saturday', 'Nedelja' => 'Sunday',
    ];
    $hours_spec[] = [
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        'opens' => '08:00', 'closes' => '20:00',
    ];
    $hours_spec[] = [
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Saturday'],
        'opens' => '10:00', 'closes' => '18:00',
    ];

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => ['HairSalon', 'LocalBusiness'],
        '@id' => home_url('/#business'),
        'name' => $biz['name'],
        'description' => 'Walk-in blowout hair bar na Novom Beogradu. Feniranje bez zakazivanja, samo dođeš.',
        'url' => home_url('/'),
        'telephone' => $biz['phone'],
        'email' => $biz['email'],
        'image' => get_template_directory_uri() . '/assets/salon/s06.webp',
        'priceRange' => '1400 – 6000 RSD',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Omladinskih Brigada 86Ž',
            'addressLocality' => 'Novi Beograd',
            'addressRegion' => 'Beograd',
            'postalCode' => '11070',
            'addressCountry' => 'RS',
        ],
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => 44.8125,
            'longitude' => 20.4144,
        ],
        'openingHoursSpecification' => $hours_spec,
        'sameAs' => [$biz['instagram_url']],
        'hasMap' => $biz['maps_url'],
        'paymentAccepted' => 'Cash, Credit Card',
        'currenciesAccepted' => 'RSD',
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'dry65_schema', 5);

/* ---- Auto-create pages on theme activation ---- */
function dry65_activate() {
    $pages = [
        ['title' => 'O nama',   'slug' => 'o-nama',   'template' => 'page-o-nama.php',   'order' => 1],
        ['title' => 'Usluge',   'slug' => 'usluge',   'template' => 'page-usluge.php',   'order' => 2],
        ['title' => 'Cenovnik', 'slug' => 'cenovnik', 'template' => 'page-cenovnik.php', 'order' => 3],
        ['title' => 'Paketi',   'slug' => 'paketi',   'template' => 'page-paketi.php',   'order' => 4],
        ['title' => 'Ambijent', 'slug' => 'ambijent', 'template' => 'page-ambijent.php', 'order' => 5],
        ['title' => 'Blog',     'slug' => 'blog',     'template' => '',                  'order' => 6],
        ['title' => 'Kontakt',  'slug' => 'kontakt',  'template' => 'page-kontakt.php',  'order' => 7],
    ];

    foreach ($pages as $p) {
        $exists = get_page_by_path($p['slug']);
        if (!$exists) {
            $id = wp_insert_post([
                'post_title'     => $p['title'],
                'post_name'      => $p['slug'],
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'menu_order'     => $p['order'],
                'post_content'   => '',
            ]);
            if ($id && !is_wp_error($id) && $p['template']) {
                update_post_meta($id, '_wp_page_template', $p['template']);
            }
        }
    }

    // Set homepage
    $front = get_page_by_path('home');
    if (!$front) {
        $front_id = wp_insert_post([
            'post_title'   => 'Početna',
            'post_name'    => 'home',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'menu_order'   => 0,
            'post_content' => '',
        ]);
        if ($front_id && !is_wp_error($front_id)) {
            update_option('page_on_front', $front_id);
            update_option('show_on_front', 'page');
        }
    }

    // Set blog page
    $blog_page = get_page_by_path('blog');
    if ($blog_page) {
        update_option('page_for_posts', $blog_page->ID);
    }

    flush_rewrite_rules();
}
add_action('after_switch_theme', 'dry65_activate');

/* ---- Nav helper ---- */
function dry65_current_page_slug() {
    if (is_front_page()) return 'home';
    global $post;
    return $post ? $post->post_name : '';
}

function dry65_nav_links($mobile = false) {
    $nav = dry65_nav();
    $cur = dry65_current_page_slug();
    foreach ($nav as $item) {
        if ($item['slug'] === 'blog') {
            $archive = get_post_type_archive_link('post');
            $url = $archive ? $archive : home_url('/blog/');
        } else {
            $page = get_page_by_path($item['slug']);
            $url  = $page ? get_permalink($page) : home_url('/' . $item['slug'] . '/');
        }
        $active = ($cur === $item['slug']) ? ' active' : '';
        echo '<a href="' . esc_url($url) . '" class="nav-link' . $active . '">' . esc_html($item['label']) . '</a>';
    }
}

/* ---- Excerpt ---- */
function dry65_excerpt_length() { return 20; }
add_filter('excerpt_length', 'dry65_excerpt_length');
function dry65_excerpt_more() { return '…'; }
add_filter('excerpt_more', 'dry65_excerpt_more');

/* ---- Body class helpers ---- */
function dry65_body_classes($classes) {
    $classes[] = 'dry65-theme';
    return $classes;
}
add_filter('body_class', 'dry65_body_classes');
