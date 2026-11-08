<?php
/*
Template Name: Za firme
*/
get_header();
$biz = dry65_biz();

$mail_subject = rawurlencode('Ponuda za firme');
$mailto = 'mailto:' . $biz['email'] . '?subject=' . $mail_subject;

$models    = dry65_b2b_models();
$sel_model = sanitize_key($_GET['model'] ?? '');
$status    = sanitize_key($_GET['prijava'] ?? '');

$offers = [
    [
        'id'       => 'mesecni-paket',
        'kicker'   => '01',
        'badge'    => 'Najtraženije',
        'title'    => 'Mesečni paketi po fiksnoj ceni',
        'price'    => '1.850',
        'unit'     => 'din / feniranje',
        'lead'     => 'Firma kupuje paket od 40 ili više feniranja mesečno po jednoj ceni, bez obzira na dužinu kose. Zaposleni dolaze kad im odgovara, bez zakazivanja.',
        'points'   => [
            'Fiksna cena od 1.850 din za pakete od 40+ feniranja mesečno',
            'Svaki zaposleni ima svoj QR kod koji pokaže na kasi',
            'Svaki dolazak se evidentira, pa znate ko je koliko koristio',
        ],
    ],
    [
        'id'       => 'sufinansiranje',
        'kicker'   => '02',
        'badge'    => '',
        'title'    => 'Deo firma, ostatak zaposleni',
        'price'    => '',
        'unit'     => 'Iznos firme po dogovoru',
        'lead'     => 'Najjednostavniji benefit, bez unapred kupljenog paketa. Firma pokriva dogovoreni iznos svakog feniranja, a zaposleni na licu mesta plaća samo razliku.',
        'points'   => [
            'Zaposleni doplaćuje razliku u odnosu na dogovoreni iznos koji plaća firma',
            'Firma plaća samo stvarne dolaske, bez minimuma',
            'Na kraju meseca šaljemo fakturu: broj dolazaka × dogovoreni iznos',
            'Uz fakturu dobijate pregled dolazaka po zaposlenom',
        ],
    ],
    [
        'id'       => 'vauceri',
        'kicker'   => '03',
        'badge'    => '',
        'title'    => 'Digitalni poklon vaučer',
        'price'    => '',
        'unit'     => 'Iznos po vašem izboru',
        'lead'     => 'Za Novu godinu, 8. mart ili rođendan. Uplatite vaučer u dinarskom iznosu na ime osobe, a ona ga troši kad poželi.',
        'points'   => [
            'Vaučer glasi na ime zaposlenog, u iznosu koji vi odredite',
            'Osoba dobija svoj QR kod, bez papira i štampe',
            'Posle usluge iznos se skida sa vaučera, ostatak ostaje za sledeći put',
            'Jedna faktura za sve vaučere',
        ],
    ],
];

$steps = [
    ['n' => '1', 't' => 'Registrujte firmu', 'd' => 'Popunite online prijavu i izaberite model koji vam odgovara.'],
    ['n' => '2', 't' => 'Potvrđujemo detalje', 'd' => 'Javljamo vam se i dogovaramo spisak zaposlenih koji ulaze u program.'],
    ['n' => '3', 't' => 'Zaposleni dobijaju QR kod', 'd' => 'Svako dobija svoj kod na telefon, koji pokaže na kasi.'],
    ['n' => '4', 't' => 'Tim dolazi kad hoće', 'd' => 'Bez zakazivanja, šest dana u nedelji, blizu West65 mall-a.'],
];
?>

