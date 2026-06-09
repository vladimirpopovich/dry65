<?php
/* ============================================================
   Dry65 — site content data
   Povlači iz ACF/CPT sa fallback na hardkodovane defaultove.
   ============================================================ */

/* ---- safe wrapper za get_field, ako ACF nije aktivan ---- */
function dry65_get_field($key, $post_id = false) {
    if (!function_exists('get_field')) return '';
    return get_field($key, $post_id);
}

/* ---- helper za uzimanje ACF vrednosti sa Settings page-a ---- */
function dry65_setting($name, $default = '') {
    static $page_id = null;
    if ($page_id === null) {
        $page = get_page_by_path('dry65-podesavanja');
        $page_id = $page ? $page->ID : 0;
    }
    if (!$page_id || !function_exists('get_field')) return $default;
    $val = get_field($name, $page_id);
    return ($val === '' || $val === null || $val === false) ? $default : $val;
}

/* ---- BIZ ---- */
function dry65_biz() {
    return [
        'name'          => dry65_setting('biz_name', 'Dry65'),
        'tagline'       => dry65_setting('biz_tagline', 'Blowout Hair Bar'),
        'address'       => dry65_setting('biz_address', 'Omladinskih Brigada 86Ž, West65, Novi Beograd'),
        'phone_display' => dry65_setting('biz_phone_display', '+381 60 6900655'),
        'phone'         => dry65_setting('biz_phone', '+381606900655'),
        'email'         => dry65_setting('biz_email', 'office@dry65.com'),
        'instagram'     => dry65_setting('biz_instagram', '@dry65belgrade'),
        'instagram_url' => dry65_setting('biz_instagram_url', 'https://instagram.com/dry65belgrade'),
        'maps_url'      => dry65_setting('biz_maps_url', 'https://www.google.com/maps/place/dry65/data=!4m2!3m1!1s0x0:0x7a2ec93c7757eebd?sa=X&ved=1t:2428&ictx=111'),
        'reviews_url'   => dry65_setting('biz_reviews_url', 'https://www.google.com/search?sca_esv=c378b476f9719619&si=AL3DRZEsmMGCryMMFSHJ3StBhOdZ2-6yYkXd_doETEE1OR-qOR6dpUKKNGrrr-_pFK37PHACJKHIDVlI3ia9lcLXcHI7gT4tEHi6TWaJcPQF5O-Iayxrj0tC1xRq-Jq-cxMN9mVHswUq&q=dry65+Reviews&sa=X&ved=2ahUKEwjErtGWw-6UAxUFNxAIHdcaM5IQ0bkNegQIJBAI'),
        'hours' => [
            ['day' => dry65_setting('h1_day', 'Ponedeljak – Petak'), 'time' => dry65_setting('h1_time', '08:00 – 20:00')],
            ['day' => dry65_setting('h2_day', 'Subota'),             'time' => dry65_setting('h2_time', '10:00 – 18:00')],
            ['day' => dry65_setting('h3_day', 'Nedelja'),            'time' => dry65_setting('h3_time', 'Ne radimo')],
        ],
    ];
}

/* ---- LENGTHS ---- */
function dry65_lengths() {
    $defaults = [
        ['id' => 'kratka',  'label' => 'Kratka',     'price' => 1400, 'img' => 'https://dry65.com/wp-content/uploads/2026/05/kratka-kosa.webp'],
        ['id' => 'srednja', 'label' => 'Srednja',    'price' => 1800, 'img' => 'https://dry65.com/wp-content/uploads/2026/05/srednja-kosa.webp'],
        ['id' => 'duga',    'label' => 'Duga',       'price' => 2000, 'img' => 'https://dry65.com/wp-content/uploads/2026/05/duga-kosa.webp'],
        ['id' => 'extra',   'label' => 'Extra duga', 'price' => 2200, 'img' => 'https://dry65.com/wp-content/uploads/2026/05/veoma-duga-kosa.webp'],
    ];
    $ids = ['kratka', 'srednja', 'duga', 'extra'];
    $out = [];
    for ($i = 1; $i <= 4; $i++) {
        $out[] = [
            'id'    => $ids[$i-1],
            'label' => dry65_setting("l{$i}_label", $defaults[$i-1]['label']),
            'price' => (int) dry65_setting("l{$i}_price", $defaults[$i-1]['price']),
            'img'   => dry65_setting("l{$i}_img",   $defaults[$i-1]['img']),
        ];
    }
    return $out;
}

