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
      <div class="live-badge-row">
        <div class="live-badge">
          <span class="live-badge-label" id="live-wait-label"><?php echo esc_html($st['wait_label']); ?></span>
        </div>
      </div>

      <?php $live_fig = dry65_live_figure_url($st['tier']); ?>
      <div class="live-figure-wrap">
        <img class="live-figure-img" id="live-figure-img" alt="" decoding="async"
             width="400" height="400"
             src="<?php echo esc_url($live_fig); ?>"
             data-tier="<?php echo esc_attr($st['tier']); ?>"
             <?php echo $live_fig ? '' : 'style="display:none;"'; ?>>
        <span class="live-dot" id="live-dot"<?php echo $live_fig ? ' style="display:none;"' : ''; ?>></span>
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

      <p class="live-note" id="live-note"<?php echo empty($st['note']) ? ' style="display:none;"' : ''; ?>><?php echo esc_html($st['note']); ?></p>
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
  .live-card[data-tier="free"]   { --accent: #84B052; --accent-ink: #22330f; }
  .live-card[data-tier="lime"]   { --accent: #C9DB5B; --accent-ink: #3f4a12; }
  .live-card[data-tier="yellow"] { --accent: #F6D63B; --accent-ink: #5a4900; }
  .live-card[data-tier="orange"] { --accent: #F0A73C; --accent-ink: #5a3400; }
  .live-card[data-tier="red"]    { --accent: #E8472B; --accent-ink: #ffffff; }
  .live-card[data-tier="closed"] { --accent: #D0CFC7; --accent-ink: #3a3a34; }

  .live-badge-row {
    margin: 0 auto 18px;
    text-align: center;
  }
  .live-badge {
    display: inline-flex; align-items: center; gap: 8px;
    font-family: var(--font-sans); font-size: 14px; color: var(--muted);
    background: rgba(17,28,29,0.04);
    border-radius: 999px; padding: 8px 16px;
  }
  .live-badge-label { font-weight: 600; color: var(--ink-soft); font-size: 16px; }
  .live-dot {
    width: clamp(54px,11vw,78px); height: clamp(54px,11vw,78px);
    border-radius: 50%; background: var(--accent);
    margin: 0 auto 16px;
  }
  .live-card[data-tier="free"] .live-dot,
  .live-card[data-tier="lime"] .live-dot { animation: livePulse 2.2s ease-in-out infinite; }
  @keyframes livePulse { 0%,100%{transform:scale(1);opacity:1;} 50%{transform:scale(1.06);opacity:.9;} }

  /* LED semafor figura — crni disk (upečen u sliku) lebdi na krem kartici */
  .live-figure-wrap { margin: 0 auto 10px; }
  .live-figure-img {
    display: block; margin: 0 auto;
    width: clamp(160px, 44vw, 212px); height: auto;
    filter: drop-shadow(0 8px 22px rgba(0,0,0,0.22));
    animation: liveLed 2.6s ease-in-out infinite; /* suptilno „disanje" svetla */
  }
  @keyframes liveLed { 0%,100%{opacity:1;} 50%{opacity:.85;} }
  @media (prefers-reduced-motion: reduce) { .live-figure-img { animation: none; } }

  .live-headline {
    font-size: clamp(34px,5.4vw,58px);
    color: var(--ink);
    margin: 4px 0 14px;
    line-height: 1.02;
  }
  .live-sub { max-width: 46ch; margin: 16px auto 0; color: var(--ink-soft); }
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
  .live-note {
    margin: 10px auto 0; max-width: 42ch;
    font-family: var(--font-sans); font-size: 12.5px; line-height: 1.5;
    color: var(--muted); font-style: italic;
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

  var FIGS = <?php echo wp_json_encode(dry65_live_figures_map()); ?>;
  var elFigImg   = document.getElementById('live-figure-img');
  var elDot      = document.getElementById('live-dot');
  var elHeadline = document.getElementById('live-headline');
  var elWaitLbl  = document.getElementById('live-wait-label');
  var elSub      = document.getElementById('live-sub');
  var elStale    = document.getElementById('live-stale');
  var elNote     = document.getElementById('live-note');
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

  // „Status je ažuriran pre 3 minuta" — mora da prati dry65_live_ago_sentence() u inc/live.php.
  // Ispod 1 minuta kaže „upravo sada" (bez „pre"); granulacija je minutna, sekunde se ne broje.
  function agoSentence(sec) {
    if (sec < 0) return '';
    sec = Math.max(0, Math.floor(sec));
    if (sec < 60) return 'Status je ažuriran upravo sada';
    var m = Math.floor(sec / 60);
    if (m < 60)   return 'Status je ažuriran pre ' + m + ' ' + srPlural(m, 'minut', 'minuta', 'minuta');
    var h = Math.floor(m / 60);
    if (h < 24)   return 'Status je ažuriran pre ' + h + ' ' + srPlural(h, 'sat', 'sata', 'sati');
    var dd = Math.floor(h / 24);
    return 'Status je ažuriran pre ' + dd + ' ' + srPlural(dd, 'dan', 'dana', 'dana');
  }

  // Stanje sa servera — remaining i "ažurirano pre" se lokalno tikaju između AJAX poziva.
  var state = {
    remainingSec: <?php echo (int) $st['remaining_sec']; ?>,
    closed:       <?php echo $st['closed'] ? 'true' : 'false'; ?>,
    agoSec:       <?php echo (int) $st['updated_ago_sec']; ?>,
    staffText:    <?php echo wp_json_encode(dry65_live_staff_text(get_option('dry65_live_staff', []))); ?>,
    chairsShow:   <?php echo get_option('dry65_live_chairs_show', '0') === '1' ? 'true' : 'false'; ?>,
    message:      <?php echo wp_json_encode(get_option('dry65_live_message', '')); ?>,
    hoursText:    <?php echo wp_json_encode(dry65_live_hours_text()); ?>,
    phone:        <?php echo wp_json_encode($biz['phone_display']); ?>
  };
  var lastTick = Date.now();

  // Procena vremena — prati STVARNO preostalo vreme, ne tier, pa se smanjuje dok tajmer ide.
  // Mora da prati dry65_live_wait_label() u inc/live.php.
  function waitLabel(min) {
    if (state.closed) return 'Zatvoreno';
    if (min <= 0)  return 'Prvi ste na redu';
    if (min >= 45) return 'Na redu ste za preko 45 minuta';
    return 'Na redu ste za manje od ' + (Math.ceil(min / 5) * 5) + ' minuta';
  }

  // Mora da prati dry65_live_tier_copy() u inc/live.php — isti pragovi i isti tekst.
  function copyFor(min) {
    // `note` je samo NASTAVAK — render() ispred zalepi „Status je ažuriran pre X. "
    var busyNote = 'Moguće je da se procena promeni kako se oslobađaju mesta.';
    if (state.closed) return { tier:'closed', headline:'Trenutno ne radimo', sub:state.hoursText, note:'' };
    if (min <= 0)  return { tier:'free',   headline:'Slobodni smo', sub:'Samo dođite, čekamo vas.', note:'Ako planirate dolazak, preporučujemo da krenete uskoro.' };
    if (min <= 10) return { tier:'lime',   headline:'Uskoro slobodni', sub:'Krenite, uskoro će se osloboditi mesto.', note:'Može se promeniti kako klijenti dolaze i odlaze.' };
    if (min <= 25) return { tier:'yellow', headline:'Malo čekanja', sub:'Ako ste u blizini, pravo je vreme da svratite.', note:busyNote };
    if (min < 45)  return { tier:'orange', headline:'Manja gužva', sub:'Popijte kafu ili prosecco dok čekate. Vreme će proći brže nego što mislite.', note:busyNote };
    return { tier:'red', headline:'Imamo gužvu', sub:'Ako vam se ne žuri, preporučujemo da svratite malo kasnije.', note:busyNote };
  }

  function render() {
    var min = Math.max(0, Math.ceil(state.remainingSec / 60));
    var c = copyFor(min);
    card.setAttribute('data-tier', c.tier);
    elHeadline.textContent = c.headline;
    if (elWaitLbl) elWaitLbl.textContent = waitLabel(min);

    // Semafor figura: prikaži za stanja koja imaju sliku; za „closed" fallback na sivu tačkicu.
    if (elFigImg && elDot) {
      var fig = FIGS[c.tier] || '';
      if (fig) {
        if (elFigImg.getAttribute('data-tier') !== c.tier) { // menjaj src samo na promeni (bez treptaja)
          elFigImg.src = fig;
          elFigImg.setAttribute('data-tier', c.tier);
        }
        elFigImg.style.display = '';
        elDot.style.display = 'none';
      } else {
        elFigImg.style.display = 'none';
        elDot.style.display = '';
      }
    }
    // custom poruka prepisuje sub (osim kad je zatvoreno)
    elSub.textContent = (state.message && c.tier !== 'closed') ? state.message : c.sub;
    // fusnota po stanju (prazna kad je zatvoreno) — ispred nje „Status je ažuriran pre X. "
    if (elNote) {
      if (c.note) {
        var ago = agoSentence(state.agoSec);
        elNote.textContent = ago ? ago + '. ' + c.note : c.note;
        elNote.style.display = '';
      } else { elNote.style.display = 'none'; }
    }
    // ko radi — prikaži samo ako je vlasnik uključio, ima imena i nije zatvoreno
    if (elChairs) {
      if (state.chairsShow && state.staffText && c.tier !== 'closed') {
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
    if (state.agoSec >= 0) state.agoSec += elapsed;
    lastTick = now;
    render();
  }, 5000);

  // AJAX — autoritativni podatak sa servera (hvata izmene admina: nova mušterija → veći broj).
  function refresh() {
    fetch(ajaxUrl + '?action=dry65_live_get&v=' + encodeURIComponent(token), { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d) return;
        state.remainingSec = d.remaining_sec;
        state.closed       = !!d.closed;
        state.agoSec       = (typeof d.updated_ago_sec === 'number') ? d.updated_ago_sec : state.agoSec;
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
  setInterval(refresh, 45000);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) refresh();
  });
})();
</script>

<?php get_footer('live'); // minimalni footer bez vidljivog bloka ?>
