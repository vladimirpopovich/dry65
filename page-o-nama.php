<?php
/*
Template Name: O nama
*/
get_header();
$biz  = dry65_biz();
$team = dry65_team();
$tpl  = get_template_directory_uri();
$stats = [
    ['n' => '100%', 'l' => 'fokus na tvoju kosu'],
    ['n' => '0',    'l' => 'zakazivanja potrebno'],
    ['n' => '5.0',  'l' => 'prosečna ocena'],
    ['n' => '1',    'l' => 'stvar, i radimo je savršeno'],
];
?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">O nama</span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:28ch;line-height:1.0;letter-spacing:0.01em;">
      Walk-in blowout salon na Novom Beogradu
    </h1>
    <p class="lead" style="margin-top:26px;max-width:640px;">
      Walk-in salon na Novom Beogradu, u West 65. Radimo jednu stvar, feniramo, i u tome smo najbolji.
    </p>
    <p class="lead" style="margin-top:14px;max-width:640px;font-size:15px;color:var(--muted);">
      Radimo isključivo sa <a href="https://www.schwarzkopf-professional.com/" target="_blank" rel="noopener" style="color:var(--clay);text-decoration:underline;text-underline-offset:3px;">Schwarzkopf Professional</a> proizvodima, vodećim svetskim brendom za salonsku negu kose.
    </p>
  </div>
</section>

<!-- Stats -->
<section class="section-sm bg-ink">
  <div class="wrap grid cols-4">
    <?php foreach ($stats as $i => $s): ?>
    <div class="reveal" data-delay="<?php echo $i * 80; ?>">
      <div class="big-num" style="color:var(--clay);"><?php echo esc_html($s['n']); ?></div>
      <p class="muted" style="margin-top:10px;font-size:15px;"><?php echo esc_html($s['l']); ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<?php /* TIM SEKCIJA - privremeno sakrivena, vraćamo kasnije
<section class="section bg-cream">
  <div class="wrap">
    <div class="center" style="max-width:720px;margin:0 auto;">
      <span class="script" style="font-size:clamp(28px,3.4vw,40px);display:block;margin-bottom:2px;">naš tim</span>
      <h2 class="display" style="font-size:clamp(34px,5.2vw,64px);margin-top:6px;">Ruke iza svakog feniranja.</h2>
      <p class="lead" style="margin-top:20px;">Pažljivo birana, posvećena ekipa stilista. Svaka članica je prošla kroz dvostruki intervju, kulturološki i tehnički, pa kroz Nikolinu školu. Rezultat je dosledan, bez obzira koja vas isfenira.</p>
    </div>
    <div class="grid cols-4" style="margin-top:clamp(34px,4vw,52px);">
      <?php foreach ($team as $i => $m): ?>
      <div class="reveal" data-delay="<?php echo $i * 70; ?>">
        <div style="aspect-ratio:3/4;border-radius:var(--radius-lg);overflow:hidden;background:var(--cream);border:1px solid var(--cream-deep);">
          <div class="ph" style="width:100%;height:100%;aspect-ratio:3/4;border:0;">
            <span class="ph-tag"><?php echo esc_html(strtolower($m['name'])); ?></span>
          </div>
        </div>
        <h3 class="display" style="font-size:27px;margin-top:16px;"><?php echo esc_html($m['name']); ?></h3>
        <p style="font-weight:600;color:var(--oxblood);font-size:14px;margin-top:2px;"><?php echo esc_html($m['role']); ?></p>
        <p class="muted" style="margin-top:10px;font-size:15px;"><?php echo esc_html($m['bio']); ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
*/ ?>

<!-- Values -->
<section class="section">
  <div class="wrap center">
    <div>
      <span class="script" style="font-size:clamp(28px,3.4vw,40px);display:block;margin-bottom:2px;">Vrednosti</span>
      <h2 class="display" style="font-size:clamp(34px,5.2vw,64px);margin-top:6px;">Zašto baš Dry65?</h2>
    </div>
    <?php
    $values = [
      ['t' => 'Bez zakazivanja',   'd' => 'Tvoje vreme je tvoje. Dođeš kad ti odgovara, mi smo tu.'],
      ['t' => 'Brzo i savršeno',   'd' => 'Fokus na jednu stvar znači da je radimo brže i bolje od svih.'],
      ['t' => 'Nega na prvom mestu','d' => 'Tretmani nege na poklon uz pakete, kosa ostaje zdrava.'],
    ];
    ?>
    <div class="grid cols-3" style="margin-top:clamp(34px,4vw,52px);text-align:left;">
      <?php foreach ($values as $i => $v): ?>
      <div class="reveal card" style="padding:30px 28px;height:100%;" data-delay="<?php echo $i * 80; ?>">
        <span class="mono" style="color:var(--clay);"><?php echo str_pad($i + 1, 2, '0', STR_PAD_LEFT); ?></span>
        <h3 class="display" style="font-size:27px;margin-top:12px;"><?php echo esc_html($v['t']); ?></h3>
        <p class="muted" style="margin-top:12px;font-size:16px;"><?php echo esc_html($v['d']); ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:44px;">
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('kontakt'))); ?>" class="btn btn-dark">
        Kako do nas <span class="arrow">→</span>
      </a>
    </div>
  </div>
</section>

</main>

<?php get_footer(); ?>
