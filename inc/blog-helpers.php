<?php
/* ============================================================
   Dry65 — Blog Helper funkcije
   ============================================================ */

/* ---- Reading time ---- */
function dry65_reading_time($post_id = null) {
    $post = $post_id ? get_post($post_id) : get_post();
    if (!$post) return 0;
    $content = strip_tags($post->post_content);
    $word_count = str_word_count($content);
    $minutes = max(1, ceil($word_count / 220)); // 220 reči po minuti
    return $minutes;
}

/* ---- Share buttons ---- */
function dry65_share_buttons($post_id = null) {
    $post = $post_id ? get_post($post_id) : get_post();
    if (!$post) return '';

    $url   = urlencode(get_permalink($post->ID));
    $title = urlencode($post->post_title);

    $facebook  = "https://www.facebook.com/sharer/sharer.php?u={$url}";
    $whatsapp  = "https://api.whatsapp.com/send?text={$title}%20{$url}";
    $copy_url  = get_permalink($post->ID);

    ob_start(); ?>
    <div class="share-buttons" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:48px;padding-top:32px;border-top:1px solid var(--sage-line);">
      <span class="mono" style="font-size:13px;color:var(--muted);text-transform:uppercase;letter-spacing:0.1em;">Podeli</span>
      <a href="<?php echo esc_url($facebook); ?>" target="_blank" rel="noopener" class="share-btn" aria-label="Podeli na Facebook">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
      </a>
      <a href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener" class="share-btn" aria-label="Podeli na WhatsApp">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
      </a>
      <button type="button" class="share-btn" data-copy="<?php echo esc_attr($copy_url); ?>" aria-label="Kopiraj link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
      </button>
    </div>
    <?php
    return ob_get_clean();
}

/* ---- Related posts ---- */
function dry65_related_posts($post_id = null, $count = 3) {
    $post = $post_id ? get_post($post_id) : get_post();
    if (!$post) return [];

    // Pokušaj iz iste kategorije
    $cats = wp_get_post_categories($post->ID);
    $args = [
        'post_type'      => 'post',
        'posts_per_page' => $count,
        'post__not_in'   => [$post->ID],
        'orderby'        => 'rand',
    ];
    if (!empty($cats)) $args['category__in'] = $cats;

    $related = get_posts($args);

    // Ako nema u istoj kategoriji, uzmi bilo koje
    if (count($related) < $count) {
        $args2 = [
            'post_type'      => 'post',
            'posts_per_page' => $count,
            'post__not_in'   => [$post->ID],
            'orderby'        => 'rand',
        ];
        $related = get_posts($args2);
    }

    return $related;
}

/* ---- CTA box za kraj posta ---- */
function dry65_post_cta() {
    $biz = dry65_biz();
    ob_start(); ?>
    <div class="post-cta" style="margin-top:64px;padding:clamp(32px,5vw,56px);background:var(--cream);border-radius:var(--radius-lg);text-align:center;">
      <span class="script" style="font-size:clamp(24px,3.4vw,38px);display:block;margin-bottom:8px;">walk-in samo dođeš</span>
      <h3 class="display" style="font-size:clamp(24px,3.6vw,40px);margin:0 0 18px;line-height:1.1;">
        Probaj profesionalno feniranje
      </h3>
      <p class="lead" style="margin:0 auto 28px;max-width:480px;">
        Bez zakazivanja. Otvoreni smo svakim danom, samo dođi i prepusti se.
      </p>
      <div class="btn-row" style="justify-content:center;gap:12px;">
        <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener" class="btn btn-dark">
          Kako do nas <span class="arrow">→</span>
        </a>
        <a href="<?php echo esc_url(get_permalink(get_page_by_path('cenovnik'))); ?>" class="btn btn-outline">
          Cenovnik
        </a>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
