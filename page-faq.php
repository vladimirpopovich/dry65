<?php
/*
Template Name: FAQ
*/
get_header();
$biz = dry65_biz();

/* FAQ podaci — kategorije + pitanja/odgovori.
   Isti podaci se koriste za HTML render i za FAQPage Schema markup dole. */
$faq_groups = [
    [
        'title' => 'Zakazivanje i termini',
        'items' => [
            [
                'q' => 'Da li treba da zakažem termin za feniranje u Dry65?',
                'a' => 'Ne, Dry65 je walk-in salon. Samo dođete kad Vam odgovara — bez zakazivanja, bez pritiska. Nalazimo se u West 65 mall-u na Novom Beogradu.',
            ],
            [
                'q' => 'Koje je radno vreme Dry65 salona?',
                'a' => 'Radimo od ponedeljka do petka 8:00 - 20:00, subotom 10:00 - 18:00. Nedeljom ne radimo.',
            ],
            [
                'q' => 'Ako je gužva, koliko čekam?',
                'a' => 'Prosek čekanja je 5-15 minuta. Za trenutan status pozovite ' . esc_html($biz['phone_display']) . ' pre dolaska.',
            ],
        ],
    ],
    [
        'title' => 'Usluge i tehnike',
        'items' => [
            [
                'q' => 'Koliko traje jedno feniranje?',
                'a' => 'Standardno feniranje traje 30 do 45 minuta, u zavisnosti od dužine i gustine kose. Extra duga i gusta kosa može trajati do sat vremena.',
            ],
            [
                'q' => 'Koje tehnike feniranja radite?',
                'a' => 'Radimo feniranje na talase, lokne, ravno, volumen, na četke i afro curls. Pored toga, imamo Hair Mask i Hair Infusion tretmane za dubinsku negu kose.',
            ],
            [
                'q' => 'Da li radite šišanje ili farbanje?',
                'a' => 'Ne. Dry65 je specijalizovan isključivo za feniranje, stilizovanje i negu kose. Radimo jednu stvar — feniramo, i u tome smo najbolji.',
            ],
            [
                'q' => 'Koje proizvode za kosu koristite?',
                'a' => 'Isključivo Schwarzkopf Professional preparate — vodeći svetski brend za profesionalnu salonsku negu kose.',
            ],
            [
                'q' => 'Šta je Hair Mask tretman?',
                'a' => 'Hair Mask je dubinski tretman nege kose koji se radi uz feniranje. Dostupan u tri nivoa (Basic, Medium, Premium), sa opcijom parne stanice za dodatan efekat.',
            ],
        ],
    ],
    [
        'title' => 'Cene i paketi',
        'items' => [
            [
                'q' => 'Koliko košta feniranje u Dry65?',
                'a' => 'Cene feniranja: kratka kosa 1.400 din, srednja 1.800 din, duga 2.000 din, extra duga 2.200 din. Detaljan cenovnik svih usluga možete videti <a href="' . esc_url(get_permalink(get_page_by_path('cenovnik'))) . '">na stranici Cenovnik</a>.',
            ],
            [
                'q' => 'Da li imate mesečne pakete?',
                'a' => 'Da. Imamo mesečne pakete od 4, 8 ili 12 feniranja mesečno, sa značajnom uštedom po feniranju. Uz svaki paket dobijate na poklon masažu glave i profesionalni tretman nege kose. <a href="' . esc_url(get_permalink(get_page_by_path('paketi'))) . '">Pogledajte pakete</a>.',
            ],
            [
                'q' => 'Kako se plaća — gotovina ili kartica?',
                'a' => 'Prihvatamo obе opcije — gotovinu i sve kartice (Visa, Mastercard, Maestro, Dinacard).',
            ],
            [
                'q' => 'Da li dajete poklon vaučere?',
                'a' => 'Da. Poklon vaučer za feniranje ili mesečni paket je odličan poklon za prijateljicu, majku ili sestru. Za detalje pozovite ' . esc_html($biz['phone_display']) . ' ili pišite na Instagram <a href="' . esc_url($biz['instagram_url']) . '" target="_blank" rel="noopener">' . esc_html($biz['instagram']) . '</a>.',
            ],
        ],
    ],
    [
        'title' => 'Lokacija i pristup',
        'items' => [
            [
                'q' => 'Gde se nalazi Dry65 salon?',
                'a' => 'Omladinskih Brigada 86Ž, West 65 mall, Novi Beograd — blizu Airport City poslovne zone. <a href="' . esc_url($biz['maps_url']) . '" target="_blank" rel="noopener">Otvori Google Maps</a>.',
            ],
            [
                'q' => 'Da li ima parking?',
                'a' => 'Da. U West 65 mall-u prvi sat parkiranja je besplatan. Salon je samo nekoliko koraka od glavnog ulaza.',
            ],
            [
                'q' => 'Kako doći javnim prevozom?',
                'a' => 'Autobuske linije 18, 65, 68 i 74 staju u neposrednoj blizini West 65 mall-a.',
            ],
        ],
    ],
    [
        'title' => 'Ostalo',
        'items' => [
            [
                'q' => 'Koliko dugo drži feniranje?',
                'a' => 'Kvalitetno feniranje sa Schwarzkopf Professional preparatima drži 3 do 5 dana, u zavisnosti od tipa kose i sna. Za produženo trajanje preporučujemo svilenu jastučnicu.',
            ],
            [
                'q' => 'Da li mogu doći sa detetom?',
                'a' => 'Naravno. Imamo prijatan ambijent gde deca mogu čekati dok mama fenira kosu.',
            ],
            [
                'q' => 'Da li imate akcije i popuste?',
                'a' => 'Aktuelne ponude i akcije objavljujemo na <a href="' . esc_url(home_url('/')) . '">početnoj strani</a> i na Instagram-u <a href="' . esc_url($biz['instagram_url']) . '" target="_blank" rel="noopener">' . esc_html($biz['instagram']) . '</a>. Prati nas za sezonske akcije.',
            ],
        ],
    ],
];
?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">Česta pitanja</span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:30ch;line-height:1.0;letter-spacing:0.01em;">
      Sve što treba da znate o Dry65 salonu
    </h1>
    <p class="lead" style="margin-top:26px;max-width:680px;">
      Odgovori na najčešća pitanja klijentkinja o feniranju, cenama, radnom vremenu i lokaciji. Ako Vaše pitanje nije ovde, pišite nam na <a href="mailto:<?php echo esc_attr($biz['email']); ?>" style="color:var(--clay);text-decoration:underline;text-underline-offset:3px;"><?php echo esc_html($biz['email']); ?></a> ili pozovite <?php echo esc_html($biz['phone_display']); ?>.
    </p>
  </div>
