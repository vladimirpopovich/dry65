<?php
/**
 * Kategorije usluga koje jos NISU spremne (placeholder tekst) — vadimo ih iz
 * Yoast XML sitemap-a i stavljamo noindex, da Google ne indeksira tanak sadrzaj.
 *
 * Kad neka kategorija dobije pravi tekst, izbaci njen slug iz liste dole.
 */
if (!defined('ABSPATH')) exit;

function dry65_unready_service_slugs() {
    return ['stilizovanje', 'nega'];
}

/** ID-jevi tih hub kategorija + sve njihove dece. */
function dry65_unready_service_ids() {
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = [];
    foreach (dry65_unready_service_slugs() as $slug) {
        $hub = get_page_by_path($slug, OBJECT, 'dry65_service');
        if (!$hub) continue;
        $ids[] = (int) $hub->ID;
        $kids = get_posts([
            'post_type'      => 'dry65_service',
            'post_parent'    => $hub->ID,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ]);
        foreach ($kids as $k) $ids[] = (int) $k;
    }
    return $ids;
}

/* 1) Izbaci ih iz Yoast XML sitemap-a. */
add_filter('wpseo_exclude_from_sitemap_by_post_ids', function ($excluded) {
    return array_merge((array) $excluded, dry65_unready_service_ids());
});

/* 2) noindex na tim stranama. */
add_filter('wpseo_robots_array', function ($robots) {
    if (is_singular('dry65_service') && in_array(get_the_ID(), dry65_unready_service_ids(), true)) {
        $robots['index'] = 'noindex';
    }
    return $robots;
});
// fallback za starije Yoast verzije (robots kao string)
add_filter('wpseo_robots', function ($robots) {
    if (is_singular('dry65_service') && in_array(get_the_ID(), dry65_unready_service_ids(), true)) {
        return 'noindex, follow';
    }
    return $robots;
});
