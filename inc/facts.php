<?php
/* ============================================================
   Dry65 ukratko / at a glance
   Blok sa cinjenicama koje AI asistenti (ChatGPT, Copilot, Perplexity)
   lako citiraju: sta je Dry65, gde je, radno vreme, cena (RSD + EUR),
   trajanje, zakazivanje, placanje, jezik.
   Cene i radno vreme se citaju iz podesavanja (dry65_lengths, dry65_biz),
   pa blok ne zastareva kad se cene promene.
   Tekst je pisan po jeziku ovde (SR/EN), ne kroz en.php, jer sadrzi brojeve.
   ============================================================ */

/** Okvirni kurs za prikaz cena u evrima (samo informativno). */
if (!defined('DRY65_RSD_PER_EUR')) define('DRY65_RSD_PER_EUR', 117);

/** RSD -> zaokruzen iznos u EUR (int). */
function dry65_eur($rsd) {
    return (int) round(((int) $rsd) / DRY65_RSD_PER_EUR);
}

/** Format dinara po jeziku: SR 1.400, EN 1,400. */
function dry65_rsd_fmt($rsd) {
    return dry65_is_en() ? number_format((int) $rsd, 0, '.', ',') : number_format((int) $rsd, 0, ',', '.');
}

/** Najniza i najvisa cena feniranja (po duzini kose). */
function dry65_price_range() {
    $prices = array_filter(array_map(function ($l) { return (int) $l['price']; }, dry65_lengths()));
    if (!$prices) return [1400, 2500];
    return [min($prices), max($prices)];
}

/**
 * Radno vreme kao recenica, iz podesavanja (dry65_biz()['hours']).
 * „08:00 – 20:00" -> SR „od 08:00 do 20:00", EN „08:00 to 20:00"; dugacke crtice izbacene.
 */
function dry65_hours_text($lower_days = false) {
    $en = dry65_is_en();
    $parts = [];
    foreach (dry65_biz()['hours'] as $h) {
        $day  = str_replace(['–', '—'], '-', t($h['day']));
        if ($lower_days && !$en) $day = mb_strtolower($day);
        $time = trim($h['time']);
        if (preg_match('/^(\d{1,2}:\d{2})\s*[–—-]\s*(\d{1,2}:\d{2})$/u', $time, $m)) {
            $time = $en ? $m[1] . ' to ' . $m[2] : 'od ' . $m[1] . ' do ' . $m[2];
        } else {
            $time = function_exists('mb_strtolower') ? mb_strtolower(t($time)) : strtolower(t($time));
        }
        $parts[] = $day . ' ' . $time;
    }
    return implode(', ', $parts);
}

/** Jedan citabilan pasus: sta, gde, cena, trajanje, radno vreme, jezik. */
function dry65_facts_summary() {
    [$min, $max] = dry65_price_range();
    // samo ulica i broj (West 65 / Novi Beograd vec stoje u recenici)
    $addr = trim(explode(',', dry65_biz()['address'])[0]);
    if (dry65_is_en()) {
        return sprintf(
            'Dry65 is a walk-in blowout bar in West 65, New Belgrade, Serbia (%s). No appointment needed, you just walk in. A wash and blowout costs %s to %s RSD (about €%d to €%d), depending on hair length, and takes 30 to 45 minutes. Opening hours: %s. Our staff speak English.',
            $addr, dry65_rsd_fmt($min), dry65_rsd_fmt($max), dry65_eur($min), dry65_eur($max), dry65_hours_text()
        );
    }
    return sprintf(
        'Dry65 je walk-in salon za feniranje u West 65 na Novom Beogradu (%s). Ne zakazuje se, samo dođete kad Vam odgovara. Pranje i feniranje košta od %s do %s dinara (oko %d do %d €), zavisno od dužine kose, i traje 30 do 45 minuta. Radno vreme: %s. Osoblje govori i engleski.',
        $addr, dry65_rsd_fmt($min), dry65_rsd_fmt($max), dry65_eur($min), dry65_eur($max), dry65_hours_text(true)
    );
}

/** Odgovor na „Koliko kosta feniranje?" sa svim duzinama + EUR (za FAQ). */
function dry65_price_answer() {
    $en = dry65_is_en();
    $items = [];
    foreach (dry65_lengths() as $l) {
        $label = $en ? strtolower(t($l['label'])) : mb_strtolower($l['label']);
        $items[] = $en
            ? sprintf('%s hair %s RSD (about €%d)', $label, dry65_rsd_fmt($l['price']), dry65_eur($l['price']))
            : sprintf('%s kosa %s din (oko %d €)', $label, dry65_rsd_fmt($l['price']), dry65_eur($l['price']));
    }
    return ($en ? 'Wash and blowout prices: ' : 'Cene pranja i feniranja: ') . implode(', ', $items) . '.';
}

