<?php
/* ============================================================
   Dry65 — Custom Post Types
   ============================================================ */

function dry65_register_cpts() {

    // USLUGE
    register_post_type('dry65_service', [
        'labels' => [
            'name'               => 'Usluge',
            'singular_name'      => 'Usluga',
            'add_new'            => 'Dodaj uslugu',
            'add_new_item'       => 'Dodaj novu uslugu',
            'edit_item'          => 'Izmeni uslugu',
            'all_items'          => 'Sve usluge',
            'menu_name'          => 'Usluge',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-art',
        'menu_position' => 21,
        'supports'      => ['title', 'page-attributes', 'thumbnail'],
        'has_archive'   => false,
    ]);

    // PAKETI
    register_post_type('dry65_package', [
        'labels' => [
            'name'               => 'Paketi',
            'singular_name'      => 'Paket',
            'add_new'            => 'Dodaj paket',
            'add_new_item'       => 'Dodaj novi paket',
            'edit_item'          => 'Izmeni paket',
            'all_items'          => 'Svi paketi',
            'menu_name'          => 'Paketi',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-tag',
        'menu_position' => 22,
        'supports'      => ['title', 'page-attributes', 'thumbnail'],
    ]);

    // TIM
    register_post_type('dry65_team', [
        'labels' => [
            'name'               => 'Tim',
            'singular_name'      => 'Član tima',
            'add_new'            => 'Dodaj člana',
            'add_new_item'       => 'Dodaj novog člana',
            'edit_item'          => 'Izmeni člana',
            'all_items'          => 'Svi članovi',
            'menu_name'          => 'Tim',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-groups',
        'menu_position' => 23,
        'supports'      => ['title', 'page-attributes', 'thumbnail'],
    ]);

    // GALERIJA (Ambijent)
    register_post_type('dry65_gallery', [
        'labels' => [
            'name'               => 'Galerija',
            'singular_name'      => 'Slika',
            'add_new'            => 'Dodaj sliku',
            'add_new_item'       => 'Dodaj novu sliku',
            'edit_item'          => 'Izmeni sliku',
            'all_items'          => 'Sve slike',
            'menu_name'          => 'Galerija',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-format-gallery',
        'menu_position' => 24,
        'supports'      => ['title', 'page-attributes', 'thumbnail'],
    ]);

    // RECENZIJE
    register_post_type('dry65_review', [
        'labels' => [
            'name'               => 'Recenzije',
            'singular_name'      => 'Recenzija',
            'add_new'            => 'Dodaj recenziju',
            'add_new_item'       => 'Dodaj novu recenziju',
            'edit_item'          => 'Izmeni recenziju',
            'all_items'          => 'Sve recenzije',
            'menu_name'          => 'Recenzije',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-star-filled',
        'menu_position' => 25,
        'supports'      => ['title', 'page-attributes'],
    ]);
}
add_action('init', 'dry65_register_cpts');

/* ---- Create hidden "Settings" page on theme activation ---- */
function dry65_create_settings_page() {
    $existing = get_page_by_path('dry65-podesavanja');
    if (!$existing) {
        wp_insert_post([
            'post_title'   => 'Dry65 Podešavanja',
            'post_name'    => 'dry65-podesavanja',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => 'Ova stranica sadrži globalna podešavanja sajta (kontakt, radno vreme, navigacija). Ne briši je.',
        ]);
    }
}
add_action('after_switch_theme', 'dry65_create_settings_page');

/* ---- Admin menu link to settings page ---- */
function dry65_admin_settings_link() {
    $page = get_page_by_path('dry65-podesavanja');
    if ($page) {
        add_menu_page(
            'Dry65 Podešavanja',
            'Dry65 Podešavanja',
            'edit_pages',
            'post.php?post=' . $page->ID . '&action=edit',
            '',
            'dashicons-admin-settings',
            20
        );
    }
}
add_action('admin_menu', 'dry65_admin_settings_link');
