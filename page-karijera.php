<?php
/*
Template Name: Karijera
*/
get_header();
$biz = dry65_biz();

$positions = [
    [
        'id'       => 'blowout',
        'kicker'   => '01',
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
            'Rad sa Schwarzkopf Professional proizvodima',
            'Edukacije i usavršavanja',
            'Moderan salon u West65 mall-u',
            'Stabilan tim i prijatna atmosfera',
        ],
    ],
    [
        'id'       => 'recepcionar',
        'kicker'   => '02',
        'title'    => 'Recepcionar',
        'lead'     => 'Prvi kontakt sa klijentkinjama. Osoba koja pravi da svako iskustvo u salonu počinje toplo i profesionalno.',
        'reqs' => [
            'Komunikativnost i pozitivna energija',
            'Osnovni rad na računaru',
            'Poznavanje engleskog jezika (poželjno)',
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
    [
        'id'       => 'asistent',
        'kicker'   => '03',
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
            'Rad sa Schwarzkopf proizvodima',
            'Prijatan i podržavajući tim',
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
      Tražimo ljude sa energijom, strašću za rad i željom da rastu uz nas. Ako želiš da radiš u modernom walk-in blowout salonu na Novom Beogradu, uz Schwarzkopf Professional proizvode i tim koji se pazi, pogledaj naše otvorene pozicije.
    </p>
  </div>
</section>

<!-- ====================================================
     POZICIJE
     ==================================================== -->
<section class="section">
  <div class="wrap stack" style="gap:clamp(40px,6vw,72px);">

    <?php foreach ($positions as $p): ?>
    <article class="reveal karijera-card" id="<?php echo esc_attr($p['id']); ?>" style="background:var(--paper);border:1px solid var(--sage-line);border-radius:var(--radius-lg);padding:clamp(28px,4vw,48px);">

      <div style="display:grid;grid-template-columns:1fr;gap:clamp(20px,3vw,32px);">

        <!-- Header pozicije -->
        <div>
          <span class="mono" style="color:var(--clay);font-size:14px;"><?php echo esc_html($p['kicker']); ?></span>
          <h2 class="display" style="font-size:clamp(28px,3.6vw,42px);margin:8px 0 0;line-height:1.1;">
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
          <a href="mailto:<?php echo esc_attr($biz['email']); ?>?subject=Prijava%20za%20poziciju:%20<?php echo rawurlencode($p['title']); ?>&body=Zdravo,%20zainteresovan%20sam%20za%20poziciju%20<?php echo rawurlencode($p['title']); ?>%20u%20Dry65.%20U%20nastavku%20su%20moji%20podaci..." class="btn btn-dark">
            Prijavi se <span class="arrow">→</span>
          </a>
          <a href="mailto:<?php echo esc_attr($biz['email']); ?>" class="btn btn-outline" style="margin-left:10px;">
            Pošalji CV
          </a>
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
