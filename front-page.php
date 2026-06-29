<?php
get_header();
$biz      = dry65_biz();
$services = dry65_services();
$lengths  = dry65_lengths();
$packages = dry65_packages();
$reviews  = dry65_reviews_smart();
$gallery  = dry65_gallery();
$tpl      = get_template_directory_uri();
$base_price = $lengths[0]['price']; // kratka = lowest "od" price
?>

<main class="page-enter">

<!-- ======================================================
     HERO
     ====================================================== -->
<section style="position:relative;overflow:hidden;" class="bg-paper2">
  <div class="wrap" style="padding-top:clamp(24px,3vw,48px);padding-bottom:clamp(40px,6vw,72px);">
    <div style="display:grid;grid-template-columns:1.05fr 0.95fr;gap:clamp(28px,4vw,56px);align-items:center;" class="hero-grid">

      <div>
        <h1 class="display caps" style="font-size:clamp(32px,4.8vw,64px);margin:0;line-height:1.04;letter-spacing:0.005em;color:var(--ink);font-weight:300;">
          Feniranje bez zakazivanja, Novi Beograd
        </h1>
        <h2 class="script" style="font-size:clamp(32px,4.2vw,54px);margin:24px 0 0;line-height:1.15;color:var(--clay);font-weight:400;">
          Samo dođeš
        </h2>
        <p class="lead" style="margin-top:28px;max-width:480px;">
          Dry65 je <strong>walk-in salon na Novom Beogradu, u West 65</strong>. Feniranje i stilizovanje kose, lokne, volumen ili ravno. Bez zakazivanja, samo dođeš.
        </p>
        <div class="btn-row" style="margin-top:34px;">
          <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener" class="btn btn-dark">
            Kako do nas <span class="arrow">→</span>
          </a>
          <a href="<?php echo esc_url(get_permalink(get_page_by_path('paketi'))); ?>" class="btn btn-outline">
            Mesečni paketi
          </a>
        </div>
      </div>

      <div style="position:relative;">
        <div style="border-radius:1000px;overflow:hidden;aspect-ratio:4/5;">
          <picture>
            <source media="(max-width: 860px)" srcset="<?php echo $tpl; ?>/assets/salon/s06-mobile.webp">
            <img src="<?php echo $tpl; ?>/assets/salon/s06.webp"
              alt="Dry65 walk-in salon na Novom Beogradu, feniranje bez zakazivanja"
              width="1200" height="1600"
              loading="eager" fetchpriority="high" decoding="async"
              style="width:100%;height:100%;object-fit:cover;object-position:center;display:block;">
          </picture>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ======================================================
     MARQUEE
     ====================================================== -->
<div style="overflow:hidden;white-space:nowrap;border-top:1px solid var(--sage-line);border-bottom:1px solid var(--sage-line);padding-block:22px;">
  <div class="marquee-track">
    <?php
    $items = ['Feniranje na talase', 'Bez zakazivanja', 'Feniranje na lokne', 'Walk-in salon', 'Nega kose', 'West65 · Novi Beograd',
              'Feniranje na volumen', 'Pranje i feniranje', 'Feniranje na cetke', 'Frizerski salon Novi Beograd'];
    foreach ($items as $it):
    ?>
      <span class="display" style="font-size:clamp(26px,3.4vw,44px);padding-inline:34px;display:inline-flex;align-items:center;gap:34px;">
        <?php echo esc_html($it); ?><span style="color:var(--clay);font-size:0.6em;">✦</span>
      </span>
    <?php endforeach; ?>
  </div>
</div>

<!-- ======================================================
     SERVICES PREVIEW
     ====================================================== -->
