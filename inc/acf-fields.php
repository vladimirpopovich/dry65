<?php
/* ============================================================
   Dry65 — ACF field groups (Free version compatible)
   ============================================================ */

if (!function_exists('acf_add_local_field_group')) return;

add_action('acf/init', function() {

    /* =========================================================
       GLOBALNA PODEŠAVANJA (vezana za "dry65-podesavanja" page)
       ========================================================= */
    $settings_page = get_page_by_path('dry65-podesavanja');
    if ($settings_page) {
        acf_add_local_field_group([
            'key' => 'group_dry65_settings',
            'title' => 'Globalna podešavanja',
            'fields' => [
                // Kontakt info
                ['key' => 'field_biz_name',     'label' => 'Naziv',    'name' => 'biz_name',     'type' => 'text', 'default_value' => 'Dry65'],
                ['key' => 'field_biz_tagline',  'label' => 'Tagline',  'name' => 'biz_tagline',  'type' => 'text', 'default_value' => 'Blowout Hair Bar'],
                ['key' => 'field_biz_address',  'label' => 'Adresa',   'name' => 'biz_address',  'type' => 'text'],
                ['key' => 'field_biz_phone',    'label' => 'Telefon (display)', 'name' => 'biz_phone_display', 'type' => 'text'],
                ['key' => 'field_biz_phone_raw','label' => 'Telefon (tel: link)','name' => 'biz_phone',  'type' => 'text', 'instructions' => 'Bez razmaka, npr. +381606900655'],
                ['key' => 'field_biz_email',    'label' => 'Email',    'name' => 'biz_email',    'type' => 'email'],
                ['key' => 'field_biz_ig',       'label' => 'Instagram handle',  'name' => 'biz_instagram', 'type' => 'text'],
                ['key' => 'field_biz_ig_url',   'label' => 'Instagram URL',     'name' => 'biz_instagram_url', 'type' => 'url'],
                ['key' => 'field_biz_maps',     'label' => 'Google Maps URL',   'name' => 'biz_maps_url', 'type' => 'url'],
                ['key' => 'field_biz_reviews',  'label' => 'Google Reviews URL','name' => 'biz_reviews_url', 'type' => 'url', 'instructions' => 'Link do Google search rezultata sa svim recenzijama'],

                // Radno vreme (3 fiksna polja — bez Repeater-a)
                ['key' => 'field_hours_tab', 'label' => 'Radno vreme', 'type' => 'tab'],
                ['key' => 'field_h1_day', 'label' => 'Dan 1', 'name' => 'h1_day',  'type' => 'text', 'default_value' => 'Ponedeljak – Petak'],
                ['key' => 'field_h1_time','label' => 'Vreme 1','name' => 'h1_time','type' => 'text', 'default_value' => '08:00 – 20:00'],
                ['key' => 'field_h2_day', 'label' => 'Dan 2', 'name' => 'h2_day',  'type' => 'text', 'default_value' => 'Subota'],
                ['key' => 'field_h2_time','label' => 'Vreme 2','name' => 'h2_time','type' => 'text', 'default_value' => '10:00 – 18:00'],
                ['key' => 'field_h3_day', 'label' => 'Dan 3', 'name' => 'h3_day',  'type' => 'text', 'default_value' => 'Nedelja'],
                ['key' => 'field_h3_time','label' => 'Vreme 3','name' => 'h3_time','type' => 'text', 'default_value' => 'Ne radimo'],

                // Dužine kose (za cenovnik)
                ['key' => 'field_len_tab', 'label' => 'Dužine kose', 'type' => 'tab'],
                ['key' => 'field_l1_label','label' => 'Dužina 1 — naziv', 'name' => 'l1_label', 'type' => 'text', 'default_value' => 'Kratka'],
                ['key' => 'field_l1_price','label' => 'Dužina 1 — cena',  'name' => 'l1_price', 'type' => 'number', 'default_value' => 1400],
                ['key' => 'field_l1_img',  'label' => 'Dužina 1 — slika', 'name' => 'l1_img',   'type' => 'image', 'return_format' => 'url'],
                ['key' => 'field_l2_label','label' => 'Dužina 2 — naziv', 'name' => 'l2_label', 'type' => 'text', 'default_value' => 'Srednja'],
                ['key' => 'field_l2_price','label' => 'Dužina 2 — cena',  'name' => 'l2_price', 'type' => 'number', 'default_value' => 1800],
                ['key' => 'field_l2_img',  'label' => 'Dužina 2 — slika', 'name' => 'l2_img',   'type' => 'image', 'return_format' => 'url'],
                ['key' => 'field_l3_label','label' => 'Dužina 3 — naziv', 'name' => 'l3_label', 'type' => 'text', 'default_value' => 'Duga'],
                ['key' => 'field_l3_price','label' => 'Dužina 3 — cena',  'name' => 'l3_price', 'type' => 'number', 'default_value' => 2000],
                ['key' => 'field_l3_img',  'label' => 'Dužina 3 — slika', 'name' => 'l3_img',   'type' => 'image', 'return_format' => 'url'],
                ['key' => 'field_l4_label','label' => 'Dužina 4 — naziv', 'name' => 'l4_label', 'type' => 'text', 'default_value' => 'Extra duga'],
                ['key' => 'field_l4_price','label' => 'Dužina 4 — cena',  'name' => 'l4_price', 'type' => 'number', 'default_value' => 2200],
                ['key' => 'field_l4_img',  'label' => 'Dužina 4 — slika', 'name' => 'l4_img',   'type' => 'image', 'return_format' => 'url'],
            ],
            'location' => [[['param' => 'page', 'operator' => '==', 'value' => $settings_page->ID]]],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
        ]);
    }

    /* =========================================================
       USLUGE (CPT)
       ========================================================= */
    acf_add_local_field_group([
        'key' => 'group_dry65_service',
        'title' => 'Detalji usluge',
        'fields' => [
            ['key' => 'field_svc_kicker', 'label' => 'Redni broj (kicker)', 'name' => 'kicker', 'type' => 'text', 'instructions' => 'npr. 01, 02, 03'],
            ['key' => 'field_svc_short',  'label' => 'Kratak opis',         'name' => 'short',  'type' => 'textarea', 'rows' => 2],
            ['key' => 'field_svc_body',   'label' => 'Pun opis',            'name' => 'body',   'type' => 'textarea', 'rows' => 5],
            ['key' => 'field_svc_image',  'label' => 'Slika',               'name' => 'image',  'type' => 'image', 'return_format' => 'url'],
            ['key' => 'field_svc_p1',     'label' => 'Stavka 1',            'name' => 'point_1','type' => 'text'],
            ['key' => 'field_svc_p2',     'label' => 'Stavka 2',            'name' => 'point_2','type' => 'text'],
            ['key' => 'field_svc_p3',     'label' => 'Stavka 3',            'name' => 'point_3','type' => 'text'],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'dry65_service']]],
    ]);

    /* =========================================================
       PAKETI (CPT)
       ========================================================= */
    acf_add_local_field_group([
        'key' => 'group_dry65_package',
        'title' => 'Detalji paketa',
        'fields' => [
            ['key' => 'field_pkg_count',    'label' => 'Broj tretmana',  'name' => 'count',   'type' => 'number', 'default_value' => 4],
            ['key' => 'field_pkg_cadence',  'label' => 'Učestalost',     'name' => 'cadence', 'type' => 'text',   'instructions' => 'npr. 4 tretmana mesečno'],
            ['key' => 'field_pkg_blurb',    'label' => 'Kratak opis',    'name' => 'blurb',   'type' => 'textarea', 'rows' => 2],
            ['key' => 'field_pkg_image',    'label' => 'Slika',          'name' => 'image',   'type' => 'image', 'return_format' => 'url'],
            ['key' => 'field_pkg_gift',     'label' => 'Poklon',         'name' => 'gift',    'type' => 'text'],
            ['key' => 'field_pkg_gift_val', 'label' => 'Vrednost poklona (RSD)', 'name' => 'gift_val', 'type' => 'number'],
            ['key' => 'field_pkg_featured', 'label' => 'Istaknut paket', 'name' => 'featured','type' => 'true_false', 'ui' => 1],
            ['key' => 'field_pkg_f1',       'label' => 'Karakteristika 1', 'name' => 'feature_1', 'type' => 'text'],
            ['key' => 'field_pkg_f2',       'label' => 'Karakteristika 2', 'name' => 'feature_2', 'type' => 'text'],
            ['key' => 'field_pkg_f3',       'label' => 'Karakteristika 3', 'name' => 'feature_3', 'type' => 'text'],
            ['key' => 'field_pkg_f4',       'label' => 'Karakteristika 4', 'name' => 'feature_4', 'type' => 'text'],
            ['key' => 'field_pkg_f5',       'label' => 'Karakteristika 5', 'name' => 'feature_5', 'type' => 'text'],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'dry65_package']]],
    ]);

    /* =========================================================
       TIM (CPT)
       ========================================================= */
    acf_add_local_field_group([
        'key' => 'group_dry65_team',
        'title' => 'Detalji člana tima',
        'fields' => [
            ['key' => 'field_team_role', 'label' => 'Pozicija', 'name' => 'role', 'type' => 'text', 'default_value' => 'Hair stilista'],
            ['key' => 'field_team_bio',  'label' => 'Bio',      'name' => 'bio',  'type' => 'textarea', 'rows' => 3],
            ['key' => 'field_team_image','label' => 'Slika',    'name' => 'image','type' => 'image', 'return_format' => 'url'],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'dry65_team']]],
    ]);

    /* =========================================================
       GALERIJA (CPT)
       ========================================================= */
    acf_add_local_field_group([
        'key' => 'group_dry65_gallery',
        'title' => 'Detalji slike',
        'fields' => [
            ['key' => 'field_gal_image', 'label' => 'Slika', 'name' => 'image', 'type' => 'image', 'return_format' => 'url'],
            ['key' => 'field_gal_tag',   'label' => 'Tag/opis ispod slike', 'name' => 'tag', 'type' => 'text'],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'dry65_gallery']]],
    ]);

    /* =========================================================
       PONUDE (CPT)
       ========================================================= */
    acf_add_local_field_group([
        'key' => 'group_dry65_offer',
        'title' => 'Detalji ponude',
        'fields' => [
            ['key' => 'field_off_badge',     'label' => 'Badge (opciono)', 'name' => 'badge',     'type' => 'text', 'instructions' => 'npr. 10% OFF, HAPPY HOUR, NOVO'],
            ['key' => 'field_off_start',     'label' => 'Datum početka',   'name' => 'start_date','type' => 'date_picker', 'display_format' => 'j. M Y', 'return_format' => 'Y-m-d'],
            ['key' => 'field_off_end',       'label' => 'Datum kraja',     'name' => 'end_date',  'type' => 'date_picker', 'display_format' => 'j. M Y', 'return_format' => 'Y-m-d'],
            ['key' => 'field_off_desc',      'label' => 'Opis ponude',     'name' => 'description','type' => 'wysiwyg', 'tabs' => 'visual', 'media_upload' => 0, 'toolbar' => 'basic', 'instructions' => 'Tekst kao što je postavljen na Google Business. Možeš koristiti bold, italic, i redove.'],
            ['key' => 'field_off_image',     'label' => 'Glavna slika',    'name' => 'image',     'type' => 'image', 'return_format' => 'url', 'instructions' => 'Idealno kvadratni format (1000x1000px)'],
            ['key' => 'field_off_btn_text',  'label' => 'Tekst dugmeta',   'name' => 'btn_text',  'type' => 'text', 'default_value' => 'Saznaj više', 'instructions' => 'npr. Saznaj više, Iskoristi ponudu'],
            ['key' => 'field_off_btn_url',   'label' => 'Link dugmeta',    'name' => 'btn_url',   'type' => 'url', 'instructions' => 'Interni ili eksterni link. Ostavi prazno ako nema dugmeta.'],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'dry65_offer']]],
    ]);

    /* =========================================================
       RECENZIJE (CPT)
       ========================================================= */
    acf_add_local_field_group([
        'key' => 'group_dry65_review',
        'title' => 'Detalji recenzije',
        'fields' => [
            ['key' => 'field_rev_rating', 'label' => 'Ocena (1-5)', 'name' => 'rating', 'type' => 'number', 'min' => 1, 'max' => 5, 'default_value' => 5],
            ['key' => 'field_rev_when',   'label' => 'Vreme objave', 'name' => 'when',   'type' => 'text', 'instructions' => 'npr. pre 2 nedelje'],
            ['key' => 'field_rev_text',   'label' => 'Tekst recenzije', 'name' => 'text', 'type' => 'textarea', 'rows' => 4],
            ['key' => 'field_rev_photo',  'label' => 'Avatar (opciono)', 'name' => 'photo', 'type' => 'image', 'return_format' => 'url', 'instructions' => 'Ako nema, prikazuje se prvo slovo imena'],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'dry65_review']]],
    ]);

    /* =========================================================
       PAGE: O NAMA
       ========================================================= */
    acf_add_local_field_group([
        'key' => 'group_page_o_nama',
        'title' => 'Sadržaj stranice — O nama',
        'fields' => [
            ['key' => 'field_on_intro',   'label' => 'Uvodni tekst',    'name' => 'intro',   'type' => 'wysiwyg', 'tabs' => 'visual', 'media_upload' => 0],
            ['key' => 'field_on_image',   'label' => 'Glavna slika',    'name' => 'image',   'type' => 'image', 'return_format' => 'url'],
        ],
        'location' => [[['param' => 'page', 'operator' => '==', 'value' => get_page_by_path('o-nama') ? get_page_by_path('o-nama')->ID : 0]]],
    ]);

    /* =========================================================
       PAGE: KONTAKT
       ========================================================= */
    acf_add_local_field_group([
        'key' => 'group_page_kontakt',
        'title' => 'Sadržaj stranice — Kontakt',
        'fields' => [
            ['key' => 'field_kt_intro', 'label' => 'Uvodni tekst', 'name' => 'intro', 'type' => 'wysiwyg', 'tabs' => 'visual', 'media_upload' => 0],
            ['key' => 'field_kt_map',   'label' => 'Embed mape (iframe HTML)', 'name' => 'map_embed', 'type' => 'textarea', 'rows' => 4],
        ],
        'location' => [[['param' => 'page', 'operator' => '==', 'value' => get_page_by_path('kontakt') ? get_page_by_path('kontakt')->ID : 0]]],
    ]);

    /* =========================================================
       FRONT PAGE (Početna)
       ========================================================= */
    $front_id = (int) get_option('page_on_front');
    if ($front_id) {
        acf_add_local_field_group([
            'key' => 'group_page_home',
            'title' => 'Sadržaj — Početna',
            'fields' => [
                ['key' => 'field_hp_hero_kicker', 'label' => 'Hero — kicker (mali tekst gore)', 'name' => 'hero_kicker', 'type' => 'text', 'default_value' => 'Bez zakazivanja'],
                ['key' => 'field_hp_hero_title',  'label' => 'Hero — naslov',                   'name' => 'hero_title',  'type' => 'text', 'default_value' => 'Samo dođeš'],
                ['key' => 'field_hp_hero_text',   'label' => 'Hero — podtekst',                 'name' => 'hero_text',   'type' => 'textarea', 'rows' => 2],
                ['key' => 'field_hp_hero_image',  'label' => 'Hero — pozadinska slika',         'name' => 'hero_image',  'type' => 'image', 'return_format' => 'url'],
            ],
            'location' => [[['param' => 'page', 'operator' => '==', 'value' => $front_id]]],
        ]);
    }
});
