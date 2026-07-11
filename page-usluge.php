<?php
/*
Template Name: Usluge
*/
get_header();
$services = dry65_services();
$tpl      = get_template_directory_uri();
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

<section class="section">
  <div class="wrap stack" style="gap:clamp(48px,7vw,96px);">
    <?php foreach ($services as $i => $s): ?>
    <div class="reveal svc-row" style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(28px,4vw,64px);align-items:center;direction:<?php echo $i % 2 ? 'rtl' : 'ltr'; ?>;">
      <div style="direction:ltr;aspect-ratio:4/3;border-radius:var(--radius-lg);overflow:hidden;">
        <?php echo dry65_picture($s['img'], $s['title'], [
          'loading' => 'lazy',
          'style'   => 'width:100%;height:100%;object-fit:cover;display:block;',
        ]); ?>
      </div>
      <div style="direction:ltr;">
        <span class="mono" style="color:var(--clay);"><?php echo esc_html($s['kicker']); ?></span>
        <h2 class="display" style="font-size:clamp(30px,4.5vw,52px);margin-top:10px;"><?php echo esc_html($s['title']); ?></h2>
        <p class="lead" style="margin-top:18px;"><?php echo esc_html($s['body']); ?></p>
        <div class="btn-row" style="margin-top:24px;gap:10px;">
          <?php foreach ($s['points'] as $pt): ?>
          <span class="chip"><?php echo esc_html($pt); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
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
