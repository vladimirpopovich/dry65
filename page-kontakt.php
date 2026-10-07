<?php
/*
Template Name: Kontakt
*/
get_header();
$biz = dry65_biz();
$rows = [
    ['k' => 'Adresa',    'v' => $biz['address'],      'href' => $biz['maps_url'],       'ext' => true],
    ['k' => 'Telefon',   'v' => $biz['phone_display'], 'href' => 'tel:' . $biz['phone'], 'ext' => false],
    ['k' => 'Email',     'v' => $biz['email'],         'href' => 'mailto:' . $biz['email'], 'ext' => false],
    ['k' => 'Instagram', 'v' => $biz['instagram'],     'href' => $biz['instagram_url'],  'ext' => true],
];
?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;"><?php echo t('Kontakt'); ?></span>
    <h1 class="display caps" style="font-size:clamp(28px,3.8vw,48px);margin-top:4px;max-width:34ch;line-height:1.05;letter-spacing:0.01em;">
      <?php echo t('Dry65 frizerski salon specijalizovan za feniranje'); ?>
    </h1>
    <p class="lead" style="margin-top:20px;max-width:680px;">
      <?php echo t('Frizerski salon specijalizovan za feniranje, na Novom Beogradu blizu West 65 mall-a. Nema zakazivanja, samo dođi.'); ?>
    </p>
    <div class="stack" style="max-width:680px;margin-top:22px;gap:14px;font-size:16px;line-height:1.7;color:var(--ink);">
      <p><?php echo t('Dry65 se nalazi na Novom Beogradu, u stambenom kompleksu West 65, na njegovoj spoljnoj ivici, u lokalu u lameli Ž. Salon je u blizini Airport City poslovne zone, a do nas se lako stiže i sa Bulevara Zorana Đinđića i Aerodromske ulice.'); ?></p>
      <div class="readmore" id="readmore-kontakt">
        <div class="readmore-inner stack" style="gap:14px;">
          <p><?php echo sprintf(
            t('Pošto se salon nalazi na spoljnom delu kompleksa, a ne unutar samog West 65 mall-a, najlakše je da lokaciju i tačnu poziciju salona pronađeš preko %s. Tamo možeš dobiti i najpreciznija uputstva za dolazak.'),
            '<a href="' . esc_url($biz['maps_url']) . '" target="_blank" rel="noopener" style="color:var(--clay);text-decoration:underline;text-underline-offset:3px;">' . t('Google Maps-a') . '</a>'
          ); ?></p>
          <p><?php echo sprintf(
            t('Radimo isključivo bez zakazivanja, pa ako imaš pitanje pre dolaska, možeš nas kontaktirati telefonom, mejlom ili putem Instagram poruke. Za informaciju o trenutnoj gužvi možeš pogledati našu stranicu %1$s pre nego što kreneš. Za pitanja o poslu i otvorenim pozicijama pogledaj stranicu %2$s.'),
            '<a href="' . esc_url(home_url('/live/')) . '" style="color:var(--clay);text-decoration:underline;text-underline-offset:3px;">' . t('uživo') . '</a>',
            '<a href="' . esc_url(get_permalink(get_page_by_path('karijera'))) . '" style="color:var(--clay);text-decoration:underline;text-underline-offset:3px;">' . t('Karijera') . '</a>'
          ); ?></p>
        </div>
      </div>
    </div>
    <button type="button" class="readmore-trigger" data-readmore-target="readmore-kontakt" data-more-label="<?php echo esc_attr(t('Pročitaj više')); ?>" data-less-label="<?php echo esc_attr(t('Prikaži manje')); ?>" aria-expanded="false" aria-controls="readmore-kontakt">
      <span class="rm-label"><?php echo t('Pročitaj više'); ?></span> <span class="arrow">↓</span>
    </button>
  </div>
</section>

<section class="section">
  <div class="wrap kontakt-grid" style="display:grid;grid-template-columns:1fr 1.1fr;gap:clamp(32px,5vw,64px);">

    <div>
      <div class="stack" style="border:1px solid var(--sage-line);border-radius:var(--radius-lg);overflow:hidden;">
        <?php foreach ($rows as $i => $r): ?>
        <a href="<?php echo esc_url($r['href']); ?>"
          <?php if ($r['ext']): ?>target="_blank" rel="noopener"<?php endif; ?>
          class="row contact-row"
          style="justify-content:space-between;padding:22px 26px;<?php echo $i < count($rows) - 1 ? 'border-bottom:1px solid var(--sage-line);' : ''; ?>transition:background .2s;">
          <span class="mono" style="font-size:12px;color:var(--clay);text-transform:uppercase;letter-spacing:0.08em;"><?php echo esc_html(t($r["k"])); ?></span>
          <span style="font-weight:500;font-size:17px;text-align:right;"><?php echo esc_html($r['v']); ?> <span style="color:var(--clay);">→</span></span>
        </a>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:30px;">
        <span class="eyebrow"><?php echo t('Radno vreme'); ?></span>
        <div style="margin-top:16px;">
          <?php foreach ($biz['hours'] as $i => $h): ?>
          <div class="row" style="justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--sage-line);">
            <span><?php echo esc_html(t($h["day"])); ?></span>
            <span style="font-weight:600;"><?php echo esc_html(t($h["time"])); ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div>
      <div class="rounded" style="position:relative;overflow:hidden;aspect-ratio:4/3;border:1px solid var(--sage-line);">
        <iframe
          title="dry65, West65, Novi Beograd"
          src="https://maps.google.com/maps?q=dry65%20Novi%20Beograd&z=16&output=embed"
          style="width:100%;height:100%;border:0;display:block;filter:grayscale(0.2) contrast(1.02);"
          loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener"
          class="btn btn-dark" style="position:absolute;bottom:16px;right:16px;">
          <?php echo t('Kako do nas'); ?> <span class="arrow">→</span>
        </a>
      </div>
      <div style="margin-top:24px;padding:26px;background:var(--cream);border-radius:var(--radius-lg);">
        <h3 class="display" style="font-size:26px;"><?php echo t('Dolaziš kolima?'); ?></h3>
        <p class="muted" style="margin-top:10px;font-size:16px;">
          <?php echo t('U kompleksu West 65 prvi sat parkiranja je besplatan. Iskoristi ga pre dolaska kod nas.'); ?>
        </p>
      </div>
    </div>

  </div>
</section>

<style>
.contact-row:hover { background: var(--paper-2); }
.contact-row .mono { white-space: nowrap; }
@media (max-width: 880px) {
  .kontakt-grid { grid-template-columns: 1fr !important; }
}
</style>

</main>

<?php get_footer(); ?>
