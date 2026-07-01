<?php
/*
Template Name: Karijera
*/
get_header();
$biz = dry65_biz();

$positions = [
    [
        'id'       => 'asistent',
        'kicker'   => '01',
        'status'   => 'open',
        'title'    => 'Asistent u radu',
        'lead'     => 'Podrška tima. Osoba koja uči zanat pored najboljih, uz konkretno praktično iskustvo od prvog dana.',
        'reqs' => [
            'Interesovanje za rad u frizerskoj industriji',
            'Volja za učenjem i predanost',
            'Odgovornost i timski duh',
            'Formalno obrazovanje nije uslov',
        ],
        'offer' => [
            'Plaćena praksa uz mentore',
            'Realno iskustvo u walk-in salonu',
            'Put ka poziciji blowout specijaliste',
            'Prijatan i podržavajući tim',
        ],
    ],
    [
        'id'       => 'blowout',
        'kicker'   => '02',
        'status'   => 'soon',
        'title'    => 'Blowout specijalista',
        'lead'     => 'Ključna članica tima. Osoba koja svaki dan pravi da klijentkinje izlaze sa savršenim feniranjem.',
        'reqs' => [
            'Iskustvo u profesionalnom feniranju kose',
            'Poznavanje tehnika: lokne, talasi, ravno, volumen',
            'Ljubaznost i osećaj za detalj',
            'Volja da učiš i rasteš uz tim',
        ],
        'offer' => [
            'Stimulativna zarada + procenti',
            'Edukacije i usavršavanja',
            'Moderan salon u West65 mall-u',
            'Stabilan tim i prijatna atmosfera',
        ],
    ],
    [
        'id'       => 'recepcionar',
        'kicker'   => '03',
        'status'   => 'soon',
        'title'    => 'Recepcionar',
        'lead'     => 'Prvi kontakt sa klijentkinjama. Osoba koja pravi da svako iskustvo u salonu počinje toplo i profesionalno.',
        'reqs' => [
            'Komunikativnost i pozitivna energija',
            'Osnovni rad na računaru',
            'Poznavanje engleskog jezika',
            'Organizovanost i pouzdanost',
        ],
        'offer' => [
            'Fiksna zarada + bonusi',
            'Fleksibilno radno vreme',
            'Obuka za rad u salonu',
            'Prijatno okruženje u West65',
            'Prostor za napredovanje',
        ],
    ],
];
?>

<main class="page-enter">

<!-- ====================================================
     HERO
     ==================================================== -->
<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">Karijera</span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:28ch;line-height:1.0;letter-spacing:0.01em;">
      Postani deo Dry65 tima
    </h1>
    <p class="lead" style="margin-top:26px;max-width:680px;">
      Tražimo ljude sa energijom, strašću za rad i željom da rastu uz nas. Ako želiš da radiš u modernom walk-in blowout salonu na Novom Beogradu, uz tim koji se pazi, pogledaj naše otvorene pozicije.
    </p>
  </div>
</section>

<!-- ====================================================
     POZICIJE
     ==================================================== -->
