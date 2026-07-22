<?php
/*
Template Name: Usluge
*/
get_header();
$tree = dry65_service_tree();
$tpl  = get_template_directory_uri();
?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">Usluge</span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:28ch;line-height:1.0;letter-spacing:0.01em;">
      Feniranje, lokne i stilizovanje kose
    </h1>
    <p class="lead" style="margin-top:26px;max-width:680px;">
      Pranje i feniranje, stilizovanje (talasi, lokne, ravno, volumen, na unutra, na spolja) i dubinska nega kose. Sve bez zakazivanja, u srcu Novog Beograda.
    </p>
    <p class="lead" style="margin-top:14px;max-width:680px;font-size:15px;color:var(--muted);">
      Koristimo isključivo <a href="https://www.schwarzkopf-professional.com/" target="_blank" rel="noopener" style="color:var(--clay);text-decoration:underline;text-underline-offset:3px;">Schwarzkopf Professional</a> proizvode, vodeću svetsku marku za profesionalne salone.
    </p>
  </div>
</section>

<?php foreach ($tree as $ci => $cat): ?>
<section class="section<?php echo $ci % 2 ? ' bg-paper2' : ''; ?>">
  <div class="wrap">
    <div style="max-width:760px;margin-bottom:clamp(28px,4vw,48px);">
      <h2 class="display" style="font-size:clamp(28px,4vw,46px);line-height:1.02;">
        <?php if (empty($cat['children'])): ?><a href="<?php echo esc_url($cat['url']); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($cat['title']); ?></a><?php else: ?><?php echo esc_html($cat['title']); ?><?php endif; ?>
      </h2>
      <?php if ($cat['intro']): ?><p class="lead" style="margin-top:16px;"><?php echo esc_html($cat['intro']); ?></p><?php endif; ?>
    </div>

    <?php if (!empty($cat['children'])): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:clamp(22px,3vw,38px);">
      <?php foreach ($cat['children'] as $c): ?>
      <a href="<?php echo esc_url($c['url']); ?>" class="svc-card reveal" style="display:block;text-decoration:none;color:inherit;">
        <div style="aspect-ratio:4/3;border-radius:var(--radius-lg);overflow:hidden;margin-bottom:16px;">
          <?php echo dry65_picture($c['img'], $c['title'], ['loading' => 'lazy', 'style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
        </div>
        <h3 class="display" style="font-size:clamp(21px,2.5vw,28px);"><?php echo esc_html($c['title']); ?></h3>
        <?php if ($c['short']): ?><p class="lead" style="margin-top:8px;font-size:16px;"><?php echo esc_html($c['short']); ?></p><?php endif; ?>
        <span style="display:inline-flex;align-items:center;gap:6px;margin-top:12px;color:var(--clay);font-weight:600;">Saznaj više <span class="arrow">→</span></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <a href="<?php echo esc_url($cat['url']); ?>" class="btn btn-outline">Saznaj više <span class="arrow">→</span></a>
    <?php endif; ?>
  </div>
</section>
<?php endforeach; ?>

<section class="section-sm bg-cream">
  <div class="wrap center">
    <h2 class="display" style="font-size:clamp(30px,4.5vw,52px);">Spremna za savršenu kosu?</h2>
    <div class="btn-row" style="justify-content:center;margin-top:26px;">
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('cenovnik'))); ?>" class="btn btn-dark">
        Pogledaj cenovnik <span class="arrow">→</span>
      </a>
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('paketi'))); ?>" class="btn btn-outline">
        Mesečni paketi
      </a>
    </div>
  </div>
</section>

</main>

<?php get_footer(); ?>
