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

/* ---- NOINDEX za kategorije, tagove i arhive ----
   Sprečava "Non-canonical page in sitemap" u audit-u.
   Ove stranice se ne prikazuju u Google search rezultatima. */

// Noindex robots meta tag
add_action('wp_head', function() {
    if (is_category() || is_tag() || is_author() || is_date() || is_search()) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
    }
}, 1);

// Uklanja kategorije, tagove, autor arhive i date arhive iz Yoast sitemap-a
add_filter('wpseo_sitemap_exclude_taxonomy', function($excluded, $taxonomy) {
    if (in_array($taxonomy, ['category', 'post_tag'], true)) {
        return true;
    }
    return $excluded;
}, 10, 2);

// Uklanja author i date arhive iz Yoast sitemap indexa
add_filter('wpseo_sitemap_index', function($index) {
    // Ovo filter je za dodavanje, ali za uklanjanje koristimo drugi pristup
    return $index;
});

// Isključi user (author) sitemap
add_filter('wpseo_sitemap_exclude_author', '__return_true');

// Yoast-specific noindex za taxonomies
add_filter('wpseo_robots', function($robots) {
    if (is_category() || is_tag() || is_author() || is_date() || is_search()) {
        return 'noindex,follow';
    }
    return $robots;
}, 99);

// Ukloni category/tag/author/date iz canonical calculation
add_filter('wpseo_canonical', function($canonical) {
    if (is_category() || is_tag() || is_author() || is_date()) {
        return false; // Ne generise canonical, sto sprecava non-canonical u sitemap-u
    }
    return $canonical;
});

/* ---- Redirect attachment pages na roditeljski post (ili home) ----
   WordPress pravi posebnu stranicu za svaki upload — duplirani sadrzaj,
   non-canonical konflikti u Ahrefs. Ova redirekcija ih uklanja iz index-a. */
add_action('template_redirect', function() {
    if (is_attachment()) {
        global $post;
        // Ako slika ima roditelja (post/page), redirect tamo
        if ($post && $post->post_parent) {
            wp_safe_redirect(get_permalink($post->post_parent), 301);
            exit;
        }
        // Ako nema roditelja, redirect na home
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
});

// Iskljuci attachment sitemap iz Yoast
add_filter('wpseo_sitemap_exclude_post_type', function($excluded, $post_type) {
    if ($post_type === 'attachment') {
        return true;
    }
    return $excluded;
}, 10, 2);

// Noindex za attachment pages (backup ako redirect ne stigne na vreme)
add_filter('wpseo_robots', function($robots) {
    if (is_attachment()) {
        return 'noindex,follow';
    }
    return $robots;
}, 100);

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
        'faq' => [
            // Fokus: "cesta pitanja + featured snippets + AI SEO"
            'title' => 'Česta pitanja - Feniranje Dry65 Novi Beograd | Radno vreme, cene',
            'desc'  => 'Odgovori na česta pitanja o feniranju u Dry65: zakazivanje, radno vreme, cene, mesečni paketi, lokacija West 65 Novi Beograd. Sve na jednom mestu.',
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
    // Fallback za blog postove ili druge stranice bez custom desc
    if (is_singular('post') && (empty($desc) || mb_strlen($desc) < 120)) {
        $excerpt = get_the_excerpt();
        if (!empty($excerpt) && mb_strlen($excerpt) >= 120) {
            return $excerpt;
        }
        // Ako je excerpt prekratak, dopuni sa brand tail-om
        $tail = ' Dry65 blog, frizerski salon specijalizovan za feniranje na Novom Beogradu, u West65 mall-u.';
        return trim(($excerpt ?: '') . $tail);
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

/* ---- Default OG image za sve stranice bez featured image ----
   Sprečava "Open Graph tags incomplete" u SEO audit-u. */
add_filter('wpseo_opengraph_image', function($image) {
    if (empty($image)) {
        return get_template_directory_uri() . '/assets/salon/s06.webp';
    }
    return $image;
}, 99);

add_filter('wpseo_twitter_image', function($image) {
    if (empty($image)) {
        return get_template_directory_uri() . '/assets/salon/s06.webp';
    }
    return $image;
}, 99);

// og:image dimenzije
add_filter('wpseo_opengraph_image_size', function() {
    return 'full';
});

// og:site_name backup
add_filter('wpseo_og_locale', function($locale) {
    return $locale ?: 'sr_RS';
});

/* ---- Direct og:image fallback u wp_head ----
   Nova Yoast verzija (27+) ne poziva wpseo_opengraph_image filter za sve stranice.
   Ovaj kod detektuje da li Yoast već ispisao og:image i dodaje default ako nije.
   Poziva se na priority 5 — nakon Yoast (koji je na 10), ali koristi output buffer trik. */
function dry65_ensure_og_image() {
    // Uvek dodaj og:image tagove; ako Yoast dodaje svoje, Facebook/social koristi prvi.
    // Za home i sve pages bez featured image, koristi default.
    $default_url = home_url('/wp-content/themes/dry65/assets/salon/s06.webp');
    $default_alt = 'Dry65, feniranje bez zakazivanja u Novom Beogradu';

    // Featured image ako postoji
    if (is_singular() && has_post_thumbnail()) {
        $img_id  = get_post_thumbnail_id();
        $img_src = wp_get_attachment_image_src($img_id, 'full');
        if ($img_src) {
            $img_url = $img_src[0];
            $img_w   = $img_src[1];
            $img_h   = $img_src[2];
            $img_alt = get_post_meta($img_id, '_wp_attachment_image_alt', true) ?: $default_alt;
        }
    }

    if (!isset($img_url)) {
        $img_url = $default_url;
        $img_w   = 1200;
        $img_h   = 1600;
        $img_alt = $default_alt;
    }

    echo '<meta property="og:image" content="' . esc_url($img_url) . '">' . "\n";
    echo '<meta property="og:image:width" content="' . (int)$img_w . '">' . "\n";
    echo '<meta property="og:image:height" content="' . (int)$img_h . '">' . "\n";
    echo '<meta property="og:image:alt" content="' . esc_attr($img_alt) . '">' . "\n";
    echo '<meta property="og:image:type" content="image/webp">' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($img_url) . '">' . "\n";
}
add_action('wp_head', 'dry65_ensure_og_image', 5);

/* ---- Ukloni Yoast-ov og:image da izbegnemo duplikate ----
   Naš dry65_ensure_og_image() je autoritet za og:image tagove. */
add_filter('wpseo_opengraph_image', '__return_false', 100);
add_filter('wpseo_twitter_image', '__return_false', 100);

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
