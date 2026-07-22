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
<!-- HUB: podstranice (stilovi u ovoj kategoriji) -->
<section class="section">
  <div class="wrap">
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

<!-- SLIKA + TEKST -->
<section class="section">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(28px,4vw,64px);align-items:start;" class="svc-single-grid">
      <?php if ($img): ?>
      <div style="aspect-ratio:4/5;border-radius:var(--radius-lg);overflow:hidden;position:sticky;top:100px;">
        <?php echo dry65_picture($img, $title, ['loading' => 'eager', 'style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
      </div>
      <?php endif; ?>

      <div<?php echo $img ? '' : ' style="grid-column:1 / -1;max-width:720px;"'; ?>>
        <?php if ($body): ?>
          <?php foreach (preg_split('/\n\s*\n/', trim($body)) as $para): if (trim($para) === '') continue; ?>
          <p class="lead" style="margin:0 0 18px;"><?php echo esc_html(trim($para)); ?></p>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="lead svc-content"><?php the_content(); ?></div>
        <?php endif; ?>

        <?php if ($points): ?>
        <div class="btn-row" style="margin-top:24px;gap:10px;flex-wrap:wrap;">
          <?php foreach ($points as $pt): ?>
          <span class="chip"><?php echo esc_html($pt); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="btn-row" style="margin-top:32px;gap:12px;flex-wrap:wrap;">
          <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener" class="btn btn-dark">Kako do nas <span class="arrow">→</span></a>
          <a href="<?php echo esc_url(get_permalink(get_page_by_path('cenovnik'))); ?>" class="btn btn-outline">Cenovnik</a>
        </div>
        <p class="muted" style="margin-top:18px;font-size:15px;">Bez zakazivanja — samo svrati. West 65, Novi Beograd.</p>
      </div>
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
