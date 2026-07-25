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
        'public'             => true,
        'publicly_queryable' => true,
        'exclude_from_search'=> false,
        'hierarchical'       => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-art',
        'menu_position'      => 21,
        'supports'           => ['title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'],
        'has_archive'        => false,
        'rewrite'            => ['slug' => 'usluge', 'with_front' => false, 'hierarchical' => true],
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

    // AKTUELNE PONUDE
    register_post_type('dry65_offer', [
        'labels' => [
            'name'               => 'Ponude',
            'singular_name'      => 'Ponuda',
            'add_new'            => 'Dodaj ponudu',
            'add_new_item'       => 'Dodaj novu ponudu',
            'edit_item'          => 'Izmeni ponudu',
            'all_items'          => 'Sve ponude',
            'menu_name'          => 'Ponude',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-megaphone',
        'menu_position' => 26,
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

/* ---- Create hidden "Settings" page on theme activation ----
   Stranica je 'private' status — samo logovani admini je vide.
   Ne pojavljuje se u sitemap-u, orphan page issue-u. */
function dry65_create_settings_page() {
    $existing = get_page_by_path('dry65-podesavanja');
    if (!$existing) {
        wp_insert_post([
            'post_title'   => 'Dry65 Podešavanja',
            'post_name'    => 'dry65-podesavanja',
            'post_status'  => 'private', // samo admin vidi, ne u sitemap-u
            'post_type'    => 'page',
            'post_content' => 'Ova stranica sadrži globalna podešavanja sajta (kontakt, radno vreme, navigacija). Ne briši je.',
        ]);
    } else if ($existing->post_status !== 'private') {
        // Ako je postojeca stranica publish, prebaci na private (fix za orphan issue)
        wp_update_post([
            'ID'          => $existing->ID,
            'post_status' => 'private',
        ]);
    }
}
add_action('after_switch_theme', 'dry65_create_settings_page');
add_action('init', 'dry65_create_settings_page'); // Radi i bez theme reactivation

/* ---- Usluge: hijerarhija (2 kategorije-huba + 10 podstranica) + flush rewrite ----
   FENIRANJE = fen + četka (prirodno, drži par dana). STILIZOVANJE = vruć alat
   (pegla/figaro, definisano, drži duže). Razdvojen tekst = bez SEO kanibalizacije.
   Postojeće kategorije (feniranje/stilizovanje/nega) dobijaju intro; stari flat
   stubovi (v1) se brišu i prave iznova kao deca. Admin sve popunjava kasnije. */
function dry65_seed_services() {
    if (get_option('dry65_services_seed_v') === '4') return;

    // 1) Kategorije-roditelji + intro tekst (razdvajanje tehnike).
    // „Feniranje na četke" je glavni hub (tehnika); deca su konkretni look-ovi.
    $parents = [
        'feniranje-na-cetke' => [
            'title' => 'Feniranje na četke',
            'old'   => 'feniranje', // stari parent „Pranje i feniranje" -> preimenuj na produkciji
            'intro' => 'Feniranje na četke je osnova svakog stila u Dry65 — oblikovanje kose fenom i okruglom četkom, posle pranja. Prirodan, mek i sjajan rezultat koji drži danima. Odavde se granaju svi look-ovi: na ravno, na talase, na lokne i na volumen. Sve bez zakazivanja.',
        ],
        'stilizovanje' => [
            'title' => 'Stilizovanje kose',
            'intro' => 'Stilizovanje je oblikovanje vrućim alatom (pegla, figaro, curler), posle sušenja. Rezultat je definisaniji i drži znatno duže od feniranja, idealno za izlaske, proslave i posebne prilike. Baš na tanjoj kosi daje najbolji efekat.',
        ],
        'nega' => [
            'title' => 'Nega kose',
            'intro' => 'Dubinska nega i tretmani koji vraćaju kosi sjaj, snagu i hidrataciju: hair infusion, maske, parna stanica i ritualne nege. Uključeno uz mesečne pakete.',
        ],
    ];
    // Oslobodi slug ako ga drži attachment (slike umeju da „ukradu" slug, npr. stilizovanje.webp)
    foreach (array_keys($parents) as $slug) {
        $att = get_page_by_path($slug, OBJECT, 'attachment');
        if ($att) wp_update_post(['ID' => $att->ID, 'post_name' => $slug . '-slika']);
    }

    $pid = [];
    foreach ($parents as $slug => $info) {
        $p = get_page_by_path($slug, OBJECT, 'dry65_service');
        // Stari slug (npr. 'feniranje' -> 'feniranje-na-cetke')
        if (!$p && !empty($info['old'])) {
            $p = get_page_by_path($info['old'], OBJECT, 'dry65_service');
        }
        // Ako postoji ali sa „-2" slug-om (raniji konflikt), nadji ga i po -2
        if (!$p) {
            $alt = get_posts(['post_type' => 'dry65_service', 'name' => $slug . '-2', 'posts_per_page' => 1, 'post_status' => 'any']);
            if ($alt) $p = $alt[0];
        }
        if ($p) {
            $pid[$slug] = $p->ID;
            $upd = ['ID' => $p->ID];
            if ($p->post_name !== $slug) $upd['post_name'] = $slug;             // vrati čist slug
            if (!empty($info['old']) && $p->post_title !== $info['title']) $upd['post_title'] = $info['title']; // preimenuj stari parent
            if (trim((string) $p->post_excerpt) === '') $upd['post_excerpt'] = $info['intro'];
            if (count($upd) > 1) wp_update_post($upd);
        } else {
            $pid[$slug] = wp_insert_post([
                'post_type' => 'dry65_service', 'post_status' => 'publish',
                'post_title' => $info['title'], 'post_name' => $slug,
                'post_excerpt' => $info['intro'], 'menu_order' => 0,
            ]);
        }
    }

    // 2) Obriši stare flat stub stilove iz v1 (bez sadržaja). NAPOMENA: 'feniranje-na-cetke'
    // NIJE u listi — to je sad glavni PARENT hub, ne sme da se obriše.
    foreach (['feniranje-na-talase', 'feniranje-na-volumen', 'feniranje-na-lokne', 'feniranje-na-ravno'] as $old) {
        $op = get_page_by_path($old, OBJECT, 'dry65_service');
        if ($op && (int) $op->post_parent === 0) wp_delete_post($op->ID, true);
    }

    // 3) Deca po grani [naslov, slug, kratak opis] — „na četke" je parent, ne dete
    $children = [
        'feniranje-na-cetke' => [
            ['Feniranje na ravno',   'feniranje-na-ravno',   'Glatka i uredna kosa, oblikovana fenom i četkom — bez pegle. Prirodan sjaj i mekoća za svaki dan.'],
            ['Feniranje na talase',  'feniranje-na-talase',  'Mekani, prirodni talasi fenom i okruglom četkom. Opušten a sređen look koji drži danima.'],
            ['Feniranje na lokne',   'feniranje-na-lokne',   'Nežne, mekane lokne postignute fenom i četkom. Prirodan volumen i pokret, savršeno za svaki dan.'],
            ['Feniranje na volumen', 'feniranje-na-volumen', 'Podignut koren i bujna, puna kosa. Feniranje koje daje maksimalan volumen i telo frizuri.'],
        ],
        'stilizovanje' => [
            ['Ravna kosa peglom', 'ravna-kosa-peglom', 'Staklasto glatka, sjajna i potpuno ravna kosa peglom. Sleek look koji drži i po vlažnom vremenu.'],
            ['Talasi peglom',     'talasi-peglom',     'Definisani talasi napravljeni peglom — izraženiji i dugotrajniji od feniranja. Za sređen, elegantan izgled.'],
            ['Lokne figarom',     'lokne-figarom',     'Bujne, definisane lokne figarom ili curlerom. Glamurozan look koji drži celu noć.'],
            ['Hollywood talasi',  'hollywood-talasi',  'Glatki, uniformni retro talasi u stilu crvenog tepiha. Za venčanja, proslave i velike izlaske.'],
            ['Beach Waves',       'beach-waves',       'Opušteni, blago razbarušeni letnji talasi. Neobavezan, moderan look koji izgleda prirodno a sređeno.'],
        ],
    ];
    foreach ($children as $pslug => $list) {
        $order = 10;
        foreach ($list as $c) {
            $exists = get_posts([
                'post_type' => 'dry65_service', 'name' => $c[1],
                'post_parent' => $pid[$pslug], 'posts_per_page' => 1, 'fields' => 'ids',
                'post_status' => 'any',
            ]);
            if (!$exists) {
                wp_insert_post([
                    'post_type' => 'dry65_service', 'post_status' => 'publish',
                    'post_title' => $c[0], 'post_name' => $c[1],
                    'post_parent' => $pid[$pslug], 'post_excerpt' => $c[2],
                    'post_content' => 'Tekst u pripremi. Dodaj detaljan opis: kako izgleda stil, kojom tehnikom se radi, kome odgovara, koliko drži i zašto ga izabrati u Dry65.',
                    'menu_order' => $order,
                ]);
            }
            $order += 10;
        }
    }

    flush_rewrite_rules(false);
    update_option('dry65_services_seed_v', '4');
}
add_action('init', 'dry65_seed_services', 20);

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
