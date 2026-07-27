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
      Feniranje, stilizovanje i nega kose
    </h1>
  </div>
</section>

<style>
  /* /usluge — editorial: tekst levo, arch/oval kartice desno */
  .uslart { display:grid; grid-template-columns:minmax(240px,320px) 1fr; gap:clamp(32px,5vw,76px); align-items:start; }
  .uslart-txt h2 { font-size:clamp(30px,3.6vw,48px); line-height:1.02; letter-spacing:0.01em; }
  .uslart-txt .lead { margin-top:18px; }
  .uslart-more { display:inline-flex; align-items:center; gap:6px; margin-top:20px; color:var(--clay); font-weight:600; text-decoration:none; }
  .uslart-more:hover { text-decoration:underline; text-underline-offset:3px; }
  .uslart-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:clamp(18px,2.4vw,34px); }
  .arch { display:block; text-decoration:none; color:inherit; }
  .arch-frame { aspect-ratio:4/5; border-radius:1000px; overflow:hidden; background:var(--cream); }
  .arch-frame img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .55s var(--ease); }
  .arch:hover .arch-frame img { transform:scale(1.045); }
  .arch h3 { font-size:clamp(18px,1.9vw,24px); margin-top:16px; line-height:1.1; }
  .arch .more { display:inline-block; margin-top:6px; color:var(--clay); font-weight:600; text-decoration:underline; text-underline-offset:3px; font-size:14px; }
  @media (max-width:900px){
    .uslart { grid-template-columns:1fr; gap:26px; }
    .uslart-cards { grid-template-columns:repeat(3,1fr); gap:16px; }
  }
  @media (max-width:600px){
    .uslart-cards { grid-template-columns:repeat(2,1fr); }
  }
</style>

<?php foreach ($tree as $ci => $cat): $kids = $cat['children']; ?>
<section class="section<?php echo $ci % 2 ? ' bg-paper2' : ''; ?>">
  <div class="wrap">
    <div class="uslart">
      <div class="uslart-txt">
        <h2 class="display">
          <a href="<?php echo esc_url($cat['url']); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($cat['title']); ?></a>
        </h2>
        <?php if ($cat['intro']): ?><p class="lead"><?php echo esc_html($cat['intro']); ?></p><?php endif; ?>
        <a href="<?php echo esc_url($cat['url']); ?>" class="uslart-more">Saznaj više <span class="arrow">→</span></a>
      </div>

      <?php if ($kids): ?>
      <div class="uslart-cards">
        <?php foreach ($kids as $c): ?>
        <a href="<?php echo esc_url($c['url']); ?>" class="arch reveal">
          <div class="arch-frame">
            <?php echo dry65_picture($c['img'], $c['title'], ['loading' => 'lazy', 'style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
          </div>
          <h3 class="display"><?php echo esc_html($c['title']); ?></h3>
          <span class="more">Saznaj više</span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <a href="<?php echo esc_url($cat['url']); ?>" class="arch reveal" style="max-width:340px;">
        <div class="arch-frame">
          <?php echo dry65_picture($cat['img'], $cat['title'], ['loading' => 'lazy', 'style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
        </div>
        <span class="more">Saznaj više</span>
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<!-- Schwarzkopf band (tekst + slika) -->
<section class="section">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(28px,4vw,64px);align-items:center;" class="svc-single-grid">
      <div style="aspect-ratio:4/3;border-radius:var(--radius-lg);overflow:hidden;">
        <?php echo dry65_picture('assets/salon/s03.webp', 'Dry65 salon, Schwarzkopf Professional proizvodi', ['loading' => 'lazy', 'style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
      </div>
      <div>
        <span class="mono" style="color:var(--clay);">Kvalitet u koji verujemo</span>
        <h2 class="display" style="font-size:clamp(24px,3.4vw,40px);margin-top:10px;line-height:1.05;">Schwarzkopf Professional</h2>
        <p class="lead" style="margin-top:18px;max-width:520px;">
          Koristimo isključivo <a href="https://www.schwarzkopf-professional.com/" target="_blank" rel="noopener" style="color:var(--clay);text-decoration:underline;text-underline-offset:3px;">Schwarzkopf Professional</a> proizvode, vodeću svetsku marku za profesionalne salone. Od pranja i nege do stilizovanja, svaki proizvod biramo da kosa ostane zdrava, mekana i puna sjaja.
        </p>
      </div>
    </div>
  </div>
</section>

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
