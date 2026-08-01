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
  /* /usluge — tekst + linkovi (bez slika, spremno za live) */
  .usl-cat { max-width:760px; }
  .usl-cat > h2 { font-size:clamp(28px,4vw,44px); line-height:1.02; letter-spacing:0.01em; }
  .usl-cat > .lead { margin-top:16px; }
  .usl-links { list-style:none; padding:0; margin:22px 0 0; display:grid; grid-template-columns:repeat(2,1fr); gap:0 28px; }
  .usl-link { display:flex; align-items:center; gap:10px; padding:12px 0; color:var(--ink); text-decoration:none;
    font-family:var(--font-sans); font-size:17px; font-weight:500; border-bottom:1px solid var(--cream-deep,#ece7df);
    transition:color .2s, gap .2s; }
  .usl-link:hover { color:var(--clay); gap:14px; }
  .usl-link .arr { color:var(--clay); font-size:15px; }
  @media (max-width:600px){ .usl-links { grid-template-columns:1fr; } }
</style>

<?php foreach ($tree as $ci => $cat): $kids = $cat['children']; ?>
<section class="section<?php echo $ci % 2 ? ' bg-paper2' : ''; ?>">
  <div class="wrap">
    <div class="usl-cat">
      <h2 class="display">
        <a href="<?php echo esc_url($cat['url']); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($cat['title']); ?></a>
      </h2>
      <?php if ($cat['intro']): ?><p class="lead"><?php echo esc_html($cat['intro']); ?></p><?php endif; ?>
      <?php if ($kids): ?>
      <ul class="usl-links">
        <?php foreach ($kids as $c): ?>
        <li><a class="usl-link" href="<?php echo esc_url($c['url']); ?>"><span class="arr">→</span> <?php echo esc_html($c['title']); ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <a href="<?php echo esc_url($cat['url']); ?>" class="usl-link" style="display:inline-flex;border:0;margin-top:18px;font-weight:600;color:var(--clay);"><span class="arr">→</span> Saznaj više</a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<!-- Schwarzkopf band (tekst + slika) -->
<section class="section">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(28px,4vw,64px);align-items:center;" class="hero-grid">
      <div style="aspect-ratio:4/5;border-radius:1000px;overflow:hidden;">
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
