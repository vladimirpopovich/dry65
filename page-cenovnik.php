<?php
/*
Template Name: Cenovnik
*/
get_header();
$pricing = dry65_pricing();
$lengths = dry65_lengths();
$biz     = dry65_biz();
$tpl     = get_template_directory_uri();

// Build lengths array for JS data attribute
$lengths_json = json_encode(array_map(fn($l) => [
    'id' => $l['id'], 'label' => $l['label'], 'price' => $l['price']
], $lengths));
?>

<?php
// Schema: Service catalog za rich results
$catalog = [
    '@context' => 'https://schema.org',
    '@type' => 'OfferCatalog',
    'name' => 'Cenovnik Dry65 — Feniranje',
    'itemListElement' => [],
];
foreach ($pricing['styling']['rows'] as $row) {
    foreach ($lengths as $i => $l) {
        $catalog['itemListElement'][] = [
            '@type' => 'Offer',
            'itemOffered' => ['@type' => 'Service', 'name' => $row['name'] . ', ' . $l['label'] . ' kosa'],
            'price' => $row['prices'][$i] ?? 0,
            'priceCurrency' => 'RSD',
            'availability' => 'https://schema.org/InStock',
        ];
    }
}
echo '<script type="application/ld+json">' . wp_json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">Cenovnik</span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:28ch;line-height:1.0;letter-spacing:0.01em;">
      Cene feniranja u Dry65
    </h1>
    <p class="lead" style="margin-top:26px;max-width:680px;">
      Cena pranja i feniranja, stilizovanja (talasi, lokne, ravno, volumen) i tretmana nege kose.
    </p>
  </div>
</section>

<!-- Length selector + Feniranje table — one continuous section -->
<section class="section" id="pricing-guide">
  <div class="wrap">

    <!-- Length visual guide -->
    <div class="center" style="max-width:640px;margin:0 auto clamp(24px,3vw,36px);">
      <span class="script" style="font-size:clamp(26px,3.2vw,40px);display:block;">koja je tvoja dužina kose?</span>
      <p class="muted" style="margin-top:8px;font-size:15px;">
        Klikni na svoju dužinu kose i istaknućemo cenu kroz ceo cenovnik.
      </p>
    </div>
    <div class="length-guide" id="length-guide" data-lengths="<?php echo esc_attr($lengths_json); ?>">
      <?php foreach ($lengths as $l): ?>
      <button class="length-card"
          data-id="<?php echo esc_attr($l['id']); ?>" aria-pressed="false">
        <span class="length-oval">
          <img src="<?php echo esc_url($l['img']); ?>" alt="<?php echo esc_attr($l['label']); ?>, dužina kose" loading="lazy">
        </span>
        <span class="length-label"><?php echo esc_html($l['label']); ?></span>
        <span class="length-price"><?php echo dry65_rsd($l['price']); ?> <span class="u">din</span></span>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Feniranje i stilizovanje — directly below selector -->
    <div style="margin-top:clamp(32px,4vw,48px);">
      <span class="script" style="font-size:clamp(28px,3.4vw,40px);display:block;margin-bottom:2px;"><?php echo esc_html($pricing['styling']['kicker']); ?></span>
      <h2 class="display" style="font-size:clamp(34px,5.2vw,64px);margin-top:6px;"><?php echo esc_html($pricing['styling']['title']); ?></h2>
    </div>

    <div class="price-card" style="margin-top:clamp(20px,2.4vw,30px);">
      <!-- Desktop / tablet tabela -->
      <div class="ptable-desktop" style="overflow-x:auto;">
        <table class="ptable collapsible" id="styling-table">
          <thead>
            <tr>
              <th></th>
              <?php foreach ($lengths as $i => $l): ?>
              <th class="pcol" data-col="<?php echo esc_attr($l['id']); ?>">
                <?php echo esc_html($l['label']); ?>
              </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pricing['styling']['rows'] as $r): ?>
            <tr>
              <td>
                <span class="svc-name"><?php echo esc_html($r['name']); ?></span>
                <?php if (!empty($r['note'])): ?>
                <span class="svc-note"><?php echo esc_html($r['note']); ?></span>
                <?php endif; ?>
              </td>
              <?php foreach ($lengths as $i => $l): ?>
              <td class="pcell" data-col="<?php echo esc_attr($l['id']); ?>">
                <span class="price"><?php echo dry65_rsd($r['prices'][$i]); ?><span class="u">din</span></span>
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile lista: grupisano po usluzi -->
      <div class="ptable-mobile">
        <?php foreach ($pricing['styling']['rows'] as $r): ?>
        <div class="pmob-group">
          <h4 class="pmob-title"><?php echo esc_html($r['name']); ?></h4>
          <?php if (!empty($r['note'])): ?>
          <p class="pmob-note"><?php echo esc_html($r['note']); ?></p>
          <?php endif; ?>
          <ul class="pmob-list">
            <?php foreach ($lengths as $i => $l): ?>
            <li>
              <span class="pmob-len"><?php echo esc_html($l['label']); ?></span>
              <span class="pmob-price num"><?php echo dry65_rsd($r['prices'][$i]); ?> <span class="u">din</span></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Samo pranje -->
    <div class="price-card" style="margin-top:clamp(18px,2.4vw,30px);">
      <div class="row" style="justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:10px;">
        <h3 class="display" style="font-size:26px;"><?php echo esc_html($pricing['wash']['title']); ?></h3>
      </div>
      <p class="muted" style="font-size:15px;margin-top:8px;max-width:620px;line-height:1.55;">
        Za sve koje danas nemaju vremena, ne stižu, ili jednostavno žele da neko drugi to uradi za njih. Pranje, ispiranje i blago sušenje, uvek ste dobrodošle.
      </p>
      <div style="margin-top:18px;">
        <?php foreach ($pricing['wash']['rows'] as $r): ?>
        <div class="price-flat-row">
          <span class="svc-name"><?php echo esc_html($r['name']); ?></span>
          <span class="price"><?php echo dry65_rsd($r['price']); ?><span class="u">din</span></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</section>

