<?php
/* ============================================================
   Dry65 — /menu  (hub strana za Google Business „Menu" link)
   ------------------------------------------------------------
   Kompaktan pregled za posetioce sa Mapa: mini live status,
   mini cenovnik, mini galerija, CTA. Svaki blok vodi na punu
   stranu. Noindex (landing, ne SEO meta). Nije u glavnom meniju.
   ============================================================ */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
    add_rewrite_rule('^menu/?$', 'index.php?dry65_menu=1', 'top');
    if (get_option('dry65_menu_rw_v') !== '1') {
        flush_rewrite_rules(false);
        update_option('dry65_menu_rw_v', '1');
    }
});
add_filter('query_vars', function ($v) { $v[] = 'dry65_menu'; return $v; });

/* Noindex (landing za GBP, ne treba u pretrazi). */
add_action('wp_head', function () {
    if (get_query_var('dry65_menu')) echo '<meta name="robots" content="noindex, follow">' . "\n";
}, 1);

add_action('template_redirect', function () {
    if (!get_query_var('dry65_menu')) return;

    $biz     = function_exists('dry65_biz') ? dry65_biz() : [];
    $st      = function_exists('dry65_live_resolve') ? dry65_live_resolve() : ['tier' => 'free', 'headline' => '', 'wait_label' => ''];
    $lengths = function_exists('dry65_lengths') ? dry65_lengths() : [];
    $tpl     = get_template_directory_uri();
    $maps    = $biz['maps_url'] ?? '#';
    $phone   = $biz['phone'] ?? '';
    $phone_d = $biz['phone_display'] ?? $phone;
    $cen     = get_page_by_path('cenovnik');
    $amb     = get_page_by_path('ambijent');
    $cen_url = $cen ? get_permalink($cen) : home_url('/cenovnik/');
    $amb_url = $amb ? get_permalink($amb) : home_url('/ambijent/');
    $rest    = home_url('/wp-json/dry65/v1/live');

    get_header();
    ?>
    <style>
      .menu-hub { max-width:620px; margin:0 auto; }
      .menu-hub .eyebrow { color:var(--clay); }
      .menu-h { font-family:var(--font-display); font-weight:300; font-size:clamp(19px,2.6vw,24px); line-height:1.05; letter-spacing:0.01em; margin:0 0 10px; }
      .menu-more { display:inline-flex; align-items:center; gap:6px; margin-top:14px; color:var(--clay); font-weight:600; font-size:15px; }
      .menu-note { font-size:13.5px; color:var(--muted); margin:14px 0 0; line-height:1.5; }
      .menu-more-btn { border:1px solid var(--clay); border-radius:999px; padding:10px 18px; transition:background .2s, color .2s; }
      .menu-card:hover .menu-more-btn { background:var(--clay); color:#fff; }
      .menu-card:hover .menu-more-btn .arrow { color:#fff; }
      .menu-card { display:block; text-decoration:none; color:inherit; border:1px solid var(--sage-line,#e5e5e0); border-radius:var(--radius-lg); background:var(--paper,#fff); padding:clamp(18px,3vw,24px); transition:border-color .2s, transform .2s; }
      .menu-card:hover { border-color:rgba(17,28,29,0.28); transform:translateY(-2px); }
      .menu-card-head { display:flex; justify-content:space-between; align-items:center; gap:12px; }
      .menu-card-head h2 { font-family:var(--font-display); font-weight:300; font-size:clamp(22px,3.4vw,28px); margin:0; }
      /* Live */
      .menu-live { display:flex; align-items:center; gap:14px; }
      .menu-live-dot { width:13px; height:13px; border-radius:50%; flex-shrink:0; background:var(--dot,#84B052); box-shadow:0 0 0 0 var(--dot,#84B052); animation:menuLivePulse 2s ease-out infinite; }
      @keyframes menuLivePulse { 0%{box-shadow:0 0 0 0 color-mix(in srgb, var(--dot) 55%, transparent);} 70%{box-shadow:0 0 0 8px transparent;} 100%{box-shadow:0 0 0 0 transparent;} }
      @media (prefers-reduced-motion: reduce){ .menu-live-dot{animation:none;} }
      .menu-live[data-tier="free"]{--dot:#84B052;} .menu-live[data-tier="lime"]{--dot:#C9DB5B;}
      .menu-live[data-tier="yellow"]{--dot:#F6D63B;} .menu-live[data-tier="orange"]{--dot:#F0A73C;}
      .menu-live[data-tier="red"]{--dot:#E8472B;} .menu-live[data-tier="closed"],.menu-live[data-tier="full"]{--dot:#D0CFC7;}
      .menu-live-txt { display:flex; flex-direction:column; line-height:1.2; }
      .menu-live-txt strong { font-size:18px; }
      .menu-live-txt span { color:var(--muted); font-size:14px; }
      /* Cenovnik */
      .menu-price-list { margin-top:2px; }
      .menu-price-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; }
      .menu-price-row + .menu-price-row { border-top:1px solid var(--sage-line,#eee); }
      .menu-price-row .num { font-family:var(--font-num); font-size:22px; }
      .menu-price-row .u { font-size:12px; margin-left:3px; color:var(--muted); }
      /* Galerija */
      .menu-gallery { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-top:2px; }
      .menu-gallery .gi { aspect-ratio:3/4; border-radius:10px; overflow:hidden; background:var(--cream); }
      @media (max-width:520px){ .menu-gallery { grid-template-columns:repeat(4,1fr); } }
    </style>

    <main class="page-enter">
      <section class="bg-paper2 section-sm" style="padding-top:clamp(22px,3vw,36px);padding-bottom:clamp(18px,2.4vw,28px);">
        <div class="wrap menu-hub">
          <span class="script" style="font-size:clamp(26px,3.4vw,40px);display:block;">Dobrodošli</span>
          <h1 class="display caps" style="font-size:clamp(26px,4vw,42px);margin-top:4px;line-height:1.02;">Dry65 — West 65, Novi Beograd</h1>
          <p class="lead" style="margin-top:12px;font-size:17px;">Walk-in feniranje, bez zakazivanja. Sve na jednom mestu.</p>
        </div>
      </section>

      <section class="section-sm" style="padding-bottom:0;">
        <div class="wrap menu-hub" style="display:flex;flex-direction:column;gap:clamp(14px,2.4vw,20px);">

          <!-- MINI LIVE -->
          <div>
            <h2 class="menu-h">Koliko se čeka u ovom trenutku</h2>
            <a href="<?php echo esc_url(home_url('/live/')); ?>" class="menu-card menu-live" id="menuLive" data-tier="<?php echo esc_attr($st['tier']); ?>">
              <span class="menu-live-dot"></span>
              <span class="menu-live-txt">
                <strong id="menuLiveHead"><?php echo esc_html($st['headline'] ?: 'Status uživo'); ?></strong>
                <span id="menuLiveWait"><?php echo esc_html($st['wait_label'] ?? ''); ?></span>
              </span>
              <span class="arrow" style="margin-left:auto;color:var(--clay);">→</span>
            </a>
          </div>

          <!-- MINI CENOVNIK -->
          <div>
            <h2 class="menu-h">Cenovnik za feniranje</h2>
            <a href="<?php echo esc_url($cen_url); ?>" class="menu-card">
              <?php if ($lengths): ?>
              <div class="menu-price-list">
                <?php foreach ($lengths as $l): ?>
                <div class="menu-price-row"><span style="font-weight:500;"><?php echo esc_html($l['label']); ?></span><span class="num"><?php echo function_exists('dry65_rsd') ? dry65_rsd($l['price']) : (int) $l['price']; ?><span class="u">din</span></span></div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <p class="menu-note">Cene feniranja po dužini kose. U <strong>detaljnom cenovniku</strong> su i ostale usluge, tretmani i paketi.</p>
              <span class="menu-more menu-more-btn">Vidi ceo cenovnik <span class="arrow">→</span></span>
            </a>
          </div>

          <!-- MINI GALERIJA -->
          <div>
            <h2 class="menu-h">Ambijent salona</h2>
            <a href="<?php echo esc_url($amb_url); ?>" class="menu-card">
              <div class="menu-gallery">
                <?php foreach (['s02', 's03', 's04', 's05'] as $s): ?>
                <div class="gi"><?php echo dry65_picture('assets/salon/' . $s . '.webp', 'Dry65 salon, Novi Beograd', ['loading' => 'lazy', 'style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?></div>
                <?php endforeach; ?>
              </div>
              <span class="menu-more">Pogledaj salon <span class="arrow">→</span></span>
            </a>
          </div>

          <!-- CTA -->
          <div class="btn-row" style="gap:12px;flex-wrap:wrap;margin-top:4px;">
            <a href="<?php echo esc_url($maps); ?>" target="_blank" rel="noopener" class="btn btn-dark">Kako do nas <span class="arrow">→</span></a>
            <?php if ($phone): ?><a href="tel:<?php echo esc_attr($phone); ?>" class="btn btn-outline">Pozovi <?php echo esc_html($phone_d); ?></a><?php endif; ?>
          </div>

        </div>
      </section>
    </main>

    <script>
    (function(){
      var el=document.getElementById('menuLive'), h=document.getElementById('menuLiveHead'), w=document.getElementById('menuLiveWait');
      if(!el) return;
      var URL=<?php echo wp_json_encode($rest); ?>;
      function refresh(){
        fetch(URL+'?_='+Date.now(),{cache:'no-store'}).then(function(r){return r.json();}).then(function(d){
          if(!d) return;
          if(d.tier) el.setAttribute('data-tier', d.tier);
          if(d.status) h.textContent = d.status;
          if(d.closed) w.textContent = 'Zatvoreno';
          else if(d.remaining_min>0) w.textContent = '~'+d.remaining_min+' min do slobodnog';
          else w.textContent = 'Slobodno, samo dođite';
        }).catch(function(){});
      }
      refresh();
      setInterval(function(){ if(!document.hidden) refresh(); }, 20000);
    })();
    </script>
    <?php
    get_footer();
    exit;
});
