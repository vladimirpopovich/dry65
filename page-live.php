<?php
/*
Template Name: Uživo (Live status)
*/

// Ova stranica NE sme da bude keširana (status se menja u realnom vremenu).
if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
do_action('litespeed_control_set_nocache', 'dry65 live status page');
nocache_headers();

$st  = dry65_live_resolve();
$biz = dry65_biz();

get_header();
?>

<main class="page-enter">
<section class="section" style="min-height:40vh;display:flex;align-items:flex-start;padding-top:clamp(16px,3vw,32px);">
  <div class="wrap" style="width:100%;">

    <div class="live-card" data-tier="<?php echo esc_attr($st['tier']); ?>" id="live-card">
      <p class="live-eyebrow" id="live-eyebrow"<?php echo $st['eyebrow'] === '' ? ' style="display:none;"' : ''; ?>><?php echo esc_html($st['eyebrow']); ?></p>

      <div class="live-ring" id="live-ring">
        <svg class="live-check" id="live-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"<?php echo $st['is_free'] ? '' : ' style="display:none;"'; ?>><path d="M4 12.5l5 5 11-11"></path></svg>
        <svg class="live-closed-icon" id="live-closed-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"<?php echo $st['tier'] === 'closed' ? '' : ' style="display:none;"'; ?>><path d="M6 12h12"></path></svg>
        <span class="live-ring-num" id="live-ring-num"<?php echo ($st['is_free'] || $st['ring_num'] === '') ? ' style="display:none;"' : ''; ?>><?php echo esc_html($st['ring_num']); ?></span>
        <span class="live-ring-unit" id="live-ring-unit"<?php echo ($st['is_free'] || $st['ring_num'] === '') ? ' style="display:none;"' : ''; ?>>minuta</span>
      </div>

      <h1 class="display live-headline" id="live-headline"><?php echo esc_html($st['headline']); ?></h1>

      <p class="lead live-sub" id="live-sub"><?php echo esc_html($st['sub']); ?></p>

      <?php
      $live_staff_text  = dry65_live_staff_text(get_option('dry65_live_staff', []));
      $live_chairs_show = get_option('dry65_live_chairs_show', '0') === '1';
      $live_chairs_vis  = $live_chairs_show && $live_staff_text !== '' && !$st['closed'];
      ?>
      <p class="live-chairs" id="live-chairs"<?php echo $live_chairs_vis ? '' : ' style="display:none;"'; ?>><?php echo esc_html($live_staff_text); ?></p>

      <?php if ($st['stale']): ?>
      <p class="live-stale" id="live-stale">Podaci možda nisu ažurni. Pozovite <?php echo esc_html($biz['phone_display']); ?> pre dolaska.</p>
      <?php else: ?>
      <p class="live-stale" id="live-stale" style="display:none;"></p>
      <?php endif; ?>

      <div class="live-viewers" id="live-viewers" style="display:none;" title="Broj ljudi koji trenutno gledaju ovu stranicu">
        <svg class="live-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg><span id="live-viewers-text"></span>
      </div>

      <p class="live-foot" id="live-foot"<?php echo empty($st['footnote']) ? ' style="display:none;"' : ''; ?>><?php echo esc_html($st['footnote']); ?></p>

    </div>

    <div class="btn-row live-cta" style="justify-content:center;flex-wrap:wrap;">
      <a href="<?php echo esc_url($biz['maps_url']); ?>" target="_blank" rel="noopener" class="btn btn-dark">Kako do nas <span class="arrow">→</span></a>
      <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $biz['phone'])); ?>" class="btn btn-ghost">Pozovi</a>
    </div>

  </div>
</section>

<?php // FAQ specifičan za /live (čekanje + walk-in) — skroz na dnu, jedinstven tekst za SEO/AI
dry65_render_faq_section('live', 'Česta pitanja o čekanju', 'Kako radi walk-in feniranje u Dry65 i koliko se čeka.'); ?>
</main>