/* ---- PRICING (zadržano hardcoded — kompleksna struktura) ---- */
function dry65_pricing() {
    return [
        'styling' => [
            'title'  => 'Feniranje i stilizovanje',
            'kicker' => 'po dužini kose',
            'rows'   => [
                ['name' => 'Pranje i feniranje', 'prices' => [1400, 1800, 2000, 2200]],
                ['name' => 'Hair curler, lokne',  'prices' => [2800, 3600, 4000, 4400]],
                ['name' => 'Hair press, presovanje', 'prices' => [2800, 3600, 4000, 4400]],
            ],
        ],
        'wash' => [
            'title' => 'Samo pranje',
            'rows'  => [
                ['name' => 'Normalna kosa', 'price' => 800],
                ['name' => 'Gusta kosa',    'price' => 1200],
            ],
            'note'  => 'Gusta i duga kosa zahteva duže vreme sušenja.',
        ],
        'density' => [
            'title'  => 'Afro i nadogradnje',
            'kicker' => 'po gustini kose',
            'cols'   => ['Tanka', 'Standardna', 'Gusta'],
            'rows'   => [
                ['name' => 'Afro curls',                  'prices' => [4000, 5000, 6000]],
                ['name' => 'Feniranje na nadogradnje',    'prices' => [2800, 3600, 4000]],
            ],
            'notes'  => [
                'Afro curls: proces traje 1–2h i zahteva feniranje prethodnog dana. Cenu određuje gustina kose. Duga i extra duga kosa ne potpada pod ovu uslugu.',
                'Kosa sa ekstenzijama uglavnom zahteva duplo više vremena.',
            ],
        ],
        'care' => [
            'title'  => 'Nega i održavanje',
            'kicker' => 'tretmani',
            'groups' => [
                ['group' => 'Hair Infusion', 'items' => [['name' => 'Infuzija', 'price' => 1500]]],
                ['group' => 'Hair Mask', 'items' => [
                    ['name' => 'Basic',   'price' => 1800],
                    ['name' => 'Medium',  'price' => 2200],
                    ['name' => 'Premium', 'price' => 2600],
                ]],
                ['group' => 'Hair Mask, parna stanica', 'items' => [
                    ['name' => 'Basic',            'price' => 2800],
                    ['name' => 'Medium',           'price' => 3200],
                    ['name' => 'Premium',          'price' => 4600],
                    ['name' => 'Booster premium',  'price' => 6000],
                ]],
            ],
            'note' => 'Parna stanica i premium proizvodi produžavaju ceo proces za 20–25 minuta.',
        ],
    ];
}