<section class="section">
  <div class="wrap">
    <div class="row" style="justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;margin-bottom:clamp(34px,4vw,56px);">
      <div>
        <span class="script" style="font-size:clamp(28px,3.4vw,40px);display:block;margin-bottom:2px;">Šta radimo</span>
        <h2 class="display" style="font-size:clamp(34px,5.2vw,64px);margin-top:6px;">Tri stvari. Sve oko feniranja.</h2>
      </div>
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('usluge'))); ?>" class="textlink">Pogledaj sve <span>→</span></a>
    </div>
    <div class="grid cols-3">
      <?php foreach ($services as $i => $s): $usl_url = get_permalink(get_page_by_path('usluge')); ?>
      <a href="<?php echo esc_url($usl_url); ?>" class="reveal card hover svc-card" style="height:100%;display:flex;flex-direction:column;color:inherit;text-decoration:none;" data-delay="<?php echo $i * 80; ?>">
        <div style="aspect-ratio:4/3;overflow:hidden;">
          <?php echo dry65_picture($s['img'], $s['title'], [
            'loading' => 'lazy',
            'style'   => 'width:100%;height:100%;object-fit:cover;display:block;',
          ]); ?>
        </div>
        <div style="padding:26px 24px 28px;display:flex;flex-direction:column;flex:1;">
          <span class="mono" style="color:var(--clay);"><?php echo esc_html($s['kicker']); ?></span>
          <h3 class="display" style="font-size:29px;margin-top:10px;"><?php echo esc_html($s['title']); ?></h3>
          <p class="muted" style="margin-top:12px;font-size:16px;flex:1;"><?php echo esc_html($s['short']); ?></p>
          <div style="margin-top:20px;">
            <span class="textlink">Saznaj više <span>→</span></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ======================================================
     GALLERY STRIP
     ====================================================== -->
<section class="section-sm bg-paper2">
  <div class="wrap">
    <div class="row" style="justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;margin-bottom:32px;">
      <div>
        <span class="script" style="font-size:clamp(28px,3.4vw,40px);display:block;margin-bottom:2px;">ambijent</span>
        <h2 class="display" style="font-size:clamp(34px,5.2vw,64px);margin-top:6px;">Kako izgleda kod nas.</h2>
      </div>
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('ambijent'))); ?>" class="textlink">Pogledaj sve <span>→</span></a>
    </div>
    <div class="grid cols-3">
      <?php foreach (array_slice($gallery, 0, 3) as $i => $g): ?>
      <div class="reveal" data-delay="<?php echo $i * 70; ?>">
        <div style="aspect-ratio:4/5;border-radius:var(--radius-lg);overflow:hidden;">
          <?php echo dry65_picture($g['img'], $g['tag'], [
            'loading' => 'lazy',
            'style'   => 'width:100%;height:100%;object-fit:cover;object-position:' . ($i === 0 ? 'center' : 'center top') . ';display:block;',
          ]); ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ======================================================
     PRICING TEASER
     ====================================================== -->
<section class="section bg-cream">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:0.9fr 1.1fr;gap:clamp(32px,5vw,72px);align-items:center;" class="hero-grid">
      <div class="reveal">
        <span class="script" style="font-size:clamp(28px,3.4vw,40px);display:block;margin-bottom:2px;">Cenovnik</span>
        <h2 class="display" style="font-size:clamp(34px,5.2vw,64px);margin-top:6px;">Cena feniranja prati dužinu kose.</h2>
        <p class="lead" style="margin-top:20px;">Cena prati dužinu Vaše kose. Sve ostalo je iskustvo koje pripada samo Vama.</p>
        <div style="margin-top:28px;">
          <a href="<?php echo esc_url(get_permalink(get_page_by_path('cenovnik'))); ?>" class="btn btn-dark">
            Ceo cenovnik <span class="arrow">→</span>
          </a>
        </div>
      </div>
      <div class="reveal stack" style="gap:0;background:var(--paper);border-radius:var(--radius-lg);border:1px solid var(--cream-deep);overflow:hidden;">
        <?php foreach ($lengths as $i => $l): ?>
        <div class="row" style="justify-content:space-between;padding:20px 26px;<?php echo $i < count($lengths) - 1 ? 'border-bottom:1px solid var(--sage-line);' : ''; ?>">
          <span class="row" style="gap:14px;">
            <span class="mono" style="color:var(--clay);"><?php echo str_pad($i + 1, 2, '0', STR_PAD_LEFT); ?></span>
            <span style="font-weight:500;font-size:19px;"><?php echo esc_html($l['label']); ?></span>
          </span>
          <span class="display num" style="font-size:30px;"><?php echo dry65_rsd($l['price']); ?><span class="u">din</span></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ======================================================
     PACKAGES TEASER
     ====================================================== -->
