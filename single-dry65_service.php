<?php
/* Single template za dry65_service — landing strana za pojedinačni stil feniranja. */
get_header();
$biz = dry65_biz();
$usluge_page = get_page_by_path('usluge');
$usluge_url  = $usluge_page ? get_permalink($usluge_page) : home_url('/usluge/');

while (have_posts()): the_post();
$id     = get_the_ID();
$title  = get_the_title();
$kicker = dry65_get_field('kicker', $id) ?: '';
$short  = dry65_get_field('short', $id) ?: get_the_excerpt();
$body   = dry65_get_field('body', $id) ?: '';
$img    = dry65_get_field('image', $id);
if (!$img && has_post_thumbnail($id)) $img = get_the_post_thumbnail_url($id, 'full');
$hero_img = $img ?: (function_exists('dry65_service_image') ? dry65_service_image(get_post($id)) : '');
$points = array_values(array_filter([
    dry65_get_field('point_1', $id),
    dry65_get_field('point_2', $id),
    dry65_get_field('point_3', $id),
]));

// Hub (kategorija sa decom) vs leaf (pojedinačni stil)
$kids   = get_posts(['post_type' => 'dry65_service', 'post_parent' => $id, 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC']);
$is_hub = !empty($kids);
$parent = (int) get_post_field('post_parent', $id);
?>

<main class="page-enter">

<style>
  /* Hero: tekst levo + jedna velika fotka desno (mirror oblik) */
  .svc-hero { display:grid; grid-template-columns:1.05fr 0.95fr; gap:clamp(28px,5vw,72px); align-items:center; }
  .svc-hero-img { aspect-ratio:4/5; border-radius:1000px; overflow:hidden; background:var(--cream); }
  .svc-hero-img img { width:100%; height:100%; object-fit:cover; display:block; }
  /* Hub nema hero fotku — tekst preko cele sirine */
  .svc-hero-solo { grid-template-columns:1fr; }
  @media (max-width:820px){
    .svc-hero { grid-template-columns:1fr; gap:24px; }
    .svc-hero-img { max-width:320px; margin:4px auto 0; }
  }
  /* Hub: opisni tekst iste dimenzije kao članak + linkovi kao na /usluge */
  .svc-hub-body { max-width:720px; }
  .svc-hub-body p { margin:0 0 18px; font-family:var(--font-sans); font-size:17px; line-height:1.72; color:var(--ink-soft); }
  .svc-hub-body h2 { font-family:var(--font-display); font-weight:300; font-size:clamp(24px,3.2vw,34px); line-height:1.08; letter-spacing:0.01em; margin:38px 0 12px; }
  .svc-hub-body h3 { font-family:var(--font-display); font-weight:400; font-size:clamp(19px,2.2vw,25px); line-height:1.15; margin:26px 0 8px; color:var(--oxblood); }
  .svc-hub-body > *:first-child { margin-top:0; }
  .svc-hub-links { list-style:none; padding:0; margin:0; max-width:720px; display:grid; grid-template-columns:repeat(2,1fr); gap:0 28px; }
  .svc-hub-link { display:flex; align-items:center; gap:10px; padding:12px 0; color:var(--ink); text-decoration:none;
    font-family:var(--font-sans); font-size:17px; font-weight:500; border-bottom:1px solid var(--cream-deep,#ece7df); transition:color .2s, gap .2s; }
  .svc-hub-link:hover { color:var(--clay); gap:14px; }
  .svc-hub-link .arr { color:var(--clay); font-size:15px; }
  @media (max-width:600px){ .svc-hub-links { grid-template-columns:1fr; } }
</style>
<!-- HERO -->
<section class="bg-paper2 section" style="padding-top:clamp(24px,3vw,44px);padding-bottom:clamp(28px,4vw,56px);">
  <div class="wrap">
    <div class="svc-hero<?php echo $is_hub ? ' svc-hero-solo' : ''; ?>">
      <div>
        <p class="mono" style="margin:0 0 10px;font-size:13px;color:var(--muted);">
          <a href="<?php echo esc_url($usluge_url); ?>" style="color:var(--clay);text-decoration:none;">Usluge</a>
          <?php if ($parent): ?> &nbsp;/&nbsp; <a href="<?php echo esc_url(get_permalink($parent)); ?>" style="color:var(--clay);text-decoration:none;"><?php echo esc_html(get_the_title($parent)); ?></a><?php endif; ?>
          &nbsp;/&nbsp; <?php echo esc_html($title); ?>
        </p>
        <?php if ($kicker): ?><span class="mono" style="color:var(--clay);"><?php echo esc_html($kicker); ?></span><?php endif; ?>
        <h1 class="display caps" style="font-size:clamp(30px,4.6vw,56px);margin-top:8px;max-width:20ch;line-height:1.02;letter-spacing:0.01em;">
          <?php echo esc_html($title); ?>
        </h1>
        <?php if ($short): ?>
        <p class="lead" style="margin-top:22px;max-width:560px;"><?php echo esc_html($short); ?></p>
        <?php endif; ?>
      </div>
      <?php if ($hero_img && !$is_hub): ?>
      <div class="svc-hero-img">
        <?php echo dry65_picture($hero_img, $title, ['loading' => 'eager', 'style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($is_hub): ?>
<!-- HUB: linkovi ka stilovima (odmah ispod hero-a) + opisni tekst -->
<?php $hub_html = trim((string) get_post_field('post_content', $id)); ?>
<section class="section">
  <div class="wrap">
    <?php if ($kids): ?>
    <h2 class="display" style="font-size:clamp(22px,3vw,32px);margin:0 0 14px;">Izaberi svoj stil</h2>
    <ul class="svc-hub-links">
      <?php foreach ($kids as $c): ?>
      <li><a class="svc-hub-link" href="<?php echo esc_url(get_permalink($c->ID)); ?>"><span class="arr">→</span> <?php echo esc_html($c->post_title); ?></a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($hub_html): ?>
    <div class="svc-hub-body" style="margin-top:clamp(34px,5vw,56px);">
      <?php echo apply_filters('the_content', $hub_html); ?>
    </div>
    <?php endif; ?>

    <div class="btn-row" style="margin-top:clamp(28px,4vw,44px);gap:12px;flex-wrap:wrap;">
      <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener" class="btn btn-dark">Kako do nas <span class="arrow">→</span></a>
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('cenovnik'))); ?>" class="btn btn-outline">Cenovnik</a>
    </div>
  </div>
</section>
<?php else: ?>

<style>
  /* Galerija — horizontalna traka (curi do ivice ekrana) */
  /* Strip je u .wrap (levo poravnat sa tekstom); curi desno negativnom marginom. Scrollbar sakriven. */
  .svc-gallery-strip { display:flex; gap:clamp(10px,1.4vw,16px); overflow-x:auto; overscroll-behavior-x:contain; scroll-snap-type:x proximity; -webkit-overflow-scrolling:touch; margin-right:calc(-1 * var(--gutter)); padding:2px 0 4px; scrollbar-width:none; -ms-overflow-style:none; }
  .svc-gallery-strip::-webkit-scrollbar { display:none; }
  .svc-gallery-item { flex:0 0 auto; width:clamp(180px,44vw,240px); aspect-ratio:3/4; border-radius:var(--radius-lg); overflow:hidden; scroll-snap-align:start; padding:0; border:0; margin:0; background:var(--cream); cursor:pointer; display:block; }
  .svc-gallery-item img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .5s var(--ease); }
  .svc-gallery-item:hover img { transform:scale(1.05); }
  /* Lightbox */
  .svc-lb { position:fixed; inset:0; z-index:9999; background:rgba(17,28,29,0.94); display:none; align-items:center; justify-content:center; }
  .svc-lb.open { display:flex; }
  .svc-lb img { max-width:92vw; max-height:88vh; object-fit:contain; border-radius:8px; box-shadow:0 30px 80px -20px rgba(0,0,0,0.6); }
  .svc-lb button { position:absolute; background:rgba(255,255,255,0.12); color:#fff; border:0; cursor:pointer; border-radius:999px; display:flex; align-items:center; justify-content:center; line-height:1; transition:background .2s; }
  .svc-lb button:hover { background:rgba(255,255,255,0.26); }
  .svc-lb-close { top:18px; right:18px; width:44px; height:44px; font-size:26px; }
  .svc-lb-prev, .svc-lb-next { top:50%; transform:translateY(-50%); width:52px; height:52px; font-size:32px; }
  .svc-lb-prev { left:18px; } .svc-lb-next { right:18px; }
  .svc-lb-count { position:absolute; bottom:22px; left:50%; transform:translateX(-50%); color:#fff; font-family:var(--font-sans); font-size:14px; opacity:.8; background:none; }
  @media (max-width:560px){ .svc-lb-prev,.svc-lb-next{width:44px;height:44px;font-size:26px;} }
  .svc-article { max-width:720px; margin:0 auto; }
  .svc-article > p { margin:0 0 18px; font-family:var(--font-sans); font-size:17px; line-height:1.72; color:var(--ink-soft); }
  .svc-article > h2 { font-family:var(--font-display); font-weight:300; font-size:clamp(24px,3.2vw,34px); line-height:1.08; letter-spacing:0.01em; margin:44px 0 14px; }
  .svc-article > h3 { font-family:var(--font-display); font-weight:400; font-size:clamp(19px,2.2vw,25px); line-height:1.15; margin:30px 0 8px; color:var(--oxblood); }
  .svc-article > *:first-child { margin-top:0; }
  /* Cenovnik blok unutar članka */
  .svc-price { border:1px solid var(--sage-line,#e5e5e0); border-radius:var(--radius-lg); background:var(--cream); padding:clamp(20px,3vw,30px); margin:38px 0; }
  .svc-price-top { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; }
  .svc-price-top h2 { font-family:var(--font-display); font-weight:300; font-size:clamp(23px,2.8vw,32px); line-height:1.05; margin:0; }
  .svc-price-list { margin-top:20px; background:var(--paper,#fff); border-radius:12px; border:1px solid var(--sage-line,#e5e5e0); overflow:hidden; }
  .svc-price-row { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; }
  .svc-price-row + .svc-price-row { border-top:1px solid var(--sage-line,#e5e5e0); }
  /* dry65 live poziv */
  .svc-live { display:flex; justify-content:space-between; align-items:center; gap:20px; flex-wrap:wrap; border:1px solid var(--sage-line,#e5e5e0); border-radius:var(--radius-lg); background:var(--paper,#fff); padding:clamp(20px,3vw,28px); margin:38px 0; }
  .svc-live-h { font-family:var(--font-display); font-weight:300; font-size:clamp(22px,2.6vw,28px); line-height:1.05; }
  .svc-live p { font-family:var(--font-sans); font-size:16px; color:var(--ink-soft); margin:8px 0 0; max-width:44ch; }
  .svc-live-link { display:inline-flex; align-items:center; gap:9px; white-space:nowrap; font-family:var(--font-sans); font-weight:600; font-size:15px; color:var(--ink); text-decoration:none; border:1px solid rgba(17,28,29,0.18); border-radius:999px; padding:11px 20px; transition:border-color .2s, background .2s; }
  .svc-live-link:hover { border-color:rgba(17,28,29,0.45); }
  .svc-live-dot { width:9px; height:9px; border-radius:50%; background:#2f9e44; flex-shrink:0; box-shadow:0 0 0 0 rgba(47,158,68,0.5); animation:svcLivePulse 2s ease-out infinite; }
  @keyframes svcLivePulse { 0%{box-shadow:0 0 0 0 rgba(47,158,68,0.5);} 70%{box-shadow:0 0 0 6px rgba(47,158,68,0);} 100%{box-shadow:0 0 0 0 rgba(47,158,68,0);} }
  @media (prefers-reduced-motion: reduce){ .svc-live-dot{animation:none;} }
</style>

<!-- GALERIJA (na vrhu) — PRIVREMENO ISKLJUČENA (bez fotki za sad; vrati na "if ($gallery)") -->
<?php $gallery = function_exists('dry65_service_gallery') ? dry65_service_gallery($id) : []; ?>
<?php if (false && $gallery): ?>
<section class="section-sm" style="padding-top:clamp(18px,2.6vw,32px);padding-bottom:0;">
  <div class="wrap">
    <div class="svc-gallery-strip">
      <?php foreach ($gallery as $gi => $g): $alt = $g['alt'] !== '' ? $g['alt'] : ($title . ' — fotografija ' . ($gi + 1)); ?>
      <button type="button" class="svc-gallery-item" data-idx="<?php echo (int) $gi; ?>" aria-label="Uvećaj: <?php echo esc_attr($alt); ?>">
        <img src="<?php echo esc_url($g['url']); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async">
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="svc-lb" id="svcLb" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Galerija">
  <button class="svc-lb-close" id="svcLbClose" aria-label="Zatvori">&times;</button>
  <button class="svc-lb-prev" id="svcLbPrev" aria-label="Prethodna">&lsaquo;</button>
  <img id="svcLbImg" src="" alt="">
  <button class="svc-lb-next" id="svcLbNext" aria-label="Sledeća">&rsaquo;</button>
  <div class="svc-lb-count" id="svcLbCount"></div>
</div>
<script>
(function(){
  var imgs = <?php echo wp_json_encode(array_values($gallery)); ?>;
  if(!imgs.length) return;
  var lb=document.getElementById('svcLb'), im=document.getElementById('svcLbImg'), ct=document.getElementById('svcLbCount'), idx=0;
  function show(i){ idx=(i+imgs.length)%imgs.length; im.src=imgs[idx].url; im.alt=imgs[idx].alt||''; ct.textContent=(idx+1)+' / '+imgs.length; }
  function open(i){ show(i); lb.classList.add('open'); lb.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
  function close(){ lb.classList.remove('open'); lb.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
  document.querySelectorAll('.svc-gallery-item').forEach(function(b){ b.addEventListener('click',function(){ open(parseInt(b.dataset.idx,10)||0); }); });
  document.getElementById('svcLbClose').addEventListener('click',close);
  document.getElementById('svcLbPrev').addEventListener('click',function(e){ e.stopPropagation(); show(idx-1); });
  document.getElementById('svcLbNext').addEventListener('click',function(e){ e.stopPropagation(); show(idx+1); });
  lb.addEventListener('click',function(e){ if(e.target===lb) close(); });
  document.addEventListener('keydown',function(e){ if(!lb.classList.contains('open')) return; if(e.key==='Escape') close(); else if(e.key==='ArrowLeft') show(idx-1); else if(e.key==='ArrowRight') show(idx+1); });
  var sx=0; lb.addEventListener('touchstart',function(e){ sx=e.touches[0].clientX; },{passive:true});
  lb.addEventListener('touchend',function(e){ var dx=e.changedTouches[0].clientX-sx; if(Math.abs(dx)>40) show(idx+(dx<0?1:-1)); });
})();
</script>
<?php endif; ?>

<!-- TRUST: Google agregat + bogata recenzija (razlicita po strani, cela) -->
<?php
$g_meta   = function_exists('dry65_google_meta') ? dry65_google_meta() : ['rating' => 0, 'total' => 0];
$g_rating = $g_meta['rating'] ?: 5.0;
$g_total  = (int) ($g_meta['total'] ?? 0);
$g_rating_disp = number_format($g_rating, 1, ',', '');
$g_quote = null;
if (function_exists('dry65_reviews_smart')) {
    $g_pool = array_values(array_filter((array) dry65_reviews_smart(), function ($r) {
        return (int) ($r['rating'] ?? 5) >= 5 && trim($r['text'] ?? '') !== '';
    }));

    // Rucna dodela recenzije po strani (ime recenzenta). Lako se dopunjuje.
    $g_manual = [
        'feniranje-na-ravno'   => 'Ana Maria Constanca Delic',
        'feniranje-na-talase'  => 'Marija Culibrk',
        'feniranje-na-lokne'   => 'Marija Anastasijevic',
        'feniranje-na-volumen' => 'Andjela Jednak',
    ];
    $g_slug = get_post_field('post_name', $id);
    if (!empty($g_manual[$g_slug])) {
        $g_norm = fn($s) => strtr(mb_strtolower(trim((string) $s)), ['č'=>'c','ć'=>'c','đ'=>'dj','š'=>'s','ž'=>'z']);
        $g_t = $g_norm($g_manual[$g_slug]);
        foreach ($g_pool as $g_rv) {
            $g_rn = $g_norm($g_rv['name'] ?? '');
            $g_ok = ($g_rn === $g_t);
            if (!$g_ok) { $g_ok = true; foreach (explode(' ', $g_t) as $g_w) { if ($g_w !== '' && mb_strpos($g_rn, $g_w) === false) { $g_ok = false; break; } } }
            if ($g_ok) { $g_quote = $g_rv; break; }
        }
    }

    // Fallback: auto po poziciji medju bracom (najbogatije prve)
    if (!$g_quote && $g_pool) {
        usort($g_pool, fn($a, $b) => mb_strlen($b['text']) <=> mb_strlen($a['text']));
        $g_sib = get_posts(['post_type' => 'dry65_service', 'post_parent' => $parent, 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC', 'fields' => 'ids']);
        $g_pos = array_search($id, $g_sib, true);
        if ($g_pos === false) $g_pos = 0;
        $g_quote = $g_pool[$g_pos % count($g_pool)];
    }
}
?>
<?php
// Sastavi review blok u promenljivu — ubacuje se UNUTAR clanka (ispod uvodnih pasusa), ne skroz gore.
$g_review_html = '';
if ($g_total > 0 || $g_quote):
    ob_start(); ?>
    <div class="svc-trust">
      <a class="svc-trust-rate" href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener">
        <span class="svc-trust-stars" aria-hidden="true">★★★★★</span>
        <span class="svc-trust-num"><?php echo esc_html($g_rating_disp); ?></span>
        <?php if ($g_total > 0): ?><span class="svc-trust-total">· <?php echo esc_html($g_total); ?> recenzija na Google-u</span><?php endif; ?>
      </a>
      <?php if ($g_quote && !empty($g_quote['text'])): ?>
      <blockquote class="svc-trust-card">
        <div class="svc-trust-stars-top" aria-hidden="true">★★★★★</div>
        <div class="svc-trust-body">
          <?php foreach (preg_split('/\n{2,}/', trim($g_quote['text'])) as $g_para):
              $g_para = trim(preg_replace('/\s*\n\s*/u', ' ', $g_para));
              if ($g_para === '') continue; ?>
          <p><?php echo esc_html($g_para); ?></p>
          <?php endforeach; ?>
        </div>
        <footer class="svc-trust-foot">
          <?php if (!empty($g_quote['photo'])): ?>
          <img class="svc-trust-avatar" src="<?php echo esc_url($g_quote['photo']); ?>" alt="<?php echo esc_attr($g_quote['name'] ?? ''); ?>" width="40" height="40" loading="lazy" referrerpolicy="no-referrer">
          <?php endif; ?>
          <div class="svc-trust-meta">
            <?php if (!empty($g_quote['name'])): ?><span class="svc-trust-name"><?php echo esc_html($g_quote['name']); ?></span><?php endif; ?>
            <?php if (!empty($g_quote['when'])): ?><span class="svc-trust-when"><?php echo esc_html($g_quote['when']); ?></span><?php endif; ?>
          </div>
          <svg class="svc-trust-g" viewBox="0 0 48 48" aria-label="Google">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
          </svg>
        </footer>
      </blockquote>
      <?php endif; ?>
    </div>
<style>
  .svc-trust { max-width:720px; margin:clamp(30px,4vw,46px) auto; display:flex; flex-direction:column; gap:16px; }
  .svc-trust-rate { display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:var(--ink); font-family:var(--font-sans); }
  .svc-trust-stars { color:#f5a623; font-size:17px; letter-spacing:1px; }
  .svc-trust-num { font-weight:700; font-size:17px; }
  .svc-trust-total { color:var(--muted); font-size:15px; }
  .svc-trust-rate:hover .svc-trust-total { color:var(--clay); }
  .svc-trust-card { margin:0; background:#fff; border:1px solid var(--cream-deep,#ece7df); border-radius:16px; padding:24px 26px; box-shadow:0 16px 44px -26px rgba(17,28,29,0.3); }
  .svc-trust-stars-top { color:#f5a623; font-size:19px; letter-spacing:2px; margin-bottom:14px; }
  .svc-trust-body p { margin:0 0 12px; font-family:var(--font-sans); font-size:16px; line-height:1.62; color:var(--ink); }
  .svc-trust-body p:last-child { margin-bottom:0; }
  .svc-trust-foot { display:flex; align-items:center; gap:12px; margin-top:20px; padding-top:18px; border-top:1px solid var(--cream-deep,#ece7df); }
  .svc-trust-avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; flex-shrink:0; }
  .svc-trust-meta { display:flex; flex-direction:column; line-height:1.3; margin-right:auto; }
  .svc-trust-name { font-weight:600; color:var(--ink); font-size:15px; }
  .svc-trust-when { color:var(--muted); font-size:13px; }
  .svc-trust-g { width:20px; height:20px; flex-shrink:0; }
</style>
    <?php $g_review_html = ob_get_clean();
endif;
?>

<!-- ARTICLE -->
<section class="section">
  <div class="wrap">
    <div class="svc-article">
      <?php
      // Cenovnik blok (po dužini kose) — ide odmah posle uvodnog teksta
      $lengths = function_exists('dry65_lengths') ? dry65_lengths() : [];
      $price_title = $title ? $title . ' cena' : 'Cena';
      ob_start(); ?>
      <div class="svc-price">
        <div class="svc-price-top">
          <div>
            <h2><?php echo esc_html($price_title); ?></h2>
          </div>
          <a href="<?php echo esc_url(get_permalink(get_page_by_path('cenovnik'))); ?>" class="btn btn-dark" style="white-space:nowrap;">Ceo cenovnik <span class="arrow">→</span></a>
        </div>
        <?php if ($lengths): ?>
        <div class="svc-price-list">
          <?php foreach ($lengths as $li => $l): ?>
          <div class="svc-price-row">
            <span class="row" style="gap:12px;"><span class="mono" style="color:var(--clay);"><?php echo str_pad($li + 1, 2, '0', STR_PAD_LEFT); ?></span><span style="font-weight:500;font-size:17px;"><?php echo esc_html($l['label']); ?></span></span>
            <span class="display num" style="font-size:26px;"><?php echo dry65_rsd($l['price']); ?><span class="u" style="font-size:13px;margin-left:3px;">din</span></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php $price_block = ob_get_clean(); ob_start(); ?>
      <div class="svc-live">
        <div>
          <div class="svc-live-h">Danas dolazite?</div>
          <p>Pogledajte trenutno stanje u salonu i procenu čekanja uživo.</p>
        </div>
        <a href="<?php echo esc_url(home_url('/live/')); ?>" class="svc-live-link"><span class="svc-live-dot" aria-hidden="true"></span> dry65 live <span class="arrow">→</span></a>
      </div>
      <?php $live_block = ob_get_clean();

      if ($body):
          $g_pc = 0;
          foreach (preg_split('/\n\s*\n/', trim($body)) as $para):
              if (trim($para) === '') continue; ?>
          <p><?php echo esc_html(trim($para)); ?></p>
          <?php $g_pc++; if ($g_pc === 3) echo $g_review_html;
          endforeach;
          if ($g_pc < 3) echo $g_review_html;     // ako ima manje od 3 pasusa
          echo $price_block;
          echo $live_block;
      else:
          $content_html = apply_filters('the_content', get_the_content());
          $parts = preg_split('/(?=<h2)/i', $content_html); // deli na svakom H2
          $intro = array_shift($parts);          // pre prvog H2 (uvod)
          $first = array_shift($parts);          // prva H2 sekcija ispod cenovnika
          echo $intro;
          echo $g_review_html;                    // recenzije ispod uvodnih pasusa (ne skroz gore)
          echo $price_block;
          if ($first !== null) echo $first;
          echo $live_block;                       // box posle te sekcije
          echo implode('', $parts);               // ostatak članka
      endif;
      ?>

      <?php if ($points): ?>
      <div class="btn-row" style="margin-top:24px;gap:10px;flex-wrap:wrap;">
        <?php foreach ($points as $pt): ?><span class="chip"><?php echo esc_html($pt); ?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="btn-row" style="margin-top:36px;gap:12px;flex-wrap:wrap;">
        <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener" class="btn btn-dark">Kako do nas <span class="arrow">→</span></a>
        <a href="<?php echo esc_url(get_permalink(get_page_by_path('cenovnik'))); ?>" class="btn btn-outline">Cenovnik</a>
      </div>
      <p class="muted" style="margin-top:18px;font-size:15px;">Bez zakazivanja, samo svrati. West 65, Novi Beograd.</p>
    </div>
  </div>
</section>

<!-- Interno povezivanje: ostali stilovi iz iste kategorije + nazad na hub -->
<?php
$siblings = $parent ? get_posts([
    'post_type'      => 'dry65_service',
    'post_parent'    => $parent,
    'post__not_in'   => [$id],
    'posts_per_page' => -1,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]) : [];
if ($siblings): ?>
<section class="section-sm bg-paper2">
  <div class="wrap">
    <h2 class="display" style="font-size:clamp(22px,3vw,32px);margin:0 0 14px;">Ostali stilovi feniranja</h2>
    <ul class="svc-hub-links">
      <?php foreach ($siblings as $s): ?>
      <li><a class="svc-hub-link" href="<?php echo esc_url(get_permalink($s->ID)); ?>"><span class="arr">→</span> <?php echo esc_html($s->post_title); ?></a></li>
      <?php endforeach; ?>
    </ul>
    <a href="<?php echo esc_url(get_permalink($parent)); ?>" style="display:inline-flex;align-items:center;gap:8px;margin-top:20px;color:var(--clay);font-weight:600;text-decoration:none;">Sve o: <?php echo esc_html(get_the_title($parent)); ?> <span class="arrow">→</span></a>
  </div>
</section>
<?php endif; ?>
<?php endif; ?>

<!-- FAQ (reusable, kategorija 'usluge') -->
<?php if (function_exists('dry65_render_faq_section')) dry65_render_faq_section('usluge', 'Česta pitanja', 'Najčešća pitanja o feniranju i stilizovanju u Dry65.'); ?>

<!-- Schema: Service -->
<script type="application/ld+json"><?php echo wp_json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Service',
    'name'     => $title,
    'serviceType' => $title,
    'description' => $short,
    'areaServed'  => ['@type' => 'City', 'name' => 'Novi Beograd'],
    'provider' => [
        '@type' => 'HairSalon',
        'name'  => 'Dry65',
        'url'   => home_url('/'),
        '@id'   => home_url('/#business'),
    ],
    'url' => get_permalink($id),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>

</main>

<?php endwhile; ?>
<?php get_footer(); ?>