<!-- 2. Afro i nadogradnje -->
<section class="section bg-cream">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.4vw,40px);display:block;margin-bottom:2px;"><?php echo esc_html($pricing['density']['kicker']); ?></span>
    <h2 class="display" style="font-size:clamp(34px,5.2vw,64px);margin-top:6px;"><?php echo esc_html($pricing['density']['title']); ?></h2>
    <div class="price-card" style="margin-top:clamp(26px,3vw,40px);">
      <!-- Desktop tabela -->
      <div class="ptable-desktop" style="overflow-x:auto;">
        <table class="ptable">
          <thead>
            <tr>
              <th></th>
              <?php foreach ($pricing['density']['cols'] as $c): ?>
              <th class="pcol"><?php echo esc_html($c); ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pricing['density']['rows'] as $r): ?>
            <tr>
              <td><span class="svc-name"><?php echo esc_html($r['name']); ?></span></td>
              <?php foreach ($r['prices'] as $p): ?>
              <td class="pcell"><span class="price"><?php echo dry65_rsd($p); ?><span class="u">din</span></span></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile lista: grupisano po usluzi -->
      <div class="ptable-mobile">
        <?php foreach ($pricing['density']['rows'] as $r): ?>
        <div class="pmob-group">
          <h4 class="pmob-title"><?php echo esc_html($r['name']); ?></h4>
          <ul class="pmob-list">
            <?php foreach ($pricing['density']['cols'] as $i => $col): ?>
            <li>
              <span class="pmob-len"><?php echo esc_html($col); ?></span>
              <span class="pmob-price num"><?php echo dry65_rsd($r['prices'][$i]); ?> <span class="u">din</span></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php foreach ($pricing['density']['notes'] as $note): ?>
    <p class="muted" style="font-size:14px;margin-top:16px;padding-left:16px;border-left:2px solid var(--clay);line-height:1.5;">
      <?php echo esc_html($note); ?>
    </p>
    <?php endforeach; ?>
  </div>
</section>

