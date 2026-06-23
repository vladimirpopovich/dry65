<?php
/*
Template Name: Paketi
*/
get_header();
$packages = dry65_packages();
$lengths  = dry65_lengths();
$biz      = dry65_biz();
$tpl      = get_template_directory_uri();
$lengths_json = json_encode(array_map(fn($l) => [
    'id' => $l['id'], 'label' => $l['label'], 'price' => $l['price']
], $lengths));
?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">Mesečni paketi</span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:28ch;line-height:1.0;letter-spacing:0.01em;">
      Mesečni paketi feniranja — Dry65 Novi Beograd
    </h1>
    <p class="lead" style="margin-top:26px;max-width:680px;">
      Članstvo za klijentkinje kojima je feniranje deo nedeljne rutine. Mogućnost izbora između 4, 8 ili 12 feniranja mesečno, sa ili bez zakazivanja uz članstvo. Cena prati dužinu kose. Uz svaki paket sledi masaža glave i profesionalni tretman nege kose.
    </p>
  </div>
</section>

<section class="section">
  <div class="wrap">

    <!-- =========================
         DESKTOP / TABLET LAYOUT
         ========================= -->
    <div class="pkg-desktop">
      <!-- 1) Slike paketa -->
      <div class="grid cols-3" id="pkg-grid">
        <?php foreach ($packages as $i => $p): ?>
        <div style="position:relative;">
          <?php if ($p['featured']): ?>
          <span class="chip" style="position:absolute;top:-15px;left:50%;transform:translateX(-50%);z-index:2;box-shadow:0 8px 18px -8px rgba(17,28,29,0.55);">Najpopularniji</span>
          <?php endif; ?>
          <img src="<?php echo esc_url(preg_match('#^https?://#', $p['img']) ? $p['img'] : $tpl . '/' . $p['img']); ?>" alt="<?php echo esc_attr($p['name']); ?> plan, Dry65" loading="lazy"
            class="rounded" style="width:100%;aspect-ratio:820/990;object-fit:cover;display:block;box-shadow:<?php echo $p['featured'] ? '0 30px 54px -30px rgba(17,28,29,0.5)' : '0 20px 40px -30px rgba(17,28,29,0.4)'; ?>;">
        </div>
        <?php endforeach; ?>
      </div>

      <!-- 2) Length selector -->
      <div class="center" style="margin:clamp(36px,4.5vw,56px) auto clamp(24px,3vw,36px);">
        <div class="mono" style="color:var(--clay);margin-bottom:14px;font-size:12px;letter-spacing:0.1em;text-transform:uppercase;">Izaberi dužinu kose</div>
        <div class="length-tabs pkg-tabs-d" data-lengths="<?php echo esc_attr($lengths_json); ?>">
          <?php foreach ($lengths as $l): ?>
          <button class="length-tab<?php echo $l['id'] === 'kratka' ? ' sel' : ''; ?>" data-id="<?php echo esc_attr($l['id']); ?>">
            <?php echo esc_html($l['label']); ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 3) Cene + benefits -->
      <div class="grid cols-3">
        <?php foreach ($packages as $i => $p):
          $monthly = $lengths[0]['price'] * $p['count']; // default: kratka
        ?>
        <div style="display:flex;flex-direction:column;">
          <div style="display:flex;align-items:baseline;gap:8px;">
            <span style="font-size:14px;color:var(--muted);">od</span>
            <span class="display pkg-price" style="font-size:clamp(38px,4.6vw,50px);color:var(--oxblood);"
              data-count="<?php echo esc_attr($p['count']); ?>"><?php echo dry65_rsd($monthly); ?></span>
            <span class="u" style="margin-left:6px;">din / mesečno</span>
          </div>
          <p class="row" style="gap:8px;margin-top:18px;font-size:14.5px;color:var(--oxblood);">
            <span style="color:var(--clay);">✦</span>
            <span>Masaža glave uz svako pranje</span>
          </p>
          <p class="row" style="gap:8px;margin-top:8px;font-size:14.5px;color:var(--oxblood);">
            <span style="color:var(--clay);">✦</span>
            <span><?php echo esc_html($p['gift']); ?></span>
          </p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- =========================
         MOBILE LAYOUT
         ========================= -->
    <div class="pkg-mobile" id="pkg-mobile">
      <!-- Sticky length selector -->
      <div class="pkg-mobile-sticky">
        <div class="mono" style="color:var(--clay);margin-bottom:10px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;text-align:center;">Izaberi dužinu kose</div>
        <div class="length-tabs pkg-tabs-m" data-lengths="<?php echo esc_attr($lengths_json); ?>">
          <?php foreach ($lengths as $l): ?>
          <button class="length-tab<?php echo $l['id'] === 'kratka' ? ' sel' : ''; ?>" data-id="<?php echo esc_attr($l['id']); ?>">
            <?php echo esc_html($l['label']); ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Kartice: slika + cena + benefits, sve zajedno -->
      <div class="pkg-mobile-cards">
        <?php foreach ($packages as $i => $p):
          $monthly = $lengths[0]['price'] * $p['count'];
        ?>
        <div class="pkg-mobile-card" style="position:relative;">
          <?php if ($p['featured']): ?>
          <span class="chip" style="position:absolute;top:-15px;left:50%;transform:translateX(-50%);z-index:2;box-shadow:0 8px 18px -8px rgba(17,28,29,0.55);">Najpopularniji</span>
          <?php endif; ?>
          <img src="<?php echo esc_url(preg_match('#^https?://#', $p['img']) ? $p['img'] : $tpl . '/' . $p['img']); ?>" alt="<?php echo esc_attr($p['name']); ?> plan, Dry65" loading="lazy"
            class="rounded" style="width:100%;aspect-ratio:820/990;object-fit:cover;display:block;box-shadow:<?php echo $p['featured'] ? '0 30px 54px -30px rgba(17,28,29,0.5)' : '0 20px 40px -30px rgba(17,28,29,0.4)'; ?>;">
          <div style="margin-top:20px;">
            <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">
              <span style="font-size:14px;color:var(--muted);">od</span>
              <span class="display pkg-price" style="font-size:clamp(36px,9vw,50px);color:var(--oxblood);"
                data-count="<?php echo esc_attr($p['count']); ?>"><?php echo dry65_rsd($monthly); ?></span>
              <span class="u" style="margin-left:6px;">din / mesečno</span>
            </div>
            <p class="row" style="gap:8px;margin-top:14px;font-size:14.5px;color:var(--oxblood);">
              <span style="color:var(--clay);">✦</span>
              <span>Masaža glave uz svako pranje</span>
            </p>
            <p class="row" style="gap:8px;margin-top:8px;font-size:14.5px;color:var(--oxblood);">
              <span style="color:var(--clay);">✦</span>
              <span><?php echo esc_html($p['gift']); ?></span>
            </p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="center" style="margin-top:clamp(40px,5vw,64px);max-width:640px;margin-inline:auto;">
      <p class="muted" style="font-size:15px;">
        Paketi se kupuju u salonu. Profesionalni tretmani dubinske nege, vredni i do 6.000 dinara, uključeni su u svako članstvo. Zdrava kosa drži feniranje duže.
      </p>
      <div class="btn-row" style="justify-content:center;margin-top:24px;">
        <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener" class="btn btn-dark">
          Kako do nas <span class="arrow">→</span>
        </a>
      </div>
    </div>

  </div>
</section>

</main>

<?php get_footer(); ?>
