<?php
/* ============================================================
   Dry65 — SEO meta override
   Postavlja Yoast SEO title i meta description za svaku
   glavnu stranicu kroz filter (umesto manuelno u WP Admin).
   ============================================================ */

/* ---- Site tagline (override sa SEO friendly) ---- */
add_action('after_setup_theme', function() {
    $current = get_option('blogdescription');
    $target  = 'Feniranje na Novom Beogradu - Bez zakazivanja';
    if ($current !== $target) {
        update_option('blogdescription', $target);
    }
});

/* ---- SEO meta po slug-u stranice ----
   Format: 'page-slug' => ['title' => '...', 'desc' => '...']
   Home koristi key 'home'. */
function dry65_seo_map() {
    return [
        'home' => [
            'title' => 'Dry65 - Feniranje na Novom Beogradu | Walk-in Blowout Salon',
            'desc'  => 'Profesionalno feniranje na Novom Beogradu bez zakazivanja. Walk-in blowout salon u West65 mall-u. Cene od 1.400 din. Otvoreni Pon-Pet 8-20h, Sub 10-18h.',
        ],
        'o-nama' => [
            'title' => 'O nama - Walk-in Blowout Hair Bar na Novom Beogradu | Dry65',
            'desc'  => 'Dry65 je walk-in blowout hair bar u West65 mall-u, Novi Beograd. Profesionalno feniranje bez zakazivanja - samo dođeš. Saznaj više o nama.',
        ],
        'usluge' => [
            'title' => 'Usluge - Feniranje, Lokne, Talasi, Glatko | Dry65 Novi Beograd',
            'desc'  => 'Profesionalno feniranje, stilizovanje (lokne, talasi, glatko, volumen) i Hair Mask tretmani u walk-in salonu na Novom Beogradu. Bez zakazivanja.',
        ],
        'cenovnik' => [
            'title' => 'Cenovnik Feniranja - od 1.400 din | Dry65 Novi Beograd',
            'desc'  => 'Cene feniranja u Dry65: kratka kosa 1.400 din, srednja 1.800 din, duga 2.000 din, extra duga 2.200 din. Walk-in salon, West65 mall, Novi Beograd.',
        ],
        'paketi' => [
            'title' => 'Mesečni Paket Feniranja - 8 Termina | Dry65 Novi Beograd',
            'desc'  => 'Mesečni paket od 8 feniranja - idealno za žene koje feniraju 2-3 puta nedeljno. Walk-in blowout salon u West65 mall-u na Novom Beogradu.',
        ],
        'ambijent' => [
            'title' => 'Ambijent Salona - Galerija | Dry65 Feniranje Novi Beograd',
            'desc'  => 'Pogledaj ambijent Dry65 walk-in blowout salona u West65 mall-u na Novom Beogradu. Moderno opremljen prostor za profesionalno feniranje.',
        ],
        'kontakt' => [
            'title' => 'Kontakt - Adresa, Telefon, Mapa | Dry65 Novi Beograd',
            'desc'  => 'Dry65 walk-in salon: Omladinskih Brigada 86Ž, West65 mall, Novi Beograd. Telefon +381 60 6900655. Pon-Pet 8-20h, Sub 10-18h.',
        ],
        'blog' => [
            'title' => 'Blog - Saveti za Kosu i Feniranje | Dry65 Novi Beograd',
            'desc'  => 'Saveti o feniranju, nezi kose i stilizovanju iz Dry65 walk-in salona u West65 mall-u, Novi Beograd. Mali vodiči za zdraviju kosu.',
        ],
    ];
}

/* ---- Helper: vrati current page slug (ili 'home') ---- */
function dry65_seo_current_slug() {
    if (is_front_page() || is_home()) return 'home';
    if (is_page()) {
        global $post;
        return $post ? $post->post_name : null;
    }
    if (is_post_type_archive('post') || is_home()) return 'blog';
    return null;
}

/* ---- Override Yoast SEO title ---- */
add_filter('wpseo_title', function($title) {
    $slug = dry65_seo_current_slug();
    $map  = dry65_seo_map();
    if ($slug && isset($map[$slug])) {
        return $map[$slug]['title'];
    }
    return $title;
}, 99);

/* ---- Override Yoast SEO meta description ---- */
add_filter('wpseo_metadesc', function($desc) {
    $slug = dry65_seo_current_slug();
    $map  = dry65_seo_map();
    if ($slug && isset($map[$slug])) {
        return $map[$slug]['desc'];
    }
    return $desc;
}, 99);

/* ---- Override Open Graph title (za social sharing) ---- */
add_filter('wpseo_opengraph_title', function($title) {
    $slug = dry65_seo_current_slug();
    $map  = dry65_seo_map();
    if ($slug && isset($map[$slug])) {
        return $map[$slug]['title'];
    }
    return $title;
}, 99);

/* ---- Override Open Graph description ---- */
add_filter('wpseo_opengraph_desc', function($desc) {
    $slug = dry65_seo_current_slug();
    $map  = dry65_seo_map();
    if ($slug && isset($map[$slug])) {
        return $map[$slug]['desc'];
    }
    return $desc;
}, 99);

/* ---- Override Twitter Card title ---- */
add_filter('wpseo_twitter_title', function($title) {
    $slug = dry65_seo_current_slug();
    $map  = dry65_seo_map();
    if ($slug && isset($map[$slug])) {
        return $map[$slug]['title'];
    }
    return $title;
}, 99);

/* ---- Override Twitter Card description ---- */
add_filter('wpseo_twitter_description', function($desc) {
    $slug = dry65_seo_current_slug();
    $map  = dry65_seo_map();
    if ($slug && isset($map[$slug])) {
        return $map[$slug]['desc'];
    }
    return $desc;
}, 99);

/* ---- Fallback ako Yoast nije aktivan: WP native title + meta ---- */

// Title tag (samo ako Yoast nije aktivan)
add_filter('pre_get_document_title', function($title) {
    if (defined('WPSEO_VERSION')) return $title; // Yoast je aktivan, ignorišemo
    $slug = dry65_seo_current_slug();
    $map  = dry65_seo_map();
    if ($slug && isset($map[$slug])) {
        return $map[$slug]['title'];
    }
    return $title;
}, 99);

// Meta description (samo ako Yoast nije aktivan)
add_action('wp_head', function() {
    if (defined('WPSEO_VERSION')) return; // Yoast je aktivan, ignorišemo
    $slug = dry65_seo_current_slug();
    $map  = dry65_seo_map();
    if ($slug && isset($map[$slug])) {
        echo '<meta name="description" content="' . esc_attr($map[$slug]['desc']) . '">' . "\n";
    }
}, 1);