<!-- 3. Nega i održavanje -->
<section class="section">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.4vw,40px);display:block;margin-bottom:2px;"><?php echo esc_html($pricing['care']['kicker']); ?></span>
    <h2 class="display" style="font-size:clamp(34px,5.2vw,64px);margin-top:6px;"><?php echo esc_html($pricing['care']['title']); ?></h2>
    <p class="lead" style="margin-top:20px;">Verujemo da je nega podjednako važna kao izgled. Uz mesečne pakete ovi tretmani idu na poklon.</p>
    <div class="grid cols-3" style="margin-top:clamp(26px,3vw,40px);align-items:start;">
      <?php foreach ($pricing['care']['groups'] as $g): ?>
      <div class="price-card">
        <h3 class="display" style="font-size:23px;margin-bottom:10px;"><?php echo esc_html($g['group']); ?></h3>
        <?php foreach ($g['items'] as $it): ?>
        <div class="price-flat-row">
          <span class="svc-name" style="font-size:16px;"><?php echo esc_html($it['name']); ?></span>
          <span class="price" style="font-size:22px;"><?php echo dry65_rsd($it['price']); ?><span class="u">din</span></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="muted" style="font-size:14px;margin-top:16px;padding-left:16px;border-left:2px solid var(--clay);line-height:1.5;">
      <?php echo esc_html($pricing['care']['note']); ?>
    </p>
  </div>
</section>

<!-- CTA -->
<section class="section-sm bg-ink">
  <div class="wrap center">
    <span class="script on-dark" style="font-size:clamp(28px,3.6vw,44px);display:block;">jer zdrava kosa drži feniranje duže</span>
    <h2 class="display" style="font-size:clamp(28px,4vw,46px);margin-top:8px;">Feniraš redovno? Uzmi paket.</h2>
    <p class="lead" style="margin-top:14px;max-width:560px;margin-inline:auto;">
      Uz pakete dobijaš niže cene po feniranju i profesionalan tretman nege na poklon.
    </p>
    <div class="btn-row" style="justify-content:center;margin-top:28px;">
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('paketi'))); ?>" class="btn btn-primary">
        Mesečni paketi <span class="arrow">→</span>
      </a>
      <a href="tel:<?php echo esc_attr($biz['phone']); ?>" class="btn btn-ghost-light">
        <?php echo esc_html($biz['phone_display']); ?>
      </a>
    </div>
    <p class="muted" style="font-size:13px;margin-top:26px;">
      Za firme i poslovne pakete preko fakture, javite nam se za ponudu.
    </p>
  </div>
</section>

<!-- ======================================================
     LENGTH PRICE MODAL (otvara se klikom na length-card)
     ====================================================== -->
<?php
$styling_rows = $pricing['styling']['rows'];
$lengths_data = [];
foreach ($lengths as $i => $l) {
    $lengths_data[$l['id']] = ['label' => $l['label'], 'index' => $i, 'price' => $l['price']];
}
?>
<div class="lp-modal" id="lp-modal" aria-hidden="true" role="dialog" aria-labelledby="lp-title" data-lengths='<?php echo esc_attr(json_encode($lengths_data)); ?>'>
  <div class="lp-backdrop" data-lp-close></div>
  <div class="lp-sheet" role="document">
    <div class="lp-handle" aria-hidden="true"></div>
    <button type="button" class="lp-close" data-lp-close aria-label="Zatvori">×</button>
    <div class="lp-head">
      <span class="lp-kicker"><?php echo esc_html($pricing['styling']['title']); ?></span>
      <h3 class="display lp-title" id="lp-title">—</h3>
      <p class="lp-sub muted">Cene za izabranu dužinu kose</p>
    </div>
    <ul class="lp-list" id="lp-list">
      <?php foreach ($styling_rows as $row): ?>
      <li class="lp-item" data-prices='<?php echo esc_attr(json_encode(array_values($row['prices']))); ?>'>
        <span class="lp-name"><?php echo esc_html($row['name']); ?></span>
        <span class="lp-price num">—</span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

</main>

<?php get_footer(); ?>
