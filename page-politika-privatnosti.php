<?php
/*
Template Name: Politika privatnosti
*/
get_header();
$biz  = dry65_biz();
$updated = 'septembar 2026.';
?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">Pravno</span>
    <h1 class="display caps" style="font-size:clamp(28px,3.8vw,48px);margin-top:4px;max-width:34ch;line-height:1.05;letter-spacing:0.01em;">
      Politika privatnosti
    </h1>
    <p class="lead" style="margin-top:20px;max-width:680px;">
      Ova politika objašnjava koje podatke o ličnosti prikupljamo, zašto ih prikupljamo i koja su tvoja prava. Poslednje ažuriranje: <?php echo esc_html($updated); ?>
    </p>
  </div>
</section>

<section class="section">
  <div class="wrap legal" style="max-width:800px;">

    <style>
      .legal h2{font-size:clamp(20px,2.4vw,26px);margin:38px 0 12px;font-weight:600;letter-spacing:0.01em;}
      .legal h2:first-of-type{margin-top:0;}
      .legal p{margin:0 0 14px;line-height:1.7;color:var(--ink,#2b2723);}
      .legal ul{margin:0 0 16px;padding-left:22px;}
      .legal li{margin:0 0 8px;line-height:1.65;}
      .legal a{color:var(--oxblood,#7a2e2e);text-decoration:underline;text-underline-offset:2px;}
      .legal strong{font-weight:600;}
    </style>

    <h2>Ko rukuje tvojim podacima</h2>
    <p>
      Rukovalac podataka je <strong><?php echo esc_html($biz['name']); ?></strong>, frizerski salon specijalizovan za feniranje.
    </p>
    <ul>
      <li>Adresa: <?php echo esc_html($biz['address']); ?></li>
      <li>Telefon: <a href="tel:<?php echo esc_attr($biz['phone']); ?>"><?php echo esc_html($biz['phone_display']); ?></a></li>
      <li>Email: <a href="mailto:<?php echo esc_attr($biz['email']); ?>"><?php echo esc_html($biz['email']); ?></a></li>
    </ul>

    <h2>Koje podatke prikupljamo</h2>
    <ul>
      <li><strong>Podatke koje nam sam ostaviš</strong> (ime i prezime, broj telefona, email) kada nas kontaktiraš preko sajta, telefonom, mejlom ili kada se prijaviš na oglas za posao.</li>
      <li><strong>Podatke iz prijava za posao</strong> koje popuniš u formularu na našim oglasima na Facebook-u i Instagramu (kontakt i odgovori na pitanja iz prijave).</li>
      <li><strong>Podatke o poseti sajtu</strong> (IP adresa, tip uređaja i pregledača, stranice koje posećuješ) koje prikupljaju analitički alati radi statistike i poboljšanja sajta.</li>
      <li><strong>Recenzije</strong> koje javno ostaviš o nama na Google-u i koje možemo prikazivati na sajtu.</li>
    </ul>

    <h2>Zašto prikupljamo podatke</h2>
    <ul>
      <li>Da odgovorimo na tvoj upit i pružimo uslugu koju tražiš.</li>
      <li>Da obradimo tvoju prijavu za posao i kontaktiramo te u vezi sa zapošljavanjem.</li>
      <li>Da vodimo statistiku posete i unapredimo sadržaj i rad sajta.</li>
      <li>Da prikažemo utiske i recenzije zadovoljnih klijenata.</li>
    </ul>
    <p>
      Pravni osnov za obradu je tvoja saglasnost, naš legitimni interes za rad i unapređenje poslovanja, odnosno preduzimanje radnji pre zasnivanja radnog odnosa kada se prijaviš na posao.
    </p>

    <h2>Prijave za posao preko Facebook i Instagram oglasa</h2>
    <p>
      Kada se prijaviš na naš oglas za posao putem formulara na Facebook-u ili Instagramu, kompanija Meta nam prosleđuje podatke koje si uneo u formular. Te podatke koristimo isključivo u svrhu procesa zapošljavanja. Ne prodajemo ih i ne koristimo za marketing.
    </p>

    <h2>Kome prosleđujemo podatke</h2>
    <p>
      Podatke ne prodajemo. Delimo ih samo sa pouzdanim partnerima koji nam pomažu u radu, u meri koja je neophodna:
    </p>
    <ul>
      <li><strong>Meta Platforms Ireland</strong> (Facebook, Instagram), za oglase i prijave putem formulara. <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener">Politika privatnosti</a>.</li>
      <li><strong>Google</strong> (analitika posete i prikaz recenzija). <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Politika privatnosti</a>.</li>
    </ul>

    <h2>Kolačići</h2>
    <p>
      Sajt koristi kolačiće koji su neophodni za rad, kao i analitičke kolačiće za statistiku posete. Kolačiće možeš da onemogućiš ili obrišeš u podešavanjima svog pregledača. Isključivanje pojedinih kolačića može uticati na funkcionalnost sajta.
    </p>

    <h2>Koliko dugo čuvamo podatke</h2>
    <p>
      Podatke čuvamo onoliko dugo koliko je potrebno za svrhu za koju su prikupljeni ili koliko nalaže zakon. Prijave za posao čuvamo do popune pozicije, odnosno u razumnom roku nakon toga, osim ako se ne dogovorimo drugačije.
    </p>

    <h2>Tvoja prava</h2>
    <p>U skladu sa Zakonom o zaštiti podataka o ličnosti, imaš pravo da:</p>
    <ul>
      <li>zatražiš pristup podacima koje imamo o tebi,</li>
      <li>tražiš ispravku netačnih ili dopunu nepotpunih podataka,</li>
      <li>tražiš brisanje podataka,</li>
      <li>tražiš ograničenje obrade ili uložiš prigovor na obradu,</li>
      <li>povučeš saglasnost u svakom trenutku,</li>
      <li>podneseš pritužbu Povereniku za informacije od javnog značaja i zaštitu podataka o ličnosti.</li>
    </ul>
    <p>
      Za ostvarivanje bilo kog prava, piši nam na <a href="mailto:<?php echo esc_attr($biz['email']); ?>"><?php echo esc_html($biz['email']); ?></a>.
    </p>

    <h2>Izmene ove politike</h2>
    <p>
      Politiku privatnosti možemo povremeno ažurirati. Aktuelna verzija je uvek dostupna na ovoj stranici, uz datum poslednjeg ažuriranja.
    </p>

  </div>
</section>

</main>
<?php get_footer(); ?>
