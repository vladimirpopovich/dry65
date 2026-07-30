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

<!-- HERO -->
<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
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
    <p class="lead" style="margin-top:22px;max-width:660px;"><?php echo esc_html($short); ?></p>
    <?php endif; ?>
  </div>
</section>

<?php if ($is_hub): ?>
<!-- HUB: opisni tekst kategorije -->
<?php $hub_body = $body ?: get_post_field('post_content', $id); ?>
<?php if (trim((string) $hub_body)): ?>
<section class="section" style="padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap" style="max-width:720px;">
    <?php foreach (preg_split('/\n\s*\n/', trim($hub_body)) as $para): if (trim($para) === '') continue; ?>
    <p class="lead" style="margin:0 0 18px;"><?php echo esc_html(trim($para)); ?></p>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- HUB: podstranice (stilovi u ovoj kategoriji) -->
<section class="section" style="padding-top:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <h2 class="display caps" style="font-size:clamp(22px,3vw,34px);margin:0 0 clamp(24px,3vw,36px);letter-spacing:0.01em;">Izaberi svoj stil</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:clamp(22px,3vw,38px);">
      <?php foreach ($kids as $c):
        $c_img = function_exists('dry65_service_image') ? dry65_service_image($c) : '';
      ?>
      <a href="<?php echo esc_url(get_permalink($c->ID)); ?>" class="svc-card reveal" style="display:block;text-decoration:none;color:inherit;">
        <?php if ($c_img): ?>
        <div style="aspect-ratio:4/3;border-radius:var(--radius-lg);overflow:hidden;margin-bottom:16px;">
          <?php echo dry65_picture($c_img, $c->post_title, ['loading' => 'lazy', 'style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
        </div>
        <?php endif; ?>
        <h2 class="display" style="font-size:clamp(21px,2.5vw,28px);"><?php echo esc_html($c->post_title); ?></h2>
        <?php if ($c->post_excerpt): ?><p class="lead" style="margin-top:8px;font-size:16px;"><?php echo esc_html($c->post_excerpt); ?></p><?php endif; ?>
        <span style="display:inline-flex;align-items:center;gap:6px;margin-top:12px;color:var(--clay);font-weight:600;">Saznaj više <span class="arrow">→</span></span>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="btn-row" style="margin-top:clamp(32px,5vw,52px);gap:12px;flex-wrap:wrap;">
      <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener" class="btn btn-dark">Kako do nas <span class="arrow">→</span></a>
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('cenovnik'))); ?>" class="btn btn-outline">Cenovnik</a>
    </div>
  </div>
</section>
<?php else: ?>

<style>
  .svc-gallery { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:clamp(10px,1.4vw,16px); }
  .svc-gallery-item { aspect-ratio:3/4; border-radius:var(--radius-lg); overflow:hidden; background:var(--cream); }
  .svc-gallery-item img { transition:transform .6s var(--ease); }
  .svc-gallery-item:hover img { transform:scale(1.04); }
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

<!-- GALERIJA (na vrhu) -->
<?php $gallery = function_exists('dry65_service_gallery') ? dry65_service_gallery($id) : []; ?>
<?php if ($gallery): ?>
<section class="section-sm" style="padding-top:clamp(18px,2.6vw,32px);padding-bottom:0;">
  <div class="wrap">
    <div class="svc-gallery">
      <?php foreach ($gallery as $g): ?>
      <div class="svc-gallery-item"><?php echo dry65_picture($g, $title, ['loading' => 'lazy', 'style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ARTICLE -->
<section class="section">
  <div class="wrap">
    <div class="svc-article">
      <?php
      // Cenovnik blok (po dužini kose) — ide odmah posle uvodnog teksta
      $lengths = function_exists('dry65_lengths') ? dry65_lengths() : [];
      $price_title = preg_replace('/^Feniranje\b/u', 'Cenovnik feniranja', $title);
      if ($price_title === $title) $price_title = 'Cenovnik';
      ob_start(); ?>
      <div class="svc-price">
        <div class="svc-price-top">
          <div>
            <h2><?php echo esc_html($price_title); ?></h2>
            <p class="muted" style="margin:4px 0 0;font-size:15px;">Bez zakazivanja</p>
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
          foreach (preg_split('/\n\s*\n/', trim($body)) as $para): if (trim($para) === '') continue; ?>
          <p><?php echo esc_html(trim($para)); ?></p>
          <?php endforeach;
          echo $price_block;
          echo $live_block;
      else:
          $content_html = apply_filters('the_content', get_the_content());
          $parts = preg_split('/(?=<h2)/i', $content_html); // deli na svakom H2
          $intro = array_shift($parts);          // pre prvog H2 (uvod)
          $first = array_shift($parts);          // prva H2 sekcija ispod cenovnika
          echo $intro;
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