<section class="section">
  <div class="wrap stack" style="gap:clamp(40px,6vw,72px);">

    <?php foreach ($positions as $p): $is_soon = ($p['status'] ?? 'open') === 'soon'; ?>
    <article class="reveal karijera-card <?php echo $is_soon ? 'is-soon' : ''; ?>" id="<?php echo esc_attr($p['id']); ?>" style="background:var(--paper);border:1px solid var(--sage-line);border-radius:var(--radius-lg);padding:clamp(28px,4vw,48px);<?php echo $is_soon ? 'opacity:0.72;' : ''; ?>">

      <div style="display:grid;grid-template-columns:1fr;gap:clamp(20px,3vw,32px);">

        <!-- Header pozicije -->
        <div>
          <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <span class="mono" style="color:var(--clay);font-size:14px;"><?php echo esc_html($p['kicker']); ?></span>
            <?php if ($is_soon): ?>
            <span class="soon-badge" style="display:inline-flex;align-items:center;gap:6px;background:var(--cream);color:var(--ink);border:1px solid var(--cream-deep);border-radius:999px;padding:4px 12px;font-size:12px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;">
              <span style="width:6px;height:6px;background:var(--clay);border-radius:50%;"></span>
              Uskoro
            </span>
            <?php endif; ?>
          </div>
          <h2 class="display" style="font-size:clamp(28px,3.6vw,42px);margin:10px 0 0;line-height:1.1;">
            <?php echo esc_html($p['title']); ?>
          </h2>
          <p class="lead" style="margin:16px 0 0;max-width:640px;">
            <?php echo esc_html($p['lead']); ?>
          </p>
        </div>

        <!-- Grid: Zahtevi + Nudimo -->
        <div class="karijera-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(24px,3vw,48px);margin-top:8px;">

          <div>
            <h3 class="mono" style="color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:0.14em;margin:0 0 16px;">
              Šta tražimo
            </h3>
            <ul style="margin:0;padding:0;list-style:none;">
              <?php foreach ($p['reqs'] as $r): ?>
              <li style="display:flex;gap:10px;padding:8px 0;line-height:1.55;">
                <span style="color:var(--clay);flex-shrink:0;">→</span>
                <span><?php echo esc_html($r); ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div>
            <h3 class="mono" style="color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:0.14em;margin:0 0 16px;">
              Šta nudimo
            </h3>
            <ul style="margin:0;padding:0;list-style:none;">
              <?php foreach ($p['offer'] as $o): ?>
              <li style="display:flex;gap:10px;padding:8px 0;line-height:1.55;">
                <span style="color:var(--clay);flex-shrink:0;">✓</span>
                <span><?php echo esc_html($o); ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>

        </div>

        <!-- CTA -->
        <div style="margin-top:12px;padding-top:24px;border-top:1px solid var(--sage-line);">
          <?php if ($is_soon): ?>
          <p style="margin:0;color:var(--muted);font-size:15px;line-height:1.6;">
            Konkurs za ovu poziciju otvaramo <strong style="color:var(--ink);">uskoro</strong>. Prati nas na <a href="<?php echo esc_url($biz['instagram_url']); ?>" target="_blank" rel="noopener" style="color:var(--clay);text-decoration:underline;text-underline-offset:3px;">Instagramu</a> ili pošalji CV unapred na <a href="mailto:<?php echo esc_attr($biz['email']); ?>?subject=Prijava%20unapred:%20<?php echo rawurlencode($p['title']); ?>" style="color:var(--clay);text-decoration:underline;text-underline-offset:3px;"><?php echo esc_html($biz['email']); ?></a>.
          </p>
          <?php else: ?>
          <a href="mailto:<?php echo esc_attr($biz['email']); ?>?subject=Prijava%20za%20poziciju:%20<?php echo rawurlencode($p['title']); ?>&body=Zdravo,%20zainteresovan%20sam%20za%20poziciju%20<?php echo rawurlencode($p['title']); ?>%20u%20Dry65.%20U%20nastavku%20su%20moji%20podaci..." class="btn btn-dark">
            Prijavi se <span class="arrow">→</span>
          </a>
          <a href="mailto:<?php echo esc_attr($biz['email']); ?>" class="btn btn-outline" style="margin-left:10px;">
            Pošalji CV
          </a>
          <?php endif; ?>
        </div>

      </div>

    </article>
    <?php endforeach; ?>

  </div>
</section>

<!-- ====================================================
     BOTTOM CTA
     ==================================================== -->
<section class="section-sm bg-cream">
  <div class="wrap center">
    <span class="script" style="font-size:clamp(24px,3vw,38px);display:block;margin-bottom:6px;">radoznali smo</span>
    <h2 class="display" style="font-size:clamp(28px,4vw,44px);margin:0 0 20px;">
      Ne vidiš svoju poziciju?
    </h2>
    <p class="lead" style="max-width:560px;margin:0 auto 28px;">
      Ako veruješ da bi bila deo Dry65 tima, pošalji nam CV. Uvek smo otvoreni za dobre ljude.
    </p>
    <div class="btn-row" style="justify-content:center;">
      <a href="mailto:<?php echo esc_attr($biz['email']); ?>?subject=Prijava%20za%20posao%20u%20Dry65" class="btn btn-dark">
        Pošalji CV na <?php echo esc_html($biz['email']); ?>
      </a>
    </div>
  </div>
</section>

</main>

<style>
@media (max-width: 720px) {
  .karijera-grid {
    grid-template-columns: 1fr !important;
    gap: 24px !important;
  }
}
</style>

<?php get_footer(); ?>
