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
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">Kontakt</span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:28ch;line-height:1.0;letter-spacing:0.01em;">
      Dry65, walk-in salon na Novom Beogradu
    </h1>
    <p class="lead" style="margin-top:20px;max-width:680px;">
      Nema zakazivanja. Samo dođi.
    </p>
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
          <span class="mono" style="font-size:12px;color:var(--clay);text-transform:uppercase;letter-spacing:0.08em;"><?php echo esc_html($r['k']); ?></span>
          <span style="font-weight:500;font-size:17px;text-align:right;"><?php echo esc_html($r['v']); ?> <span style="color:var(--clay);">→</span></span>
        </a>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:30px;">
        <span class="eyebrow">Radno vreme</span>
        <div style="margin-top:16px;">
          <?php foreach ($biz['hours'] as $i => $h): ?>
          <div class="row" style="justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--sage-line);">
            <span><?php echo esc_html($h['day']); ?></span>
            <span style="font-weight:600;"><?php echo esc_html($h['time']); ?></span>
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
          Kako do nas <span class="arrow">→</span>
        </a>
      </div>
      <div style="margin-top:24px;padding:26px;background:var(--cream);border-radius:var(--radius-lg);">
        <h3 class="display" style="font-size:26px;">Dolaziš kolima?</h3>
        <p class="muted" style="margin-top:10px;font-size:16px;">
          U West 65 Mall prvi sat parkiranja je besplatan. Iskoristi ga, mi smo samo nekoliko koraka od ulaza.
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
