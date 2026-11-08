<?php
/**
 * B2B online prijava firme (/za-firme/#prijava).
 *
 * Firma popuni formular -> prijava se čuva kao privatni CPT `dry65_b2b_prijava`
 * (wp-admin: B2B Firme -> Prijave) i šalje se mejl na biz email.
 * Prijava NE pravi firmu u B2B sistemu automatski: admin je pregleda i
 * ručno kreira firmu kad se dogovore detalji.
 *
 * Bez nonce-a namerno: strana je keširana (LiteSpeed), pa bi nonce istekao.
 * Zaštita: honeypot polje, minimalno vreme popunjavanja, limit po IP-u.
 */

if (!defined('ABSPATH')) exit;

function dry65_b2b_models() {
    return [
        'mesecni-paket'  => 'Mesečni paketi po fiksnoj ceni (1.850 din, 40+ feniranja)',
        'sufinansiranje' => 'Deo firma, ostatak zaposleni',
        'vauceri'        => 'Digitalni poklon vaučeri',
        'nije-sigurno'   => 'Nismo sigurni, posavetujte nas',
    ];
}

/* ---- CPT ---- */
add_action('init', function () {
    register_post_type('dry65_b2b_prijava', [
        'labels' => [
            'name'          => 'Prijave firmi',
            'singular_name' => 'Prijava firme',
            'menu_name'     => 'Prijave',
            'all_items'     => 'Prijave',
            'edit_item'     => 'Prijava firme',
            'search_items'  => 'Pretraži prijave',
            'not_found'     => 'Još nema prijava.',
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'dry65-b2b',
        'show_in_rest'        => false,
        'exclude_from_search' => true,
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'capabilities'        => ['create_posts' => 'do_not_allow'],
        'map_meta_cap'        => true,
    ]);
});

function dry65_b2b_prijava_fields() {
    return [
        'company'   => 'Naziv firme',
        'pib'       => 'PIB',
        'contact'   => 'Kontakt osoba',
        'email'     => 'Email',
        'phone'     => 'Telefon',
        'model'     => 'Model',
        'employees' => 'Broj zaposlenih',
        'note'      => 'Napomena',
    ];
}

/* ---- Obrada formulara ---- */
function dry65_b2b_prijava_handle() {
    $back = wp_get_referer() ?: home_url('/za-firme/');
    $back = remove_query_arg(['prijava', 'model'], $back);
    $go = function ($code) use ($back) {
        wp_safe_redirect(add_query_arg('prijava', $code, $back) . '#prijava');
        exit;
    };

    // Honeypot + prebrzo popunjeno = bot. Tiho "uspeh" da bot ne uči.
    $hp = trim((string) ($_POST['website'] ?? ''));
    $ts = (int) ($_POST['ts'] ?? 0);
    if ($hp !== '' || ($ts && time() - $ts < 3)) $go('ok');

    $ip  = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    $key = 'dry65_b2bp_' . md5($ip);
    if (get_transient($key)) $go('cekaj');

    $models = dry65_b2b_models();
    $d = [
        'company'   => sanitize_text_field(wp_unslash($_POST['company'] ?? '')),
        'pib'       => preg_replace('/\D/', '', (string) ($_POST['pib'] ?? '')),
        'contact'   => sanitize_text_field(wp_unslash($_POST['contact'] ?? '')),
        'email'     => sanitize_email(wp_unslash($_POST['email'] ?? '')),
        'phone'     => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
        'model'     => sanitize_key($_POST['model'] ?? ''),
        'employees' => max(0, (int) ($_POST['employees'] ?? 0)),
        'note'      => sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')),
    ];

    if ($d['company'] === '' || $d['contact'] === '' || $d['phone'] === ''
        || !is_email($d['email']) || strlen($d['pib']) !== 9 || !isset($models[$d['model']])) {
        $go('greska');
    }
    $d['company'] = mb_substr($d['company'], 0, 190);
    $d['note']    = mb_substr($d['note'], 0, 2000);

    $post_id = wp_insert_post([
        'post_type'   => 'dry65_b2b_prijava',
        'post_status' => 'publish',
        'post_title'  => $d['company'],
    ], true);
    if (is_wp_error($post_id)) $go('greska');

    foreach ($d as $k => $v) update_post_meta($post_id, '_b2bp_' . $k, $v);
    update_post_meta($post_id, '_b2bp_ip', $ip);
    set_transient($key, 1, 60);

    // Mejl salonu (Reply-To = firma)
    $biz   = dry65_biz();
    $label = dry65_b2b_prijava_fields();
    $lines = [];
    foreach ($d as $k => $v) {
        if ($k === 'model') $v = $models[$v];
        $lines[] = $label[$k] . ': ' . ($v === '' ? '/' : $v);
    }
    $lines[] = '';
    $lines[] = 'Pregled u wp-admin: ' . admin_url('post.php?post=' . $post_id . '&action=edit');
    wp_mail(
        $biz['email'],
        'Nova B2B prijava: ' . $d['company'],
        implode("\n", $lines),
        ['Reply-To: ' . $d['contact'] . ' <' . $d['email'] . '>']
    );

    $go('ok');
}
add_action('admin_post_nopriv_dry65_b2b_prijava', 'dry65_b2b_prijava_handle');
add_action('admin_post_dry65_b2b_prijava', 'dry65_b2b_prijava_handle');

/* ---- wp-admin: kolone u listi ---- */
add_filter('manage_dry65_b2b_prijava_posts_columns', function () {
    return [
        'cb'        => '<input type="checkbox">',
        'title'     => 'Firma',
        'b2b_model' => 'Model',
        'b2b_cont'  => 'Kontakt',
        'b2b_emp'   => 'Zaposlenih',
        'date'      => 'Datum',
    ];
});
add_action('manage_dry65_b2b_prijava_posts_custom_column', function ($col, $id) {
    $m = fn($k) => get_post_meta($id, '_b2bp_' . $k, true);
    if ($col === 'b2b_model') {
        $models = dry65_b2b_models();
        echo esc_html($models[$m('model')] ?? $m('model'));
    } elseif ($col === 'b2b_cont') {
        echo esc_html($m('contact')) . '<br><a href="mailto:' . esc_attr($m('email')) . '">' . esc_html($m('email')) . '</a><br>' . esc_html($m('phone'));
    } elseif ($col === 'b2b_emp') {
        echo (int) $m('employees');
    }
}, 10, 2);

/* ---- wp-admin: detalj prijave (samo za čitanje) ---- */
add_action('add_meta_boxes_dry65_b2b_prijava', function () {
    add_meta_box('dry65_b2bp_data', 'Podaci iz prijave', function ($post) {
        $models = dry65_b2b_models();
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach (dry65_b2b_prijava_fields() as $k => $label) {
            $v = get_post_meta($post->ID, '_b2bp_' . $k, true);
            if ($k === 'model') $v = $models[$v] ?? $v;
            if ($k === 'email' && $v) $v = '<a href="mailto:' . esc_attr($v) . '">' . esc_html($v) . '</a>';
            elseif ($k === 'phone' && $v) $v = '<a href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $v)) . '">' . esc_html($v) . '</a>';
            else $v = nl2br(esc_html((string) $v));
            echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . ($v === '' ? '/' : $v) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=dry65-b2b')) . '">Otvori B2B Firme da kreiraš firmu</a></p>';
    }, 'dry65_b2b_prijava', 'normal', 'high');
});