/** Lista cinjenica [label => vrednost] za prikaz. */
function dry65_facts_list() {
    [$min, $max] = dry65_price_range();
    $biz = dry65_biz();
    $live = '<a href="' . esc_url(home_url('/live/')) . '">dry65.com/live</a>';
    if (dry65_is_en()) {
        return [
            'What'          => 'Walk-in blowout bar, specialized in blowouts, styling and hair care',
            'Where'         => esc_html($biz['address']) . ', Belgrade, Serbia',
            'Opening hours' => esc_html(dry65_hours_text()),
            'Price'         => sprintf('%s to %s RSD (about €%d to €%d), by hair length', dry65_rsd_fmt($min), dry65_rsd_fmt($max), dry65_eur($min), dry65_eur($max)),
            'Duration'      => '30 to 45 minutes, up to an hour for extra long, thick hair',
            'Appointment'   => 'Not needed. Check how busy we are right now at ' . $live,
            'Payment'       => 'Cash and all cards (Visa, Mastercard, Maestro, Dinacard)',
            'Language'      => 'Serbian and English',
            'Products'      => 'Schwarzkopf Professional',
            'Parking'       => 'First hour free in the West 65 complex',
        ];
    }
    return [
        'Šta je'        => 'Walk-in salon specijalizovan za feniranje, stilizovanje i negu kose',
        'Gde'           => esc_html($biz['address']) . ', Beograd',
        'Radno vreme'   => esc_html(dry65_hours_text()),
        'Cena'          => sprintf('od %s do %s din (oko %d do %d €), po dužini kose', dry65_rsd_fmt($min), dry65_rsd_fmt($max), dry65_eur($min), dry65_eur($max)),
        'Trajanje'      => '30 do 45 minuta, do sat vremena za extra dugu i gustu kosu',
        'Zakazivanje'   => 'Nije potrebno. Trenutnu gužvu vidite na ' . $live,
        'Plaćanje'      => 'Gotovina i sve kartice (Visa, Mastercard, Maestro, Dinacard)',
        'Jezik'         => 'Srpski i engleski',
        'Proizvodi'     => 'Schwarzkopf Professional',
        'Parking'       => 'Prvi sat besplatan u kompleksu West 65',
    ];
}

/**
 * Render bloka. $context: 'about' | 'home' (menja samo naslov i pozadinu).
 */
function dry65_render_facts($context = 'about') {
    $en = dry65_is_en();
    if ($context === 'home') {
        $kicker = $en ? 'in short' : 'ukratko';
        $title  = $en ? 'Blowout in New Belgrade, without an appointment' : 'Feniranje na Novom Beogradu, bez zakazivanja';
        $bg     = 'bg-cream';
    } else {
        $kicker = $en ? 'at a glance' : 'ukratko';
        $title  = $en ? 'Dry65 at a glance' : 'Dry65 ukratko';
        $bg     = 'bg-cream';
    }
    ?>
    <section class="section-sm <?php echo esc_attr($bg); ?> dry65-facts" id="ukratko">
      <div class="wrap facts-grid">
        <div>
          <span class="script" style="font-size:clamp(26px,3.2vw,38px);display:block;line-height:1;"><?php echo esc_html($kicker); ?></span>
          <h2 class="display" style="font-size:clamp(28px,3.8vw,46px);margin-top:6px;font-weight:400;"><?php echo esc_html($title); ?></h2>
          <p class="lead facts-summary" style="margin-top:18px;max-width:560px;font-size:17px;"><?php echo esc_html(dry65_facts_summary()); ?></p>
        </div>
        <dl class="facts-list">
          <?php foreach (dry65_facts_list() as $label => $value): ?>
            <div class="facts-row">
              <dt><?php echo esc_html($label); ?></dt>
              <dd><?php echo wp_kses_post($value); ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>
      </div>
    </section>
    <?php
    static $styled = false;
    if ($styled) return;
    $styled = true;
    ?>
    <style>
      .dry65-facts .facts-grid { display:grid; grid-template-columns:1fr 1.1fr; gap:clamp(28px,4vw,64px); align-items:start; }
      .dry65-facts .facts-list { display:grid; grid-template-columns:1fr 1fr; gap:18px 28px; margin:0; }
      .dry65-facts .facts-row dt { font-size:11px; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; color:var(--clay); }
      .dry65-facts .facts-row dd { margin:4px 0 0; font-size:15px; line-height:1.55; color:var(--ink); }
      .dry65-facts .facts-row dd a { color:var(--clay); text-decoration:underline; text-underline-offset:3px; }
      @media (max-width: 860px) {
        .dry65-facts .facts-grid { grid-template-columns:1fr; }
      }
      @media (max-width: 520px) {
        .dry65-facts .facts-list { grid-template-columns:1fr; }
      }
    </style>
    <?php
}