</section>

<section class="section">
  <div class="wrap" style="max-width:840px;">
    <?php foreach ($faq_groups as $g_i => $group): ?>
      <div class="faq-group" style="margin-bottom:clamp(40px,5vw,64px);">
        <h2 class="display" style="font-size:clamp(26px,3.6vw,38px);margin-bottom:20px;color:var(--oxblood);">
          <?php echo esc_html($group['title']); ?>
        </h2>
        <div class="faq-list">
          <?php foreach ($group['items'] as $i_i => $item): ?>
            <details class="faq-item" style="border:1px solid var(--sage-line);border-radius:var(--radius-lg);padding:0;margin-bottom:12px;background:#fff;">
              <summary class="faq-q" style="padding:20px 24px;cursor:pointer;font-family:var(--font-sans);font-weight:600;font-size:17px;color:var(--ink);list-style:none;display:flex;justify-content:space-between;align-items:center;gap:16px;">
                <span><?php echo esc_html($item['q']); ?></span>
                <span class="faq-icon" aria-hidden="true" style="flex-shrink:0;font-size:20px;color:var(--clay);transition:transform .25s var(--ease);">+</span>
              </summary>
              <div class="faq-a" style="padding:0 24px 22px;font-family:var(--font-sans);font-size:16px;line-height:1.6;color:var(--muted);">
                <?php echo wp_kses_post($item['a']); ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section-sm bg-cream">
  <div class="wrap center">
    <h2 class="display" style="font-size:clamp(28px,4vw,44px);">Niste našli odgovor?</h2>
    <p class="lead" style="margin-top:16px;max-width:520px;margin-inline:auto;">
      Kontaktirajte nas — rado ćemo pojasniti sve što Vas interesuje.
    </p>
    <div class="btn-row" style="justify-content:center;margin-top:26px;">
      <a href="tel:<?php echo esc_attr($biz['phone']); ?>" class="btn btn-dark">
        Pozovi nas <span class="arrow">→</span>
      </a>
      <a href="<?php echo esc_url(get_permalink(get_page_by_path('kontakt'))); ?>" class="btn btn-outline">
        Sve kontakt info
      </a>
    </div>
  </div>
</section>

</main>

<style>
.faq-item[open] .faq-icon { transform: rotate(45deg); }
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary:hover { background: var(--paper-2); }
.faq-item[open] summary { border-bottom: 1px solid var(--sage-line); }
.faq-a a { color: var(--oxblood); text-decoration: underline; text-underline-offset: 2px; }
</style>

<?php
/* ============================================================
   FAQPage Schema markup (JSON-LD) — za Google Featured Snippets
   i AI SEO (ChatGPT, Perplexity, Claude direktno vuku odavde)
   ============================================================ */
$faq_schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => [],
];
foreach ($faq_groups as $group) {
    foreach ($group['items'] as $item) {
        // Za Schema — plain text bez HTML tagova
        $answer_plain = wp_strip_all_tags($item['a']);
        $faq_schema['mainEntity'][] = [
            '@type' => 'Question',
            'name'  => $item['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $answer_plain,
            ],
        ];
    }
}
?>
<script type="application/ld+json"><?php echo wp_json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

<?php get_footer(); ?>
