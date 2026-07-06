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
    // TITLE DIVERSIFICATION strategija: svaka stranica cilja
    // razlicit primary keyword da izbegnemo cannibalization.
    // Svaki title ima najbitniji keyword na POCETKU (Google podeblja).
    return [
        'home' => [
            // Fokus: "feniranje bez zakazivanja" + "Novi Beograd"
            'title' => 'Feniranje bez zakazivanja - Novi Beograd | Dry65 West65',
            'desc'  => 'Frizerski salon specijalizovan za feniranje, na Novom Beogradu u West65. Walk-in blowout hair bar - samo dođeš, bez termina. Cene od 1.400 din.',
        ],
        'o-nama' => [
            // Fokus: "frizerski salon Novi Beograd"
            'title' => 'Frizerski salon Novi Beograd - O Dry65 blowout hair bar-u',
            'desc'  => 'Dry65 je frizerski salon specijalizovan za feniranje, u West65 mall-u na Novom Beogradu. Walk-in koncept, bez zakazivanja - samo dođeš.',
        ],
        'usluge' => [
            // Fokus: "frizerski salon West65" + tehnike stilizovanja
            'title' => 'Frizerski salon West65 - Feniranje, lokne, talasi | Dry65',
            'desc'  => 'Profesionalno feniranje, stilizovanje (lokne, talasi, glatko, volumen) i Hair Mask tretmani. Frizerski salon Dry65 u West65 mall-u, Novi Beograd.',
        ],
        'cenovnik' => [
            // Fokus: "cenovnik feniranja Novi Beograd" + cena od 1.400
            'title' => 'Cenovnik feniranja Novi Beograd, od 1.400 din | Dry65 West65',
            'desc'  => 'Cene feniranja u Dry65: kratka 1.400 din, srednja 1.800 din, duga 2.000 din, extra duga 2.200 din. Frizerski salon West65, Novi Beograd, bez zakazivanja.',
        ],
        'paketi' => [
            // Fokus: "mesečni paket feniranja" + benefit
            'title' => 'Mesečni paket feniranja - 8 termina | Dry65 Novi Beograd',
            'desc'  => 'Mesečni paket od 8 feniranja - idealno za žene koje feniraju 2-3 puta nedeljno. Frizerski salon u West65 mall-u na Novom Beogradu.',
        ],
        'ambijent' => [
            // Fokus: "blowout hair bar Novi Beograd" + galerija
            'title' => 'Blowout hair bar Novi Beograd - Ambijent salona | Dry65',
            'desc'  => 'Pogledaj ambijent Dry65 walk-in blowout hair bar-a u West65 mall-u na Novom Beogradu. Moderno opremljen frizerski salon za profesionalno feniranje.',
        ],
        'kontakt' => [
            // Fokus: "frizerski salon West65" + adresa/kontakt
            'title' => 'Kontakt - Frizerski salon West65, Novi Beograd | Dry65',
            'desc'  => 'Dry65 frizerski salon: Omladinskih Brigada 86Ž, West65 mall, Novi Beograd. Telefon +381 60 6900655. Pon-Pet 8-20h, Sub 10-18h. Walk-in, bez zakazivanja.',
        ],
        'karijera' => [
            // Fokus: "posao frizer / salon Novi Beograd"
            'title' => 'Karijera - Posao u frizerskom salonu Novi Beograd | Dry65',
            'desc'  => 'Otvorene pozicije u Dry65 frizerskom salonu na Novom Beogradu: asistent u radu, blowout specijalista, recepcionar. Prijavi se ili pošalji CV.',
        ],
        'blog' => [
            // Fokus: "saveti za kosu" + edukativno
            'title' => 'Saveti za feniranje i negu kose | Blog Dry65 Novi Beograd',
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