<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@600;700&display=swap');
  .live-card {
    max-width: 640px;
    margin: 0 auto;
    text-align: center;
    padding: clamp(18px,3vw,30px) clamp(20px,4vw,44px) clamp(30px,5vw,52px);
    border-radius: 22px;
    background: #ffffff;
    border: 1px solid rgba(17,28,29,0.08);
    box-shadow: 0 30px 70px -50px rgba(17,28,29,0.5);
    /* akcent boja po tier-u (default green) */
    --accent: #1f9d55;
  }
  .live-card[data-tier="free"]   { --accent: #84B052; --accent-ink: #000000; }
  .live-card[data-tier="lime"]   { --accent: #C9DB5B; --accent-ink: #000000; }
  .live-card[data-tier="yellow"] { --accent: #F6D63B; --accent-ink: #000000; }
  .live-card[data-tier="orange"] { --accent: #F0A73C; --accent-ink: #000000; }
  .live-card[data-tier="red"]    { --accent: #E8472B; --accent-ink: #ffffff; }
  .live-card[data-tier="closed"] { --accent: #D0CFC7; --accent-ink: #000000; }

  .live-eyebrow {
    font-family: var(--font-sans); font-size: 14px; font-weight: 400;
    letter-spacing: 0.2em; text-transform: uppercase; color: var(--muted);
    margin: 0 auto 22px; line-height: 1.4;
  }
  .live-ring {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    width: 150px; height: 150px;
    border-radius: 50%; border: 3px solid var(--accent);
    margin: 0 auto 24px; color: var(--accent);
  }
  .live-ring-num {
    font-family: 'Inter', var(--font-sans); font-weight: 700; line-height: 1;
    font-size: 48px; letter-spacing: -0.01em; color: #000;
  }
  .live-ring-unit {
    font-family: var(--font-sans); font-size: 14px; font-weight: 400;
    color: #000; margin-top: 4px;
  }
  .live-check { width: 44%; height: 44%; }
  .live-closed-icon { width: 44%; height: 44%; color: var(--muted); }

  .live-headline {
    font-size: 40px; font-weight: 300;
    color: var(--ink);
    margin: 4px 0 14px;
    line-height: 1.05;
  }
  .live-sub {
    max-width: 46ch; margin: 16px auto 0; color: var(--ink-soft);
    font-size: 18px; font-weight: 300;
  }
  .live-chairs {
    margin: 12px auto 0;
    font-family: var(--font-sans); font-size: 13.5px; font-weight: 500;
    color: var(--muted);
  }
  .live-stale {
    margin: 18px auto 0; max-width: 44ch;
    font-family: var(--font-sans); font-size: 14px; font-weight: 500;
    color: var(--oxblood);
    background: rgba(120,51,50,0.07);
    border-radius: 10px; padding: 10px 16px;
  }
  .live-foot {
    margin: 24px auto 0; max-width: 46ch;
    font-family: var(--font-sans); font-size: 12px; font-weight: 400; line-height: 1.55;
    color: var(--muted);
  }
  .live-cta { margin-top: 28px; }
  .live-viewers {
    margin-top: 12px;
    font-family: var(--font-sans); font-size: 14px; font-weight: 600;
    color: var(--muted);
    display: inline-flex; align-items: center; gap: 6px;
  }
  .live-eye { opacity: 0.75; flex-shrink: 0; }
  .btn-ghost {
    background: transparent; border: 1px solid rgba(17,28,29,0.25); color: var(--ink);
  }
</style>

<script>
(function () {
  var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
  var card = document.getElementById('live-card');
  if (!card) return;

  var elHeadline = document.getElementById('live-headline');
  var elEyebrow  = document.getElementById('live-eyebrow');
  var elCheck    = document.getElementById('live-check');
  var elClosedIc = document.getElementById('live-closed-icon');
  var elRingNum  = document.getElementById('live-ring-num');
  var elRingUnit = document.getElementById('live-ring-unit');
  var elSub      = document.getElementById('live-sub');
  var elFoot     = document.getElementById('live-foot');
  var elStale    = document.getElementById('live-stale');
  var elViewers  = document.getElementById('live-viewers');
  var elViewersT = document.getElementById('live-viewers-text');
  var elChairs   = document.getElementById('live-chairs');

  // Token po tabu (sessionStorage) — služi da server broji jedinstvene gledaoce.
  var token;
  try {
    token = sessionStorage.getItem('dry65_v');
    if (!token) { token = Math.random().toString(36).slice(2, 12); sessionStorage.setItem('dry65_v', token); }
  } catch (e) { token = Math.random().toString(36).slice(2, 12); }

  // Opšta srpska pluralizacija (1 / 2-4 / 5+)
  function srPlural(n, one, few, many) {
    var d1 = n % 10, d100 = n % 100;
    if (d1 === 1 && d100 !== 11) return one;
    if (d1 >= 2 && d1 <= 4 && (d100 < 12 || d100 > 14)) return few;
    return many;
  }

  // Stanje sa servera — remaining se lokalno tika između AJAX poziva.
  var state = {
    remainingSec: <?php echo (int) $st['remaining_sec']; ?>,
    closed:       <?php echo $st['closed'] ? 'true' : 'false'; ?>,
    staffText:    <?php echo wp_json_encode(dry65_live_staff_text(get_option('dry65_live_staff', []))); ?>,
    chairsShow:   <?php echo get_option('dry65_live_chairs_show', '0') === '1' ? 'true' : 'false'; ?>,
    message:      <?php echo wp_json_encode(get_option('dry65_live_message', '')); ?>,
    hoursText:    <?php echo wp_json_encode(dry65_live_hours_text()); ?>,
    phone:        <?php echo wp_json_encode($biz['phone_display']); ?>
  };
  var lastTick = Date.now();

  // Boja (tier) po preostalom vremenu — mora da prati dry65_live_tier_copy() u inc/live.php.
  function tierFor(min) {
    if (state.closed) return 'closed';
    if (min <= 0)  return 'free';
    if (min <= 10) return 'lime';
    if (min <= 30) return 'yellow';
    if (min <= 45) return 'orange';
    return 'red';
  }

  // Editabilni tekstovi iz admina — prag = najmanja vrednost >= min. Prati dry65_live_copy() u PHP-u.
  var DRY65_WAITS = <?php echo wp_json_encode(dry65_live_allowed_waits()); ?>;
  var DRY65_TEXTS = <?php echo wp_json_encode(dry65_live_texts(), JSON_UNESCAPED_UNICODE); ?>;
  function copyText(min) {
    if (state.closed) return ['Zatvoreni smo', state.hoursText];
    for (var i = 0; i < DRY65_WAITS.length; i++) {
      var v = DRY65_WAITS[i];
      if (min <= v && DRY65_TEXTS[v]) return [DRY65_TEXTS[v].h, DRY65_TEXTS[v].s];
    }
    var last = DRY65_TEXTS[DRY65_WAITS[DRY65_WAITS.length - 1]];
    return [last.h, last.s];
  }

  // Broj u prstenu — mora da prati dry65_live_ring_num() u inc/live.php.
  function ringNum(min) { return Math.ceil(min / 5) * 5; }

  function render() {
    var min  = Math.max(0, Math.ceil(state.remainingSec / 60));
    var tier = tierFor(min);
    var free = (!state.closed && min <= 0);
    var cp   = copyText(min);

    card.setAttribute('data-tier', tier);
    elHeadline.textContent = cp[0];
    // custom poruka prepisuje podtekst (osim kad je zatvoreno)
    elSub.textContent = (state.message && !state.closed) ? state.message : cp[1];

    // Eyebrow
    if (elEyebrow) {
      elEyebrow.textContent = state.closed ? 'TRENUTNI STATUS' : (free ? 'SLOBODAN TERMIN' : 'SLEDEĆI SLOBODAN TERMIN JE ZA MANJE OD');
      elEyebrow.style.display = '';
    }
    // Prsten: kvačica (slobodno) / broj+minuta (čekanje) / ⊖ (zatvoreno)
    if (elCheck)    elCheck.style.display    = free ? '' : 'none';
    if (elClosedIc) elClosedIc.style.display = state.closed ? '' : 'none';
    var showNum = (!state.closed && !free);
    if (elRingNum)  { elRingNum.textContent = showNum ? String(ringNum(min)) : ''; elRingNum.style.display = showNum ? '' : 'none'; }
    if (elRingUnit) elRingUnit.style.display = showNum ? '' : 'none';

    // Fusnota — samo kad nije zatvoreno
    if (elFoot) elFoot.style.display = state.closed ? 'none' : '';

    // ko radi — prikaži samo ako je vlasnik uključio, ima imena i nije zatvoreno
    if (elChairs) {
      if (state.chairsShow && state.staffText && !state.closed) {
        elChairs.textContent = state.staffText;
        elChairs.style.display = '';
      } else {
        elChairs.style.display = 'none';
      }
    }
  }

  // Lokalno tikanje — svakih 5s skini proteklo vreme (i za čekanje i za "ažurirano pre").
  setInterval(function () {
    var now = Date.now();
    var elapsed = (now - lastTick) / 1000;
    if (!state.closed) {
      state.remainingSec = Math.max(0, state.remainingSec - elapsed);
    }
    lastTick = now;
    render();
  }, 5000);

  // AJAX — autoritativni podatak sa servera (hvata izmene admina: nova mušterija → veći broj).
  function refresh() {
    fetch(ajaxUrl + '?action=dry65_live_get&v=' + encodeURIComponent(token) + '&_=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d) return;
        state.remainingSec = d.remaining_sec;
        state.closed       = !!d.closed;
        state.message      = d.message || '';
        state.phone        = d.phone || state.phone;
        state.staffText    = d.staff_text || '';
        state.chairsShow   = !!d.chairs_show;
        lastTick = Date.now();
        render();

        if (d.stale) {
          elStale.style.display = '';
          elStale.textContent = 'Podaci možda nisu ažurni. Pozovite ' + state.phone + ' pre dolaska.';
        } else {
          elStale.style.display = 'none';
        }

        // Brojač gledalaca — ikonica oka + broj + osoba/osobe, prikaži iznad praga
        if (d.viewers >= d.viewers_min) {
          elViewersT.textContent = d.viewers + ' ' + srPlural(d.viewers, 'osoba', 'osobe', 'osoba');
          elViewers.style.display = '';
        } else {
          elViewers.style.display = 'none';
        }
      })
      .catch(function () { /* tiho — zadrži prikazani status */ });
  }

  render();
  refresh();                        // odmah registruj presence i povuci svež podatak
  setInterval(refresh, 20000);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) refresh();
  });
})();
</script>

<?php get_footer('live'); // minimalni footer bez vidljivog bloka ?>