<style data-no-optimize="1">
  .b2b-offers { display: grid; grid-template-columns: repeat(3, 1fr); gap: clamp(18px, 2.4vw, 30px); align-items: stretch; }
  .b2b-offer {
    position: relative; display: flex; flex-direction: column;
    background: #fff; border: 0; border-radius: var(--radius-lg);
    box-shadow: 0 2px 6px rgba(17,28,29,0.04), 0 24px 48px -28px rgba(17,28,29,0.32);
    padding: clamp(24px, 2.6vw, 36px);
  }
  .b2b-offer.is-featured { box-shadow: 0 2px 6px rgba(17,28,29,0.05), 0 30px 56px -28px rgba(17,28,29,0.42); }
  .b2b-offer .chip { position: absolute; top: -15px; left: clamp(24px, 2.6vw, 36px); box-shadow: 0 8px 18px -8px rgba(17,28,29,0.55); }
  .b2b-kicker { font-size: 13px; font-weight: 600; letter-spacing: 0.1em; color: var(--clay); }
  .b2b-offer h2 { font-size: clamp(26px, 2.6vw, 34px); line-height: 1.08; margin-top: 10px; font-weight: 400; }
  .b2b-offer .b2b-lead { margin-top: 14px; font-size: 15.5px; line-height: 1.55; color: var(--ink-soft); }
  .b2b-points { list-style: none; margin: 20px 0 0; padding: 18px 0 0; border-top: 1px solid var(--sage-line); display: grid; gap: 10px; }
  .b2b-points li { display: flex; gap: 10px; font-size: 14.5px; line-height: 1.45; color: var(--oxblood); }
  .b2b-points li::before { content: "✦"; color: var(--clay); flex-shrink: 0; }
  .b2b-offer .b2b-cta { margin-top: auto; padding-top: 24px; }
  .b2b-amount { display: flex; align-items: baseline; flex-wrap: wrap; gap: 8px; margin-top: 16px; min-height: 48px; }
  .b2b-amount-n { font-family: var(--font-sans); font-weight: 600; font-size: clamp(30px, 3vw, 38px); color: var(--oxblood); line-height: 1; }
  #ponude, #prijava { scroll-margin-top: 72px; }
  .b2b-offer h2 .b2b-num { font-family: var(--font-sans) !important; font-weight: 500; font-size: 0.86em; letter-spacing: -0.01em; }
  .b2b-btn-soft { background: #fff; color: var(--ink); border-color: transparent; box-shadow: 0 1px 2px rgba(17,28,29,0.08), 0 10px 22px -10px rgba(17,28,29,0.35); }
  .b2b-btn-soft:hover { background: var(--oxblood); color: var(--cream); }
  .b2b-unit { font-size: 14px; color: var(--muted); }
  .b2b-unit.is-solo { font-size: 18px; color: var(--oxblood); font-weight: 600; }

  .b2b-form-wrap { display: grid; grid-template-columns: 1fr 1.3fr; gap: clamp(28px, 4vw, 64px); align-items: start; }
  .b2b-form { background: #fff; box-shadow: 0 30px 60px -30px rgba(0,0,0,0.45); border-radius: var(--radius-lg); padding: clamp(22px, 2.6vw, 36px); color: var(--ink); }
  .b2b-form .f-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .b2b-form .f-full { grid-column: 1 / -1; }
  .b2b-form label { display: block; font-size: 13px; font-weight: 600; letter-spacing: 0.02em; margin-bottom: 6px; color: var(--ink-soft); }
  .b2b-form input, .b2b-form select, .b2b-form textarea {
    width: 100%; box-sizing: border-box; font: inherit; font-size: 16px; color: var(--ink);
    background: #fff; border: 1px solid rgba(17,28,29,0.18); border-radius: 10px; padding: 12px 14px;
    transition: border-color .15s;
  }
  .b2b-form textarea { min-height: 96px; resize: vertical; }
  .b2b-form input:hover, .b2b-form select:hover, .b2b-form textarea:hover { border-color: rgba(17,28,29,0.32); }
  .b2b-form input:focus, .b2b-form select:focus, .b2b-form textarea:focus { outline: none; border-color: var(--clay); box-shadow: 0 0 0 3px rgba(199,110,94,0.18); }
  .b2b-form .f-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
  .b2b-form .f-foot { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-top: 22px; }
  .b2b-form .f-foot p { font-size: 13px; color: var(--muted); max-width: 320px; }
  .b2b-msg { border-radius: 12px; padding: 14px 16px; font-size: 15px; margin-bottom: 18px; }
  .b2b-msg.ok { background: #e7f3ea; color: #1e5b2c; }
  .b2b-msg.err { background: #fbe9e6; color: #8a2c22; }
  .b2b-contact-line { color: var(--cream); text-decoration: none; border-bottom: 1px solid rgba(242,225,190,0.35); }
  @media (max-width: 960px) {
    .b2b-form-wrap { grid-template-columns: 1fr; }
  }
  @media (max-width: 560px) {
    .b2b-form .f-grid { grid-template-columns: 1fr; }
  }

  .b2b-steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: clamp(18px, 2.4vw, 30px); counter-reset: none; }
  .b2b-step-n {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%; background: #fff;
    box-shadow: 0 1px 2px rgba(17,28,29,0.08), 0 8px 18px -8px rgba(17,28,29,0.30);
    color: var(--oxblood); font-weight: 600; font-size: 15px;
  }
  .b2b-step h3 { font-size: 19px; font-weight: 600; margin-top: 16px; font-family: var(--font-sans); }
  .b2b-step p { margin-top: 6px; font-size: 15px; line-height: 1.5; }


  @media (max-width: 960px) {
    .b2b-offers { grid-template-columns: 1fr; gap: 34px; max-width: 620px; margin-inline: auto; }
    .b2b-steps { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 520px) {
    .b2b-steps { grid-template-columns: 1fr; }
  }
</style>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;"><?php echo t('Za firme'); ?></span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:24ch;line-height:1.0;letter-spacing:0.01em;">
      <?php echo t('Feniranje kao benefit za vaš tim'); ?>
    </h1>
    <p class="lead" style="margin-top:26px;max-width:680px;">
      <?php echo t('Mesečni paket po fiksnoj ceni, sufinansiranje feniranja ili digitalni poklon vaučeri. Zaposleni dolaze bez zakazivanja i pokažu svoj QR kod na kasi, a firma dobija jednu fakturu.'); ?>
    </p>
    <div class="btn-row" style="margin-top:26px;">
      <a href="#prijava" class="btn btn-dark"><?php echo t('Registrujte firmu'); ?></a>
      <a href="#ponude" class="btn b2b-btn-soft"><?php echo t('Pogledajte ponude'); ?></a>
    </div>
  </div>
</section>

<section class="section" id="ponude">
  <div class="wrap">
    <div class="b2b-offers">
      <?php foreach ($offers as $o): ?>
      <article class="b2b-offer<?php echo $o['badge'] ? ' is-featured' : ''; ?>" id="<?php echo esc_attr($o['id']); ?>">
        <?php if ($o['badge']): ?><span class="chip"><?php echo esc_html(t($o['badge'])); ?></span><?php endif; ?>
        <span class="b2b-kicker"><?php echo esc_html($o['kicker']); ?></span>
        <h2 class="display"><?php echo preg_replace('/[0-9][0-9.]*/', '<span class="b2b-num">$0</span>', esc_html(t($o['title']))); ?></h2>
        <div class="b2b-amount">
          <?php if ($o['price']): ?><span class="b2b-amount-n"><?php echo esc_html($o['price']); ?></span><?php endif; ?>
          <span class="b2b-unit<?php echo $o['price'] ? '' : ' is-solo'; ?>"><?php echo esc_html(t($o['unit'])); ?></span>
        </div>
        <p class="b2b-lead"><?php echo esc_html(t($o['lead'])); ?></p>
        <ul class="b2b-points">
          <?php foreach ($o['points'] as $pt): ?>
          <li><span><?php echo esc_html(t($pt)); ?></span></li>
          <?php endforeach; ?>
        </ul>
        <div class="b2b-cta">
          <a href="#prijava" data-model="<?php echo esc_attr($o['id']); ?>" class="textlink b2b-pick"><?php echo t('Prijavite firmu'); ?></a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-paper2" style="border-top:1px solid var(--sage-line);">
  <div class="wrap">
    <span class="eyebrow"><?php echo t('Kako funkcioniše'); ?></span>
    <h2 class="display" style="font-size:clamp(26px,3.6vw,42px);margin-top:10px;font-weight:400;max-width:22ch;">
      <?php echo t('Od prijave do prvog feniranja za nekoliko dana'); ?>
    </h2>
    <div class="b2b-steps" style="margin-top:clamp(28px,3.5vw,48px);">
      <?php foreach ($steps as $s): ?>
      <div class="b2b-step">
        <span class="b2b-step-n"><?php echo esc_html($s['n']); ?></span>
        <h3><?php echo esc_html(t($s['t'])); ?></h3>
        <p class="muted"><?php echo esc_html(t($s['d'])); ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-oxblood" id="prijava">
  <div class="wrap b2b-form-wrap">
    <div>
      <span class="eyebrow on-dark"><?php echo t('Online registracija'); ?></span>
      <h2 class="display" style="font-size:clamp(28px,3.8vw,46px);font-weight:400;color:var(--cream);margin-top:12px;">
        <?php echo t('Registrujte firmu za nekoliko minuta'); ?>
      </h2>
      <p class="lead" style="margin-top:16px;font-size:17px;">
        <?php echo t('Popunite podatke o firmi i izaberite model. Javljamo vam se da potvrdimo detalje, a zatim zaposleni dobijaju svoje QR kodove.'); ?>
      </p>
      <p class="lead" style="margin-top:16px;font-size:17px;">
        <?php echo t('Za dodatna pitanja, pišite na'); ?> <a class="b2b-contact-line" href="<?php echo esc_url($mailto); ?>"><?php echo esc_html($biz['email']); ?></a>.
      </p>
    </div>

    <form class="b2b-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php if ($status === 'ok'): ?>
        <div class="b2b-msg ok" role="status"><?php echo t('Hvala! Prijava je primljena. Javljamo vam se uskoro da potvrdimo detalje.'); ?></div>
      <?php elseif ($status === 'greska'): ?>
        <div class="b2b-msg err" role="alert"><?php echo t('Proverite podatke: sva polja osim napomene su obavezna, a PIB ima 9 cifara.'); ?></div>
      <?php elseif ($status === 'cekaj'): ?>
        <div class="b2b-msg err" role="alert"><?php echo t('Prijava je upravo poslata. Sačekajte minut pre nove prijave.'); ?></div>
      <?php endif; ?>

      <input type="hidden" name="action" value="dry65_b2b_prijava">
      <input type="hidden" name="ts" value="" id="b2b-ts">
      <div class="f-hp" aria-hidden="true"><label for="b2b-website">Website</label><input type="text" name="website" id="b2b-website" tabindex="-1" autocomplete="off"></div>

      <div class="f-grid">
        <div class="f-full">
          <label for="b2b-company"><?php echo t('Naziv firme'); ?></label>
          <input type="text" id="b2b-company" name="company" required maxlength="190" autocomplete="organization">
        </div>
        <div>
          <label for="b2b-pib"><?php echo t('PIB'); ?></label>
          <input type="text" id="b2b-pib" name="pib" required inputmode="numeric" pattern="\d{9}" maxlength="9" title="<?php echo esc_attr(t('PIB ima 9 cifara')); ?>">
        </div>
        <div>
          <label for="b2b-employees"><?php echo t('Broj zaposlenih'); ?></label>
          <input type="number" id="b2b-employees" name="employees" required min="1" max="100000">
        </div>
        <div>
          <label for="b2b-contact"><?php echo t('Kontakt osoba'); ?></label>
          <input type="text" id="b2b-contact" name="contact" required maxlength="120" autocomplete="name">
        </div>
        <div>
          <label for="b2b-phone"><?php echo t('Telefon'); ?></label>
          <input type="tel" id="b2b-phone" name="phone" required maxlength="40" autocomplete="tel">
        </div>
        <div class="f-full">
          <label for="b2b-email"><?php echo t('Email'); ?></label>
          <input type="email" id="b2b-email" name="email" required maxlength="190" autocomplete="email">
        </div>
        <div class="f-full">
          <label for="b2b-model"><?php echo t('Model koji vas zanima'); ?></label>
          <select id="b2b-model" name="model" required>
            <?php foreach ($models as $k => $label): ?>
            <option value="<?php echo esc_attr($k); ?>"<?php selected($sel_model, $k); ?>><?php echo esc_html(t($label)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="f-full">
          <label for="b2b-note"><?php echo t('Napomena (nije obavezno)'); ?></label>
          <textarea id="b2b-note" name="note" maxlength="2000" placeholder="<?php echo esc_attr(t('Npr. željeni iznos vaučera, broj feniranja mesečno, rok…')); ?>"></textarea>
        </div>
      </div>

      <div class="f-foot">
        <p><?php echo t('Slanjem prijave ne obavezujete se ni na šta. Ponuda postaje aktivna tek kad je zajedno potvrdimo.'); ?></p>
        <button type="submit" class="btn btn-dark"><?php echo t('Pošalji prijavu'); ?></button>
      </div>
    </form>
  </div>
</section>

<script data-no-optimize="1">
  (function () {
    var ts = document.getElementById('b2b-ts');
    if (ts) ts.value = Math.floor(Date.now() / 1000);
    var sel = document.getElementById('b2b-model');
    document.querySelectorAll('.b2b-pick').forEach(function (a) {
      a.addEventListener('click', function () { if (sel) sel.value = a.getAttribute('data-model'); });
    });
  })();
</script>

<?php dry65_render_faq_section('za-firme', t('Pitanja firmi'), t('Najčešća pitanja o ponudama za firme.')); ?>

</main>

<?php get_footer(); ?>