<section class="section bg-ink">
  <div class="wrap">
    <div class="center" style="margin-bottom:clamp(34px,4vw,56px);">
      <span class="script on-dark" style="font-size:clamp(28px,3.4vw,40px);display:block;margin-bottom:2px;">Mesečni paketi</span>
      <h2 class="display" style="font-size:clamp(34px,5.2vw,64px);margin-top:6px;">Jer zdrava kosa drži feniranje duže.</h2>
      <p class="lead" style="margin-top:20px;max-width:680px;margin-inline:auto;">
        Članstvo za klijentkinje kojima je feniranje deo nedeljne rutine. Mogućnost izbora između 4, 8 ili 12 feniranja mesečno, sa ili bez zakazivanja uz članstvo.
      </p>
    </div>
    <div class="grid cols-3">
      <?php foreach ($packages as $i => $p): ?>
      <div class="reveal" style="position:relative;" data-delay="<?php echo $i * 90; ?>">
        <?php if ($p['featured']): ?>
        <span class="chip" style="position:absolute;top:-15px;left:50%;transform:translateX(-50%);z-index:2;box-shadow:0 8px 18px -8px rgba(17,28,29,0.55);">Najpopularniji</span>
        <?php endif; ?>
        <a href="<?php echo esc_url(get_permalink(get_page_by_path('paketi'))); ?>" style="height:100%;display:flex;flex-direction:column;position:relative;text-decoration:none;color:inherit;">
          <img src="<?php echo esc_url(preg_match('#^https?://#', $p['img']) ? $p['img'] : $tpl . '/' . $p['img']); ?>" alt="<?php echo esc_attr($p['name']); ?> plan, Dry65" loading="lazy"
            class="rounded pkg-img" style="width:100%;aspect-ratio:820/990;object-fit:cover;display:block;">
          <div class="row" style="justify-content:space-between;align-items:baseline;margin-top:20px;gap:10px;">
            <span style="font-size:15px;"><?php echo esc_html($p['cadence']); ?></span>
            <span class="row" style="gap:6px;align-items:baseline;">
              <span style="font-size:13px;opacity:0.7;">od</span>
              <span class="display num" style="font-size:30px;color:var(--cream);"><?php echo dry65_rsd($base_price * $p['count']); ?><span class="u">din</span></span>
            </span>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="center" style="margin-top:clamp(36px,4vw,52px);">
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('paketi'))); ?>" class="btn btn-primary">
        Pogledaj pakete <span class="arrow">→</span>
      </a>
    </div>
  </div>
</section>

<!-- ======================================================
     REVIEWS
     ====================================================== -->