/* ---- SERVICES (CPT dry65_service) ---- */
function dry65_services() {
    $posts = get_posts([
        'post_type'      => 'dry65_service',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    if (empty($posts)) {
        // Fallback
        return [
            ['id' => 'feniranje', 'kicker' => '01', 'title' => 'Pranje i feniranje', 'short' => 'Naš potpis. Pranje i savršeno feniranje.', 'body' => 'Sve počinje i završava se feniranjem.', 'img' => 'assets/services/feniranje.webp', 'points' => ['Pranje kose', 'Stilizovanje prema dužini', 'Bez zakazivanja']],
            ['id' => 'stilizovanje', 'kicker' => '02', 'title' => 'Stilizovanje kose', 'short' => 'Talasi, glatko, volumen, look za svaku priliku.', 'body' => '', 'img' => 'assets/services/stilizovanje.webp', 'points' => ['Talasi i lokne', 'Glatko / sleek finiš', 'Look za događaj']],
            ['id' => 'nega', 'kicker' => '03', 'title' => 'Nega i održavanje', 'short' => 'Tretmani koji čine da feniranje traje.', 'body' => '', 'img' => 'assets/services/nega.webp', 'points' => ['Dubinska hidratacija', 'Obnova i zaštita', 'Poklon uz pakete']],
        ];
    }

    // Fallback slike po slug-u
    $fallback_images = [
        'feniranje'    => 'assets/services/feniranje.webp',
        'stilizovanje' => 'assets/services/stilizovanje.webp',
        'nega'         => 'assets/services/nega.webp',
    ];

    $out = [];
    foreach ($posts as $p) {
        $points = array_filter([
            dry65_get_field('point_1', $p->ID),
            dry65_get_field('point_2', $p->ID),
            dry65_get_field('point_3', $p->ID),
        ]);
        $img = dry65_get_field('image', $p->ID);
        if (!$img) {
            $img = $fallback_images[$p->post_name] ?? 'assets/services/feniranje.webp';
        }
        $out[] = [
            'id'     => $p->post_name,
            'kicker' => dry65_get_field('kicker', $p->ID) ?: '',
            'title'  => $p->post_title,
            'short'  => dry65_get_field('short', $p->ID) ?: '',
            'body'   => dry65_get_field('body', $p->ID) ?: '',
            'img'    => $img,
            'points' => array_values($points),
        ];
    }
    return $out;
}

/* ---- PACKAGES (CPT dry65_package) ---- */
function dry65_packages() {
    $posts = get_posts([
        'post_type'      => 'dry65_package',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    if (empty($posts)) {
        return [
            ['id' => 'essential', 'name' => 'Essential', 'img' => 'assets/packages/essential.webp', 'count' => 4, 'cadence' => '4 tretmana mesečno', 'blurb' => 'Savršen izbor za održavanje besprekorne frizure tokom nedelje.', 'gift' => 'Deep Hair Infusion na poklon', 'gift_val' => 2000, 'featured' => false, 'features' => ['4 tretmana feniranja mesečno', 'Bez zakazivanja, prioritet u redu', 'Deep Hair Infusion na poklon']],
            ['id' => 'signature', 'name' => 'Signature', 'img' => 'assets/packages/signature.webp', 'count' => 8, 'cadence' => '8 tretmana mesečno', 'blurb' => 'Kreirano za žene koje vole besprekoran styling dva puta nedeljno.', 'gift' => 'Signature Hair Mask na poklon', 'gift_val' => 4000, 'featured' => true, 'features' => ['8 tretmana feniranja mesečno', 'Prioritet u redu', 'Signature Hair Mask na poklon', 'Popust na dodatne usluge']],
            ['id' => 'premium', 'name' => 'Premium', 'img' => 'assets/packages/premium.webp', 'count' => 12, 'cadence' => '12 tretmana mesečno', 'blurb' => 'Ultimativni ritual za savršeno stilizovanu kosu tokom cele nedelje.', 'gift' => 'Premium Boost Steam Ritual na poklon', 'gift_val' => 6000, 'featured' => false, 'features' => ['12 tretmana feniranja mesečno', 'Najviši prioritet, dry65 Club', 'Premium Boost Steam Ritual na poklon', 'Popust na dodatne usluge']],
        ];
    }

    $fallback_pkg = [
        'essential' => 'assets/packages/essential.webp',
        'signature' => 'assets/packages/signature.webp',
        'premium'   => 'assets/packages/premium.webp',
    ];

    $out = [];
    foreach ($posts as $p) {
        $features = array_filter([
            dry65_get_field('feature_1', $p->ID),
            dry65_get_field('feature_2', $p->ID),
            dry65_get_field('feature_3', $p->ID),
            dry65_get_field('feature_4', $p->ID),
            dry65_get_field('feature_5', $p->ID),
        ]);
        $pimg = dry65_get_field('image', $p->ID);
        if (!$pimg) $pimg = $fallback_pkg[$p->post_name] ?? 'assets/packages/essential.webp';
        $out[] = [
            'id'       => $p->post_name,
            'name'     => $p->post_title,
            'img'      => $pimg,
            'count'    => (int) (dry65_get_field('count', $p->ID) ?: 0),
            'cadence'  => dry65_get_field('cadence', $p->ID) ?: '',
            'blurb'    => dry65_get_field('blurb', $p->ID) ?: '',
            'gift'     => dry65_get_field('gift', $p->ID) ?: '',
            'gift_val' => (int) (dry65_get_field('gift_val', $p->ID) ?: 0),
            'featured' => (bool) dry65_get_field('featured', $p->ID),
            'features' => array_values($features),
        ];
    }
    return $out;
}

/* ---- TEAM (CPT dry65_team) ---- */
function dry65_team() {
    $posts = get_posts([
        'post_type'      => 'dry65_team',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    if (empty($posts)) {
        return [
            ['name' => 'Jelena', 'role' => 'Hair stilista',          'bio' => 'Precizna i posvećena, tvoja kosa je kod nje u najboljim rukama.'],
            ['name' => 'Ema',    'role' => 'Hair stilista',          'bio' => 'Kraljica volumena i mekanih talasa. Brza ruka, topao osmeh.'],
            ['name' => 'Jovana', 'role' => 'Hair stilista',          'bio' => 'Preciznost i glatki finiš su njen potpis. Obožava sleek lookove.'],
            ['name' => 'Nikola', 'role' => 'Osnivač & glavni stilista', 'bio' => 'Iza Dry65 koncepta. Veruje da savršeno feniranje može da promeni ceo dan.'],
        ];
    }

    $out = [];
    foreach ($posts as $p) {
        $out[] = [
            'name'  => $p->post_title,
            'role'  => dry65_get_field('role', $p->ID) ?: '',
            'bio'   => dry65_get_field('bio', $p->ID) ?: '',
            'image' => dry65_get_field('image', $p->ID) ?: '',
        ];
    }
    return $out;
}

/* ---- REVIEWS (CPT dry65_review) ---- */
function dry65_reviews() {
    $posts = get_posts([
        'post_type'      => 'dry65_review',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    if (empty($posts)) {
        return [];
    }

    $out = [];
    foreach ($posts as $p) {
        $out[] = [
            'name'   => $p->post_title,
            'rating' => (int) (dry65_get_field('rating', $p->ID) ?: 5),
            'when'   => dry65_get_field('when', $p->ID) ?: '',
            'text'   => dry65_get_field('text', $p->ID) ?: '',
            'photo'  => dry65_get_field('photo', $p->ID) ?: '',
        ];
    }
    return $out;
}

/* ---- GALLERY (CPT dry65_gallery) ---- */
function dry65_gallery() {
    $posts = get_posts([
        'post_type'      => 'dry65_gallery',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    if (empty($posts)) {
        return [
            ['img' => 'assets/salon/s06.webp', 'tag' => 'Radne stanice'],
            ['img' => 'assets/salon/s01.webp', 'tag' => 'Salon'],
            ['img' => 'assets/salon/s14.webp', 'tag' => 'Zona za pranje'],
            ['img' => 'assets/salon/s02.webp', 'tag' => 'Zid sa fenovima'],
            ['img' => 'assets/salon/s05.webp', 'tag' => 'Ogledala'],
            ['img' => 'assets/salon/s10.webp', 'tag' => 'Čekaonica'],
            ['img' => 'assets/salon/s08.webp', 'tag' => 'Polica sa proizvodima'],
            ['img' => 'assets/salon/s04.webp', 'tag' => 'Lavabo'],
            ['img' => 'assets/salon/s13.webp', 'tag' => 'Enterijer'],
        ];
    }

    $out = [];
    foreach ($posts as $p) {
        $out[] = [
            'img' => dry65_get_field('image', $p->ID) ?: '',
            'tag' => dry65_get_field('tag', $p->ID) ?: $p->post_title,
        ];
    }
    return $out;
}

/* ---- NAV (static) ---- */
function dry65_nav() {
    return [
        ['id' => 'o-nama',    'label' => 'O nama',    'slug' => 'o-nama'],
        ['id' => 'usluge',    'label' => 'Usluge',    'slug' => 'usluge'],
        ['id' => 'cenovnik',  'label' => 'Cenovnik',  'slug' => 'cenovnik'],
        ['id' => 'paketi',    'label' => 'Paketi',    'slug' => 'paketi'],
        ['id' => 'ambijent',  'label' => 'Ambijent',  'slug' => 'ambijent'],
        // ['id' => 'blog',      'label' => 'Blog',      'slug' => 'blog'], // privremeno sakriveno, vraćamo kad bude sadržaja
        ['id' => 'kontakt',   'label' => 'Kontakt',   'slug' => 'kontakt'],
    ];
}

function dry65_rsd($n) {
    return number_format($n, 0, ',', '.');
}