<section class="section">
  <div class="wrap">
    <?php $gmeta = dry65_google_meta(); ?>
    <div class="g-rating-bar" style="margin-bottom:clamp(28px,3.5vw,48px);">
      <div class="g-rating-badge">
        <svg class="g-rating-logo" viewBox="0 0 272 92" xmlns="http://www.w3.org/2000/svg" aria-label="Google">
          <path fill="#EA4335" d="M115.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18C71.25 34.32 81.24 25 93.5 25s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44S80.99 39.2 80.99 47.18c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z"/>
          <path fill="#FBBC05" d="M163.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18c0-12.85 9.99-22.18 22.25-22.18s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44s-12.51 5.46-12.51 13.44c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z"/>
          <path fill="#4285F4" d="M209.75 26.34v39.82c0 16.38-9.66 23.07-21.08 23.07-10.75 0-17.22-7.19-19.66-13.07l8.48-3.53c1.51 3.61 5.21 7.87 11.17 7.87 7.31 0 11.84-4.51 11.84-13v-3.19h-.34c-2.18 2.69-6.38 5.04-11.68 5.04-11.09 0-21.25-9.66-21.25-22.09 0-12.52 10.16-22.26 21.25-22.26 5.29 0 9.49 2.35 11.68 4.96h.34v-3.61h9.25zm-8.56 20.92c0-7.81-5.21-13.52-11.84-13.52-6.72 0-12.35 5.71-12.35 13.52 0 7.73 5.63 13.36 12.35 13.36 6.63 0 11.84-5.63 11.84-13.36z"/>
          <path fill="#34A853" d="M225 3v65h-9.5V3h9.5z"/>
          <path fill="#EA4335" d="M262.02 54.48l7.56 5.04c-2.44 3.61-8.32 9.83-18.48 9.83-12.6 0-22.01-9.74-22.01-22.18 0-13.19 9.49-22.18 20.92-22.18 11.51 0 17.14 9.16 18.98 14.11l1.01 2.52-29.65 12.28c2.27 4.45 5.8 6.72 10.75 6.72 4.96 0 8.4-2.44 10.92-6.14zm-23.27-7.98l19.82-8.23c-1.09-2.77-4.37-4.7-8.23-4.7-4.95 0-11.84 4.37-11.59 12.93z"/>
          <path fill="#4285F4" d="M35.29 41.41V32H67c.31 1.64.47 3.58.47 5.68 0 7.06-1.93 15.79-8.15 22.01-6.05 6.3-13.78 9.66-24.02 9.66C16.32 69.35.36 53.89.36 34.91.36 15.93 16.32.47 35.3.47c10.5 0 17.98 4.12 23.6 9.49l-6.64 6.64c-4.03-3.78-9.49-6.72-16.97-6.72-13.86 0-24.7 11.17-24.7 25.03 0 13.86 10.84 25.03 24.7 25.03 8.99 0 14.11-3.61 17.39-6.89 2.66-2.66 4.41-6.46 5.1-11.65l-22.49.01z"/>
        </svg>
      </div>
      <div class="g-rating-row">
        <span class="g-rating-stars" aria-hidden="true">
          <?php
          $r = $gmeta['rating'] ?: 5.0;
          for ($i = 1; $i <= 5; $i++) {
            $fill = $r >= $i ? 'full' : ($r > $i - 1 ? 'half' : 'empty');
            echo '<span class="g-star g-' . $fill . '">★</span>';
          }
          ?>
        </span>
        <?php if ($gmeta['total'] > 0): ?>
        <span class="g-rating-count"><?php echo number_format($gmeta['total']); ?> recenzija</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="reviews-slider-wrap">
      <button class="rs-arrow rs-prev" aria-label="Prethodna">‹</button>
      <div class="reviews-slider" id="reviews-slider">
        <?php foreach ($reviews as $i => $r):
          $initial = function_exists('mb_substr') ? mb_substr($r['name'], 0, 1, 'UTF-8') : substr($r['name'], 0, 1);
        ?>
        <article class="rs-card">
          <div class="rs-stars" aria-label="<?php echo (int)$r['rating']; ?>/5">
            <?php echo str_repeat('★', (int)$r['rating']); ?><?php echo str_repeat('☆', 5 - (int)$r['rating']); ?>
          </div>
          <p class="rs-text"><?php echo esc_html($r['text']); ?></p>
          <div class="rs-foot">
            <?php if (!empty($r['photo'])): ?>
              <img class="rs-avatar" src="<?php echo esc_url($r['photo']); ?>" alt="<?php echo esc_attr($r['name']); ?>" loading="lazy">
            <?php else: ?>
              <span class="rs-avatar rs-avatar-letter"><?php echo esc_html($initial); ?></span>
            <?php endif; ?>
            <div class="rs-author">
              <span class="rs-name"><?php echo esc_html($r['name']); ?></span>
              <span class="rs-when"><?php echo esc_html($r['when']); ?></span>
            </div>
            <span class="rs-google" role="img" aria-label="Google review">
              <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.69-2.26 1.1-3.71 1.1-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.69-.35-1.43-.35-2.09s.13-1.4.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            </span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <button class="rs-arrow rs-next" aria-label="Sledeća">›</button>
    </div>

    <div class="center" style="margin-top:40px;">
      <a href="<?php echo esc_url($biz['reviews_url']); ?>" target="_blank" rel="noopener" class="btn btn-outline">
        Pogledaj na Google-u <span class="arrow">→</span>
      </a>
    </div>
  </div>
</section>


<!-- ======================================================
     HOURS CTA
     ====================================================== -->
<section class="section bg-oxblood">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(32px,5vw,64px);align-items:center;" class="hero-grid">
      <div class="reveal">
        <span class="eyebrow on-dark">Radno vreme</span>
        <div style="margin-top:26px;">
          <?php foreach ($biz['hours'] as $i => $h): ?>
          <div class="row" style="justify-content:space-between;padding:14px 0;border-bottom:1px solid rgba(242,225,190,0.22);">
            <span style="font-size:18px;"><?php echo esc_html($h['day']); ?></span>
            <span class="display" style="font-size:24px;"><?php echo esc_html($h['time']); ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="reveal">
        <h2 class="display" style="font-size:clamp(34px,5vw,58px);">
          Ne moraš da zakažeš.<br>Samo svrati.
        </h2>
        <p class="lead" style="margin-top:18px;"><?php echo esc_html($biz['address']); ?></p>
        <div class="btn-row" style="margin-top:28px;">
          <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener" class="btn btn-ghost-light">
            Kako do nas <span class="arrow">→</span>
          </a>
          <a href="tel:<?php echo esc_attr($biz['phone']); ?>" class="btn btn-ghost-light">
            <?php echo esc_html($biz['phone_display']); ?>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>


</main>

<?php get_footer(); ?>
